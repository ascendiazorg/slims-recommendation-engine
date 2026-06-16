<?php
/**
 * SIMULASI HYBRID RECOMMENDATION (CBF + CF)
 * ===========================================
 * Gabungkan hasil CBF dan CF menggunakan Weighted Fusion
 * Bobot: CF = 60%, CBF = 40%
 *
 * Data: 10 buku, 4 user (dari data.php)
 * Jalankan: php hybrid_simulation.php
 */

require_once 'data.php';
require_once 'cbf_simulation.php';  // ambil $tfidf, $similarityMatrix (CBF)
require_once 'cf_simulation.php';   // ambil $itemSimilarity, $predictions (CF)

echo "\n\n========================================================\n";
echo "  SIMULASI HYBRID RECOMMENDATION (CBF + CF)\n";
echo "  Weighted Fusion: CF=60% + CBF=40%\n";
echo "========================================================\n\n";

define('BOBOT_CF',  0.60);
define('BOBOT_CBF', 0.40);

// ============================================================
// Fungsi: Normalisasi skor ke range 0-1
// ============================================================
function normalizeScores(array $scores): array
{
    if (empty($scores)) return [];
    $max = max($scores);
    if ($max == 0) return $scores;
    return array_map(fn($s) => $s / $max, $scores);
}

// ============================================================
// Hybrid Recommendation untuk setiap user
// ============================================================
echo "--- HASIL HYBRID RECOMMENDATION per User ---\n\n";

foreach ($userIds as $uid) {
    $user     = $users[$uid];
    $borrowed = array_keys(array_filter($matrix[$uid]));

    echo "╔══════════════════════════════════════════════════════╗\n";
    echo "║ User [{$uid}]: {$user['name']} ({$user['jurusan']})\n";
    echo "╠══════════════════════════════════════════════════════╣\n";

    // Buku yang belum dipinjam = kandidat rekomendasi
    $candidates = array_keys(array_filter($matrix[$uid], fn($v) => $v === 0));

    if (empty($candidates)) {
        echo "║ → Sudah meminjam semua buku!\n";
        echo "╚══════════════════════════════════════════════════════╝\n\n";
        continue;
    }

    // --- Skor CBF: rata-rata similarity dengan buku yang dipinjam ---
    $cbfScores = [];
    foreach ($candidates as $candidateId) {
        $total = 0;
        $count = 0;
        foreach ($borrowed as $borrowedId) {
            $total += $similarityMatrix[$candidateId][$borrowedId] ?? 0;
            $count++;
        }
        $cbfScores[$candidateId] = $count > 0 ? $total / $count : 0;
    }

    // --- Skor CF: dari prediksi item-based ---
    $cfScores = [];
    foreach ($candidates as $candidateId) {
        $cfScores[$candidateId] = $predictions[$uid][$candidateId] ?? 0;
    }

    // --- Normalisasi ke 0-1 ---
    $cbfNorm = normalizeScores($cbfScores);
    $cfNorm  = normalizeScores($cfScores);

    // --- Weighted Fusion ---
    $hybridScores = [];
    foreach ($candidates as $candidateId) {
        $hybridScores[$candidateId] =
            (BOBOT_CF  * ($cfNorm[$candidateId]  ?? 0)) +
            (BOBOT_CBF * ($cbfNorm[$candidateId] ?? 0));
    }
    arsort($hybridScores);

    // Tampilkan detail skor
    echo "║ Buku yang sudah dipinjam:\n";
    foreach ($borrowed as $bid) {
        echo "║   ✓ [{$bid}] {$books[$bid]['title']}\n";
    }

    echo "║\n";
    printf("║ %-5s %-30s %8s %8s %10s\n", "ID", "Judul", "CBF", "CF", "HYBRID");
    echo "║ " . str_repeat("-", 65) . "\n";

    foreach ($candidates as $candidateId) {
        $cbf    = round($cbfNorm[$candidateId] ?? 0, 4);
        $cf     = round($cfNorm[$candidateId]  ?? 0, 4);
        $hybrid = round($hybridScores[$candidateId], 4);

        printf("║ [%2d] %-30s %8.4f %8.4f %10.4f\n",
            $candidateId,
            substr($books[$candidateId]['title'], 0, 30),
            $cbf, $cf, $hybrid
        );
    }

    echo "║\n";
    echo "║ ⭐ Top-3 Rekomendasi HYBRID:\n";
    $rank = 1;
    foreach ($hybridScores as $candidateId => $score) {
        if ($rank > 3) break;
        printf("║   %d. [Buku %2d] %-35s (%.4f)\n",
            $rank,
            $candidateId,
            '"' . $books[$candidateId]['title'] . '"',
            $score
        );
        $rank++;
    }

    echo "╚══════════════════════════════════════════════════════╝\n\n";
}

// ============================================================
// Perbandingan: CBF vs CF vs Hybrid untuk User 1 (Budi)
// ============================================================
echo "========================================================\n";
echo "  PERBANDINGAN CBF vs CF vs HYBRID — User [1]: Budi\n";
echo "========================================================\n\n";

$uid      = 1;
$borrowed = array_keys(array_filter($matrix[$uid]));
$candidates = array_keys(array_filter($matrix[$uid], fn($v) => $v === 0));

// CBF skor
$cbfScores = [];
foreach ($candidates as $candidateId) {
    $total = 0; $count = 0;
    foreach ($borrowed as $bid) {
        $total += $similarityMatrix[$candidateId][$bid] ?? 0;
        $count++;
    }
    $cbfScores[$candidateId] = $count > 0 ? round($total / $count, 4) : 0;
}
arsort($cbfScores);

// CF skor
$cfScores = $predictions[$uid] ?? [];
arsort($cfScores);

// Hybrid
$cbfNorm = normalizeScores($cbfScores);
$cfNorm  = normalizeScores(array_intersect_key($cfScores, array_flip($candidates)));
$hybridScores = [];
foreach ($candidates as $cid) {
    $hybridScores[$cid] =
        (BOBOT_CF  * ($cfNorm[$cid]  ?? 0)) +
        (BOBOT_CBF * ($cbfNorm[$cid] ?? 0));
}
arsort($hybridScores);

echo "Budi sudah pinjam: " . implode(', ', array_map(
    fn($id) => "[{$id}] {$books[$id]['title']}",
    $borrowed
)) . "\n\n";

echo "CBF TOP-3:\n";
$r = 1;
foreach ($cbfScores as $id => $s) {
    if ($r > 3) break;
    echo "  {$r}. [{$id}] {$books[$id]['title']} → skor: {$s}\n";
    $r++;
}

echo "\nCF TOP-3:\n";
$r = 1;
foreach ($cfScores as $id => $s) {
    if (!in_array($id, $candidates)) continue;
    if ($r > 3) break;
    echo "  {$r}. [{$id}] {$books[$id]['title']} → skor: {$s}\n";
    $r++;
}

echo "\nHYBRID TOP-3 (CF×0.6 + CBF×0.4):\n";
$r = 1;
foreach ($hybridScores as $id => $s) {
    if ($r > 3) break;
    echo "  {$r}. [{$id}] {$books[$id]['title']} → skor: " . round($s, 4) . "\n";
    $r++;
}

echo "\n========================================================\n";
echo "  SELESAI — Hybrid Simulation\n";
echo "========================================================\n";
