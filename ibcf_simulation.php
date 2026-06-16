<?php
/**
 * Simulasi Item-Based Collaborative Filtering (IBCF)
 * Menggunakan Jaccard Similarity
 *
 * STANDALONE — tidak perlu SLIMS, tidak perlu database
 * Jalankan: php ibcf_simulation.php (via terminal)
 *           atau buka via browser di XAMPP
 */

// ============================================================
// DATA INPUT
// ============================================================

// 10 Buku (id => judul)
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

// Interaksi user-buku (siapa membuka/meminjam buku apa)
// Format: member_id => [biblio_id, biblio_id, ...]
$interaksi = [
    'M001' => [1, 7, 8, 11],
    'M002' => [1, 2, 3, 7, 8],
    'M003' => [7, 8, 9, 10, 11],
    'M004' => [1, 3, 5, 9, 10],
    'M005' => [2, 4, 5, 9, 10],
];

// ============================================================
// LANGKAH 1: BALIK ARRAY
// Dari: member => [buku]
// Menjadi: buku => [member]
// ============================================================
$buku_users = []; // buku_id => [member yang buka buku ini]

foreach ($interaksi as $member_id => $list_buku) {
    foreach ($list_buku as $buku_id) {
        $buku_users[$buku_id][] = $member_id;
    }
}

// ============================================================
// LANGKAH 2: HITUNG JACCARD SIMILARITY
// Untuk setiap pasang buku, hitung kemiripannya
// ============================================================

/**
 * Fungsi hitung Jaccard Similarity
 * @param array $users_a  list member yang buka buku A
 * @param array $users_b  list member yang buka buku B
 * @return float nilai similarity antara 0 dan 1
 */
function jaccard(array $users_a, array $users_b): float {
    $intersect = count(array_intersect($users_a, $users_b)); // yang sama
    $union     = count(array_unique(array_merge($users_a, $users_b))); // gabungan
    if ($union === 0) return 0;
    return round($intersect / $union, 4);
}

$similarity = []; // similarity[buku_a][buku_b] = nilai
$buku_ids   = array_keys($buku_users);

for ($i = 0; $i < count($buku_ids); $i++) {
    for ($j = 0; $j < count($buku_ids); $j++) {
        $a = $buku_ids[$i];
        $b = $buku_ids[$j];
        if ($a === $b) {
            $similarity[$a][$b] = 1.0; // buku dengan dirinya sendiri = 1
        } else {
            $similarity[$a][$b] = jaccard($buku_users[$a], $buku_users[$b]);
        }
    }
}

// ============================================================
// LANGKAH 3: FUNGSI REKOMENDASI
// Untuk buku tertentu, ambil top-N buku paling mirip
// ============================================================

/**
 * Fungsi ambil rekomendasi untuk satu buku
 * @param int   $target_id  buku yang sedang dilihat
 * @param array $similarity matrix similarity
 * @param int   $topN       berapa rekomendasi yang ditampilkan
 * @return array list rekomendasi diurutkan dari similarity tertinggi
 */
function getRekomendasi(int $target_id, array $similarity, int $topN = 5): array {
    $kandidat = $similarity[$target_id] ?? [];

    // Hapus buku itu sendiri dari kandidat
    unset($kandidat[$target_id]);

    // Hapus yang similarity = 0
    $kandidat = array_filter($kandidat, fn($sim) => $sim > 0);

    // Urutkan dari similarity tertinggi
    arsort($kandidat);

    // Ambil top N
    return array_slice($kandidat, 0, $topN, true);
}

// ============================================================
// OUTPUT — Tampilkan Hasil
// ============================================================
$is_browser = isset($_SERVER['HTTP_HOST']);
if ($is_browser) {
    echo "<html><head><meta charset='UTF-8'>
    <style>
        body { font-family: monospace; padding: 20px; background: #f9f9f9; }
        h2   { color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px; }
        h3   { color: #16a085; }
        table { border-collapse: collapse; margin: 10px 0 20px; width: 100%; }
        th   { background: #2c3e50; color: white; padding: 6px 10px; text-align: left; }
        td   { border: 1px solid #ccc; padding: 5px 10px; }
        tr:nth-child(even) { background: #f0f0f0; }
        .high   { color: green;  font-weight: bold; }
        .medium { color: orange; font-weight: bold; }
        .low    { color: #999; }
        pre  { background: #fff; border: 1px solid #ddd; padding: 10px; }
    </style></head><body>";
}

// --- BAGIAN 1: Matrix Interaksi ---
echo $is_browser ? "<h2>LANGKAH 1 — Matrix Interaksi User-Buku</h2>" : "\n=== LANGKAH 1: MATRIX INTERAKSI ===\n";
echo $is_browser ? "<p>Tanda <b>✓</b> = user pernah membuka/meminjam buku tersebut</p>" : "";

$buku_ids_sorted = array_keys($buku);

if ($is_browser) {
    echo "<table><tr><th>Member</th>";
    foreach ($buku_ids_sorted as $bid) {
        echo "<th title='{$buku[$bid]}'>Buku $bid</th>";
    }
    echo "</tr>";
    foreach ($interaksi as $mid => $list) {
        echo "<tr><td><b>$mid</b></td>";
        foreach ($buku_ids_sorted as $bid) {
            echo in_array($bid, $list)
                ? "<td style='text-align:center;color:green;'>✓</td>"
                : "<td style='text-align:center;color:#ccc;'>-</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    // CLI output
    printf("%-8s", "Member");
    foreach ($buku_ids_sorted as $bid) printf("%-6s", "B$bid");
    echo "\n" . str_repeat("-", 8 + 6 * count($buku_ids_sorted)) . "\n";
    foreach ($interaksi as $mid => $list) {
        printf("%-8s", $mid);
        foreach ($buku_ids_sorted as $bid) {
            printf("%-6s", in_array($bid, $list) ? "✓" : "-");
        }
        echo "\n";
    }
}

// --- BAGIAN 2: Tabel Similarity ---
echo $is_browser ? "<h2>LANGKAH 2 — Jaccard Similarity Antar Buku</h2>" : "\n=== LANGKAH 2: JACCARD SIMILARITY ===\n";
echo $is_browser ? "<p>Nilai 1.0 = selalu dibuka bersama | 0.0 = tidak pernah dibuka bersama</p>" : "";

if ($is_browser) {
    echo "<table><tr><th>Buku A</th><th>Buku B</th><th>Intersect</th><th>Union</th><th>Similarity</th><th>%</th></tr>";
    foreach ($buku_ids as $a) {
        foreach ($buku_ids as $b) {
            if ($a >= $b) continue;
            $sim       = $similarity[$a][$b];
            $intersect = count(array_intersect($buku_users[$a], $buku_users[$b]));
            $union     = count(array_unique(array_merge($buku_users[$a], $buku_users[$b])));
            $pct       = round($sim * 100);
            $cls       = $sim >= 0.6 ? 'high' : ($sim >= 0.3 ? 'medium' : 'low');
            echo "<tr>
                <td>[$a] {$buku[$a]}</td>
                <td>[$b] {$buku[$b]}</td>
                <td style='text-align:center;'>$intersect</td>
                <td style='text-align:center;'>$union</td>
                <td style='text-align:center;' class='$cls'>$sim</td>
                <td style='text-align:center;' class='$cls'>$pct%</td>
            </tr>";
        }
    }
    echo "</table>";
} else {
    printf("%-35s %-35s %10s %6s %10s\n", "Buku A", "Buku B", "Intersect", "Union", "Similarity");
    echo str_repeat("-", 100) . "\n";
    foreach ($buku_ids as $a) {
        foreach ($buku_ids as $b) {
            if ($a >= $b) continue;
            $sim       = $similarity[$a][$b];
            $intersect = count(array_intersect($buku_users[$a], $buku_users[$b]));
            $union     = count(array_unique(array_merge($buku_users[$a], $buku_users[$b])));
            printf("%-35s %-35s %10d %6d %10.4f\n",
                substr("[$a] {$buku[$a]}", 0, 34),
                substr("[$b] {$buku[$b]}", 0, 34),
                $intersect, $union, $sim
            );
        }
    }
}

// --- BAGIAN 3: Hasil Rekomendasi ---
echo $is_browser ? "<h2>LANGKAH 3 — Hasil Rekomendasi</h2>" : "\n=== LANGKAH 3: HASIL REKOMENDASI ===\n";

foreach (array_keys($buku) as $target_id) {
    $rekomendasi = getRekomendasi($target_id, $similarity, 5);
    if (empty($rekomendasi)) continue;

    if ($is_browser) {
        echo "<h3>Buku [$target_id]: {$buku[$target_id]}</h3>";
        echo "<table><tr><th>Rank</th><th>ID</th><th>Judul Buku</th><th>Similarity</th><th>Kecocokan</th></tr>";
        $rank = 1;
        foreach ($rekomendasi as $rec_id => $sim) {
            $pct = round($sim * 100);
            $cls = $sim >= 0.6 ? 'high' : ($sim >= 0.3 ? 'medium' : 'low');
            echo "<tr>
                <td style='text-align:center;'>$rank</td>
                <td style='text-align:center;'>$rec_id</td>
                <td>{$buku[$rec_id]}</td>
                <td style='text-align:center;' class='$cls'>$sim</td>
                <td style='text-align:center;' class='$cls'>$pct%</td>
            </tr>";
            $rank++;
        }
        echo "</table>";
    } else {
        echo "\nBuku [$target_id]: {$buku[$target_id]}\n";
        $rank = 1;
        foreach ($rekomendasi as $rec_id => $sim) {
            printf("  %d. [%2d] %-40s → %.4f (%d%%)\n",
                $rank++, $rec_id, $buku[$rec_id], $sim, round($sim * 100));
        }
    }
}

// --- BAGIAN 4: Ringkasan ---
echo $is_browser ? "<h2>Ringkasan</h2>" : "\n=== RINGKASAN ===\n";

$total_pasang   = count($buku_ids) * (count($buku_ids) - 1) / 2;
$pasang_nonzero = 0;
foreach ($buku_ids as $a) {
    foreach ($buku_ids as $b) {
        if ($a < $b && $similarity[$a][$b] > 0) $pasang_nonzero++;
    }
}

$info = [
    'Total buku'                    => count($buku),
    'Total member'                  => count($interaksi),
    'Total interaksi (user-buku)'   => array_sum(array_map('count', array_values($interaksi))),
    'Total pasang buku dievaluasi'  => $total_pasang,
    'Pasang dengan similarity > 0'  => $pasang_nonzero,
    'Algoritma'                     => 'Item-Based CF (IBCF)',
    'Rumus Similarity'              => 'Jaccard Similarity',
];

if ($is_browser) {
    echo "<table><tr><th>Keterangan</th><th>Nilai</th></tr>";
    foreach ($info as $k => $v) {
        echo "<tr><td>$k</td><td><b>$v</b></td></tr>";
    }
    echo "</table></body></html>";
} else {
    foreach ($info as $k => $v) {
        printf("%-40s: %s\n", $k, $v);
    }
}
