<?php
/**
 * ============================================================
 * EVALUASI STANDALONE — TANPA DATABASE SLIMS
 * ============================================================
 * Bisa dijalankan TANPA perlu koneksi database.
 * Data dummy: 20 buku, 15 user dengan pola pinjam realistis.
 *
 * Algoritma dievaluasi:
 *   - CBF  : TF-IDF + Cosine Similarity
 *   - CF   : Item-Based + Jaccard Similarity
 *   - HYBRID: Weighted Fusion CF=60% + CBF=40%
 *
 * Metode evaluasi: Leave-One-Out Cross Validation (LOOCV)
 * Metrik: Precision, Recall, F-Measure
 *
 * Cara jalankan:
 *   Browser : http://localhost/code_slims/evaluasi_standalone.php
 *   Terminal: php evaluasi_standalone.php
 * ============================================================
 */

// ============================================================
// DATA: 20 Buku
// ============================================================
$books = [
    1  => ['title' => 'Pemrograman Python untuk Pemula',      'subject' => 'python pemrograman komputer coding'],
    2  => ['title' => 'Machine Learning dengan Python',       'subject' => 'machine learning python kecerdasan buatan'],
    3  => ['title' => 'Deep Learning dan Neural Network',     'subject' => 'deep learning neural network kecerdasan buatan'],
    4  => ['title' => 'Algoritma dan Struktur Data',          'subject' => 'algoritma struktur data pemrograman komputer'],
    5  => ['title' => 'Basis Data MySQL untuk Web',           'subject' => 'database mysql basis data web server'],
    6  => ['title' => 'Jaringan Komputer dan Keamanan',       'subject' => 'jaringan komputer keamanan network'],
    7  => ['title' => 'Pemrograman Web dengan PHP',           'subject' => 'php web pemrograman coding server'],
    8  => ['title' => 'Data Science dan Analitik',            'subject' => 'data science analitik statistik python'],
    9  => ['title' => 'Sistem Operasi Linux',                 'subject' => 'linux sistem operasi komputer server'],
    10 => ['title' => 'Kecerdasan Buatan Teori dan Praktik',  'subject' => 'kecerdasan buatan artificial intelligence machine'],
    11 => ['title' => 'Pemrograman Java untuk Pemula',        'subject' => 'java pemrograman komputer objek oriented'],
    12 => ['title' => 'Keamanan Siber dan Kriptografi',       'subject' => 'keamanan siber kriptografi enkripsi network'],
    13 => ['title' => 'Cloud Computing dan Virtualisasi',     'subject' => 'cloud computing virtualisasi server infrastruktur'],
    14 => ['title' => 'Internet of Things dengan Arduino',    'subject' => 'iot arduino sensor hardware embedded'],
    15 => ['title' => 'Analisis dan Desain Sistem Informasi', 'subject' => 'sistem informasi analisis desain bisnis proses'],
    16 => ['title' => 'Pemrograman Mobile Android',           'subject' => 'android mobile pemrograman java aplikasi'],
    17 => ['title' => 'Statistika untuk Data Science',        'subject' => 'statistika matematika data analisis regresi'],
    18 => ['title' => 'Pengolahan Citra Digital',             'subject' => 'citra digital pengolahan gambar computer vision'],
    19 => ['title' => 'Komputer Kuantum dan Algoritma',       'subject' => 'komputer kuantum algoritma komputasi fisika'],
    20 => ['title' => 'Rekayasa Perangkat Lunak',             'subject' => 'rekayasa perangkat lunak software engineering agile'],
];

// ============================================================
// DATA: 15 User + Riwayat Pinjaman
// ============================================================
$users = [
    1  => ['name' => 'Budi',  'jurusan' => 'Teknik Informatika', 'pinjam' => [1, 2, 4, 11]],
    2  => ['name' => 'Ani',   'jurusan' => 'Sistem Informasi',   'pinjam' => [1, 2, 3, 8]],
    3  => ['name' => 'Reza',  'jurusan' => 'Teknik Informatika', 'pinjam' => [4, 5, 7, 9]],
    4  => ['name' => 'Sari',  'jurusan' => 'Ilmu Komputer',      'pinjam' => [2, 3, 10, 19]],
    5  => ['name' => 'Dito',  'jurusan' => 'Teknik Informatika', 'pinjam' => [1, 4, 11, 16]],
    6  => ['name' => 'Nisa',  'jurusan' => 'Sistem Informasi',   'pinjam' => [5, 7, 13, 15]],
    7  => ['name' => 'Eko',   'jurusan' => 'Teknik Informatika', 'pinjam' => [2, 8, 17, 18]],
    8  => ['name' => 'Putri', 'jurusan' => 'Ilmu Komputer',      'pinjam' => [2, 3, 10, 19]],
    9  => ['name' => 'Hadi',  'jurusan' => 'Teknik Elektro',     'pinjam' => [6, 12, 13, 14]],
    10 => ['name' => 'Lina',  'jurusan' => 'Sistem Informasi',   'pinjam' => [5, 7, 15, 16]],
    11 => ['name' => 'Fajar', 'jurusan' => 'Teknik Informatika', 'pinjam' => [1, 2, 8, 17]],
    12 => ['name' => 'Maya',  'jurusan' => 'Matematika',         'pinjam' => [2, 8, 10, 17]],
    13 => ['name' => 'Bagas', 'jurusan' => 'Teknik Informatika', 'pinjam' => [4, 9, 11, 20]],
    14 => ['name' => 'Citra', 'jurusan' => 'Sistem Informasi',   'pinjam' => [5, 6, 7, 13]],
    15 => ['name' => 'Yoga',  'jurusan' => 'Teknik Elektro',     'pinjam' => [6, 9, 12, 14]],
];

$bookIds = array_keys($books);
$userIds = array_keys($users);

const BOBOT_CF_EVAL  = 0.60;
const BOBOT_CBF_EVAL = 0.40;
const TOP_N_EVAL     = 3;

// ============================================================
// FUNGSI UTILITAS
// ============================================================
function tokenizeEval(string $text): array {
    $tokens = preg_split('/\s+/', strtolower(trim($text)));
    return array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
}

function cosineSimEval(array $vecA, array $vecB): float {
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $k => $w) { $dot += $w * ($vecB[$k] ?? 0); $normA += $w * $w; }
    foreach ($vecB as $w) { $normB += $w * $w; }
    $d = sqrt($normA) * sqrt($normB);
    return $d > 0 ? round($dot / $d, 6) : 0.0;
}

function normalizeEval(array $scores): array {
    if (empty($scores)) return $scores;
    $max = max($scores);
    return $max > 0 ? array_map(fn($s) => round($s / $max, 6), $scores) : $scores;
}

// ============================================================
// BANGUN TF-IDF + MATRIKS CBF (sekali, static)
// ============================================================
$tokenized = [];
foreach ($books as $id => $book) {
    $tokenized[$id] = tokenizeEval($book['title'] . ' ' . $book['subject']);
}

$N       = count($books);
$allTerms = array_unique(array_merge(...array_values($tokenized)));
$idf     = [];
foreach ($allTerms as $kata) {
    $df = 0;
    foreach ($tokenized as $tokens) { if (in_array($kata, $tokens)) $df++; }
    $idf[$kata] = log($N / ($df + 1)) + 1;
}

$tfidf = [];
foreach ($tokenized as $id => $tokens) {
    $total = count($tokens);
    $freq  = array_count_values($tokens);
    foreach ($freq as $kata => $jml) {
        $tfidf[$id][$kata] = ($jml / $total) * ($idf[$kata] ?? 1);
    }
}

$cbfMatrix = [];
foreach ($bookIds as $a) {
    foreach ($bookIds as $b) {
        $cbfMatrix[$a][$b] = ($a === $b) ? 1.0 : cosineSimEval($tfidf[$a] ?? [], $tfidf[$b] ?? []);
    }
}

// ============================================================
// FUNGSI REKOMENDASI CBF
// ============================================================
function getCBFRecs(array $trainSet, array $bookIds, array $cbfMatrix, int $topN): array {
    $kandidat = array_diff($bookIds, $trainSet);
    $scores   = [];
    foreach ($kandidat as $cid) {
        $total = 0;
        foreach ($trainSet as $pid) $total += $cbfMatrix[$cid][$pid] ?? 0;
        $scores[$cid] = count($trainSet) > 0 ? $total / count($trainSet) : 0;
    }
    arsort($scores);
    return array_keys(array_slice($scores, 0, $topN, true));
}

// ============================================================
// FUNGSI REKOMENDASI CF
// ============================================================
function getCFRecs(int $uid, array $trainSet, array $users, array $bookIds, int $topN): array {
    $matrix = [];
    foreach ($users as $u => $data) {
        $pinjamU = ($u === $uid) ? $trainSet : $data['pinjam'];
        foreach ($bookIds as $bid) {
            $matrix[$u][$bid] = in_array($bid, $pinjamU) ? 1 : 0;
        }
    }

    $cfSim = [];
    foreach ($bookIds as $a) {
        foreach ($bookIds as $b) {
            if ($a === $b) { $cfSim[$a][$b] = 1.0; continue; }
            $irisan = $gabungan = 0;
            foreach ($matrix as $row) {
                $va = $row[$a] ?? 0; $vb = $row[$b] ?? 0;
                if ($va && $vb) $irisan++;
                if ($va || $vb) $gabungan++;
            }
            $cfSim[$a][$b] = $gabungan > 0 ? $irisan / $gabungan : 0.0;
        }
    }

    $kandidat = array_diff($bookIds, $trainSet);
    $scores   = [];
    foreach ($kandidat as $cid) {
        $total = 0;
        foreach ($trainSet as $pid) $total += $cfSim[$cid][$pid] ?? 0;
        $scores[$cid] = count($trainSet) > 0 ? $total / count($trainSet) : 0;
    }
    arsort($scores);
    return array_keys(array_slice($scores, 0, $topN, true));
}

// ============================================================
// FUNGSI REKOMENDASI HYBRID
// ============================================================
function getHybridRecs(int $uid, array $trainSet, array $users, array $bookIds, array $cbfMatrix, int $topN): array {
    $matrix = [];
    foreach ($users as $u => $data) {
        $pinjamU = ($u === $uid) ? $trainSet : $data['pinjam'];
        foreach ($bookIds as $bid) {
            $matrix[$u][$bid] = in_array($bid, $pinjamU) ? 1 : 0;
        }
    }

    $cfSim = [];
    foreach ($bookIds as $a) {
        foreach ($bookIds as $b) {
            if ($a === $b) { $cfSim[$a][$b] = 1.0; continue; }
            $irisan = $gabungan = 0;
            foreach ($matrix as $row) {
                $va = $row[$a] ?? 0; $vb = $row[$b] ?? 0;
                if ($va && $vb) $irisan++;
                if ($va || $vb) $gabungan++;
            }
            $cfSim[$a][$b] = $gabungan > 0 ? $irisan / $gabungan : 0.0;
        }
    }

    $kandidat = array_diff($bookIds, $trainSet);
    $cbfScores = $cfScores = [];
    foreach ($kandidat as $cid) {
        $tc = $tf = 0;
        foreach ($trainSet as $pid) {
            $tc += $cbfMatrix[$cid][$pid] ?? 0;
            $tf += $cfSim[$cid][$pid]    ?? 0;
        }
        $cbfScores[$cid] = count($trainSet) > 0 ? $tc / count($trainSet) : 0;
        $cfScores[$cid]  = count($trainSet) > 0 ? $tf / count($trainSet) : 0;
    }

    $cbfNorm = normalizeEval($cbfScores);
    $cfNorm  = normalizeEval($cfScores);

    $hybridScores = [];
    foreach ($kandidat as $cid) {
        $hybridScores[$cid] = (BOBOT_CF_EVAL * ($cfNorm[$cid] ?? 0))
                            + (BOBOT_CBF_EVAL * ($cbfNorm[$cid] ?? 0));
    }
    arsort($hybridScores);
    return array_keys(array_slice($hybridScores, 0, $topN, true));
}

// ============================================================
// FUNGSI HITUNG METRIK
// ============================================================
function computeMetrics(array $results): array {
    $tp = $fp = $fn = 0;
    foreach ($results as $r) { $tp += $r['tp']; $fp += $r['fp']; $fn += $r['fn']; }
    $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0;
    $recall    = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0;
    $fMeasure  = ($precision + $recall) > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0;
    return [
        'precision'  => round($precision, 4),
        'recall'     => round($recall, 4),
        'f_measure'  => round($fMeasure, 4),
        'tp'         => $tp, 'fp' => $fp, 'fn' => $fn,
        'iterations' => count($results),
    ];
}

// ============================================================
// JALANKAN LOOCV
// ============================================================
$resultsCBF = $resultsCF = $resultsHybrid = [];
$detail     = [];

foreach ($users as $uid => $user) {
    $allPinjam = $user['pinjam'];
    if (count($allPinjam) < 2) continue;

    foreach ($allPinjam as $heldOut) {
        $trainSet = array_values(array_diff($allPinjam, [$heldOut]));

        $recCBF    = getCBFRecs($trainSet, $bookIds, $cbfMatrix, TOP_N_EVAL);
        $recCF     = getCFRecs($uid, $trainSet, $users, $bookIds, TOP_N_EVAL);
        $recHybrid = getHybridRecs($uid, $trainSet, $users, $bookIds, $cbfMatrix, TOP_N_EVAL);

        $tpCBF = in_array($heldOut, $recCBF)    ? 1 : 0;
        $tpCF  = in_array($heldOut, $recCF)     ? 1 : 0;
        $tpHyb = in_array($heldOut, $recHybrid) ? 1 : 0;

        $resultsCBF[]    = ['tp' => $tpCBF, 'fp' => TOP_N_EVAL - $tpCBF, 'fn' => 1 - $tpCBF];
        $resultsCF[]     = ['tp' => $tpCF,  'fp' => TOP_N_EVAL - $tpCF,  'fn' => 1 - $tpCF];
        $resultsHybrid[] = ['tp' => $tpHyb, 'fp' => TOP_N_EVAL - $tpHyb, 'fn' => 1 - $tpHyb];

        $detail[] = [
            'uid' => $uid, 'nama' => $user['name'], 'heldOut' => $heldOut,
            'trainSet' => $trainSet,
            'recCBF' => $recCBF, 'recCF' => $recCF, 'recHybrid' => $recHybrid,
            'tpCBF' => $tpCBF, 'tpCF' => $tpCF, 'tpHyb' => $tpHyb,
        ];
    }
}

$metrikCBF    = computeMetrics($resultsCBF);
$metrikCF     = computeMetrics($resultsCF);
$metrikHybrid = computeMetrics($resultsHybrid);

// ============================================================
// OUTPUT — Browser atau CLI
// ============================================================
$isBrowser = isset($_SERVER['HTTP_HOST']);

if ($isBrowser):
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Evaluasi Standalone — Recommendation Engine</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #2c3e50; padding: 24px; }
h1   { font-size: 22px; color: #1a5276; margin-bottom: 6px; }
h2   { font-size: 16px; color: #1a5276; margin: 28px 0 10px; border-bottom: 2px solid #2980b9; padding-bottom: 6px; }
h3   { font-size: 14px; color: #555; margin: 16px 0 8px; }
.sub { font-size: 13px; color: #7f8c8d; margin-bottom: 20px; }
table  { border-collapse: collapse; width: 100%; margin-bottom: 16px; font-size: 13px; }
th     { background: #1a5276; color: white; padding: 9px 12px; text-align: left; }
td     { padding: 8px 12px; border-bottom: 1px solid #e8ecef; }
tr:hover td { background: #f5f9ff; }
.hit   { color: #27ae60; font-weight: 600; }
.miss  { color: #e74c3c; }
.cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 20px; }
.card  { background: white; border-radius: 10px; padding: 18px; border: 1px solid #e0e6ed; }
.card.cbf { border-top: 4px solid #27ae60; }
.card.cf  { border-top: 4px solid #2980b9; }
.card.hyb { border-top: 4px solid #e67e22; }
.card h3  { font-size: 13px; margin: 0 0 12px; }
.metric-val { font-size: 28px; font-weight: 700; margin-bottom: 2px; }
.metric-name { font-size: 11px; color: #7f8c8d; margin-bottom: 14px; }
.bar-wrap { background: #eee; border-radius: 4px; height: 8px; margin-top: 4px; }
.bar-fill { height: 8px; border-radius: 4px; }
.best { font-weight: 700; color: #1e8449; }
.info-box { background: #eaf4fb; border-left: 4px solid #2980b9; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 13px; margin-bottom: 20px; }
</style>
</head>
<body>

<h1>📊 Evaluasi Sistem Rekomendasi — STANDALONE</h1>
<p class="sub">
    Data: <?= count($books) ?> buku &bull; <?= count($users) ?> user &bull;
    Metode: LOOCV &bull; Top-N: <?= TOP_N_EVAL ?> &bull;
    Total iterasi: <?= count($detail) ?>
</p>

<div class="info-box">
    <strong>Metode LOOCV:</strong> Untuk setiap user dan setiap buku dalam riwayatnya,
    satu buku disembunyikan (<em>held-out</em>). Algoritma dijalankan dengan sisa buku,
    lalu dicek apakah buku tersembunyi muncul di Top-<?= TOP_N_EVAL ?> rekomendasi.
    TP = buku ditemukan, FN = tidak ditemukan.
</div>

<h2>Kartu Metrik per Algoritma</h2>
<div class="cards">
<?php
$algo_cards = [
    ['class' => 'cbf', 'label' => '📗 CBF — TF-IDF + Cosine', 'color' => '#27ae60', 'm' => $metrikCBF],
    ['class' => 'cf',  'label' => '📘 CF — Item-Based + Jaccard', 'color' => '#2980b9', 'm' => $metrikCF],
    ['class' => 'hyb', 'label' => '🔀 Hybrid (CF 60% + CBF 40%)', 'color' => '#e67e22', 'm' => $metrikHybrid],
];
foreach ($algo_cards as $ac):
    $m = $ac['m'];
    $color = function(float $v): string {
        if ($v >= 0.6) return '#27ae60';
        if ($v >= 0.3) return '#e67e22';
        return '#e74c3c';
    };
?>
<div class="card <?= $ac['class'] ?>">
    <h3 style="color:<?= $ac['color'] ?>"><?= $ac['label'] ?></h3>
    <?php foreach (['precision' => 'Precision', 'recall' => 'Recall', 'f_measure' => 'F1-Measure'] as $key => $label): ?>
    <div class="metric-val" style="color:<?= $color($m[$key]) ?>"><?= number_format($m[$key] * 100, 1) ?>%</div>
    <div class="metric-name"><?= $label ?></div>
    <div class="bar-wrap"><div class="bar-fill" style="width:<?= round($m[$key]*100) ?>%;background:<?= $color($m[$key]) ?>"></div></div>
    <?php endforeach; ?>
    <div style="font-size:11px;color:#7f8c8d;margin-top:10px;">
        TP: <?= $m['tp'] ?> &bull; FP: <?= $m['fp'] ?> &bull; FN: <?= $m['fn'] ?>
        &bull; Iterasi: <?= $m['iterations'] ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<h2>Tabel Perbandingan Metrik</h2>
<table>
    <thead>
        <tr><th>Algoritma</th><th>Precision</th><th>Recall</th><th>F1-Measure</th><th>TP</th><th>FP</th><th>FN</th><th>Iterasi</th></tr>
    </thead>
    <tbody>
    <?php
    $algos = [
        ['label' => 'CBF (TF-IDF + Cosine)',      'm' => $metrikCBF],
        ['label' => 'CF (Item-Based + Jaccard)',   'm' => $metrikCF],
        ['label' => 'Hybrid (CF 60% + CBF 40%)',  'm' => $metrikHybrid],
    ];
    $bestF = max(array_column(array_column($algos, 'm'), 'f_measure'));
    foreach ($algos as $a):
        $m = $a['m'];
        $isBest = abs($m['f_measure'] - $bestF) < 0.0001;
    ?>
    <tr>
        <td><strong><?= $a['label'] ?></strong></td>
        <td><?= number_format($m['precision'] * 100, 2) ?>%</td>
        <td><?= number_format($m['recall']    * 100, 2) ?>%</td>
        <td class="<?= $isBest ? 'best' : '' ?>">
            <?= number_format($m['f_measure'] * 100, 2) ?><?= $isBest ? '% ★' : '%' ?>
        </td>
        <td><?= $m['tp'] ?></td>
        <td><?= $m['fp'] ?></td>
        <td><?= $m['fn'] ?></td>
        <td><?= $m['iterations'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$hybridBest = $metrikHybrid['f_measure'] >= $metrikCF['f_measure']
           && $metrikCF['f_measure']     >= $metrikCBF['f_measure'];
$statusColor = $hybridBest ? '#27ae60' : '#e74c3c';
?>
<div style="padding:14px 18px;background:white;border-radius:8px;border-left:4px solid <?= $statusColor ?>;font-size:13px;margin-bottom:24px;">
    <strong>Hipotesis Hybrid &gt; CF &gt; CBF:</strong>
    <span style="color:<?= $statusColor ?>;font-weight:700;">
        <?= $hybridBest ? '✓ TERBUKTI' : '✗ BELUM TERBUKTI' ?>
    </span><br>
    F1 → Hybrid: <strong><?= $metrikHybrid['f_measure'] ?></strong>
    &bull; CF: <strong><?= $metrikCF['f_measure'] ?></strong>
    &bull; CBF: <strong><?= $metrikCBF['f_measure'] ?></strong>
</div>

<h2>Detail Proses LOOCV (<?= count($detail) ?> iterasi)</h2>
<table>
    <thead>
        <tr>
            <th>User</th><th>Hidden</th><th>Train Set</th>
            <th>Rec CBF</th><th>Rec CF</th><th>Rec Hybrid</th>
            <th>Hit CBF</th><th>Hit CF</th><th>Hit HYB</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($detail as $d): ?>
    <tr>
        <td><?= $d['nama'] ?></td>
        <td>[<?= $d['heldOut'] ?>]</td>
        <td>[<?= implode(',', $d['trainSet']) ?>]</td>
        <td>[<?= implode(',', $d['recCBF']) ?>]</td>
        <td>[<?= implode(',', $d['recCF']) ?>]</td>
        <td>[<?= implode(',', $d['recHybrid']) ?>]</td>
        <td class="<?= $d['tpCBF'] ? 'hit' : 'miss' ?>"><?= $d['tpCBF'] ? '✓' : '✗' ?></td>
        <td class="<?= $d['tpCF']  ? 'hit' : 'miss' ?>"><?= $d['tpCF']  ? '✓' : '✗' ?></td>
        <td class="<?= $d['tpHyb'] ? 'hit' : 'miss' ?>"><?= $d['tpHyb'] ? '✓' : '✗' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
<?php else: // CLI output
$br  = str_repeat('=', 70) . "\n";
$sep = str_repeat('-', 70) . "\n";

echo $br;
echo "  EVALUASI SISTEM REKOMENDASI — STANDALONE (LOOCV)\n";
echo "  Data: " . count($books) . " buku, " . count($users) . " user | Top-N: " . TOP_N_EVAL . "\n";
echo $br . "\n";

echo "[ DETAIL LOOCV — " . count($detail) . " Iterasi ]\n";
echo $sep;
printf("%-7s %-6s %-10s %-12s %-12s %-12s %s\n", "User", "Hide", "Train", "Rec CBF", "Rec CF", "Rec Hybrid", "Hit?");
echo $sep;
foreach ($detail as $d) {
    printf("%-7s %-6s %-10s %-12s %-12s %-12s CBF:%s CF:%s HYB:%s\n",
        $d['nama'], "B{$d['heldOut']}",
        '['.implode(',', $d['trainSet']).']',
        '['.implode(',', $d['recCBF']).']',
        '['.implode(',', $d['recCF']).']',
        '['.implode(',', $d['recHybrid']).']',
        $d['tpCBF'] ? '✓' : '✗',
        $d['tpCF']  ? '✓' : '✗',
        $d['tpHyb'] ? '✓' : '✗'
    );
}
echo "\n";

echo str_repeat('═', 60) . "\n";
echo "  HASIL EVALUASI\n";
echo str_repeat('═', 60) . "\n";
printf("  %-25s │ %9s │ %8s │ %9s\n", "Algoritma", "Precision", "Recall", "F-Measure");
echo "  " . str_repeat('─', 60) . "\n";

foreach ([
    'CBF (TF-IDF + Cosine)'     => $metrikCBF,
    'CF (Item-Based + Jaccard)' => $metrikCF,
    'Hybrid (CF60% + CBF40%)'   => $metrikHybrid,
] as $nama => $m) {
    printf("  %-25s │ %9.4f │ %8.4f │ %9.4f\n", $nama, $m['precision'], $m['recall'], $m['f_measure']);
}
echo str_repeat('═', 60) . "\n\n";

$hybridBest = $metrikHybrid['f_measure'] >= $metrikCF['f_measure']
           && $metrikCF['f_measure']     >= $metrikCBF['f_measure'];
echo "Hipotesis Hybrid > CF > CBF : " . ($hybridBest ? "✓ TERBUKTI" : "✗ BELUM TERBUKTI") . "\n\n";
echo $br;
echo "  SELESAI\n";
echo $br;
endif;
