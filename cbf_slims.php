<?php
/**
 * CBF SIMULATION — PAKAI DATABASE SLIMS ASLI
 * ─────────────────────────────────────────────────────────
 * Perbedaan dari cbf_simulation.php (versi array):
 *  - Query langsung ke tabel SLIMS (biblio, item, loan, member)
 *  - subject diambil via JOIN biblio_topic + mst_topic (bukan kolom langsung)
 *  - author diambil via JOIN biblio_author + mst_author
 *  - Nama tabel item (bukan biblio_item!)
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/slims_connection.php';
$pdo = getSlimsConnection();

// ─────────────────────────────────────────────────────────
// STEP 1 — Ambil buku dari SLIMS dengan query yang benar
// subject dan author via JOIN (tidak ada di biblio langsung)
// ─────────────────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT
        b.biblio_id,
        b.title,
        COALESCE(b.classification, '000') AS classification,
        COALESCE(
            GROUP_CONCAT(DISTINCT t.topic ORDER BY t.topic SEPARATOR ' '),
        '') AS subject,
        COALESCE(
            GROUP_CONCAT(DISTINCT a.author_name ORDER BY a.author_name SEPARATOR ' '),
        '') AS author
    FROM biblio b
    LEFT JOIN biblio_topic  bt ON b.biblio_id  = bt.biblio_id
    LEFT JOIN mst_topic      t  ON bt.topic_id  = t.topic_id
    LEFT JOIN biblio_author ba  ON b.biblio_id  = ba.biblio_id
    LEFT JOIN mst_author     a  ON ba.author_id = a.author_id
    WHERE b.opac_hide = 0
    GROUP BY b.biblio_id, b.title, b.classification
    ORDER BY b.biblio_id
    LIMIT 500
");
$books = $stmt->fetchAll();

echo "✅ Buku dimuat dari SLIMS: " . count($books) . " judul\n";

if (empty($books)) {
    die("⚠️  Tidak ada data buku di SLIMS. Isi dulu data biblio.\n");
}

// ─────────────────────────────────────────────────────────
// STEP 2 — Fungsi DDC ke label teks
// ─────────────────────────────────────────────────────────
function ddcToLabel(string $ddc): string {
    $n = (int) preg_replace('/[^0-9]/', '', $ddc);
    if ($n < 100) return 'ilmu komputer teknologi informasi sistem';
    if ($n < 200) return 'filsafat psikologi';
    if ($n < 300) return 'agama';
    if ($n < 400) return 'ilmu sosial';
    if ($n < 500) return 'bahasa linguistik';
    if ($n < 600) return 'ilmu alam matematika sains fisika kimia biologi';
    if ($n < 700) return 'teknologi terapan teknik rekayasa';
    if ($n < 800) return 'seni budaya musik';
    if ($n < 900) return 'sastra';
    return 'sejarah geografi';
}

// ─────────────────────────────────────────────────────────
// STEP 3 — Tokenisasi
// ─────────────────────────────────────────────────────────
function tokenize(string $text): array {
    $text  = strtolower(trim($text));
    $text  = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    // Buang stopword sederhana
    $stop  = ['dan','atau','yang','untuk','dari','ke','di','ini','itu',
              'adalah','dengan','pada','oleh','dalam','sebagai','suatu',
              'the','and','for','with','from','using'];
    return array_values(array_filter($words,
        fn($w) => strlen($w) >= 3 && !in_array($w, $stop)
    ));
}

// ─────────────────────────────────────────────────────────
// STEP 4 — Bangun TF-IDF vektor
// ─────────────────────────────────────────────────────────
$documents = [];
foreach ($books as $b) {
    $text = implode(' ', [
        $b['title'],
        $b['subject'],
        $b['author'],
        ddcToLabel($b['classification'])
    ]);
    $documents[$b['biblio_id']] = tokenize($text);
}

// Hitung TF
$tf = [];
foreach ($documents as $bookId => $tokens) {
    $total  = count($tokens);
    if ($total === 0) continue;
    $counts = array_count_values($tokens);
    foreach ($counts as $term => $count) {
        $tf[$bookId][$term] = $count / $total;
    }
}

// Hitung DF
$df = [];
foreach ($documents as $bookId => $tokens) {
    foreach (array_unique($tokens) as $term) {
        $df[$term] = ($df[$term] ?? 0) + 1;
    }
}

// Hitung IDF
$N   = count($books);
$idf = [];
foreach ($df as $term => $docCount) {
    $idf[$term] = log($N / $docCount);
}

// Hitung TF-IDF
$tfidf = [];
foreach ($tf as $bookId => $terms) {
    foreach ($terms as $term => $tfVal) {
        $val = $tfVal * ($idf[$term] ?? 0);
        if ($val > 0) $tfidf[$bookId][$term] = $val;
    }
}

echo "✅ Vektor TF-IDF selesai dibangun untuk " . count($tfidf) . " buku\n";

// ─────────────────────────────────────────────────────────
// STEP 5 — Cosine Similarity
// ─────────────────────────────────────────────────────────
function cosineSim(array $vecA, array $vecB): float {
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $term => $val) {
        $dot   += $val * ($vecB[$term] ?? 0);
        $normA += $val * $val;
    }
    foreach ($vecB as $val) {
        $normB += $val * $val;
    }
    $denom = sqrt($normA) * sqrt($normB);
    return $denom > 0 ? round($dot / $denom, 6) : 0.0;
}

// ─────────────────────────────────────────────────────────
// STEP 6 — Mining: hitung semua pasang & simpan ke DB
// ─────────────────────────────────────────────────────────
$ids   = array_keys($tfidf);
$saved = 0;
$skip  = 0;

// Kosongkan tabel similarity lama
$pdo->exec("TRUNCATE TABLE rec_cbf_similarity");

$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO rec_cbf_similarity
        (biblio_id_a, biblio_id_b, similarity, computed_at)
    VALUES (?, ?, ?, NOW())
");

$total = count($ids);
echo "⏳ Mining CBF: {$total} buku → " . ($total * ($total-1)/2) . " pasang...\n";

for ($i = 0; $i < $total - 1; $i++) {
    for ($j = $i + 1; $j < $total; $j++) {
        $idA = $ids[$i];
        $idB = $ids[$j];

        // Lewati jika salah satu tidak punya vektor
        if (empty($tfidf[$idA]) || empty($tfidf[$idB])) {
            $skip++;
            continue;
        }

        $sim = cosineSim($tfidf[$idA], $tfidf[$idB]);

        if ($sim > 0.10) {
            $insertStmt->execute([$idA, $idB, $sim]);
            $saved++;
        }
    }
}

echo "✅ Mining CBF selesai!\n";
echo "   Pasang disimpan    : {$saved}\n";
echo "   Pasang dilewati    : {$skip} (vektor kosong)\n";

// ─────────────────────────────────────────────────────────
// STEP 7 — Tampilkan hasil: buku serupa
// ─────────────────────────────────────────────────────────
function cbfGetSimilar(PDO $pdo, int $targetId, int $topN = 5): array {
    $stmt = $pdo->prepare("
        SELECT
            CASE WHEN s.biblio_id_a = :id THEN s.biblio_id_b
                 ELSE s.biblio_id_a END AS similar_id,
            s.similarity,
            b.title,
            b.classification
        FROM rec_cbf_similarity s
        JOIN biblio b ON b.biblio_id = CASE
            WHEN s.biblio_id_a = :id THEN s.biblio_id_b
            ELSE s.biblio_id_a END
        WHERE (s.biblio_id_a = :id OR s.biblio_id_b = :id)
          AND b.opac_hide = 0
        ORDER BY s.similarity DESC
        LIMIT :topN
    ");
    $stmt->bindValue(':id',   $targetId, PDO::PARAM_INT);
    $stmt->bindValue(':topN', $topN,     PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Ambil 3 buku pertama dari SLIMS untuk dijadikan contoh
$sampleBooks = $pdo->query("
    SELECT biblio_id, title, classification
    FROM biblio WHERE opac_hide = 0
    LIMIT 3
")->fetchAll();

foreach ($sampleBooks as $target) {
    echo "\n" . str_repeat('═', 60) . "\n";
    echo "CBF: Buku serupa dengan [{$target['biblio_id']}] {$target['title']}\n";
    echo str_repeat('─', 60) . "\n";

    $results = cbfGetSimilar($pdo, (int)$target['biblio_id'], 5);

    if (empty($results)) {
        echo "  (Tidak ada buku serupa — coba turunkan threshold di INSERT)\n";
    }
    foreach ($results as $i => $r) {
        $bar = str_repeat('█', (int)($r['similarity'] * 25));
        echo sprintf("  %d. [%.4f] %s %-40s DDC:%s\n",
            $i + 1, $r['similarity'], $bar,
            mb_substr($r['title'], 0, 38), $r['classification']
        );
    }
}
