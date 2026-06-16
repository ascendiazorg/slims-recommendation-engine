<?php
/**
 * SIMULASI COLLABORATIVE FILTERING (CF)
 * =======================================
 * Algoritma: Item-Based CF + Jaccard Similarity
 * Data    : 10 buku, 4 user (dari data.php)
 *
 * Alur:
 *  1. Bangun user-item matrix dari riwayat pinjaman
 *  2. Hitung Jaccard Similarity antar buku
 *  3. Prediksi skor rekomendasi per user
 *  4. Tampilkan top-N rekomendasi
 *
 * Jalankan: php cf_simulation.php
 */

require_once 'data.php';

echo "========================================================\n";
echo "  SIMULASI COLLABORATIVE FILTERING (CF)\n";
echo "  Algoritma: Item-Based CF + Jaccard Similarity\n";
echo "========================================================\n\n";

// ============================================================
// LANGKAH 1: Bangun User-Item Matrix
// Baris = User, Kolom = Buku
// Nilai = 1 jika pinjam, 0 jika tidak
// ============================================================
echo "--- LANGKAH 1: User-Item Matrix ---\n\n";

$bookIds = array_keys($books);
$userIds = array_keys($users);

// Bangun matrix
$matrix = [];
foreach ($userIds as $uid) {
    foreach ($bookIds as $bid) {
        $matrix[$uid][$bid] = in_array($bid, $loanHistory[$uid] ?? []) ? 1 : 0;
    }
}

// Tampilkan matrix
echo str_pad("User\Buku", 12);
foreach ($bookIds as $bid) {
    echo str_pad("B{$bid}", 5);
}
echo "\n";
echo str_repeat("-", 12 + 5 * count($bookIds)) . "\n";

foreach ($userIds as $uid) {
    echo str_pad("{$users[$uid]['name']}", 12);
    foreach ($bookIds as $bid) {
        echo str_pad($matrix[$uid][$bid], 5);
    }
    echo "\n";
}

echo "\nKeterangan: 1 = pernah dipinjam, 0 = belum pernah\n";

// ============================================================
// LANGKAH 2: Hitung Jaccard Similarity antar buku
//
//                  |Users(A) ∩ Users(B)|
// Jaccard(A,B) = ────────────────────────
//                  |Users(A) ∪ Users(B)|
//
// Users(A) = set user yang meminjam buku A
// ∩ = irisan (yang pinjam keduanya)
// ∪ = gabungan (yang pinjam salah satu atau keduanya)
// ============================================================
echo "\n--- LANGKAH 2: Jaccard Similarity antar Buku ---\n\n";

function jaccardSimilarity(array $matrix, int $bookA, int $bookB): float
{
    $intersection = 0;
    $union        = 0;

    foreach ($matrix as $uid => $row) {
        $hasA = $row[$bookA] ?? 0;
        $hasB = $row[$bookB] ?? 0;

        if ($hasA && $hasB) $intersection++;
        if ($hasA || $hasB) $union++;
    }

    return $union > 0 ? round($intersection / $union, 4) : 0.0;
}

// Tampilkan detail perhitungan untuk 2 pasang buku sebagai contoh
echo "CONTOH PERHITUNGAN DETAIL:\n";
$contohPasang = [[1, 2], [1, 4]];

foreach ($contohPasang as [$bA, $bB]) {
    $intersection = 0;
    $union        = 0;
    $usersA = [];
    $usersB = [];

    foreach ($matrix as $uid => $row) {
        if ($row[$bA]) $usersA[] = $users[$uid]['name'];
        if ($row[$bB]) $usersB[] = $users[$uid]['name'];
        if ($row[$bA] && $row[$bB]) $intersection++;
        if ($row[$bA] || $row[$bB]) $union++;
    }

    $sim = $union > 0 ? round($intersection / $union, 4) : 0;

    echo "\nBuku [{$bA}] \"{$books[$bA]['title']}\"\n";
    echo "Buku [{$bB}] \"{$books[$bB]['title']}\"\n";
    echo "  Users pinjam Buku {$bA}: [" . implode(', ', $usersA) . "]\n";
    echo "  Users pinjam Buku {$bB}: [" . implode(', ', $usersB) . "]\n";
    echo "  |Irisan|  = {$intersection}  (pinjam KEDUANYA)\n";
    echo "  |Gabungan| = {$union}  (pinjam SALAH SATU atau KEDUANYA)\n";
    echo "  Jaccard   = {$intersection} / {$union} = {$sim}\n";
}

// Hitung semua pasang buku
$itemSimilarity = [];
foreach ($bookIds as $i => $idA) {
    foreach ($bookIds as $j => $idB) {
        if ($idA === $idB) {
            $itemSimilarity[$idA][$idB] = 1.0;
            continue;
        }
        $itemSimilarity[$idA][$idB] = jaccardSimilarity($matrix, $idA, $idB);
    }
}

// Tampilkan matriks Jaccard
echo "\n\nMatriks Jaccard Similarity Item-Item (10×10):\n\n";
echo str_pad("", 6);
foreach ($bookIds as $id) {
    echo str_pad("B{$id}", 7);
}
echo "\n";
echo str_repeat("-", 6 + 7 * count($bookIds)) . "\n";

foreach ($bookIds as $idA) {
    echo str_pad("B{$idA}", 6);
    foreach ($bookIds as $idB) {
        echo str_pad(number_format($itemSimilarity[$idA][$idB], 3), 7);
    }
    echo "\n";
}

// ============================================================
// LANGKAH 3: Prediksi Skor CF untuk setiap user
// Untuk setiap buku yang belum dipinjam user:
//   prediksi = rata-rata Jaccard dengan buku yang sudah dipinjam
// ============================================================
echo "\n--- LANGKAH 3: Prediksi Skor CF per User ---\n\n";

$predictions = [];

foreach ($userIds as $uid) {
    $borrowed    = array_keys(array_filter($matrix[$uid]));
    $notBorrowed = array_keys(array_filter($matrix[$uid], fn($v) => $v === 0));

    foreach ($notBorrowed as $candidateId) {
        $totalSim = 0;
        $count    = 0;

        // Hitung rata-rata similarity kandidat dengan semua buku yang sudah dipinjam
        foreach ($borrowed as $borrowedId) {
            $totalSim += $itemSimilarity[$candidateId][$borrowedId];
            $count++;
        }

        $predictions[$uid][$candidateId] = $count > 0
            ? round($totalSim / $count, 4)
            : 0;
    }
}

// ============================================================
// LANGKAH 4: Tampilkan Rekomendasi CF per User
// ============================================================
echo "--- LANGKAH 4: Top-3 Rekomendasi CF per User ---\n\n";

foreach ($userIds as $uid) {
    $user    = $users[$uid];
    $borrowed = array_keys(array_filter($matrix[$uid]));
    $scores  = $predictions[$uid] ?? [];

    arsort($scores);

    echo "User [{$uid}] {$user['name']} ({$user['jurusan']})\n";
    echo "  Buku yang sudah dipinjam:\n";
    foreach ($borrowed as $bid) {
        echo "    - [{$bid}] {$books[$bid]['title']}\n";
    }

    echo "  Prediksi Skor CF (buku belum dipinjam):\n";
    foreach ($scores as $candidateId => $score) {
        printf("    [Buku %2d] %-40s → skor: %.4f\n",
            $candidateId,
            '"' . $books[$candidateId]['title'] . '"',
            $score
        );
    }

    echo "  Top-3 Rekomendasi CF:\n";
    $rank = 1;
    foreach ($scores as $candidateId => $score) {
        if ($score <= 0) continue;
        if ($rank > 3) break;
        printf("    %d. [Buku %2d] %-40s (skor: %.4f)\n",
            $rank,
            $candidateId,
            '"' . $books[$candidateId]['title'] . '"',
            $score
        );
        $rank++;
    }

    if ($rank === 1) {
        echo "    → Tidak ada rekomendasi (cold start / data kurang)\n";
    }

    echo "\n";
}

echo "========================================================\n";
echo "  SELESAI — CF Simulation\n";
echo "========================================================\n";
