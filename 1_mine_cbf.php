<?php
/**
 * STEP 1 — MINING CONTENT-BASED FILTERING (CBF)
 * ─────────────────────────────────────────────────────────
 * Algoritma : TF-IDF + Cosine Similarity
 * Input     : tabel biblio + biblio_topic + mst_topic + biblio_author + mst_author
 * Output    : tabel rec_cbf_similarity (pasang buku + nilai similarity)
 *
 * Cara jalankan:
 *   Browser : http://localhost/code_slims/1_mine_cbf.php
 *   Terminal: php 1_mine_cbf.php
 * ─────────────────────────────────────────────────────────
 * Jalankan SEBELUM membuka rekomendasi.php
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/config.php';
$pdo = getSlimsConnection();

$isBrowser = isset($_SERVER['HTTP_HOST']);
$t0 = microtime(true);

function pr(string $msg, bool $isBrowser): void {
    echo $isBrowser ? nl2br(htmlspecialchars($msg)) . "\n" : $msg;
    ob_flush(); flush();
}

if ($isBrowser) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
        body { font-family: monospace; background:#1e1e1e; color:#d4d4d4; padding:20px; }
        .ok  { color:#4ec9b0; } .warn { color:#dcdcaa; } .err { color:#f44747; }
        h2   { color:#9cdcfe; border-bottom:1px solid #333; padding-bottom:6px; }
        pre  { margin:0; }
    </style>
    </head><body><h2>⚙️ Mining CBF — TF-IDF + Cosine Similarity</h2><pre>';
}

// ─────────────────────────────────────────────────────────
// STEP 1 — Ambil buku dari SLIMS (JOIN ke mst_topic & mst_author)
// ─────────────────────────────────────────────────────────
pr("📖 Mengambil data buku dari SLIMS...\n", $isBrowser);

$rows = $pdo->query("
    SELECT
        b.biblio_id,
        b.title,
        COALESCE(b.classification, '000') AS classification,
        COALESCE(
            GROUP_CONCAT(DISTINCT t.topic ORDER BY t.topic SEPARATOR ' '), ''
        ) AS subject,
        COALESCE(
            GROUP_CONCAT(DISTINCT a.author_name ORDER BY a.author_name SEPARATOR ' '), ''
        ) AS author
    FROM biblio b
    LEFT JOIN biblio_topic  bt ON b.biblio_id = bt.biblio_id
    LEFT JOIN mst_topic      t  ON bt.topic_id = t.topic_id
    LEFT JOIN biblio_author ba  ON b.biblio_id = ba.biblio_id
    LEFT JOIN mst_author     a  ON ba.author_id = a.author_id
    WHERE b.opac_hide = 0
    GROUP BY b.biblio_id, b.title, b.classification
    ORDER BY b.biblio_id
    LIMIT 2000
")->fetchAll();

if (empty($rows)) {
    pr("⚠️  Tidak ada data buku (biblio kosong atau semua opac_hide=1).\n", $isBrowser);
    if ($isBrowser) echo '</pre></body></html>';
    exit;
}
pr("✅ Buku dimuat: " . count($rows) . " judul\n\n", $isBrowser);

// ─────────────────────────────────────────────────────────
// STEP 2 — DDC label, Tokenisasi, TF-IDF
// ─────────────────────────────────────────────────────────
function ddcLabel(string $ddc): string {
    $n = (int) preg_replace('/[^0-9]/', '', $ddc);
    if ($n <  100) return 'ilmu komputer teknologi informasi sistem';
    if ($n <  200) return 'filsafat psikologi';
    if ($n <  300) return 'agama';
    if ($n <  400) return 'ilmu sosial';
    if ($n <  500) return 'bahasa linguistik';
    if ($n <  600) return 'ilmu alam matematika sains fisika kimia biologi';
    if ($n <  700) return 'teknologi terapan teknik rekayasa';
    if ($n <  800) return 'seni budaya musik';
    if ($n <  900) return 'sastra';
    return 'sejarah geografi';
}

function tokenize(string $text): array {
    $text  = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $stop  = ['dan','atau','yang','untuk','dari','ke','di','ini','itu','adalah',
              'dengan','pada','oleh','dalam','sebagai','the','and','for','with',
              'from','using','suatu','sebuah','oleh','tidak'];
    return array_values(array_filter($words,
        fn($w) => strlen($w) >= 3 && !in_array($w, $stop)
    ));
}

$documents = [];
foreach ($rows as $b) {
    $text = implode(' ', [$b['title'], $b['subject'], $b['author'], ddcLabel($b['classification'])]);
    $documents[$b['biblio_id']] = tokenize($text);
}

// TF
$tf = [];
foreach ($documents as $bid => $tokens) {
    $total  = count($tokens);
    if ($total === 0) continue;
    $counts = array_count_values($tokens);
    foreach ($counts as $term => $count) {
        $tf[$bid][$term] = $count / $total;
    }
}

// DF + IDF
$df = [];
foreach ($documents as $tokens) {
    foreach (array_unique($tokens) as $term) {
        $df[$term] = ($df[$term] ?? 0) + 1;
    }
}
$N   = count($rows);
$idf = [];
foreach ($df as $term => $docCount) {
    $idf[$term] = log($N / $docCount);
}

// TF-IDF
$tfidf = [];
foreach ($tf as $bid => $terms) {
    foreach ($terms as $term => $tfVal) {
        $val = $tfVal * ($idf[$term] ?? 0);
        if ($val > 0) $tfidf[$bid][$term] = $val;
    }
}

pr("✅ TF-IDF dibangun: " . count($tfidf) . " buku memiliki vektor\n\n", $isBrowser);

// ─────────────────────────────────────────────────────────
// STEP 3 — Cosine Similarity + Simpan ke DB
// ─────────────────────────────────────────────────────────
function cosineSim(array $a, array $b): float {
    $dot = $normA = $normB = 0.0;
    foreach ($a as $k => $v) { $dot += $v * ($b[$k] ?? 0); $normA += $v * $v; }
    foreach ($b as $v) { $normB += $v * $v; }
    $d = sqrt($normA) * sqrt($normB);
    return $d > 0 ? round($dot / $d, 6) : 0.0;
}

$pdo->exec("TRUNCATE TABLE rec_cbf_similarity");

$stmt = $pdo->prepare(
    "INSERT IGNORE INTO rec_cbf_similarity (biblio_id_a, biblio_id_b, similarity, computed_at)
     VALUES (?, ?, ?, NOW())"
);

$ids   = array_keys($tfidf);
$total = count($ids);
$pairs = $total * ($total - 1) / 2;
pr("⏳ Mining {$total} buku → {$pairs} pasang...\n", $isBrowser);

$saved = 0;
$threshold = 0.10;

for ($i = 0; $i < $total - 1; $i++) {
    for ($j = $i + 1; $j < $total; $j++) {
        $idA = $ids[$i];
        $idB = $ids[$j];
        if (empty($tfidf[$idA]) || empty($tfidf[$idB])) continue;

        $sim = cosineSim($tfidf[$idA], $tfidf[$idB]);
        if ($sim >= $threshold) {
            $stmt->execute([$idA, $idB, $sim]);
            $saved++;
        }
    }
    if ($isBrowser && $i % 50 === 0) {
        ob_flush(); flush();
    }
}

$elapsed = round(microtime(true) - $t0, 2);
pr("\n✅ Mining CBF selesai!\n", $isBrowser);
pr("   Pasang tersimpan  : {$saved} (similarity ≥ {$threshold})\n", $isBrowser);
pr("   Total buku        : {$total}\n", $isBrowser);
pr("   Waktu             : {$elapsed} detik\n\n", $isBrowser);
pr("👉 Lanjutkan dengan menjalankan: 2_mine_cf.php\n", $isBrowser);

if ($isBrowser) echo '</pre></body></html>';
