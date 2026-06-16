<?php
/**
 * STEP 2 — MINING COLLABORATIVE FILTERING (CF)
 * ─────────────────────────────────────────────────────────
 * Algoritma : Item-Based CF + Jaccard Similarity
 * Input     : tabel loan + item (join 3 tabel)
 * Output    : tabel rec_cf_similarity
 *
 * Cara jalankan:
 *   Browser : http://localhost/code_slims/2_mine_cf.php
 *   Terminal: php 2_mine_cf.php
 * ─────────────────────────────────────────────────────────
 * Jalankan SETELAH 1_mine_cbf.php, SEBELUM rekomendasi.php
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/config.php';
$pdo = getSlimsConnection();

$isBrowser = isset($_SERVER['HTTP_HOST']);
$t0 = microtime(true);

function pr(string $msg, bool $isBrowser): void {
    echo $isBrowser ? nl2br(htmlspecialchars($msg)) . "\n" : $msg;
    ob_flush(); flush();
}

if ($isBrowser) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
        body { font-family: monospace; background:#1e1e1e; color:#d4d4d4; padding:20px; }
        .ok  { color:#4ec9b0; } .warn { color:#dcdcaa; } .err { color:#f44747; }
        h2   { color:#9cdcfe; border-bottom:1px solid #333; padding-bottom:6px; }
        pre  { margin:0; }
    </style>
    </head><body><h2>⚙️ Mining CF — Item-Based + Jaccard Similarity</h2><pre>';
}

// ─────────────────────────────────────────────────────────
// STEP 1 — Cek data pinjaman
// ─────────────────────────────────────────────────────────
$total = (int) $pdo->query("SELECT COUNT(*) FROM loan WHERE is_return = 1")->fetchColumn();
pr("📋 Transaksi pinjaman (sudah kembali): {$total}\n", $isBrowser);

if ($total < 5) {
    pr("⚠️  Data terlalu sedikit (< 5). CF tidak akan menghasilkan similarity.\n", $isBrowser);
    pr("   Pastikan tabel loan dan item terisi di SLIMS.\n", $isBrowser);
    if ($isBrowser) echo '</pre></body></html>';
    exit;
}

// ─────────────────────────────────────────────────────────
// STEP 2 — Bangun borrowers: biblio_id → [member_id, ...]
// JOIN: loan → item (BUKAN biblio_item!) → biblio
// member_id adalah VARCHAR(20) di SLIMS asli
// ─────────────────────────────────────────────────────────
pr("🔄 Membaca riwayat pinjaman (loan → item → biblio)...\n", $isBrowser);

$rows = $pdo->query("
    SELECT l.member_id, i.biblio_id
    FROM loan l
    JOIN item i ON l.item_code = i.item_code
    WHERE l.is_return = 1 AND i.biblio_id IS NOT NULL
")->fetchAll();

$borrowers = [];  // borrowers[biblio_id] = [member_id, ...]

foreach ($rows as $r) {
    $bid = (int) $r['biblio_id'];
    $mid = $r['member_id'];
    if (!in_array($mid, $borrowers[$bid] ?? [])) {
        $borrowers[$bid][] = $mid;
    }
}

$totalBooks   = count($borrowers);
$totalMembers = count(array_unique(array_column($rows, 'member_id')));
pr("✅ Buku dengan riwayat pinjam : {$totalBooks}\n", $isBrowser);
pr("✅ Member yang pernah meminjam: {$totalMembers}\n\n", $isBrowser);

// ─────────────────────────────────────────────────────────
// STEP 3 — Jaccard Similarity + Simpan ke DB
// Jaccard(A,B) = |users(A) ∩ users(B)| / |users(A) ∪ users(B)|
// ─────────────────────────────────────────────────────────
function jaccardSim(array $setA, array $setB): float {
    $inter = count(array_intersect($setA, $setB));
    $union = count(array_unique(array_merge($setA, $setB)));
    return $union > 0 ? round($inter / $union, 6) : 0.0;
}

$pdo->exec("TRUNCATE TABLE rec_cf_similarity");

$stmt = $pdo->prepare(
    "INSERT IGNORE INTO rec_cf_similarity (biblio_id_a, biblio_id_b, similarity, computed_at)
     VALUES (?, ?, ?, NOW())"
);

$bookIds   = array_keys($borrowers);
$total     = count($bookIds);
$threshold = 0.05;
$saved     = 0;
$skipped   = 0;

pr("⏳ Mining CF: {$total} buku...\n", $isBrowser);

foreach ($bookIds as $idx => $idA) {
    $usersA = $borrowers[$idA];
    if (count($usersA) < 2) { $skipped++; continue; }

    foreach ($bookIds as $idB) {
        if ($idA >= $idB) continue;

        $usersB = $borrowers[$idB];
        if (count($usersB) < 2) continue;

        $sim = jaccardSim($usersA, $usersB);
        if ($sim >= $threshold) {
            $stmt->execute([$idA, $idB, $sim]);
            $saved++;
        }
    }
    if ($isBrowser && $idx % 50 === 0) {
        ob_flush(); flush();
    }
}

$elapsed = round(microtime(true) - $t0, 2);
pr("\n✅ Mining CF selesai!\n", $isBrowser);
pr("   Pasang tersimpan  : {$saved} (Jaccard ≥ {$threshold})\n", $isBrowser);
pr("   Buku dilewati     : {$skipped} (hanya 1 peminjam)\n", $isBrowser);
pr("   Waktu             : {$elapsed} detik\n\n", $isBrowser);
pr("👉 Sekarang buka: rekomendasi.php\n", $isBrowser);

if ($isBrowser) echo '</pre></body></html>';
