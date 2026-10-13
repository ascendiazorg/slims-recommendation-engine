<?php
/**
 * Evaluasi Perbandingan Algoritma
 * CF saja vs CBF saja vs Hybrid (CF 60% + CBF 40%)
 * Metode: Leave-One-Out Cross Validation, Top-5
 */

define('INDEX_AUTH', 1);
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'localhost';

require_once __DIR__ . '/../../sysconfig.inc.php';

require_once __DIR__ . '/classes/CollaborativeFilter.php';
require_once __DIR__ . '/classes/ContentBasedFilter.php';
require_once __DIR__ . '/classes/HybridRecommender.php';

$TOP_N   = 5;
$cf      = new CollaborativeFilter($dbs);
$cbf     = new ContentBasedFilter($dbs);

// -----------------------------------------------------------------------
// Ambil semua member dengan riwayat >= 2 buku
// -----------------------------------------------------------------------
$result = $dbs->query(
    "SELECT member_id, COUNT(DISTINCT biblio_id) AS total
     FROM loan_history WHERE is_return = 1
     GROUP BY member_id HAVING total >= 2"
);
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row['member_id'];
}

$totalTests = 0;
$hits = ['cf' => 0, 'cbf' => 0, 'hybrid' => 0];
$detail = [];

// -----------------------------------------------------------------------
// Fungsi normalize
// -----------------------------------------------------------------------
function normalize(array $scores): array {
    $max = !empty($scores) ? max($scores) : 0;
    return $max > 0 ? array_map(fn($s) => $s / $max, $scores) : $scores;
}

// -----------------------------------------------------------------------
// LOO untuk setiap member
// -----------------------------------------------------------------------
foreach ($members as $memberId) {
    // Urutkan berdasarkan waktu kembali — sembunyikan buku PALING TERAKHIR dipinjam
    $r2 = $dbs->query(
        "SELECT biblio_id,
                COALESCE(MAX(return_date), MAX(loan_date), '1900-01-01') AS last_return
         FROM loan_history
         WHERE member_id = '{$dbs->escape_string($memberId)}' AND is_return = 1
         GROUP BY biblio_id
         ORDER BY last_return ASC"
    );
    $allBorrowed = [];
    while ($row = $r2->fetch_assoc()) {
        $allBorrowed[] = (int)$row['biblio_id'];
    }
    if (count($allBorrowed) < 2) continue;

    // Sembunyikan buku yang paling terakhir dikembalikan
    $testBook  = array_pop($allBorrowed);
    $remaining = $allBorrowed;

    // --- CF saja ---
    $cfScores  = $cf->getScores($remaining, []);
    arsort($cfScores);
    $cfTop = array_slice($cfScores, 0, $TOP_N, true);

    // --- CBF saja ---
    $cbfScores = $cbf->getScores($remaining, []);
    arsort($cbfScores);
    $cbfTop = array_slice($cbfScores, 0, $TOP_N, true);

    // --- Hybrid — pakai konstanta dari HybridRecommender agar bobot selalu sinkron ---
    $cfNorm  = normalize($cfScores);
    $cbfNorm = normalize($cbfScores);
    $candidates = array_unique(array_merge(array_keys($cfNorm), array_keys($cbfNorm)));
    $hybridScores = [];
    foreach ($candidates as $cid) {
        $hybridScores[$cid] = (HybridRecommender::BOBOT_CF  * ($cfNorm[$cid]  ?? 0))
                            + (HybridRecommender::BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
    }
    arsort($hybridScores);
    $hybridTop = array_slice($hybridScores, 0, $TOP_N, true);

    // Cek hit
    $hitCf     = array_key_exists($testBook, $cfTop);
    $hitCbf    = array_key_exists($testBook, $cbfTop);
    $hitHybrid = array_key_exists($testBook, $hybridTop);

    if ($hitCf)     $hits['cf']++;
    if ($hitCbf)    $hits['cbf']++;
    if ($hitHybrid) $hits['hybrid']++;

    $detail[] = [
        'member_id' => $memberId,
        'test_book' => $testBook,
        'hit_cf'    => $hitCf,
        'hit_cbf'   => $hitCbf,
        'hit_hybrid'=> $hitHybrid,
    ];

    $totalTests++;
}

// -----------------------------------------------------------------------
// Hitung metrik
// -----------------------------------------------------------------------
function calcMetrics(int $hits, int $totalTests, int $topN): array {
    if ($totalTests === 0) return ['precision' => 0, 'recall' => 0, 'f_measure' => 0];
    $p = $hits / ($totalTests * $topN);
    $r = $hits / $totalTests;
    $f = ($p + $r) > 0 ? 2 * $p * $r / ($p + $r) : 0;
    return [
        'precision' => round($p * 100, 2),
        'recall'    => round($r * 100, 2),
        'f_measure' => round($f * 100, 2),
        'hits'      => $hits,
    ];
}

$results = [
    'CF (Item-Based Jaccard)'         => calcMetrics($hits['cf'],     $totalTests, $TOP_N),
    'CBF (TF-IDF Cosine Similarity)'  => calcMetrics($hits['cbf'],    $totalTests, $TOP_N),
    'Hybrid (CF 60% + CBF 40%)'       => calcMetrics($hits['hybrid'], $totalTests, $TOP_N),
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Evaluasi Algoritma - Recommendation Engine</title>
<style>
body { font-family: Arial, sans-serif; padding: 24px; max-width: 900px; color: #333; }
h2  { color: #2c5f8a; margin-bottom: 4px; }
.subtitle { color: #777; font-size: 13px; margin-bottom: 24px; }

/* Tabel utama perbandingan */
.tbl-compare { width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 32px; }
.tbl-compare th { background: #2c5f8a; color: #fff; padding: 10px 14px; text-align: center; }
.tbl-compare th:first-child { text-align: left; }
.tbl-compare td { padding: 10px 14px; border-bottom: 1px solid #e0e0e0; text-align: center; }
.tbl-compare td:first-child { text-align: left; font-weight: bold; }
.tbl-compare tr:last-child td { background: #eef4fb; font-weight: bold; }
.best { color: #28a745; font-weight: bold; }

/* Tabel detail per member */
.tbl-detail { width: 100%; border-collapse: collapse; font-size: 12px; }
.tbl-detail th { background: #555; color: #fff; padding: 7px 10px; text-align: center; }
.tbl-detail td { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: center; }
.tbl-detail tr:nth-child(even) td { background: #f9f9f9; }
.hit  { color: #28a745; font-weight: bold; }
.miss { color: #dc3545; }

.badge-info { display:inline-block; background:#2c5f8a; color:#fff;
              border-radius:3px; padding:2px 8px; font-size:11px; }
</style>
</head>
<body>

<h2>Evaluasi Perbandingan Algoritma Rekomendasi</h2>
<p class="subtitle">
    Metode: Leave-One-Out Cross Validation &nbsp;|&nbsp;
    Top-N: <?= $TOP_N ?> &nbsp;|&nbsp;
    Data Uji: <b><?= $totalTests ?> member</b>
</p>

<!-- Tabel Perbandingan -->
<table class="tbl-compare">
    <thead>
        <tr>
            <th>Algoritma</th>
            <th>Hit</th>
            <th>Precision</th>
            <th>Recall</th>
            <th>F-Measure</th>
        </tr>
    </thead>
    <tbody>
    <?php
    // Cari nilai tertinggi tiap kolom untuk highlight
    $maxP = max(array_column($results, 'precision'));
    $maxR = max(array_column($results, 'recall'));
    $maxF = max(array_column($results, 'f_measure'));

    foreach ($results as $algo => $m):
    ?>
    <tr>
        <td><?= $algo ?></td>
        <td><?= $m['hits'] ?>/<?= $totalTests ?></td>
        <td class="<?= $m['precision'] === $maxP ? 'best' : '' ?>">
            <?= $m['precision'] ?>%
        </td>
        <td class="<?= $m['recall'] === $maxR ? 'best' : '' ?>">
            <?= $m['recall'] ?>%
        </td>
        <td class="<?= $m['f_measure'] === $maxF ? 'best' : '' ?>">
            <?= $m['f_measure'] ?>%
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Detail per Member -->
<h3 style="color:#2c5f8a;font-size:15px">
    Detail Hasil per Member
    <span class="badge-info"><?= $totalTests ?> data uji</span>
</h3>
<table class="tbl-detail">
    <thead>
        <tr>
            <th>No</th>
            <th>Member ID</th>
            <th>Buku Tersembunyi</th>
            <th>CF</th>
            <th>CBF</th>
            <th>Hybrid</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($detail as $i => $d):
        // Ambil judul buku
        $judul = $dbs->query(
            "SELECT title FROM biblio WHERE biblio_id = {$d['test_book']}"
        )->fetch_assoc()['title'] ?? 'Unknown';
    ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($d['member_id']) ?></td>
        <td style="text-align:left">#<?= $d['test_book'] ?> — <?= htmlspecialchars(mb_substr($judul, 0, 45)) ?></td>
        <td class="<?= $d['hit_cf']     ? 'hit' : 'miss' ?>"><?= $d['hit_cf']     ? 'HIT' : 'miss' ?></td>
        <td class="<?= $d['hit_cbf']    ? 'hit' : 'miss' ?>"><?= $d['hit_cbf']    ? 'HIT' : 'miss' ?></td>
        <td class="<?= $d['hit_hybrid'] ? 'hit' : 'miss' ?>"><?= $d['hit_hybrid'] ? 'HIT' : 'miss' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="margin-top:20px;font-size:12px;color:#aaa">
    Nilai terbaik per kolom ditandai warna <span style="color:#28a745;font-weight:bold">hijau</span>.
    &nbsp;|&nbsp; CF = Collaborative Filtering &nbsp;|&nbsp; CBF = Content-Based Filtering
</p>

</body>
</html>
