<?php
/**
 * SIMULASI CONTENT-BASED FILTERING (CBF)
 * ========================================
 * Algoritma: TF-IDF + Cosine Similarity
 * Data    : 10 buku, 4 user (dari data.php)
 *
 * Alur:
 *  1. Ambil metadata semua buku
 *  2. Hitung TF-IDF setiap buku
 *  3. Hitung Cosine Similarity setiap pasang buku
 *  4. Tampilkan top-N rekomendasi
 *
 * Jalankan: php cbf_simulation.php
 */

require_once 'data.php';

echo "========================================================\n";
echo "  SIMULASI CONTENT-BASED FILTERING (CBF)\n";
echo "  Algoritma: TF-IDF + Cosine Similarity\n";
echo "========================================================\n\n";

// ============================================================
// LANGKAH 1: Bangun dokumen teks dari metadata setiap buku
// ============================================================
echo "--- LANGKAH 1: Dokumen Teks per Buku ---\n";

$documents = [];
foreach ($books as $id => $book) {
    // Gabungkan semua metadata jadi satu string teks
    $text = implode(' ', [
        $book['title'],
        $book['subject'],
        $book['author'],
    ]);
    $documents[$id] = strtolower($text);
    echo "Buku [{$id}] {$book['title']}\n";
    echo "  Teks: {$documents[$id]}\n\n";
}

// ============================================================
// LANGKAH 2: Tokenisasi — pecah teks jadi kata-kata
// ============================================================
echo "\n--- LANGKAH 2: Tokenisasi ---\n";

$tokenized = [];
foreach ($documents as $id => $text) {
    // Pecah jadi kata, buang yang < 3 huruf
    $tokens = preg_split('/\s+/', trim($text));
    $tokens = array_filter($tokens, fn($t) => strlen($t) >= 3);
    $tokens = array_values($tokens);
    $tokenized[$id] = $tokens;
    echo "Buku [{$id}]: " . implode(', ', $tokens) . "\n";
}

// ============================================================
// LANGKAH 3: Hitung TF (Term Frequency) per buku
// TF(term, doc) = jumlah kemunculan term / total term di doc
// ============================================================
echo "\n--- LANGKAH 3: TF (Term Frequency) ---\n";

$tf = [];
foreach ($tokenized as $id => $tokens) {
    $total = count($tokens);
    $freq  = array_count_values($tokens);
    foreach ($freq as $term => $count) {
        $tf[$id][$term] = $count / $total;
    }
}

// Tampilkan TF buku pertama sebagai contoh
$contohId = 1;
echo "Contoh TF untuk Buku [{$contohId}] '{$books[$contohId]['title']}':\n";
arsort($tf[$contohId]);
foreach ($tf[$contohId] as $term => $score) {
    printf("  %-20s → TF = %.4f\n", $term, $score);
}

// ============================================================
// LANGKAH 4: Hitung IDF (Inverse Document Frequency)
// IDF(term) = log(jumlah_doc / jumlah_doc_mengandung_term)
// ============================================================
echo "\n--- LANGKAH 4: IDF (Inverse Document Frequency) ---\n";

$N   = count($tokenized);  // total jumlah dokumen (buku)
$idf = [];

// Kumpulkan semua term unik dari semua buku
$allTerms = [];
foreach ($tokenized as $tokens) {
    $allTerms = array_merge($allTerms, array_unique($tokens));
}
$allTerms = array_unique($allTerms);

// Hitung IDF tiap term
foreach ($allTerms as $term) {
    $df = 0; // document frequency
    foreach ($tokenized as $tokens) {
        if (in_array($term, $tokens)) {
            $df++;
        }
    }
    // Tambah 1 untuk menghindari log(0)
    $idf[$term] = log($N / ($df + 1)) + 1;
}

echo "Contoh IDF beberapa term:\n";
$contohTerms = ['python', 'machine', 'learning', 'pemrograman', 'komputer', 'algoritma'];
foreach ($contohTerms as $term) {
    if (isset($idf[$term])) {
        printf("  %-20s → IDF = %.4f\n", $term, $idf[$term]);
    }
}

// ============================================================
// LANGKAH 5: Hitung TF-IDF = TF × IDF
// ============================================================
echo "\n--- LANGKAH 5: Vektor TF-IDF per Buku ---\n";

$tfidf = [];
foreach ($tf as $id => $termTF) {
    foreach ($termTF as $term => $tfScore) {
        $tfidf[$id][$term] = $tfScore * ($idf[$term] ?? 1);
    }
}

echo "Contoh TF-IDF untuk Buku [1] '{$books[1]['title']}':\n";
arsort($tfidf[1]);
foreach (array_slice($tfidf[1], 0, 6, true) as $term => $score) {
    printf("  %-20s → TF-IDF = %.4f\n", $term, $score);
}

// ============================================================
// LANGKAH 6: Hitung Cosine Similarity antar semua pasang buku
//
//                  A · B
// Cosine(A,B) = ─────────────
//               ||A|| × ||B||
// ============================================================
echo "\n--- LANGKAH 6: Cosine Similarity antar Buku ---\n\n";

function cosineSimilarity(array $vecA, array $vecB): float
{
    $dot   = 0.0;
    $normA = 0.0;
    $normB = 0.0;

    foreach ($vecA as $term => $w) {
        $dot   += $w * ($vecB[$term] ?? 0);
        $normA += $w * $w;
    }
    foreach ($vecB as $w) {
        $normB += $w * $w;
    }

    $denom = sqrt($normA) * sqrt($normB);
    return $denom > 0 ? round($dot / $denom, 4) : 0.0;
}

$similarityMatrix = [];
$bookIds = array_keys($books);

foreach ($bookIds as $i => $idA) {
    foreach ($bookIds as $j => $idB) {
        if ($idA === $idB) {
            $similarityMatrix[$idA][$idB] = 1.0;
            continue;
        }
        $sim = cosineSimilarity($tfidf[$idA], $tfidf[$idB]);
        $similarityMatrix[$idA][$idB] = $sim;
    }
}

// Tampilkan matriks similarity (sebagian)
echo "Matriks Cosine Similarity (10×10):\n\n";
echo str_pad("", 6);
foreach ($bookIds as $id) {
    echo str_pad("B{$id}", 7);
}
echo "\n";
echo str_repeat("-", 6 + 7 * count($bookIds)) . "\n";

foreach ($bookIds as $idA) {
    echo str_pad("B{$idA}", 6);
    foreach ($bookIds as $idB) {
        echo str_pad(number_format($similarityMatrix[$idA][$idB], 3), 7);
    }
    echo "\n";
}

// ============================================================
// LANGKAH 7: Tampilkan Rekomendasi CBF per Buku
// ============================================================
echo "\n--- LANGKAH 7: Top-3 Rekomendasi CBF per Buku ---\n\n";

foreach ($bookIds as $targetId) {
    $scores = $similarityMatrix[$targetId];
    arsort($scores);

    echo "Buku Target [{$targetId}]: \"{$books[$targetId]['title']}\"\n";
    echo "  Rekomendasi:\n";

    $rank = 1;
    foreach ($scores as $candidateId => $sim) {
        if ($candidateId === $targetId) continue;
        if ($rank > 3) break;
        printf("    %d. [Buku %2d] %-40s (similarity: %.4f)\n",
            $rank,
            $candidateId,
            '"' . $books[$candidateId]['title'] . '"',
            $sim
        );
        $rank++;
    }
    echo "\n";
}

// ============================================================
// LANGKAH 8: Rekomendasi CBF untuk User tertentu
// (berdasarkan buku terakhir yang dipinjam)
// ============================================================
echo "--- LANGKAH 8: Rekomendasi CBF untuk Setiap User ---\n\n";

foreach ($users as $userId => $user) {
    $borrowed = $loanHistory[$userId] ?? [];

    if (empty($borrowed)) {
        echo "User [{$userId}] {$user['name']}: belum ada pinjaman\n\n";
        continue;
    }

    // Ambil buku terakhir dipinjam
    $lastBook = end($borrowed);

    // Cari buku serupa dari buku terakhir, exclude yang sudah dipinjam
    $scores = $similarityMatrix[$lastBook];
    arsort($scores);

    echo "User [{$userId}] {$user['name']} ({$user['jurusan']})\n";
    echo "  Riwayat pinjam: " . implode(', ', array_map(
        fn($id) => "[{$id}] {$books[$id]['title']}",
        $borrowed
    )) . "\n";
    echo "  Berdasarkan buku terakhir: [{$lastBook}] \"{$books[$lastBook]['title']}\"\n";
    echo "  Rekomendasi CBF:\n";

    $rank = 1;
    foreach ($scores as $candidateId => $sim) {
        if (in_array($candidateId, $borrowed)) continue; // sudah dipinjam
        if ($rank > 3) break;
        printf("    %d. [Buku %2d] %-40s (similarity: %.4f)\n",
            $rank,
            $candidateId,
            '"' . $books[$candidateId]['title'] . '"',
            $sim
        );
        $rank++;
    }
    echo "\n";
}

echo "========================================================\n";
echo "  SELESAI — CBF Simulation\n";
echo "========================================================\n";
