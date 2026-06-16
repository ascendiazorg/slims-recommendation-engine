<?php
/**
 * ============================================================
 * SIMULASI CONTENT-BASED FILTERING (CBF)
 * Algoritma: TF-IDF + Cosine Similarity
 * ============================================================
 * Data  : 10 buku, 4 user
 * Cara  : php cbf.php  ATAU  buka di browser via XAMPP
 * ============================================================
 */

// ============================================================
// DATA: 10 Buku
// ============================================================
$books = [
    1  => ['title' => 'Pemrograman Python untuk Pemula',      'subject' => 'python pemrograman komputer coding',                  'ddc' => '005.13'],
    2  => ['title' => 'Machine Learning dengan Python',       'subject' => 'machine learning python kecerdasan buatan',           'ddc' => '006.31'],
    3  => ['title' => 'Deep Learning dan Neural Network',     'subject' => 'deep learning neural network kecerdasan buatan',      'ddc' => '006.32'],
    4  => ['title' => 'Algoritma dan Struktur Data',          'subject' => 'algoritma struktur data pemrograman komputer',        'ddc' => '005.1' ],
    5  => ['title' => 'Basis Data MySQL untuk Web',           'subject' => 'database mysql basis data web server',                'ddc' => '005.74'],
    6  => ['title' => 'Jaringan Komputer dan Keamanan',       'subject' => 'jaringan komputer keamanan network',                  'ddc' => '004.6' ],
    7  => ['title' => 'Pemrograman Web dengan PHP',           'subject' => 'php web pemrograman coding server',                   'ddc' => '005.13'],
    8  => ['title' => 'Data Science dan Analitik',            'subject' => 'data science analitik statistik python',              'ddc' => '006.31'],
    9  => ['title' => 'Sistem Operasi Linux',                 'subject' => 'linux sistem operasi komputer server',                'ddc' => '005.43'],
    10 => ['title' => 'Kecerdasan Buatan Teori dan Praktik',  'subject' => 'kecerdasan buatan artificial intelligence machine',   'ddc' => '006.3' ],
    11 => ['title' => 'Pemrograman Java untuk Pemula',        'subject' => 'java pemrograman komputer objek oriented',            'ddc' => '005.13'],
    12 => ['title' => 'Keamanan Siber dan Kriptografi',       'subject' => 'keamanan siber kriptografi enkripsi network',         'ddc' => '005.8' ],
    13 => ['title' => 'Cloud Computing dan Virtualisasi',     'subject' => 'cloud computing virtualisasi server infrastruktur',   'ddc' => '004.67'],
    14 => ['title' => 'Internet of Things dengan Arduino',    'subject' => 'iot arduino sensor hardware embedded',                'ddc' => '004.16'],
    15 => ['title' => 'Analisis dan Desain Sistem Informasi', 'subject' => 'sistem informasi analisis desain bisnis proses',      'ddc' => '004.2' ],
    16 => ['title' => 'Pemrograman Mobile Android',           'subject' => 'android mobile pemrograman java aplikasi',            'ddc' => '005.13'],
    17 => ['title' => 'Statistika untuk Data Science',        'subject' => 'statistika matematika data analisis regresi',         'ddc' => '519.5' ],
    18 => ['title' => 'Pengolahan Citra Digital',             'subject' => 'citra digital pengolahan gambar computer vision',     'ddc' => '006.42'],
    19 => ['title' => 'Komputer Kuantum dan Algoritma',       'subject' => 'komputer kuantum algoritma komputasi fisika',         'ddc' => '006.3' ],
    20 => ['title' => 'Rekayasa Perangkat Lunak',             'subject' => 'rekayasa perangkat lunak software engineering agile', 'ddc' => '005.1' ],
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

// ============================================================
// LANGKAH 1: Tokenisasi metadata setiap buku
// → gabungkan title + subject → pecah jadi kata-kata
// ============================================================
function tokenize(string $text): array
{
    $text   = strtolower($text);
    $tokens = preg_split('/\s+/', trim($text));
    return array_values(array_filter($tokens, fn($t) => strlen($t) >= 3));
}

$tokenized = [];
foreach ($books as $id => $book) {
    $teks = $book['title'] . ' ' . $book['subject'];
    $tokenized[$id] = tokenize($teks);
}

// ============================================================
// LANGKAH 2: Hitung TF (Term Frequency)
// TF(kata, buku) = jumlah_muncul / total_kata_di_buku
// ============================================================
$tf = [];
foreach ($tokenized as $id => $tokens) {
    $total = count($tokens);
    $freq  = array_count_values($tokens);
    foreach ($freq as $kata => $jumlah) {
        $tf[$id][$kata] = $jumlah / $total;
    }
}

// ============================================================
// LANGKAH 3: Hitung IDF (Inverse Document Frequency)
// IDF(kata) = log(jumlah_buku / jumlah_buku_mengandung_kata) + 1
// ============================================================
$N       = count($books);
$allTerms = array_unique(array_merge(...array_values($tokenized)));
$idf     = [];

foreach ($allTerms as $kata) {
    $df = 0;
    foreach ($tokenized as $tokens) {
        if (in_array($kata, $tokens)) $df++;
    }
    $idf[$kata] = log($N / ($df + 1)) + 1;
}

// ============================================================
// LANGKAH 4: Hitung TF-IDF = TF × IDF
// ============================================================
$tfidf = [];
foreach ($tf as $id => $termTF) {
    foreach ($termTF as $kata => $tfScore) {
        $tfidf[$id][$kata] = $tfScore * ($idf[$kata] ?? 1);
    }
}

// ============================================================
// LANGKAH 5: Cosine Similarity antara 2 vektor TF-IDF
//
//               A · B
// cos(A,B) = ──────────
//            |A| × |B|
// ============================================================
function cosineSimilarity(array $vecA, array $vecB): float
{
    $dot = $normA = $normB = 0.0;
    foreach ($vecA as $kata => $w) {
        $dot   += $w * ($vecB[$kata] ?? 0);
        $normA += $w * $w;
    }
    foreach ($vecB as $w) {
        $normB += $w * $w;
    }
    $penyebut = sqrt($normA) * sqrt($normB);
    return $penyebut > 0 ? round($dot / $penyebut, 4) : 0.0;
}

// ============================================================
// LANGKAH 6: Bangun matriks similarity semua pasang buku
// ============================================================
$sim = [];
$ids = array_keys($books);
foreach ($ids as $a) {
    foreach ($ids as $b) {
        $sim[$a][$b] = ($a === $b) ? 1.0 : cosineSimilarity($tfidf[$a], $tfidf[$b]);
    }
}

// ============================================================
// OUTPUT
// ============================================================
$br  = str_repeat('=', 60) . "\n";
$sep = str_repeat('-', 60) . "\n";

echo $br;
echo "  SIMULASI CONTENT-BASED FILTERING (CBF)\n";
echo "  TF-IDF + Cosine Similarity\n";
echo $br . "\n";

// --- Tampilkan vektor TF-IDF buku 1 sebagai contoh ---
echo "[ CONTOH ] Vektor TF-IDF Buku [1] \"Pemrograman Python\"\n";
echo $sep;
arsort($tfidf[1]);
foreach (array_slice($tfidf[1], 0, 8, true) as $kata => $skor) {
    printf("  %-20s → %.4f\n", $kata, $skor);
}
echo "\n";

// --- Matriks Cosine Similarity ---
echo "[ MATRIKS ] Cosine Similarity antar Buku (10x10)\n";
echo $sep;
printf("%5s", "");
foreach ($ids as $id) printf("%7s", "B{$id}");
echo "\n";

foreach ($ids as $a) {
    printf("%5s", "B{$a}");
    foreach ($ids as $b) {
        printf("%7s", number_format($sim[$a][$b], 3));
    }
    echo "\n";
}
echo "\n";

// --- Top-3 rekomendasi CBF per buku ---
echo "[ REKOMENDASI CBF ] Top-3 per Buku\n";
echo $sep;
foreach ($ids as $target) {
    $scores = $sim[$target];
    arsort($scores);
    echo "Buku [{$target}] \"{$books[$target]['title']}\"\n";
    $rank = 1;
    foreach ($scores as $cid => $score) {
        if ($cid === $target) continue;
        if ($rank > 3) break;
        printf("  %d. [%2d] %-42s sim=%.4f\n", $rank, $cid, '"'.$books[$cid]['title'].'"', $score);
        $rank++;
    }
    echo "\n";
}

// --- Rekomendasi CBF per user ---
echo "[ REKOMENDASI CBF ] Per User\n";
echo $sep;
foreach ($users as $uid => $user) {
    $pinjam = $user['pinjam'];
    $last   = end($pinjam);   // berdasarkan buku terakhir dipinjam

    $scores = $sim[$last];
    arsort($scores);

    echo "User [{$uid}] {$user['name']} ({$user['jurusan']})\n";
    echo "  Riwayat pinjam : " . implode(', ', array_map(fn($i) => "[{$i}]", $pinjam)) . "\n";
    echo "  Berdasarkan buku terakhir: [{$last}] {$books[$last]['title']}\n";
    echo "  Rekomendasi:\n";
    $rank = 1;
    foreach ($scores as $cid => $score) {
        if (in_array($cid, $pinjam)) continue;
        if ($rank > 3) break;
        printf("    %d. [%2d] %-42s sim=%.4f\n", $rank, $cid, '"'.$books[$cid]['title'].'"', $score);
        $rank++;
    }
    echo "\n";
}

echo $br;
echo "  SELESAI — CBF Simulation\n";
echo $br;
