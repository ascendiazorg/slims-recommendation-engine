<?php
/**
 * ============================================================
 * EVALUASI SISTEM REKOMENDASI — LOOCV
 * Leave-One-Out Cross Validation
 * Metrik: Precision, Recall, F-Measure
 * ============================================================
 * Membandingkan 3 algoritma:
 *   - CBF  : Content-Based Filtering (TF-IDF + Cosine Similarity)
 *   - CF   : Collaborative Filtering (Item-Based + Jaccard)
 *   - HYBRID: Weighted Fusion CF=60% + CBF=40%
 * ============================================================
 * Cara jalankan:
 *   Browser : http://localhost/simulasi/evaluate.php
 *   Terminal: php evaluate.php
 * ============================================================
 */

// ============================================================
// DATA: 10 Buku
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
// DATA: 4 User + Riwayat Pinjaman
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

// Bobot Hybrid
const BOBOT_CF  = 0.60;
const BOBOT_CBF = 0.40;
const TOP_N     = 3;     // jumlah rekomendasi yang dievaluasi

// ============================================================
// FUNGSI UTILITAS
// ============================================================

function tokenize(string $text): array
{
    $tokens = preg_split('/\s+/', strtolower(trim($text)));
    return array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
}

function cosineSimilarity(array $vecA, array $vecB): float
{
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $k => $w) {
        $dot   += $w * ($vecB[$k] ?? 0);
        $normA += $w * $w;
    }
    foreach ($vecB as $w) { $normB += $w * $w; }
    $d = sqrt($normA) * sqrt($normB);
    return $d > 0 ? round($dot / $d, 6) : 0.0;
}

function jaccardSimilarity(array $matrixRow, array $allMatrix, int $bookA, int $bookB): float
{
    $irisan = $gabungan = 0;
    foreach ($allMatrix as $row) {
        $a = $row[$bookA] ?? 0;
        $b = $row[$bookB] ?? 0;
        if ($a && $b) $irisan++;
        if ($a || $b) $gabungan++;
    }
    return $gabungan > 0 ? round($irisan / $gabungan, 6) : 0.0;
}

function normalizeScores(array $scores): array
{
    if (empty($scores)) return $scores;
    $max = max($scores);
    return $max > 0 ? array_map(fn($s) => round($s / $max, 6), $scores) : $scores;
}

// ============================================================
// BANGUN TF-IDF GLOBAL (tidak berubah antar iterasi LOOCV)
// CBF hanya bergantung pada konten buku, bukan riwayat pinjam
// ============================================================
$tokenized = [];
foreach ($books as $id => $book) {
    $tokenized[$id] = tokenize($book['title'] . ' ' . $book['subject']);
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

// Matriks CBF (pre-computed, static)
$cbfMatrix = [];
foreach ($bookIds as $a) {
    foreach ($bookIds as $b) {
        $cbfMatrix[$a][$b] = ($a === $b) ? 1.0 : cosineSimilarity($tfidf[$a], $tfidf[$b]);
    }
}

// ============================================================
// FUNGSI: Dapatkan rekomendasi CBF
// trainSet = buku yang sudah dipinjam user (setelah LOOCV)
// kandidat = semua buku minus trainSet
// ============================================================
function getCBFRecs(array $trainSet, array $bookIds, array $cbfMatrix, int $topN): array
{
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
// FUNGSI: Dapatkan rekomendasi CF
// trainSet = riwayat pinjam user saat ini (sudah dikurangi held-out)
// Semua user lain tetap menggunakan riwayat asli
// ============================================================
function getCFRecs(int $uid, array $trainSet, array $users, array $bookIds, int $topN): array
{
    // Bangun matrix: user lain pakai data asli, user ini pakai trainSet
    $matrix = [];
    foreach ($users as $u => $data) {
        $pinjamU = ($u === $uid) ? $trainSet : $data['pinjam'];
        foreach ($bookIds as $bid) {
            $matrix[$u][$bid] = in_array($bid, $pinjamU) ? 1 : 0;
        }
    }

    // Hitung Jaccard antar buku
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

    // Skor kandidat = rata-rata Jaccard dengan trainSet
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
// FUNGSI: Dapatkan rekomendasi Hybrid (CBF 40% + CF 60%)
// ============================================================
function getHybridRecs(int $uid, array $trainSet, array $users, array $bookIds, array $cbfMatrix, int $topN): array
{
    // Bangun matrix CF
    $matrix = [];
    foreach ($users as $u => $data) {
        $pinjamU = ($u === $uid) ? $trainSet : $data['pinjam'];
        foreach ($bookIds as $bid) {
            $matrix[$u][$bid] = in_array($bid, $pinjamU) ? 1 : 0;
        }
    }

    // Jaccard similarity
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

    $kandidat  = array_diff($bookIds, $trainSet);
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

    $cbfNorm = normalizeScores($cbfScores);
    $cfNorm  = normalizeScores($cfScores);

    $hybridScores = [];
    foreach ($kandidat as $cid) {
        $hybridScores[$cid] = (BOBOT_CF * ($cfNorm[$cid] ?? 0)) + (BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
    }
    arsort($hybridScores);
    return array_keys(array_slice($hybridScores, 0, $topN, true));
}

// ============================================================
// FUNGSI: Hitung metrik dari hasil LOOCV
// ============================================================
function computeMetrics(array $results, int $topN): array
{
    $totalTP = $totalFP = $totalFN = 0;
    foreach ($results as $r) {
        $totalTP += $r['tp'];
        $totalFP += $r['fp'];
        $totalFN += $r['fn'];
    }
    $precision = ($totalTP + $totalFP) > 0 ? $totalTP / ($totalTP + $totalFP) : 0;
    $recall    = ($totalTP + $totalFN) > 0 ? $totalTP / ($totalTP + $totalFN) : 0;
    $fMeasure  = ($precision + $recall) > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0;
    return [
        'precision' => round($precision, 4),
        'recall'    => round($recall, 4),
        'f_measure' => round($fMeasure, 4),
        'tp'        => $totalTP,
        'fp'        => $totalFP,
        'fn'        => $totalFN,
        'iterations'=> count($results),
    ];
}

// ============================================================
// LOOCV — Jalankan untuk setiap algoritma
// ============================================================
$resultsCBF    = [];
$resultsCF     = [];
$resultsHybrid = [];
$detail        = [];  // detail per iterasi untuk ditampilkan

foreach ($users as $uid => $user) {
    $allPinjam = $user['pinjam'];
    if (count($allPinjam) < 2) continue;  // butuh minimal 2 buku

    foreach ($allPinjam as $heldOut) {
        $trainSet = array_values(array_diff($allPinjam, [$heldOut]));

        // Rekomendasi dari 3 algoritma
        $recCBF    = getCBFRecs($trainSet, $bookIds, $cbfMatrix, TOP_N);
        $recCF     = getCFRecs($uid, $trainSet, $users, $bookIds, TOP_N);
        $recHybrid = getHybridRecs($uid, $trainSet, $users, $bookIds, $cbfMatrix, TOP_N);

        // Hitung TP, FP, FN untuk setiap algoritma
        // TP = 1 jika held-out ada di rekomendasi
        // FP = jumlah rekomendasi - TP  (rekomendasi lain yang bukan held-out)
        // FN = 1 - TP  (held-out yang tidak ketemu)
        $tpCBF = in_array($heldOut, $recCBF)    ? 1 : 0;
        $tpCF  = in_array($heldOut, $recCF)     ? 1 : 0;
        $tpHyb = in_array($heldOut, $recHybrid) ? 1 : 0;

        $resultsCBF[]    = ['uid' => $uid, 'held' => $heldOut, 'tp' => $tpCBF, 'fp' => TOP_N - $tpCBF, 'fn' => 1 - $tpCBF];
        $resultsCF[]     = ['uid' => $uid, 'held' => $heldOut, 'tp' => $tpCF,  'fp' => TOP_N - $tpCF,  'fn' => 1 - $tpCF];
        $resultsHybrid[] = ['uid' => $uid, 'held' => $heldOut, 'tp' => $tpHyb, 'fp' => TOP_N - $tpHyb, 'fn' => 1 - $tpHyb];

        $detail[] = [
            'uid'       => $uid,
            'nama'      => $user['name'],
            'heldOut'   => $heldOut,
            'trainSet'  => $trainSet,
            'recCBF'    => $recCBF,
            'recCF'     => $recCF,
            'recHybrid' => $recHybrid,
            'tpCBF'     => $tpCBF,
            'tpCF'      => $tpCF,
            'tpHyb'     => $tpHyb,
        ];
    }
}

// Hitung metrik final
$metrikCBF    = computeMetrics($resultsCBF,    TOP_N);
$metrikCF     = computeMetrics($resultsCF,     TOP_N);
$metrikHybrid = computeMetrics($resultsHybrid, TOP_N);

// ============================================================
// OUTPUT
// ============================================================
$br  = str_repeat('=', 70) . "\n";
$sep = str_repeat('-', 70) . "\n";

echo $br;
echo "  EVALUASI SISTEM REKOMENDASI — LOOCV (Leave-One-Out Cross Validation)\n";
echo "  Metrik: Precision | Recall | F-Measure\n";
echo "  Top-N  : " . TOP_N . " rekomendasi per iterasi\n";
echo $br . "\n";

// ============================================================
// Bagian 1: Penjelasan LOOCV
// ============================================================
echo "[ METODE EVALUASI: LOOCV ]\n";
echo $sep;
echo "Cara kerja:\n";
echo "  1. Untuk setiap user, ambil semua riwayat pinjaman\n";
echo "  2. Sembunyikan 1 buku (held-out) → ini adalah ground truth\n";
echo "  3. Jalankan rekomendasi dengan sisa buku (training set)\n";
echo "  4. Cek apakah buku yang disembunyikan muncul di Top-" . TOP_N . " rekomendasi\n";
echo "  5. Ulangi untuk setiap buku dalam riwayat\n";
echo "  6. Hitung rata-rata Precision, Recall, F-Measure\n\n";
echo "Formula:\n";
echo "  Precision  = TP / (TP + FP)   → seberapa tepat rekomendasi\n";
echo "  Recall     = TP / (TP + FN)   → seberapa lengkap rekomendasi\n";
echo "  F-Measure  = 2 × P × R / (P+R) → keseimbangan P dan R\n\n";
echo "  TP (True Positive)  = buku held-out MUNCUL di top-" . TOP_N . " rekomendasi\n";
echo "  FP (False Positive) = rekomendasi yang bukan held-out = " . TOP_N . " - TP\n";
echo "  FN (False Negative) = held-out TIDAK MUNCUL = 1 - TP\n\n";

// ============================================================
// Bagian 2: Detail tiap iterasi LOOCV
// ============================================================
echo "[ DETAIL PROSES LOOCV — " . count($detail) . " Iterasi ]\n";
echo $sep;
printf("%-5s %-6s %-8s %-14s %-14s %-14s %-14s\n",
    "User", "Hide", "Train", "Rec CBF", "Rec CF", "Rec Hybrid", "Hit?");
echo $sep;

$prevUid = null;
foreach ($detail as $d) {
    if ($prevUid !== null && $prevUid !== $d['uid']) {
        echo str_repeat('·', 70) . "\n";
    }
    $recCBFStr    = '[' . implode(',', $d['recCBF'])    . ']';
    $recCFStr     = '[' . implode(',', $d['recCF'])     . ']';
    $recHybridStr = '[' . implode(',', $d['recHybrid']) . ']';
    $trainStr     = '[' . implode(',', $d['trainSet'])  . ']';
    $hitStr = sprintf("CBF:%s CF:%s HYB:%s",
        $d['tpCBF'] ? '✓' : '✗',
        $d['tpCF']  ? '✓' : '✗',
        $d['tpHyb'] ? '✓' : '✗'
    );
    printf("%-5s %-6s %-8s %-14s %-14s %-14s %-14s\n",
        $d['nama'], "B{$d['heldOut']}", $trainStr,
        $recCBFStr, $recCFStr, $recHybridStr, $hitStr
    );
    $prevUid = $d['uid'];
}
echo "\n";

// ============================================================
// Bagian 3: Rekap TP per user
// ============================================================
echo "[ REKAP TP PER USER ]\n";
echo $sep;
printf("%-8s  %-10s  %6s  %6s  %6s\n", "User", "Iterasi", "CBF", "CF", "Hybrid");
echo $sep;

$tpPerUser = [];
foreach ($detail as $d) {
    $uid = $d['uid'];
    $tpPerUser[$uid]['nama']    = $d['nama'];
    $tpPerUser[$uid]['iter']    = ($tpPerUser[$uid]['iter']    ?? 0) + 1;
    $tpPerUser[$uid]['tpCBF']   = ($tpPerUser[$uid]['tpCBF']   ?? 0) + $d['tpCBF'];
    $tpPerUser[$uid]['tpCF']    = ($tpPerUser[$uid]['tpCF']    ?? 0) + $d['tpCF'];
    $tpPerUser[$uid]['tpHybrid']= ($tpPerUser[$uid]['tpHybrid']?? 0) + $d['tpHyb'];
}
foreach ($tpPerUser as $uid => $r) {
    printf("%-8s  %-10s  %6s  %6s  %6s\n",
        $r['nama'],
        $r['iter'] . ' iterasi',
        $r['tpCBF']    . '/' . $r['iter'] . ' hit',
        $r['tpCF']     . '/' . $r['iter'] . ' hit',
        $r['tpHybrid'] . '/' . $r['iter'] . ' hit'
    );
}
echo "\n";

// ============================================================
// Bagian 4: Perhitungan Metrik
// ============================================================
echo "[ PERHITUNGAN METRIK EVALUASI ]\n";
echo $sep;

$algos = [
    'CBF'    => $metrikCBF,
    'CF'     => $metrikCF,
    'HYBRID' => $metrikHybrid,
];

foreach ($algos as $nama => $m) {
    echo "Algoritma: {$nama}\n";
    echo "  Total iterasi : {$m['iterations']}\n";
    echo "  Total TP      : {$m['tp']}  (held-out ditemukan di Top-" . TOP_N . ")\n";
    echo "  Total FP      : {$m['fp']}  (rekomendasi bukan held-out)\n";
    echo "  Total FN      : {$m['fn']}  (held-out tidak ditemukan)\n";
    echo "  Precision     = {$m['tp']} / ({$m['tp']} + {$m['fp']}) = {$m['precision']}\n";
    echo "  Recall        = {$m['tp']} / ({$m['tp']} + {$m['fn']}) = {$m['recall']}\n";
    echo "  F-Measure     = 2 × {$m['precision']} × {$m['recall']} / ({$m['precision']} + {$m['recall']}) = {$m['f_measure']}\n";
    echo "\n";
}

// ============================================================
// Bagian 5: Tabel Perbandingan Final
// ============================================================
echo str_repeat('═', 60) . "\n";
echo "  HASIL EVALUASI SISTEM REKOMENDASI\n";
echo str_repeat('═', 60) . "\n";
printf("  %-12s │ %9s │ %8s │ %9s\n", "Algoritma", "Precision", "Recall", "F-Measure");
echo "  " . str_repeat('─', 12) . "─┼─" . str_repeat('─', 9) . "─┼─" . str_repeat('─', 8) . "─┼─" . str_repeat('─', 9) . "\n";

foreach ($algos as $nama => $m) {
    $star  = ($nama === 'HYBRID') ? ' ★' : '';
    $label = $nama . $star;
    printf("  %-12s │ %9.4f │ %8.4f │ %9.4f\n",
        $label, $m['precision'], $m['recall'], $m['f_measure']);
}
echo str_repeat('═', 60) . "\n\n";

// ============================================================
// Bagian 6: Target Tesis
// ============================================================
echo "[ TARGET TESIS vs HASIL ]\n";
echo $sep;

$targets = [
    'Precision'  => ['target' => 0.70, 'cbf' => $metrikCBF['precision'],  'cf' => $metrikCF['precision'],  'hyb' => $metrikHybrid['precision']],
    'Recall'     => ['target' => 0.60, 'cbf' => $metrikCBF['recall'],     'cf' => $metrikCF['recall'],     'hyb' => $metrikHybrid['recall']],
    'F-Measure'  => ['target' => 0.65, 'cbf' => $metrikCBF['f_measure'],  'cf' => $metrikCF['f_measure'],  'hyb' => $metrikHybrid['f_measure']],
];

printf("%-12s %8s │ %8s │ %8s │ %8s │ %6s\n", "Metrik", "Target", "CBF", "CF", "HYBRID", "Status");
echo $sep;

foreach ($targets as $nama => $t) {
    $status = $t['hyb'] >= $t['target'] ? '✓ PASS' : '✗ FAIL';
    $trend  = ($t['hyb'] >= $t['cf'] && $t['cf'] >= $t['cbf']) ? 'Hybrid>CF>CBF ✓' : 'urutan tidak ideal';
    printf("%-12s %8.4f │ %8.4f │ %8.4f │ %8.4f │ %s\n",
        $nama, $t['target'], $t['cbf'], $t['cf'], $t['hyb'], $status);
}
echo "\n";

// Kesimpulan urutan
$hybridBest = ($metrikHybrid['f_measure'] >= $metrikCF['f_measure'])
           && ($metrikCF['f_measure']     >= $metrikCBF['f_measure']);

echo "Urutan F-Measure: ";
echo "Hybrid ({$metrikHybrid['f_measure']}) vs CF ({$metrikCF['f_measure']}) vs CBF ({$metrikCBF['f_measure']})\n";
echo "Hipotesis Hybrid > CF > CBF : " . ($hybridBest ? "✓ TERBUKTI" : "✗ BELUM TERBUKTI") . "\n\n";

echo $br;
echo "  SELESAI — Evaluasi LOOCV\n";
echo $br;
