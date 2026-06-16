<?php
/**
 * Evaluasi IBCF — Precision, Recall, F-Measure, MAE, RMSE, Coverage, NDCG
 *
 * Metode evaluasi: Leave-One-Out Cross Validation (LOOCV)
 * Untuk setiap user: sembunyikan 1 buku dari riwayat,
 * jalankan IBCF tanpa buku tersebut, cek apakah muncul di top-N.
 *
 * STANDALONE — tidak perlu database atau SLIMS.
 * Jalankan: php ibcf_evaluation.php
 *           atau buka via browser (XAMPP)
 */

// ============================================================
// DATA INPUT (sama dengan ibcf_simulation.php)
// ============================================================

$buku = [
    1  => 'PHP 5 for Dummies',
    2  => 'Linux In a Nutshell',
    3  => 'The Definitive Guide to MySQL 5',
    4  => 'Cathedral and the Bazaar',
    5  => 'Producing Open Source Software',
    7  => 'Web Application Architecture',
    8  => 'Ajax: Creating Web Pages',
    9  => 'The Organization of Information',
    10 => 'Library and Information Center Management',
    11 => 'Information Architecture for the Web',
];

$interaksi = [
    'M001' => [1, 7, 8, 11],
    'M002' => [1, 2, 3, 7, 8],
    'M003' => [7, 8, 9, 10, 11],
    'M004' => [1, 3, 5, 9, 10],
    'M005' => [2, 4, 5, 9, 10],
];

$TOP_N = 5; // jumlah rekomendasi yang dievaluasi

// ============================================================
// FUNGSI INTI
// ============================================================

/** Balik interaksi: buku => [member] */
function buildBukuUsers(array $interaksi): array {
    $buku_users = [];
    foreach ($interaksi as $member_id => $list_buku) {
        foreach ($list_buku as $buku_id) {
            $buku_users[$buku_id][] = $member_id;
        }
    }
    return $buku_users;
}

/** Hitung Jaccard Similarity */
function jaccard(array $users_a, array $users_b): float {
    $intersect = count(array_intersect($users_a, $users_b));
    $union     = count(array_unique(array_merge($users_a, $users_b)));
    if ($union === 0) return 0.0;
    return round($intersect / $union, 4);
}

/** Bangun matrix similarity dari buku_users */
function buildSimilarity(array $buku_users): array {
    $buku_ids   = array_keys($buku_users);
    $similarity = [];
    foreach ($buku_ids as $a) {
        foreach ($buku_ids as $b) {
            $similarity[$a][$b] = ($a === $b)
                ? 1.0
                : jaccard($buku_users[$a], $buku_users[$b]);
        }
    }
    return $similarity;
}

/**
 * Hasilkan rekomendasi untuk buku tertentu
 * Mengambil top-N buku paling mirip dengan target,
 * dikecualikan buku yang sudah diakses user.
 */
function getRekomendasiUntukUser(
    string $member_id,
    array  $seen_books,   // buku yang sudah dilihat user (termasuk hidden item)
    array  $similarity,
    int    $topN
): array {
    // Gabungkan semua skor untuk buku yang belum dilihat
    $skor = [];
    $all_buku_ids = array_keys($similarity);

    foreach ($seen_books as $seen_id) {
        if (!isset($similarity[$seen_id])) continue;
        foreach ($similarity[$seen_id] as $candidate_id => $sim) {
            if (in_array($candidate_id, $seen_books)) continue; // skip yg sudah dilihat
            if ($sim <= 0) continue;
            $skor[$candidate_id] = ($skor[$candidate_id] ?? 0) + $sim;
        }
    }

    if (empty($skor)) return [];
    arsort($skor);
    return array_slice($skor, 0, $topN, true);
}

// ============================================================
// LEAVE-ONE-OUT CROSS VALIDATION (LOOCV)
// ============================================================

$hasil_loocv = []; // detail per iterasi
$tp_total    = 0;
$fp_total    = 0;
$fn_total    = 0;

// Untuk MAE / RMSE (error antara predicted score dan actual binary)
$mae_errors  = [];
$rmse_errors = [];

// Untuk Coverage
$recommended_books = [];

// Untuk NDCG
$ndcg_list = [];

foreach ($interaksi as $member_id => $list_buku) {
    if (count($list_buku) < 2) continue; // minimal 2 buku supaya LOOCV bisa jalan

    foreach ($list_buku as $hidden_id) {
        // Buat data interaksi tanpa hidden_id untuk user ini
        $interaksi_loocv = $interaksi;
        $interaksi_loocv[$member_id] = array_values(
            array_filter($list_buku, fn($id) => $id !== $hidden_id)
        );

        // Bangun model dari data yang dikurangi
        $buku_users_loocv  = buildBukuUsers($interaksi_loocv);
        $similarity_loocv  = buildSimilarity($buku_users_loocv);

        // Buku yang masih dilihat oleh user (setelah dikurangi hidden)
        $seen = $interaksi_loocv[$member_id];

        // Hasilkan rekomendasi
        $rekomendasi = getRekomendasiUntukUser($member_id, $seen, $similarity_loocv, $TOP_N);

        $is_hit       = array_key_exists($hidden_id, $rekomendasi);
        $rec_ids      = array_keys($rekomendasi);
        $rec_scores   = array_values($rekomendasi);

        // TP, FP, FN
        $tp = $is_hit ? 1 : 0;
        $fp = count($rekomendasi) - $tp;
        $fn = $is_hit ? 0 : 1;

        $tp_total += $tp;
        $fp_total += $fp;
        $fn_total += $fn;

        // MAE & RMSE: predicted vs actual (binary 1/0)
        // Untuk buku yang direkomendasikan: predicted = normalized score, actual = 1 jika hidden
        // Sederhananya: error = |predicted - actual|
        // actual hidden_id = 1.0, semua lainnya = 0.0
        $max_score = !empty($rec_scores) ? max($rec_scores) : 1;
        foreach ($rekomendasi as $rec_id => $score) {
            $predicted = ($max_score > 0) ? $score / $max_score : 0;
            $actual    = ($rec_id === $hidden_id) ? 1.0 : 0.0;
            $error     = abs($predicted - $actual);
            $mae_errors[]  = $error;
            $rmse_errors[] = $error * $error;
        }
        // Tambahkan error untuk hidden_id jika tidak direkomendasikan
        if (!$is_hit) {
            $mae_errors[]  = 1.0; // predicted=0, actual=1
            $rmse_errors[] = 1.0;
        }

        // Coverage: kumpulkan semua buku yang pernah direkomendasikan
        foreach ($rec_ids as $rid) {
            $recommended_books[$rid] = true;
        }

        // NDCG: ideal = hidden_id di rank 1
        $ndcg = 0.0;
        if ($is_hit) {
            $rank = array_search($hidden_id, $rec_ids) + 1; // 1-indexed
            $ndcg = 1 / log($rank + 1, 2); // DCG untuk satu item relevan
            // IDCG = 1/log2(2) = 1.0 (item relevan di rank 1)
            $ndcg = $ndcg / 1.0; // NDCG = DCG / IDCG
        }
        $ndcg_list[] = $ndcg;

        // Simpan detail
        $hasil_loocv[] = [
            'member'      => $member_id,
            'hidden'      => $hidden_id,
            'rekomendasi' => $rec_ids,
            'is_hit'      => $is_hit,
            'tp'          => $tp,
            'fp'          => $fp,
            'fn'          => $fn,
            'ndcg'        => round($ndcg, 4),
        ];
    }
}

// ============================================================
// HITUNG METRIK AKHIR
// ============================================================

// Precision@N = TP / (TP + FP)
$precision = ($tp_total + $fp_total) > 0
    ? round($tp_total / ($tp_total + $fp_total), 4)
    : 0.0;

// Recall@N = TP / (TP + FN)
$recall = ($tp_total + $fn_total) > 0
    ? round($tp_total / ($tp_total + $fn_total), 4)
    : 0.0;

// F-Measure = 2 * P * R / (P + R)
$f_measure = ($precision + $recall) > 0
    ? round(2 * $precision * $recall / ($precision + $recall), 4)
    : 0.0;

// MAE = rata-rata |predicted - actual|
$mae = count($mae_errors) > 0
    ? round(array_sum($mae_errors) / count($mae_errors), 4)
    : 0.0;

// RMSE = sqrt(rata-rata squared error)
$rmse = count($rmse_errors) > 0
    ? round(sqrt(array_sum($rmse_errors) / count($rmse_errors)), 4)
    : 0.0;

// Coverage = buku unik yang pernah direkomendasikan / total buku
$coverage = round(count($recommended_books) / count($buku), 4);

// NDCG rata-rata
$ndcg_avg = count($ndcg_list) > 0
    ? round(array_sum($ndcg_list) / count($ndcg_list), 4)
    : 0.0;

// ============================================================
// OUTPUT
// ============================================================
$is_browser = isset($_SERVER['HTTP_HOST']);

if ($is_browser) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Evaluasi IBCF</title>
    <style>
        body    { font-family: monospace; padding: 20px; background: #f9f9f9; max-width: 1100px; margin: 0 auto; }
        h2      { color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px; margin-top: 30px; }
        h3      { color: #16a085; }
        table   { border-collapse: collapse; margin: 10px 0 20px; width: 100%; }
        th      { background: #2c3e50; color: white; padding: 6px 10px; text-align: left; }
        td      { border: 1px solid #ccc; padding: 5px 10px; }
        tr:nth-child(even) { background: #f0f0f0; }
        .hit    { color: green; font-weight: bold; }
        .miss   { color: #c0392b; }
        .metric { font-size: 13pt; padding: 8px 12px; }
        .good   { color: green; font-weight: bold; }
        .medium { color: orange; font-weight: bold; }
        .bad    { color: #c0392b; font-weight: bold; }
        .box    { background: white; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .formula { background: #eaf3fb; border-left: 4px solid #2980b9; padding: 8px 12px; margin: 5px 0; font-size: 10pt; }
    </style></head><body>";
    echo "<h1 style='text-align:center;color:#2c3e50;'>Evaluasi Item-Based CF (IBCF)<br><small style='font-size:12pt;color:#666;'>Jaccard Similarity &bull; Leave-One-Out Cross Validation &bull; Top-$TOP_N</small></h1>";
}

// --- BAGIAN 1: Penjelasan Metode ---
if ($is_browser) {
    echo "<h2>Metode Evaluasi: Leave-One-Out Cross Validation (LOOCV)</h2>";
    echo "<div class='box'>";
    echo "<p><b>Cara kerja LOOCV:</b></p>";
    echo "<ol>
        <li>Untuk setiap user dan setiap buku yang pernah mereka buka:</li>
        <li>Sembunyikan 1 buku dari riwayat user tersebut (<i>hidden item</i>)</li>
        <li>Jalankan IBCF menggunakan sisa data (tanpa buku yang disembunyikan)</li>
        <li>Cek apakah buku yang disembunyikan muncul dalam Top-$TOP_N rekomendasi</li>
        <li>Jika muncul → <b>HIT</b> (TP=1); jika tidak → <b>MISS</b> (FN=1)</li>
        <li>Ulangi untuk semua kombinasi user-buku, hitung rata-rata</li>
    </ol>";
    echo "</div>";
} else {
    echo "\n=== METODE: LEAVE-ONE-OUT CROSS VALIDATION ===\n";
    echo "Untuk setiap user, sembunyikan 1 buku, jalankan IBCF, cek apakah muncul di Top-$TOP_N\n";
}

// --- BAGIAN 2: Detail LOOCV ---
if ($is_browser) {
    echo "<h2>Detail Iterasi LOOCV</h2>";
    echo "<table>
        <tr>
            <th>Member</th>
            <th>Hidden Item (disembunyikan)</th>
            <th>Top-$TOP_N Rekomendasi</th>
            <th>Hasil</th>
            <th>TP</th><th>FP</th><th>FN</th>
            <th>NDCG</th>
        </tr>";
    foreach ($hasil_loocv as $h) {
        $hidden_label = "[{$h['hidden']}] " . $GLOBALS['buku'][$h['hidden']];
        $rec_labels = array_map(fn($id) => "[$id]", $h['rekomendasi']);
        $rec_str    = implode(', ', $rec_labels);
        $hit_class  = $h['is_hit'] ? 'hit' : 'miss';
        $hit_label  = $h['is_hit'] ? '✓ HIT' : '✗ MISS';
        echo "<tr>
            <td><b>{$h['member']}</b></td>
            <td>$hidden_label</td>
            <td>$rec_str</td>
            <td class='$hit_class'>$hit_label</td>
            <td>{$h['tp']}</td>
            <td>{$h['fp']}</td>
            <td>{$h['fn']}</td>
            <td>{$h['ndcg']}</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "\n=== DETAIL ITERASI LOOCV ===\n";
    printf("%-6s %-6s %-30s %-6s %-4s %-4s %-4s %-6s\n",
        "Member", "Hidden", "Top-$TOP_N Rekomendasi", "Hasil", "TP", "FP", "FN", "NDCG");
    echo str_repeat("-", 80) . "\n";
    foreach ($hasil_loocv as $h) {
        $rec_str = implode(',', array_map(fn($id) => "[$id]", $h['rekomendasi']));
        printf("%-6s %-6s %-30s %-6s %-4d %-4d %-4d %-6.4f\n",
            $h['member'],
            "[{$h['hidden']}]",
            substr($rec_str, 0, 29),
            $h['is_hit'] ? 'HIT' : 'MISS',
            $h['tp'], $h['fp'], $h['fn'], $h['ndcg']
        );
    }
}

// --- BAGIAN 3: Rumus & Hasil Metrik ---
if ($is_browser) {
    echo "<h2>Hasil Evaluasi Metrik</h2>";

    $metrik = [
        [
            'nama'    => 'Precision@' . $TOP_N,
            'nilai'   => $precision,
            'pct'     => round($precision * 100, 1),
            'rumus'   => "TP / (TP + FP) = $tp_total / " . ($tp_total + $fp_total),
            'arti'    => "Dari semua buku yang direkomendasikan, $precision × 100 = {$pct}% relevan",
            'threshold' => 0.5,
        ],
        [
            'nama'    => 'Recall@' . $TOP_N,
            'nilai'   => $recall,
            'pct'     => round($recall * 100, 1),
            'rumus'   => "TP / (TP + FN) = $tp_total / " . ($tp_total + $fn_total),
            'arti'    => "Dari semua item relevan, $recall × 100 = {$pct}% berhasil ditemukan",
            'threshold' => 0.5,
        ],
        [
            'nama'    => 'F-Measure',
            'nilai'   => $f_measure,
            'pct'     => round($f_measure * 100, 1),
            'rumus'   => "2 × P × R / (P + R) = 2 × $precision × $recall / ($precision + $recall)",
            'arti'    => "Rata-rata harmonik Precision dan Recall",
            'threshold' => 0.5,
        ],
        [
            'nama'    => 'MAE',
            'nilai'   => $mae,
            'pct'     => null,
            'rumus'   => "rata-rata |predicted_score - actual(0/1)|",
            'arti'    => "Rata-rata error absolut prediksi (mendekati 0 = lebih baik)",
            'threshold' => null,
            'lower_better' => true,
        ],
        [
            'nama'    => 'RMSE',
            'nilai'   => $rmse,
            'pct'     => null,
            'rumus'   => "√(rata-rata (predicted_score - actual)²)",
            'arti'    => "Root Mean Square Error (mendekati 0 = lebih baik)",
            'threshold' => null,
            'lower_better' => true,
        ],
        [
            'nama'    => 'Coverage',
            'nilai'   => $coverage,
            'pct'     => round($coverage * 100, 1),
            'rumus'   => count($recommended_books) . " buku unik direkomendasikan / " . count($buku) . " total buku",
            'arti'    => "Seberapa luas cakupan katalog buku yang direkomendasikan",
            'threshold' => 0.5,
        ],
        [
            'nama'    => 'NDCG',
            'nilai'   => $ndcg_avg,
            'pct'     => round($ndcg_avg * 100, 1),
            'rumus'   => "rata-rata NDCG = rata-rata (1 / log2(rank + 1)) untuk setiap HIT",
            'arti'    => "Kualitas urutan rekomendasi (item relevan di rank 1 = nilai tertinggi)",
            'threshold' => 0.5,
        ],
    ];

    echo "<table>
        <tr>
            <th>Metrik</th>
            <th>Nilai</th>
            <th>Persentase</th>
            <th>Rumus / Perhitungan</th>
            <th>Interpretasi</th>
        </tr>";

    foreach ($metrik as $m) {
        $lower_better = $m['lower_better'] ?? false;
        if ($lower_better) {
            $cls = $m['nilai'] <= 0.3 ? 'good' : ($m['nilai'] <= 0.6 ? 'medium' : 'bad');
        } else {
            $cls = $m['nilai'] >= 0.6 ? 'good' : ($m['nilai'] >= 0.3 ? 'medium' : 'bad');
        }
        $pct_str = $m['pct'] !== null ? $m['pct'] . '%' : '—';
        echo "<tr>
            <td><b>{$m['nama']}</b></td>
            <td class='metric $cls'>{$m['nilai']}</td>
            <td class='$cls'>$pct_str</td>
            <td class='formula'>{$m['rumus']}</td>
            <td>{$m['arti']}</td>
        </tr>";
    }
    echo "</table>";

} else {
    echo "\n=== HASIL EVALUASI METRIK ===\n";
    echo str_repeat("=", 60) . "\n";
    $pct_precision = round($precision * 100, 1);
    $pct_recall    = round($recall * 100, 1);
    $pct_fmeasure  = round($f_measure * 100, 1);
    $pct_coverage  = round($coverage * 100, 1);
    $pct_ndcg      = round($ndcg_avg * 100, 1);
    printf("%-20s : %.4f  (%s%%)\n", "Precision@$TOP_N",  $precision,  $pct_precision);
    printf("%-20s : %.4f  (%s%%)\n", "Recall@$TOP_N",     $recall,     $pct_recall);
    printf("%-20s : %.4f  (%s%%)\n", "F-Measure",         $f_measure,  $pct_fmeasure);
    printf("%-20s : %.4f  (lebih kecil = lebih baik)\n",  "MAE",  $mae);
    printf("%-20s : %.4f  (lebih kecil = lebih baik)\n",  "RMSE", $rmse);
    printf("%-20s : %.4f  (%s%%)\n", "Coverage",          $coverage,   $pct_coverage);
    printf("%-20s : %.4f  (%s%%)\n", "NDCG",              $ndcg_avg,   $pct_ndcg);
    echo str_repeat("=", 60) . "\n";
}

// --- BAGIAN 4: Agregat TP/FP/FN ---
$total_iter = count($hasil_loocv);
$total_hit  = $tp_total;

if ($is_browser) {
    echo "<h2>Agregat LOOCV</h2>";
    echo "<table>
        <tr><th>Keterangan</th><th>Nilai</th></tr>
        <tr><td>Total iterasi LOOCV</td><td><b>$total_iter</b></td></tr>
        <tr><td>Total HIT (TP)</td><td class='hit'><b>$total_hit</b></td></tr>
        <tr><td>Total MISS (FN)</td><td class='miss'><b>$fn_total</b></td></tr>
        <tr><td>Total FP (rekomendasikan item tidak relevan)</td><td><b>$fp_total</b></td></tr>
        <tr><td>Top-N yang dievaluasi</td><td><b>$TOP_N</b></td></tr>
        <tr><td>Buku unik yang direkomendasikan</td><td><b>" . count($recommended_books) . " dari " . count($buku) . " buku</b></td></tr>
    </table>";
} else {
    echo "\n=== AGREGAT ===\n";
    printf("%-35s : %d\n", "Total iterasi LOOCV",     $total_iter);
    printf("%-35s : %d\n", "Total HIT (TP)",           $total_hit);
    printf("%-35s : %d\n", "Total MISS (FN)",           $fn_total);
    printf("%-35s : %d\n", "Total FP",                  $fp_total);
    printf("%-35s : %d dari %d buku\n",
        "Buku unik direkomendasikan",
        count($recommended_books), count($buku));
}

// --- BAGIAN 5: Penjelasan Metrik untuk Laporan ---
if ($is_browser) {
    echo "<h2>Penjelasan Metrik (untuk Laporan / Bimbingan)</h2>";
    echo "<div class='box'>";
    echo "<table>
        <tr><th>Metrik</th><th>Definisi</th><th>Interpretasi Hasil</th></tr>
        <tr>
            <td><b>Precision@N</b></td>
            <td>Proporsi item yang direkomendasikan yang benar-benar relevan</td>
            <td>Semakin tinggi = semakin tepat sasaran rekomendasinya</td>
        </tr>
        <tr>
            <td><b>Recall@N</b></td>
            <td>Proporsi item relevan yang berhasil ditemukan oleh sistem</td>
            <td>Semakin tinggi = semakin sedikit item relevan yang terlewat</td>
        </tr>
        <tr>
            <td><b>F-Measure</b></td>
            <td>Rata-rata harmonik Precision dan Recall (keseimbangan keduanya)</td>
            <td>Metrik tunggal terbaik untuk membandingkan antar algoritma</td>
        </tr>
        <tr>
            <td><b>MAE</b></td>
            <td>Rata-rata selisih absolut antara skor prediksi dan nilai aktual</td>
            <td>Semakin kecil = prediksi skor lebih akurat</td>
        </tr>
        <tr>
            <td><b>RMSE</b></td>
            <td>Seperti MAE tapi error besar dihukum lebih berat (dikuadratkan)</td>
            <td>Semakin kecil = lebih baik; sensitif terhadap outlier</td>
        </tr>
        <tr>
            <td><b>Coverage</b></td>
            <td>Persentase katalog buku yang pernah masuk dalam rekomendasi</td>
            <td>Semakin tinggi = rekomendasi lebih beragam (tidak monoton)</td>
        </tr>
        <tr>
            <td><b>NDCG</b></td>
            <td>Kualitas urutan (ranking) rekomendasi; item relevan di atas lebih baik</td>
            <td>Nilai 1.0 = sempurna; item relevan selalu di rank pertama</td>
        </tr>
    </table>";
    echo "</div>";
    echo "</body></html>";
} else {
    echo "\n=== PENJELASAN METRIK ===\n";
    echo "Precision : Dari rekomendasinya, berapa % yang tepat\n";
    echo "Recall    : Dari yg harusnya muncul, berapa % yg ditemukan\n";
    echo "F-Measure : Keseimbangan Precision & Recall (metrik utama)\n";
    echo "MAE       : Rata-rata error prediksi (kecil = bagus)\n";
    echo "RMSE      : Error dikuadratkan dulu (sensitif outlier)\n";
    echo "Coverage  : Seberapa banyak katalog tercakup rekomendasinya\n";
    echo "NDCG      : Kualitas urutan rekomendasi (1.0 = sempurna)\n";
}
