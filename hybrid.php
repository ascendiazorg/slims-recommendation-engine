<?php
/**
 * ============================================================
 * SIMULASI HYBRID RECOMMENDATION (CBF + CF)
 * Weighted Fusion: CF=60% + CBF=40%
 * ============================================================
 * Data  : 10 buku, 4 user
 * Cara  : php hybrid.php  ATAU  buka di browser via XAMPP
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

// ============================================================
// BOBOT HYBRID
// ============================================================
const BOBOT_CF  = 0.60;
const BOBOT_CBF = 0.40;

// ============================================================
// FUNGSI: Tokenisasi teks
// ============================================================
function tokenize(string $text): array
{
    $tokens = preg_split('/\s+/', strtolower(trim($text)));
    return array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
}

// ============================================================
// FUNGSI: Cosine Similarity (untuk CBF)
// ============================================================
function cosineSim(array $vecA, array $vecB): float
{
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $k => $w) { $dot += $w * ($vecB[$k] ?? 0); $normA += $w * $w; }
    foreach ($vecB as $w)       { $normB += $w * $w; }
    $d = sqrt($normA) * sqrt($normB);
    return $d > 0 ? round($dot / $d, 4) : 0.0;
}

// ============================================================
// FUNGSI: Jaccard Similarity (untuk CF)
// ============================================================
function jaccardSim(array $matrix, int $a, int $b): float
{
    $irisan = $gabungan = 0;
    foreach ($matrix as $row) {
        $va = $row[$a] ?? 0; $vb = $row[$b] ?? 0;
        if ($va && $vb) $irisan++;
        if ($va || $vb) $gabungan++;
    }
    return $gabungan > 0 ? round($irisan / $gabungan, 4) : 0.0;
}

// ============================================================
// FUNGSI: Normalisasi skor ke 0-1
// ============================================================
function normalize(array $scores): array
{
    $max = max($scores);
    return $max > 0 ? array_map(fn($s) => round($s / $max, 4), $scores) : $scores;
}

// ============================================================
// HITUNG CBF: TF-IDF + Cosine Similarity
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

$cbfSim = [];
foreach ($bookIds as $a) {
    foreach ($bookIds as $b) {
        $cbfSim[$a][$b] = ($a === $b) ? 1.0 : cosineSim($tfidf[$a], $tfidf[$b]);
    }
}

// ============================================================
// HITUNG CF: User-Item Matrix + Jaccard Similarity
// ============================================================
$matrix = [];
foreach ($userIds as $uid) {
    foreach ($bookIds as $bid) {
        $matrix[$uid][$bid] = in_array($bid, $users[$uid]['pinjam']) ? 1 : 0;
    }
}

$cfSim = [];
foreach ($bookIds as $a) {
    foreach ($bookIds as $b) {
        $cfSim[$a][$b] = ($a === $b) ? 1.0 : jaccardSim($matrix, $a, $b);
    }
}

// ============================================================
// OUTPUT
// ============================================================
$br  = str_repeat('=', 65) . "\n";
$sep = str_repeat('-', 65) . "\n";

echo $br;
echo "  SIMULASI HYBRID RECOMMENDATION (CBF + CF)\n";
echo "  Bobot: CF=" . (BOBOT_CF*100) . "% + CBF=" . (BOBOT_CBF*100) . "%\n";
echo $br . "\n";

// ============================================================
// Rekomendasi Hybrid per User
// ============================================================
foreach ($userIds as $uid) {
    $user    = $users[$uid];
    $pinjam  = $user['pinjam'];
    $kandidat = array_diff($bookIds, $pinjam);

    echo "╔" . str_repeat('═', 63) . "╗\n";
    printf("║  User [%d]: %-50s║\n", $uid, $user['name'] . ' (' . $user['jurusan'] . ')');
    echo "╠" . str_repeat('═', 63) . "╣\n";

    // --- Buku yang sudah dipinjam ---
    printf("║  Sudah pinjam: %-47s║\n", implode(', ', array_map(fn($i) => "[{$i}]", $pinjam)));
    echo "║" . str_repeat(' ', 63) . "║\n";

    // --- Hitung skor CBF untuk setiap kandidat ---
    $cbfScores = [];
    foreach ($kandidat as $cid) {
        $total = 0;
        foreach ($pinjam as $pid) $total += $cbfSim[$cid][$pid];
        $cbfScores[$cid] = count($pinjam) > 0 ? round($total / count($pinjam), 4) : 0;
    }

    // --- Hitung skor CF untuk setiap kandidat ---
    $cfScores = [];
    foreach ($kandidat as $cid) {
        $total = 0;
        foreach ($pinjam as $pid) $total += $cfSim[$cid][$pid];
        $cfScores[$cid] = count($pinjam) > 0 ? round($total / count($pinjam), 4) : 0;
    }

    // --- Normalisasi ---
    $cbfNorm = !empty($cbfScores) && max($cbfScores) > 0 ? normalize($cbfScores) : $cbfScores;
    $cfNorm  = !empty($cfScores)  && max($cfScores)  > 0 ? normalize($cfScores)  : $cfScores;

    // --- Weighted Fusion ---
    $hybridScores = [];
    foreach ($kandidat as $cid) {
        $hybridScores[$cid] = round(
            (BOBOT_CF * ($cfNorm[$cid] ?? 0)) + (BOBOT_CBF * ($cbfNorm[$cid] ?? 0)),
            4
        );
    }
    arsort($hybridScores);

    // --- Tabel Skor ---
    printf("║  %-4s %-28s %8s %8s %10s║\n", "ID", "Judul (singkat)", "CBF", "CF", "HYBRID");
    echo "║  " . str_repeat('-', 60) . " ║\n";

    foreach ($kandidat as $cid) {
        printf("║  [%2d] %-28s %8.4f %8.4f %10.4f║\n",
            $cid,
            substr($books[$cid]['title'], 0, 28),
            $cbfNorm[$cid] ?? 0,
            $cfNorm[$cid]  ?? 0,
            $hybridScores[$cid]
        );
    }

    // --- Top 3 Rekomendasi ---
    echo "║" . str_repeat(' ', 63) . "║\n";
    echo "║  ⭐ TOP-3 REKOMENDASI HYBRID:" . str_repeat(' ', 34) . "║\n";
    $rank = 1;
    foreach ($hybridScores as $cid => $skor) {
        if ($rank > 3) break;
        printf("║   %d. [%2d] %-44s %.4f║\n",
            $rank, $cid,
            substr('"'.$books[$cid]['title'].'"', 0, 44),
            $skor
        );
        $rank++;
    }

    echo "╚" . str_repeat('═', 63) . "╝\n\n";
}

// ============================================================
// Perbandingan CBF vs CF vs Hybrid untuk semua user
// ============================================================
echo "[ PERBANDINGAN ] CBF vs CF vs HYBRID\n";
echo $sep;
printf("%-8s %-6s %-30s %8s %8s %10s\n", "User", "BookID", "Judul (singkat)", "CBF", "CF", "HYBRID");
echo $sep;

foreach ($userIds as $uid) {
    $pinjam  = $users[$uid]['pinjam'];
    $kandidat = array_diff($bookIds, $pinjam);

    $cbfScores = $cfScores = [];
    foreach ($kandidat as $cid) {
        $tc = $tf = 0;
        foreach ($pinjam as $pid) { $tc += $cbfSim[$cid][$pid]; $tf += $cfSim[$cid][$pid]; }
        $cbfScores[$cid] = count($pinjam) > 0 ? $tc / count($pinjam) : 0;
        $cfScores[$cid]  = count($pinjam) > 0 ? $tf / count($pinjam) : 0;
    }
    $cbfNorm = max($cbfScores) > 0 ? normalize($cbfScores) : $cbfScores;
    $cfNorm  = max($cfScores)  > 0 ? normalize($cfScores)  : $cfScores;

    $hybridScores = [];
    foreach ($kandidat as $cid) {
        $hybridScores[$cid] = (BOBOT_CF * ($cfNorm[$cid] ?? 0)) + (BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
    }
    arsort($hybridScores);
    $top = array_keys(array_slice($hybridScores, 0, 3, true));

    foreach ($top as $i => $cid) {
        printf("%-8s %-6s %-30s %8.4f %8.4f %10.4f\n",
            $i === 0 ? $users[$uid]['name'] : '',
            "[{$cid}]",
            substr($books[$cid]['title'], 0, 30),
            $cbfNorm[$cid] ?? 0,
            $cfNorm[$cid]  ?? 0,
            $hybridScores[$cid]
        );
    }
    echo $sep;
}

echo $br;
echo "  SELESAI — Hybrid Simulation\n";
echo $br;
