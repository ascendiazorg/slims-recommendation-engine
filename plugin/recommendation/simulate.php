<?php
/**
 * Simulasi Standalone — Recommendation Engine
 * Hybrid CF (60%) + CBF (40%) dengan 20 buku, 6 user
 *
 * Cara run di VSCode terminal:
 *   php plugins/recommendation/simulate.php
 * atau dari folder plugin:
 *   php simulate.php
 *
 * Tidak butuh XAMPP, MySQL, atau koneksi apapun.
 */

declare(strict_types=1);

// =============================================================================
// DATA BUKU (20 buku)
// =============================================================================
$books = [
    1  => ['title' => 'Pemrograman PHP Dasar',              'subject' => 'pemrograman web php',              'ddc' => '005', 'author' => 'Budi Santoso'],
    2  => ['title' => 'Pemrograman PHP Lanjutan',           'subject' => 'pemrograman web php framework',    'ddc' => '005', 'author' => 'Budi Santoso'],
    3  => ['title' => 'Database MySQL untuk Pemula',        'subject' => 'database mysql query',             'ddc' => '005', 'author' => 'Andi Wijaya'],
    4  => ['title' => 'Sistem Basis Data',                  'subject' => 'database relasional sql normalisasi','ddc' => '005','author' => 'Andi Wijaya'],
    5  => ['title' => 'Algoritma dan Pemrograman',          'subject' => 'algoritma logika pemrograman',     'ddc' => '005', 'author' => 'Rinaldi Munir'],
    6  => ['title' => 'Struktur Data',                      'subject' => 'struktur data algoritma tree graph','ddc' => '005','author' => 'Rinaldi Munir'],
    7  => ['title' => 'Kecerdasan Buatan',                  'subject' => 'kecerdasan buatan machine learning','ddc' => '006','author' => 'Sri Kusumadewi'],
    8  => ['title' => 'Machine Learning dengan Python',     'subject' => 'machine learning python data science','ddc' => '006','author' => 'Agus Naba'],
    9  => ['title' => 'Data Mining Konsep dan Teknik',      'subject' => 'data mining analisis pola',        'ddc' => '006', 'author' => 'Agus Naba'],
    10 => ['title' => 'Jaringan Komputer',                  'subject' => 'jaringan komputer protokol tcp ip', 'ddc' => '004','author' => 'Forouzan'],
    11 => ['title' => 'Keamanan Jaringan',                  'subject' => 'keamanan jaringan enkripsi firewall','ddc' => '004','author' => 'Forouzan'],
    12 => ['title' => 'Sistem Operasi',                     'subject' => 'sistem operasi proses memori',     'ddc' => '005', 'author' => 'Silberschatz'],
    13 => ['title' => 'Rekayasa Perangkat Lunak',           'subject' => 'rekayasa perangkat lunak sdlc',    'ddc' => '005', 'author' => 'Pressman'],
    14 => ['title' => 'Interaksi Manusia Komputer',         'subject' => 'antarmuka desain ux ui manusia',   'ddc' => '004', 'author' => 'Shneiderman'],
    15 => ['title' => 'Statistika untuk Penelitian',        'subject' => 'statistika penelitian uji hipotesis','ddc' => '519','author' => 'Sugiyono'],
    16 => ['title' => 'Metodologi Penelitian',              'subject' => 'metodologi penelitian kuantitatif', 'ddc' => '001', 'author' => 'Sugiyono'],
    17 => ['title' => 'Manajemen Proyek IT',                'subject' => 'manajemen proyek it perencanaan',  'ddc' => '658', 'author' => 'Schwalbe'],
    18 => ['title' => 'Sistem Informasi Manajemen',         'subject' => 'sistem informasi manajemen bisnis', 'ddc' => '658','author' => 'O Brien'],
    19 => ['title' => 'E-Commerce Teori dan Implementasi',  'subject' => 'ecommerce bisnis digital transaksi','ddc' => '658','author' => 'Suyanto'],
    20 => ['title' => 'Cloud Computing',                    'subject' => 'cloud computing virtualisasi server','ddc' => '004','author' => 'Buyya'],
];

// =============================================================================
// DATA RIWAYAT PINJAM (6 user, setiap user pinjam 4-7 buku)
// =============================================================================
$history = [
    'U001' => [1, 2, 3, 5, 13],        // suka PHP + algoritma + RPL
    'U002' => [1, 2, 4, 3, 18],        // suka PHP + database + SIM
    'U003' => [5, 6, 7, 8, 9, 15],     // suka algoritma + AI + ML + statistik
    'U004' => [7, 8, 9, 15, 16],       // suka AI + ML + metodologi
    'U005' => [10, 11, 12, 20, 14],    // suka jaringan + keamanan + cloud + OS
    'U006' => [17, 18, 19, 13, 16],    // suka manajemen + e-commerce + metodologi
];

// =============================================================================
// FUNGSI HELPER
// =============================================================================
function printHeader(string $title): void {
    $line = str_repeat('=', 70);
    echo "\n{$line}\n  {$title}\n{$line}\n";
}

function printSubHeader(string $title): void {
    echo "\n--- {$title} ---\n";
}

function truncate(string $s, int $n = 40): string {
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 3) . '...' : $s;
}

// =============================================================================
// BAGIAN 1: COLLABORATIVE FILTERING (Item-Based Jaccard)
// =============================================================================
printHeader('COLLABORATIVE FILTERING — Item-Based Jaccard Similarity');

// Bangun inverted index: biblio_id → list of user yang pinjam
$borrowerIndex = [];
foreach ($history as $userId => $books_list) {
    foreach ($books_list as $bookId) {
        $borrowerIndex[$bookId][] = $userId;
    }
}

// Hitung Jaccard Similarity semua pasang buku
$cfSimilarity = [];
$bookIds = array_keys($borrowerIndex);
foreach ($bookIds as $idA) {
    foreach ($bookIds as $idB) {
        if ($idA >= $idB) continue;
        $irisan   = count(array_intersect($borrowerIndex[$idA], $borrowerIndex[$idB]));
        $gabungan = count(array_unique(array_merge($borrowerIndex[$idA], $borrowerIndex[$idB])));
        $sim = $gabungan > 0 ? round($irisan / $gabungan, 4) : 0.0;
        if ($sim > 0) {
            $cfSimilarity[$idA][$idB] = $sim;
            $cfSimilarity[$idB][$idA] = $sim;
        }
    }
}

printSubHeader('Top 10 Pasang Buku (Jaccard Similarity tertinggi)');
$cfPairs = [];
foreach ($cfSimilarity as $a => $targets) {
    foreach ($targets as $b => $sim) {
        if ($a < $b) $cfPairs[] = [$a, $b, $sim];
    }
}
usort($cfPairs, fn($x, $y) => $y[2] <=> $x[2]);

printf("%-40s  %-40s  %s\n", 'Buku A', 'Buku B', 'Jaccard');
echo str_repeat('-', 90) . "\n";
foreach (array_slice($cfPairs, 0, 10) as [$a, $b, $sim]) {
    printf("%-40s  %-40s  %.4f\n",
        truncate($books[$a]['title']),
        truncate($books[$b]['title']),
        $sim
    );
}

// =============================================================================
// BAGIAN 2: CONTENT-BASED FILTERING (TF-IDF + Cosine Similarity)
// =============================================================================
printHeader('CONTENT-BASED FILTERING — TF-IDF + Cosine Similarity');

// Tokenisasi teks buku
$tokenized = [];
foreach ($books as $id => $book) {
    $text = $book['title'] . ' ' . $book['subject'] . ' ' . $book['ddc'] . ' ' . $book['author'];
    $tokens = preg_split('/\s+/', strtolower(trim($text)));
    $tokenized[$id] = array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
}

// Hitung IDF
$N = count($books);
$allTerms = array_unique(array_merge(...array_values($tokenized)));
$idf = [];
foreach ($allTerms as $term) {
    $df = 0;
    foreach ($tokenized as $tokens) {
        if (in_array($term, $tokens)) $df++;
    }
    $idf[$term] = log($N / ($df + 1)) + 1;
}

// Bangun vektor TF-IDF
$tfidf = [];
foreach ($tokenized as $id => $tokens) {
    $total = count($tokens);
    if ($total === 0) { $tfidf[$id] = []; continue; }
    $freq = array_count_values($tokens);
    foreach ($freq as $term => $count) {
        $tfidf[$id][$term] = ($count / $total) * ($idf[$term] ?? 1);
    }
}

// Hitung Cosine Similarity
function cosineSim(array $vecA, array $vecB): float {
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $k => $w) {
        $dot   += $w * ($vecB[$k] ?? 0);
        $normA += $w * $w;
    }
    foreach ($vecB as $w) $normB += $w * $w;
    $d = sqrt($normA) * sqrt($normB);
    return $d > 0 ? round($dot / $d, 4) : 0.0;
}

$cbfSimilarity = [];
$allIds = array_keys($books);
foreach ($allIds as $idA) {
    foreach ($allIds as $idB) {
        if ($idA >= $idB) continue;
        $sim = cosineSim($tfidf[$idA] ?? [], $tfidf[$idB] ?? []);
        if ($sim > 0) {
            $cbfSimilarity[$idA][$idB] = $sim;
            $cbfSimilarity[$idB][$idA] = $sim;
        }
    }
}

printSubHeader('Top 10 Pasang Buku (Cosine Similarity tertinggi)');
$cbfPairs = [];
foreach ($cbfSimilarity as $a => $targets) {
    foreach ($targets as $b => $sim) {
        if ($a < $b) $cbfPairs[] = [$a, $b, $sim];
    }
}
usort($cbfPairs, fn($x, $y) => $y[2] <=> $x[2]);

printf("%-40s  %-40s  %s\n", 'Buku A', 'Buku B', 'Cosine');
echo str_repeat('-', 90) . "\n";
foreach (array_slice($cbfPairs, 0, 10) as [$a, $b, $sim]) {
    printf("%-40s  %-40s  %.4f\n",
        truncate($books[$a]['title']),
        truncate($books[$b]['title']),
        $sim
    );
}

// =============================================================================
// BAGIAN 3: HYBRID RECOMMENDER (CF 60% + CBF 40%)
// =============================================================================
printHeader('HYBRID RECOMMENDER — CF (60%) + CBF (40%)');

const BOBOT_CF  = 0.60;
const BOBOT_CBF = 0.40;

function getScoresCF(array $borrowed, array $cfSim, array $exclude = []): array {
    $scores = [];
    $allExclude = array_merge($borrowed, $exclude);
    foreach ($borrowed as $ownedId) {
        foreach ($cfSim[$ownedId] ?? [] as $cand => $sim) {
            if (in_array($cand, $allExclude)) continue;
            $scores[$cand] = ($scores[$cand] ?? 0) + $sim;
        }
    }
    return $scores;
}

function getScoresCBF(array $borrowed, array $cbfSim, array $exclude = []): array {
    $scores = [];
    $allExclude = array_merge($borrowed, $exclude);
    foreach ($borrowed as $ownedId) {
        foreach ($cbfSim[$ownedId] ?? [] as $cand => $sim) {
            if (in_array($cand, $allExclude)) continue;
            $scores[$cand] = ($scores[$cand] ?? 0) + $sim;
        }
    }
    return $scores;
}

function normalize(array $scores): array {
    $max = !empty($scores) ? max($scores) : 0;
    return $max > 0 ? array_map(fn($s) => round($s / $max, 4), $scores) : $scores;
}

function hybridScores(array $cfRaw, array $cbfRaw): array {
    $cfNorm  = normalize($cfRaw);
    $cbfNorm = normalize($cbfRaw);
    $candidates = array_unique(array_merge(array_keys($cfNorm), array_keys($cbfNorm)));
    $hybrid = [];
    foreach ($candidates as $cid) {
        $hybrid[$cid] = round(
            (BOBOT_CF  * ($cfNorm[$cid]  ?? 0)) +
            (BOBOT_CBF * ($cbfNorm[$cid] ?? 0)),
            4
        );
    }
    arsort($hybrid);
    return $hybrid;
}

foreach ($history as $userId => $borrowed) {
    echo "\nUser {$userId} (pinjam: " . implode(', ', $borrowed) . ")\n";

    $cfRaw  = getScoresCF($borrowed, $cfSimilarity);
    $cbfRaw = getScoresCBF($borrowed, $cbfSimilarity);
    $hybrid = hybridScores($cfRaw, $cbfRaw);
    $top5   = array_slice($hybrid, 0, 5, true);

    printf("  %-3s  %-40s  %s\n", 'No', 'Judul Buku', 'Skor');
    echo '  ' . str_repeat('-', 55) . "\n";
    $rank = 1;
    foreach ($top5 as $bookId => $score) {
        printf("  %-3d  %-40s  %.4f\n", $rank++, truncate($books[$bookId]['title']), $score);
    }
}

// =============================================================================
// BAGIAN 4: EVALUASI — Leave-One-Out Cross Validation (Top-5)
// =============================================================================
printHeader('EVALUASI — Leave-One-Out Cross Validation (Top-5)');

$TOP_N = 5;
$totalTests = 0;
$hits = ['cf' => 0, 'cbf' => 0, 'hybrid' => 0];
$detail = [];

foreach ($history as $userId => $borrowed) {
    if (count($borrowed) < 2) continue;

    // Sembunyikan buku TERAKHIR di list sebagai buku uji
    $testBook  = end($borrowed);
    $remaining = array_slice($borrowed, 0, -1);

    // CF saja
    $cfRaw = getScoresCF($remaining, $cfSimilarity);
    arsort($cfRaw);
    $cfTop = array_slice($cfRaw, 0, $TOP_N, true);

    // CBF saja
    $cbfRaw = getScoresCBF($remaining, $cbfSimilarity);
    arsort($cbfRaw);
    $cbfTop = array_slice($cbfRaw, 0, $TOP_N, true);

    // Hybrid
    $hybridTop = array_slice(hybridScores($cfRaw, $cbfRaw), 0, $TOP_N, true);

    $hitCf     = array_key_exists($testBook, $cfTop);
    $hitCbf    = array_key_exists($testBook, $cbfTop);
    $hitHybrid = array_key_exists($testBook, $hybridTop);

    if ($hitCf)     $hits['cf']++;
    if ($hitCbf)    $hits['cbf']++;
    if ($hitHybrid) $hits['hybrid']++;

    $detail[] = compact('userId', 'testBook', 'hitCf', 'hitCbf', 'hitHybrid');
    $totalTests++;
}

// Detail per user
printSubHeader('Detail Hasil per User');
printf("%-6s  %-40s  %-8s  %-8s  %-8s\n", 'User', 'Buku Tersembunyi', 'CF', 'CBF', 'Hybrid');
echo str_repeat('-', 75) . "\n";
foreach ($detail as $d) {
    printf("%-6s  %-40s  %-8s  %-8s  %-8s\n",
        $d['userId'],
        truncate($books[$d['testBook']]['title']),
        $d['hitCf']     ? 'HIT' : 'miss',
        $d['hitCbf']    ? 'HIT' : 'miss',
        $d['hitHybrid'] ? 'HIT' : 'miss'
    );
}

// Hitung metrik
function calcMetrics(int $hits, int $total, int $topN): array {
    if ($total === 0) return ['p' => 0, 'r' => 0, 'f' => 0];
    $p = $hits / ($total * $topN);
    $r = $hits / $total;
    $f = ($p + $r) > 0 ? 2 * $p * $r / ($p + $r) : 0;
    return [
        'p' => round($p * 100, 2),
        'r' => round($r * 100, 2),
        'f' => round($f * 100, 2),
    ];
}

$mCF     = calcMetrics($hits['cf'],     $totalTests, $TOP_N);
$mCBF    = calcMetrics($hits['cbf'],    $totalTests, $TOP_N);
$mHybrid = calcMetrics($hits['hybrid'], $totalTests, $TOP_N);

printSubHeader("Tabel Perbandingan Algoritma (Total uji: {$totalTests} user, Top-{$TOP_N})");
printf("%-35s  %6s  %5s  %11s  %9s\n", 'Algoritma', 'Hit', 'Precision', 'Recall', 'F-Measure');
echo str_repeat('-', 75) . "\n";
printf("%-35s  %6s  %9s  %9s  %9s\n",
    'CF (Item-Based Jaccard)',
    "{$hits['cf']}/{$totalTests}",
    "{$mCF['p']}%", "{$mCF['r']}%", "{$mCF['f']}%"
);
printf("%-35s  %6s  %9s  %9s  %9s\n",
    'CBF (TF-IDF Cosine Similarity)',
    "{$hits['cbf']}/{$totalTests}",
    "{$mCBF['p']}%", "{$mCBF['r']}%", "{$mCBF['f']}%"
);
printf("%-35s  %6s  %9s  %9s  %9s\n",
    'Hybrid (CF 60% + CBF 40%)',
    "{$hits['hybrid']}/{$totalTests}",
    "{$mHybrid['p']}%", "{$mHybrid['r']}%", "{$mHybrid['f']}%"
);
echo str_repeat('=', 75) . "\n";

echo "\nKeterangan:\n";
echo "  Precision  = Hit / (Total Uji x Top-N)\n";
echo "  Recall     = Hit / Total Uji\n";
echo "  F-Measure  = 2 x Precision x Recall / (Precision + Recall)\n";
echo "  Metode     = Leave-One-Out Cross Validation\n\n";
