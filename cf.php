<?php
/**
 * ============================================================
 * SIMULASI COLLABORATIVE FILTERING (CF)
 * Algoritma: Item-Based CF + Jaccard Similarity
 * ============================================================
 * Data  : 10 buku, 4 user
 * Cara  : php cf.php  ATAU  buka di browser via XAMPP
 * ============================================================
 */

// ============================================================
// DATA: 10 Buku
// ============================================================
$books = [
    1  => ['title' => 'Pemrograman Python untuk Pemula'],
    2  => ['title' => 'Machine Learning dengan Python'],
    3  => ['title' => 'Deep Learning dan Neural Network'],
    4  => ['title' => 'Algoritma dan Struktur Data'],
    5  => ['title' => 'Basis Data MySQL untuk Web'],
    6  => ['title' => 'Jaringan Komputer dan Keamanan'],
    7  => ['title' => 'Pemrograman Web dengan PHP'],
    8  => ['title' => 'Data Science dan Analitik'],
    9  => ['title' => 'Sistem Operasi Linux'],
    10 => ['title' => 'Kecerdasan Buatan Teori dan Praktik'],
    11 => ['title' => 'Pemrograman Java untuk Pemula'],
    12 => ['title' => 'Keamanan Siber dan Kriptografi'],
    13 => ['title' => 'Cloud Computing dan Virtualisasi'],
    14 => ['title' => 'Internet of Things dengan Arduino'],
    15 => ['title' => 'Analisis dan Desain Sistem Informasi'],
    16 => ['title' => 'Pemrograman Mobile Android'],
    17 => ['title' => 'Statistika untuk Data Science'],
    18 => ['title' => 'Pengolahan Citra Digital'],
    19 => ['title' => 'Komputer Kuantum dan Algoritma'],
    20 => ['title' => 'Rekayasa Perangkat Lunak'],
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
// LANGKAH 1: Bangun User-Item Matrix
// matrix[user][buku] = 1 jika dipinjam, 0 jika tidak
// ============================================================
$matrix = [];
foreach ($userIds as $uid) {
    foreach ($bookIds as $bid) {
        $matrix[$uid][$bid] = in_array($bid, $users[$uid]['pinjam']) ? 1 : 0;
    }
}

// ============================================================
// LANGKAH 2: Hitung Jaccard Similarity antar buku
//
//                |Users(A) ∩ Users(B)|
// Jaccard(A,B) = ─────────────────────
//                |Users(A) ∪ Users(B)|
//
// Irisan   = user yang pinjam KEDUA buku
// Gabungan = user yang pinjam SALAH SATU atau keduanya
// ============================================================
function jaccard(array $matrix, int $bookA, int $bookB): float
{
    $irisan = $gabungan = 0;
    foreach ($matrix as $row) {
        $a = $row[$bookA] ?? 0;
        $b = $row[$bookB] ?? 0;
        if ($a && $b) $irisan++;
        if ($a || $b) $gabungan++;
    }
    return $gabungan > 0 ? round($irisan / $gabungan, 4) : 0.0;
}

$sim = [];
foreach ($bookIds as $a) {
    foreach ($bookIds as $b) {
        $sim[$a][$b] = ($a === $b) ? 1.0 : jaccard($matrix, $a, $b);
    }
}

// ============================================================
// LANGKAH 3: Prediksi skor CF untuk setiap user
// Untuk buku yang belum dipinjam:
//   skor = rata-rata Jaccard dengan semua buku yang sudah dipinjam
// ============================================================
$prediksi = [];
foreach ($userIds as $uid) {
    $sudahPinjam  = $users[$uid]['pinjam'];
    $belumPinjam  = array_diff($bookIds, $sudahPinjam);

    foreach ($belumPinjam as $kandidat) {
        $total = 0;
        foreach ($sudahPinjam as $bid) {
            $total += $sim[$kandidat][$bid];
        }
        $prediksi[$uid][$kandidat] = count($sudahPinjam) > 0
            ? round($total / count($sudahPinjam), 4)
            : 0;
    }
}

// ============================================================
// OUTPUT
// ============================================================
$br  = str_repeat('=', 60) . "\n";
$sep = str_repeat('-', 60) . "\n";

echo $br;
echo "  SIMULASI COLLABORATIVE FILTERING (CF)\n";
echo "  Item-Based CF + Jaccard Similarity\n";
echo $br . "\n";

// --- User-Item Matrix ---
echo "[ USER-ITEM MATRIX ]\n";
echo $sep;
printf("%12s", "");
foreach ($bookIds as $bid) printf("%5s", "B{$bid}");
echo "\n" . str_repeat('-', 12 + 5 * count($bookIds)) . "\n";
foreach ($userIds as $uid) {
    printf("%12s", $users[$uid]['name']);
    foreach ($bookIds as $bid) printf("%5s", $matrix[$uid][$bid]);
    echo "\n";
}
echo "Keterangan: 1=pernah dipinjam, 0=belum\n\n";

// --- Contoh perhitungan Jaccard detail ---
echo "[ CONTOH ] Perhitungan Jaccard Buku [1] vs Buku [2]\n";
echo $sep;
$usersA = $usersB = [];
$irisan = $gabungan = 0;
foreach ($userIds as $uid) {
    $a = $matrix[$uid][1]; $b = $matrix[$uid][2];
    if ($a) $usersA[] = $users[$uid]['name'];
    if ($b) $usersB[] = $users[$uid]['name'];
    if ($a && $b) $irisan++;
    if ($a || $b) $gabungan++;
}
echo "Users pinjam Buku[1]: [" . implode(', ', $usersA) . "]\n";
echo "Users pinjam Buku[2]: [" . implode(', ', $usersB) . "]\n";
echo "Irisan   (pinjam KEDUANYA) = {$irisan}\n";
echo "Gabungan (pinjam SALAH SATU) = {$gabungan}\n";
echo "Jaccard = {$irisan} / {$gabungan} = " . round($irisan/$gabungan, 4) . "\n\n";

// --- Matriks Jaccard ---
echo "[ MATRIKS ] Jaccard Similarity Item-Item (10x10)\n";
echo $sep;
printf("%5s", "");
foreach ($bookIds as $id) printf("%7s", "B{$id}");
echo "\n";
foreach ($bookIds as $a) {
    printf("%5s", "B{$a}");
    foreach ($bookIds as $b) printf("%7s", number_format($sim[$a][$b], 3));
    echo "\n";
}
echo "\n";

// --- Rekomendasi CF per user ---
echo "[ REKOMENDASI CF ] Per User\n";
echo $sep;
foreach ($userIds as $uid) {
    $user   = $users[$uid];
    $scores = $prediksi[$uid];
    arsort($scores);

    echo "User [{$uid}] {$user['name']} ({$user['jurusan']})\n";
    echo "  Sudah pinjam : " . implode(', ', array_map(
        fn($i) => "[{$i}] {$books[$i]['title']}",
        $user['pinjam']
    )) . "\n";

    echo "  Semua skor prediksi:\n";
    foreach ($scores as $cid => $skor) {
        printf("    [%2d] %-38s → %.4f\n", $cid, '"'.$books[$cid]['title'].'"', $skor);
    }

    echo "  TOP-3 Rekomendasi CF:\n";
    $rank = 1;
    foreach ($scores as $cid => $skor) {
        if ($skor <= 0) { echo "    → Tidak ada rekomendasi (data terlalu sparse)\n"; break; }
        if ($rank > 3) break;
        printf("    %d. [%2d] %-38s skor=%.4f\n", $rank, $cid, '"'.$books[$cid]['title'].'"', $skor);
        $rank++;
    }
    echo "\n";
}

echo $br;
echo "  SELESAI — CF Simulation\n";
echo $br;
