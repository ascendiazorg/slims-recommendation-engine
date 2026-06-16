<?php
/**
 * CF SIMULATION — PAKAI DATABASE SLIMS ASLI
 * ─────────────────────────────────────────────────────────
 * Perbedaan dari cf_simulation.php (versi array):
 *  - Tabel item (BUKAN biblio_item)
 *  - loan.member_id adalah VARCHAR(20), bukan INT
 *  - Join: loan → item → biblio (3 tabel, bukan 2)
 *  - Filter is_return = 1 (hanya buku yang sudah dikembalikan)
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/slims_connection.php';
$pdo = getSlimsConnection();

// ─────────────────────────────────────────────────────────
// STEP 1 — Cek data pinjaman tersedia
// ─────────────────────────────────────────────────────────
$totalLoan = $pdo->query("SELECT COUNT(*) FROM loan WHERE is_return = 1")->fetchColumn();
echo "✅ Total transaksi pinjaman (sudah dikembalikan): {$totalLoan}\n";

if ($totalLoan < 10) {
    die("⚠️  Data pinjaman terlalu sedikit (< 10). CF tidak akan akurat.\n"
      . "   Pastikan ada data di tabel loan & item di SLIMS.\n");
}

// ─────────────────────────────────────────────────────────
// STEP 2 — Bangun inverted index: biblio_id → [member_id, ...]
// JOIN: loan → item (bukan biblio_item!) → biblio
// ─────────────────────────────────────────────────────────
$rows = $pdo->query("
    SELECT
        l.member_id,            -- VARCHAR(20)
        i.biblio_id             -- INT
    FROM loan l
    JOIN item i ON l.item_code = i.item_code   -- ← NAMA TABEL: item bukan biblio_item
    WHERE l.is_return = 1
      AND i.biblio_id IS NOT NULL
")->fetchAll();

$borrowers = [];   // borrowers[biblio_id] = [member_id, ...]
$userLoans = [];   // userLoans[member_id] = [biblio_id, ...]

foreach ($rows as $row) {
    $bid = (int)$row['biblio_id'];
    $mid = $row['member_id'];   // tetap string VARCHAR(20)

    if (!in_array($mid, $borrowers[$bid] ?? [])) {
        $borrowers[$bid][] = $mid;
    }
    if (!in_array($bid, $userLoans[$mid] ?? [])) {
        $userLoans[$mid][] = $bid;
    }
}

$totalBooks = count($borrowers);
$totalUsers = count($userLoans);
echo "✅ Buku dengan riwayat pinjam  : {$totalBooks}\n";
echo "✅ Member yang pernah meminjam : {$totalUsers}\n";

// ─────────────────────────────────────────────────────────
// STEP 3 — Jaccard Similarity
// ─────────────────────────────────────────────────────────
function jaccardSim(array $setA, array $setB): float {
    $intersect = count(array_intersect($setA, $setB));
    $union     = count(array_unique(array_merge($setA, $setB)));
    return $union > 0 ? round($intersect / $union, 6) : 0.0;
}

// ─────────────────────────────────────────────────────────
// STEP 4 — Mining: hitung similarity dan simpan ke DB
// ─────────────────────────────────────────────────────────
$pdo->exec("TRUNCATE TABLE rec_cf_similarity");

$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO rec_cf_similarity
        (biblio_id_a, biblio_id_b, similarity, computed_at)
    VALUES (?, ?, ?, NOW())
");

$bookIds = array_keys($borrowers);
$total   = count($bookIds);
$saved   = 0;

echo "⏳ Mining CF: {$total} buku...\n";

foreach ($bookIds as $idA) {
    $usersA = $borrowers[$idA];
    if (count($usersA) < 2) continue;  // skip jika hanya 1 peminjam

    foreach ($bookIds as $idB) {
        if ($idA >= $idB) continue;    // hanya proses setengah matrix

        $usersB = $borrowers[$idB];
        if (count($usersB) < 2) continue;

        $sim = jaccardSim($usersA, $usersB);

        if ($sim >= 0.05) {
            $insertStmt->execute([$idA, $idB, $sim]);
            $saved++;
        }
    }
}

echo "✅ Mining CF selesai! Pasang disimpan: {$saved}\n";

// ─────────────────────────────────────────────────────────
// STEP 5 — Fungsi rekomendasi CF untuk satu user
// ─────────────────────────────────────────────────────────
function cfRecommend(PDO $pdo, string $memberId, int $topN = 5): array {
    // Ambil buku yang sudah dipinjam
    $borrowed = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l
        JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1
    ");
    $borrowed->execute([$memberId]);
    $alreadyBorrowed = $borrowed->fetchAll(PDO::FETCH_COLUMN);

    if (empty($alreadyBorrowed)) return [];

    // Ambil buku terakhir (5 terakhir)
    $recent = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l
        JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1
        ORDER BY l.loan_date DESC
        LIMIT 5
    ");
    $recent->execute([$memberId]);
    $recentBooks = $recent->fetchAll(PDO::FETCH_COLUMN);

    // Kumpulkan skor dari tabel CF similarity
    $scores = [];
    foreach ($recentBooks as $ownedId) {
        $sim = $pdo->prepare("
            SELECT
                CASE WHEN biblio_id_a = :id THEN biblio_id_b
                     ELSE biblio_id_a END AS candidate_id,
                similarity
            FROM rec_cf_similarity
            WHERE (biblio_id_a = :id OR biblio_id_b = :id)
              AND similarity >= 0.05
            ORDER BY similarity DESC
            LIMIT 30
        ");
        $sim->bindValue(':id', (int)$ownedId, PDO::PARAM_INT);
        $sim->execute();

        foreach ($sim->fetchAll() as $row) {
            $cid = (int)$row['candidate_id'];
            if (in_array($cid, $alreadyBorrowed)) continue;
            $scores[$cid] = ($scores[$cid] ?? 0) + (float)$row['similarity'];
        }
    }

    if (empty($scores)) return [];
    arsort($scores);
    $topIds = array_slice(array_keys($scores), 0, $topN);

    // Ambil detail buku
    $placeholders = implode(',', array_fill(0, count($topIds), '?'));
    $detail = $pdo->prepare("
        SELECT biblio_id, title, classification
        FROM biblio
        WHERE biblio_id IN ($placeholders)
    ");
    $detail->execute($topIds);
    $booksDetail = array_column($detail->fetchAll(), null, 'biblio_id');

    $result = [];
    foreach ($topIds as $id) {
        $result[] = [
            'biblio_id'      => $id,
            'title'          => $booksDetail[$id]['title'] ?? 'N/A',
            'classification' => $booksDetail[$id]['classification'] ?? '',
            'score'          => round($scores[$id], 4),
        ];
    }
    return $result;
}

// ─────────────────────────────────────────────────────────
// STEP 6 — Tampilkan hasil untuk beberapa member
// ─────────────────────────────────────────────────────────
// Ambil 3 member aktif dari SLIMS
$sampleMembers = $pdo->query("
    SELECT m.member_id, m.member_name, m.inst_name,
           COUNT(l.loan_id) AS total_pinjam
    FROM member m
    JOIN loan l ON m.member_id = l.member_id
    WHERE l.is_return = 1
    GROUP BY m.member_id, m.member_name, m.inst_name
    HAVING total_pinjam >= 2
    ORDER BY total_pinjam DESC
    LIMIT 3
")->fetchAll();

if (empty($sampleMembers)) {
    echo "⚠️  Tidak ada member dengan minimal 2 pinjaman. Periksa data loan & member.\n";
} else {
    foreach ($sampleMembers as $member) {
        echo "\n" . str_repeat('═', 60) . "\n";
        echo "CF: Rekomendasi untuk [{$member['member_id']}] {$member['member_name']}\n";
        echo "    Jurusan: {$member['inst_name']} | Total pinjam: {$member['total_pinjam']}\n";
        echo str_repeat('─', 60) . "\n";

        // Tampilkan buku yang sudah dipinjam
        $loanList = $pdo->prepare("
            SELECT DISTINCT b.title
            FROM loan l JOIN item i ON l.item_code = i.item_code
            JOIN biblio b ON i.biblio_id = b.biblio_id
            WHERE l.member_id = ? AND l.is_return = 1
            ORDER BY l.loan_date DESC LIMIT 5
        ");
        $loanList->execute([$member['member_id']]);
        $owned = $loanList->fetchAll(PDO::FETCH_COLUMN);
        echo "  Sudah pinjam: " . implode(' | ', array_map(fn($t) => mb_substr($t,0,20), $owned)) . "\n";
        echo str_repeat('─', 60) . "\n";

        $results = cfRecommend($pdo, $member['member_id'], 5);

        if (empty($results)) {
            echo "  (Tidak ada rekomendasi CF — perlu lebih banyak data pinjaman)\n";
        }
        foreach ($results as $i => $r) {
            $bar = str_repeat('█', (int)($r['score'] * 20));
            echo sprintf("  %d. [%.4f] %s %-38s DDC:%s\n",
                $i+1, $r['score'], $bar,
                mb_substr($r['title'], 0, 36), $r['classification']
            );
        }
    }
}
