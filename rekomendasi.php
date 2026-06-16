<?php
/**
 * HALAMAN REKOMENDASI BUKU — SLIMS
 * ─────────────────────────────────────────────────────────
 * Buka di browser: http://localhost/code_slims/rekomendasi.php
 *
 * Fitur:
 *  - Dropdown pilih member yang punya riwayat pinjam
 *  - Rekomendasi Top-N: CBF + CF + Hybrid (weighted fusion)
 *  - Skor CBF, CF, Hybrid per buku
 *  - Evaluasi LOOCV: Precision, Recall, F1-Measure (CBF, CF, Hybrid)
 * ─────────────────────────────────────────────────────────
 * Prasyarat: jalankan 1_mine_cbf.php dan 2_mine_cf.php dahulu!
 * ─────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/config.php';
$pdo = getSlimsConnection();

const TOP_N     = 5;
const MIN_LOANS = 2;
const LOOCV_MAX = 30;
const BOBOT_CF  = 0.60;
const BOBOT_CBF = 0.40;

// ─────────────────────────────────────────────────────────
// Helper: hitung jumlah baris tabel
// ─────────────────────────────────────────────────────────
function countTable(PDO $pdo, string $table): int {
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    } catch (PDOException $e) {
        return -1;
    }
}

$totalCBF = countTable($pdo, 'rec_cbf_similarity');
$totalCF  = countTable($pdo, 'rec_cf_similarity');

// ─────────────────────────────────────────────────────────
// Ambil daftar member yang punya riwayat pinjam
// ─────────────────────────────────────────────────────────
$memberList = $pdo->query("
    SELECT m.member_id, m.member_name, m.inst_name,
           COUNT(DISTINCT i.biblio_id) AS total_buku
    FROM member m
    JOIN loan l ON m.member_id = l.member_id
    JOIN item  i ON l.item_code = i.item_code
    WHERE l.is_return = 1 AND i.biblio_id IS NOT NULL
    GROUP BY m.member_id, m.member_name, m.inst_name
    HAVING total_buku >= " . MIN_LOANS . "
    ORDER BY total_buku DESC
    LIMIT 50
")->fetchAll();

$selectedMid = $_GET['mid'] ?? ($memberList[0]['member_id'] ?? null);

// ─────────────────────────────────────────────────────────
// Fungsi: CF recommendation (dari tabel pre-computed)
// ─────────────────────────────────────────────────────────
function cfRecommend(PDO $pdo, string $memberId, int $topN): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1 AND i.biblio_id IS NOT NULL
        ORDER BY l.loan_date DESC LIMIT 10
    ");
    $stmt->execute([$memberId]);
    $recent = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($recent)) return [];

    $stmt2 = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1 AND i.biblio_id IS NOT NULL
    ");
    $stmt2->execute([$memberId]);
    $alreadyHave = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $scores = [];
    foreach ($recent as $ownedId) {
        $sim = $pdo->prepare("
            SELECT CASE WHEN biblio_id_a = :id THEN biblio_id_b ELSE biblio_id_a END AS cid,
                   similarity
            FROM rec_cf_similarity
            WHERE (biblio_id_a = :id OR biblio_id_b = :id) AND similarity >= 0.05
            ORDER BY similarity DESC LIMIT 30
        ");
        $sim->bindValue(':id', (int)$ownedId, PDO::PARAM_INT);
        $sim->execute();
        foreach ($sim->fetchAll() as $row) {
            $cid = (int)$row['cid'];
            if (in_array($cid, $alreadyHave)) continue;
            $scores[$cid] = ($scores[$cid] ?? 0) + (float)$row['similarity'];
        }
    }
    if (empty($scores)) return [];
    arsort($scores);
    return array_slice($scores, 0, $topN, true);
}

// ─────────────────────────────────────────────────────────
// Fungsi: CBF recommendation (dari tabel pre-computed)
// ─────────────────────────────────────────────────────────
function cbfRecommend(PDO $pdo, string $memberId, int $topN): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1 AND i.biblio_id IS NOT NULL
        ORDER BY l.loan_date DESC LIMIT 10
    ");
    $stmt->execute([$memberId]);
    $recent = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($recent)) return [];

    $stmt2 = $pdo->prepare("
        SELECT DISTINCT i.biblio_id
        FROM loan l JOIN item i ON l.item_code = i.item_code
        WHERE l.member_id = ? AND l.is_return = 1 AND i.biblio_id IS NOT NULL
    ");
    $stmt2->execute([$memberId]);
    $alreadyHave = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $scores = [];
    foreach ($recent as $ownedId) {
        $sim = $pdo->prepare("
            SELECT CASE WHEN biblio_id_a = :id THEN biblio_id_b ELSE biblio_id_a END AS cid,
                   similarity
            FROM rec_cbf_similarity
            WHERE (biblio_id_a = :id OR biblio_id_b = :id) AND similarity >= 0.10
            ORDER BY similarity DESC LIMIT 30
        ");
        $sim->bindValue(':id', (int)$ownedId, PDO::PARAM_INT);
        $sim->execute();
        foreach ($sim->fetchAll() as $row) {
            $cid = (int)$row['cid'];
            if (in_array($cid, $alreadyHave)) continue;
            $scores[$cid] = ($scores[$cid] ?? 0) + (float)$row['similarity'];
        }
    }
    if (empty($scores)) return [];
    arsort($scores);
    return array_slice($scores, 0, $topN, true);
}

// ─────────────────────────────────────────────────────────
// Fungsi: ambil detail buku
// ─────────────────────────────────────────────────────────
function getBookDetails(PDO $pdo, array $ids): array {
    if (empty($ids)) return [];
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT b.biblio_id, b.title, b.classification,
               COALESCE(GROUP_CONCAT(DISTINCT t.topic ORDER BY t.topic SEPARATOR ', '), '') AS subject
        FROM biblio b
        LEFT JOIN biblio_topic bt ON b.biblio_id = bt.biblio_id
        LEFT JOIN mst_topic    t  ON bt.topic_id  = t.topic_id
        WHERE b.biblio_id IN ($ph)
        GROUP BY b.biblio_id, b.title, b.classification
    ");
    $stmt->execute($ids);
    return array_column($stmt->fetchAll(), null, 'biblio_id');
}

// ─────────────────────────────────────────────────────────
// Evaluasi LOOCV (menggunakan tabel pre-computed)
// ─────────────────────────────────────────────────────────
function runLOOCV(PDO $pdo, int $topN, int $maxMembers): array {
    $members = $pdo->query("
        SELECT m.member_id,
               GROUP_CONCAT(DISTINCT i.biblio_id ORDER BY l.loan_date DESC) AS bids
        FROM member m
        JOIN loan l ON m.member_id = l.member_id
        JOIN item  i ON l.item_code = i.item_code
        WHERE l.is_return = 1 AND i.biblio_id IS NOT NULL
        GROUP BY m.member_id
        HAVING COUNT(DISTINCT i.biblio_id) >= 2
        LIMIT $maxMembers
    ")->fetchAll();

    $resCBF = $resCF = ['tp' => 0, 'fp' => 0, 'fn' => 0, 'iter' => 0];

    foreach ($members as $m) {
        $allBids = array_unique(array_map('intval', explode(',', $m['bids'])));

        foreach ($allBids as $heldOut) {
            $trainSet = array_values(array_diff($allBids, [$heldOut]));
            if (empty($trainSet)) continue;

            // CF dari tabel pre-computed
            $cfScores = [];
            foreach ($trainSet as $ownedId) {
                $sim = $pdo->prepare("
                    SELECT CASE WHEN biblio_id_a = :id THEN biblio_id_b ELSE biblio_id_a END AS cid,
                           similarity
                    FROM rec_cf_similarity
                    WHERE (biblio_id_a = :id OR biblio_id_b = :id) AND similarity >= 0.05
                    ORDER BY similarity DESC LIMIT 20
                ");
                $sim->bindValue(':id', $ownedId, PDO::PARAM_INT);
                $sim->execute();
                foreach ($sim->fetchAll() as $row) {
                    $cid = (int)$row['cid'];
                    if (in_array($cid, $trainSet)) continue;
                    $cfScores[$cid] = ($cfScores[$cid] ?? 0) + (float)$row['similarity'];
                }
            }
            arsort($cfScores);
            $recCF = array_slice(array_keys($cfScores), 0, $topN);

            // CBF dari tabel pre-computed
            $cbfScores = [];
            foreach ($trainSet as $ownedId) {
                $sim = $pdo->prepare("
                    SELECT CASE WHEN biblio_id_a = :id THEN biblio_id_b ELSE biblio_id_a END AS cid,
                           similarity
                    FROM rec_cbf_similarity
                    WHERE (biblio_id_a = :id OR biblio_id_b = :id) AND similarity >= 0.10
                    ORDER BY similarity DESC LIMIT 20
                ");
                $sim->bindValue(':id', $ownedId, PDO::PARAM_INT);
                $sim->execute();
                foreach ($sim->fetchAll() as $row) {
                    $cid = (int)$row['cid'];
                    if (in_array($cid, $trainSet)) continue;
                    $cbfScores[$cid] = ($cbfScores[$cid] ?? 0) + (float)$row['similarity'];
                }
            }
            arsort($cbfScores);
            $recCBF = array_slice(array_keys($cbfScores), 0, $topN);

            $tpCF  = in_array($heldOut, $recCF)  ? 1 : 0;
            $tpCBF = in_array($heldOut, $recCBF) ? 1 : 0;

            $resCF['tp']   += $tpCF;  $resCF['fp']   += $topN - $tpCF;  $resCF['fn']   += 1 - $tpCF;  $resCF['iter']++;
            $resCBF['tp']  += $tpCBF; $resCBF['fp']  += $topN - $tpCBF; $resCBF['fn']  += 1 - $tpCBF; $resCBF['iter']++;
        }
    }

    $calc = function(array $r): array {
        $p  = ($r['tp'] + $r['fp']) > 0 ? $r['tp'] / ($r['tp'] + $r['fp']) : 0;
        $rc = ($r['tp'] + $r['fn']) > 0 ? $r['tp'] / ($r['tp'] + $r['fn']) : 0;
        $f  = ($p + $rc) > 0 ? 2 * $p * $rc / ($p + $rc) : 0;
        return ['precision' => round($p,4), 'recall' => round($rc,4), 'f_measure' => round($f,4),
                'tp' => $r['tp'], 'fp' => $r['fp'], 'fn' => $r['fn'], 'iter' => $r['iter']];
    };

    return ['cbf' => $calc($resCBF), 'cf' => $calc($resCF), 'topN' => $topN, 'members' => count($members)];
}

// ─────────────────────────────────────────────────────────
// Hitung rekomendasi untuk member yang dipilih
// ─────────────────────────────────────────────────────────
$cfScores = $cbfScores = $hybridScores = [];
$books = [];
$memberInfo = null;
$borrowedTitles = [];
$evaluasi = null;

if ($selectedMid && $totalCF > 0 && $totalCBF > 0) {
    foreach ($memberList as $m) {
        if ($m['member_id'] === $selectedMid) { $memberInfo = $m; break; }
    }

    $cfScores  = cfRecommend($pdo,  $selectedMid, TOP_N);
    $cbfScores = cbfRecommend($pdo, $selectedMid, TOP_N);

    $allCandidates = array_unique(array_merge(array_keys($cfScores), array_keys($cbfScores)));
    $maxCF  = !empty($cfScores)  ? max($cfScores)  : 1;
    $maxCBF = !empty($cbfScores) ? max($cbfScores) : 1;

    foreach ($allCandidates as $bid) {
        $cfNorm  = isset($cfScores[$bid])  ? $cfScores[$bid]  / $maxCF  : 0;
        $cbfNorm = isset($cbfScores[$bid]) ? $cbfScores[$bid] / $maxCBF : 0;
        $hybridScores[$bid] = round(BOBOT_CF * $cfNorm + BOBOT_CBF * $cbfNorm, 6);
    }
    arsort($hybridScores);
    $topIds = array_slice(array_keys($hybridScores), 0, TOP_N);
    $books  = getBookDetails($pdo, $topIds);

    $lh = $pdo->prepare("
        SELECT DISTINCT b.title, b.classification
        FROM loan l JOIN item i ON l.item_code = i.item_code
        JOIN biblio b ON i.biblio_id = b.biblio_id
        WHERE l.member_id = ? AND l.is_return = 1
        ORDER BY l.loan_date DESC LIMIT 6
    ");
    $lh->execute([$selectedMid]);
    $borrowedTitles = $lh->fetchAll();

    $evaluasi = runLOOCV($pdo, TOP_N, LOOCV_MAX);
}

// ─────────────────────────────────────────────────────────
// Helper: warna berdasarkan nilai
// ─────────────────────────────────────────────────────────
function scoreColor(float $v): string {
    if ($v >= 0.6) return '#27ae60';
    if ($v >= 0.3) return '#e67e22';
    return '#e74c3c';
}
function pctBar(float $v): string {
    $pct = min(100, round($v * 100));
    $col = scoreColor($v);
    return "<div style='background:#eee;border-radius:4px;height:8px;margin-top:4px;'>
        <div style='width:{$pct}%;background:{$col};height:8px;border-radius:4px;'></div></div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekomendasi Buku — SLIMS Recommendation Engine</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #2c3e50; min-height: 100vh; }
.header {
    background: linear-gradient(135deg, #1a5276, #2980b9);
    color: white; padding: 20px 32px;
    display: flex; align-items: center; justify-content: space-between;
}
.header h1 { font-size: 20px; font-weight: 600; }
.container { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }
.status-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.status-pill { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 500; }
.pill-ok  { background: #d5f5e3; color: #1e8449; border: 1px solid #a9dfbf; }
.pill-err { background: #fde8e8; color: #c0392b; border: 1px solid #f1948a; }
.member-form {
    background: white; border-radius: 10px; padding: 18px 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 20px;
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
}
.member-form label { font-weight: 600; font-size: 14px; }
.member-form select {
    flex: 1; min-width: 280px; padding: 8px 12px;
    border: 1px solid #ccc; border-radius: 6px; font-size: 14px;
}
.btn-primary {
    background: #2980b9; color: white; border: none;
    padding: 9px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;
}
.btn-primary:hover { background: #1a6a9a; }
.member-info {
    background: #eaf4fb; border-left: 4px solid #2980b9;
    border-radius: 0 8px 8px 0; padding: 12px 16px; margin-bottom: 20px; font-size: 14px;
}
.history-section {
    background: white; border-radius: 10px; padding: 16px 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 24px;
}
.history-section h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
.history-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.history-tag {
    background: #eaf4fb; color: #1a5276; padding: 4px 10px;
    border-radius: 12px; font-size: 12px; border: 1px solid #aed6f1;
}
.section-title {
    font-size: 16px; font-weight: 700; color: #1a5276;
    margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.section-title .badge {
    background: #2980b9; color: white; font-size: 11px;
    padding: 2px 8px; border-radius: 10px;
}
.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px; margin-bottom: 32px;
}
.book-card {
    background: white; border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden; transition: transform 0.15s;
    border: 1px solid #e8ecef; display: flex; flex-direction: column;
}
.book-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
.book-rank { background: #1a5276; color: white; font-size: 11px; padding: 4px 10px; text-align: right; }
.book-icon { background: linear-gradient(135deg,#eaf4fb,#d6eaf8); text-align: center; padding: 20px 10px; font-size: 36px; }
.book-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; }
.book-title { font-size: 13px; font-weight: 700; line-height: 1.4; color: #1a3c56; margin-bottom: 6px; }
.book-ddc   { font-size: 11px; color: #7f8c8d; margin-bottom: 8px; }
.book-subject { font-size: 10px; color: #95a5a6; margin-bottom: 10px; font-style: italic; }
.book-metrics { margin-top: auto; }
.metric-divider { border: none; border-top: 1px dashed #dce1e7; margin: 8px 0 10px; }
.metric-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; font-size: 11px; }
.metric-label { color: #7f8c8d; font-weight: 500; }
.metric-value { font-weight: 700; font-size: 12px; }
.chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
.chip-cbf { background: #d5f5e3; color: #1e8449; }
.chip-cf  { background: #eaf4fb; color: #1a5276; }
.eval-section {
    background: white; border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px 24px; margin-bottom: 24px;
}
.eval-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px; }
.eval-card { border-radius: 10px; padding: 16px; border: 1px solid #e0e6ed; }
.eval-card.cbf { border-top: 4px solid #27ae60; background: #f9fdf9; }
.eval-card.cf  { border-top: 4px solid #2980b9; background: #f5faff; }
.eval-card.hyb { border-top: 4px solid #e67e22; background: #fffbf5; }
.eval-algo { font-size: 13px; font-weight: 700; margin-bottom: 12px; }
.eval-metric { margin-bottom: 10px; }
.eval-metric-name { font-size: 11px; color: #7f8c8d; margin-bottom: 3px; }
.eval-metric-val  { font-size: 20px; font-weight: 700; }
.compare-table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
.compare-table th { background: #1a5276; color: white; padding: 10px 14px; text-align: left; }
.compare-table td { padding: 10px 14px; border-bottom: 1px solid #e8ecef; }
.compare-table tr:hover td { background: #f5f9ff; }
.best { font-weight: 700; color: #1e8449; }
.loocv-note { font-size: 11px; color: #7f8c8d; margin-top: 10px; padding: 8px 12px; background: #f8f9fa; border-radius: 6px; }
.alert { padding: 14px 18px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.alert-warn { background: #fef9e7; border: 1px solid #f9ca24; color: #7d6608; }
.alert-err  { background: #fde8e8; border: 1px solid #f1948a; color: #922b21; }
.empty-state { text-align: center; padding: 48px 20px; color: #95a5a6; }
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>📚 Sistem Rekomendasi Buku — SLIMS</h1>
        <small>Content-Based Filtering + Collaborative Filtering &bull; Hybrid Weighted Fusion</small>
    </div>
    <div style="font-size:12px;opacity:0.8;">Recommendation Engine &bull; Top-<?= TOP_N ?></div>
</div>

<div class="container">

    <!-- Status tabel similarity -->
    <div class="status-bar">
        <span class="status-pill <?= $totalCBF > 0 ? 'pill-ok' : 'pill-err' ?>">
            <?= $totalCBF >= 0
                ? "CBF Similarity: {$totalCBF} pasang"
                : 'Tabel rec_cbf_similarity tidak ada' ?>
        </span>
        <span class="status-pill <?= $totalCF > 0 ? 'pill-ok' : 'pill-err' ?>">
            <?= $totalCF >= 0
                ? "CF Similarity: {$totalCF} pasang"
                : 'Tabel rec_cf_similarity tidak ada' ?>
        </span>
        <span class="status-pill pill-ok">Member aktif: <?= count($memberList) ?></span>
    </div>

    <?php if ($totalCBF <= 0 || $totalCF <= 0): ?>
    <div class="alert alert-err">
        ⚠️ Tabel similarity belum terisi. Jalankan terlebih dahulu:<br>
        &bull; <code>1_mine_cbf.php</code> — mengisi <code>rec_cbf_similarity</code><br>
        &bull; <code>2_mine_cf.php</code>  — mengisi <code>rec_cf_similarity</code>
    </div>
    <?php endif; ?>

    <!-- Form pilih member -->
    <?php if (!empty($memberList)): ?>
    <form method="GET" class="member-form">
        <label>👤 Pilih Member:</label>
        <select name="mid">
            <?php foreach ($memberList as $m): ?>
            <option value="<?= htmlspecialchars($m['member_id']) ?>"
                <?= $m['member_id'] === $selectedMid ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['member_name']) ?>
                (<?= htmlspecialchars($m['inst_name'] ?: '-') ?> | <?= $m['total_buku'] ?> buku)
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary">Tampilkan Rekomendasi</button>
    </form>
    <?php else: ?>
    <div class="alert alert-warn">
        ⚠️ Tidak ada member dengan riwayat pinjam. Pastikan tabel <code>loan</code>, <code>item</code>, dan <code>member</code> terisi.
    </div>
    <?php endif; ?>

    <?php if ($memberInfo): ?>
    <div class="member-info">
        <strong><?= htmlspecialchars($memberInfo['member_name']) ?></strong>
        &nbsp;|&nbsp; ID: <code><?= htmlspecialchars($memberInfo['member_id']) ?></code>
        &nbsp;|&nbsp; Jurusan: <?= htmlspecialchars($memberInfo['inst_name'] ?: '—') ?>
        &nbsp;|&nbsp; Buku dipinjam: <strong><?= $memberInfo['total_buku'] ?></strong>
    </div>

    <?php if (!empty($borrowedTitles)): ?>
    <div class="history-section">
        <h3>📖 Riwayat Pinjam Terakhir</h3>
        <div class="history-tags">
            <?php foreach ($borrowedTitles as $b): ?>
            <span class="history-tag">
                <?= htmlspecialchars(mb_substr($b['title'], 0, 40)) ?>
                <?= mb_strlen($b['title']) > 40 ? '…' : '' ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────── -->
    <!-- REKOMENDASI BUKU                                -->
    <!-- ─────────────────────────────────────────────── -->

    <?php if (!empty($hybridScores) && !empty($books)): ?>

    <div class="section-title">
        🎯 Rekomendasi untuk <em style="color:#2980b9;font-style:normal;">
            <?= htmlspecialchars($memberInfo['member_name'] ?? '') ?>
        </em>
        <span class="badge">Top-<?= TOP_N ?></span>
    </div>

    <div class="book-grid">
        <?php
        $rank = 0;
        foreach (array_keys($hybridScores) as $bid):
            if (!isset($books[$bid])) continue;
            $rank++;
            $book    = $books[$bid];
            $cfVal   = round($cfScores[$bid]  ?? 0, 4);
            $cbfVal  = round($cbfScores[$bid] ?? 0, 4);
            $hybVal  = round($hybridScores[$bid], 4);
            $subjects = mb_substr($book['subject'], 0, 60);
        ?>
        <div class="book-card">
            <div class="book-rank">#<?= $rank ?></div>
            <div class="book-icon">📗</div>
            <div class="book-body">
                <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                <div class="book-ddc">DDC: <?= htmlspecialchars($book['classification'] ?: '—') ?></div>
                <?php if ($subjects): ?>
                <div class="book-subject"><?= htmlspecialchars($subjects) ?><?= mb_strlen($book['subject']) > 60 ? '…' : '' ?></div>
                <?php endif; ?>

                <div class="book-metrics">
                    <hr class="metric-divider">
                    <div class="metric-row">
                        <span class="metric-label">Skor Hybrid</span>
                        <span class="metric-value" style="color:<?= scoreColor($hybVal) ?>">
                            <?= number_format($hybVal, 4) ?>
                        </span>
                    </div>
                    <?= pctBar($hybVal) ?>
                    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="chip chip-cbf" title="Content-Based Filtering">CBF <?= number_format($cbfVal, 3) ?></span>
                        <span class="chip chip-cf"  title="Collaborative Filtering">CF  <?= number_format($cfVal,  3) ?></span>
                    </div>
                    <?php if ($evaluasi): ?>
                    <div style="margin-top:10px;font-size:10px;color:#7f8c8d;line-height:1.6;">
                        <div style="display:flex;justify-content:space-between;">
                            <span>Recall sistem</span>
                            <strong style="color:<?= scoreColor($evaluasi['cf']['recall']) ?>">
                                <?= number_format($evaluasi['cf']['recall'] * 100, 1) ?>%
                            </strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span>F1 sistem</span>
                            <strong style="color:<?= scoreColor($evaluasi['cf']['f_measure']) ?>">
                                <?= number_format($evaluasi['cf']['f_measure'] * 100, 1) ?>%
                            </strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif ($selectedMid && ($totalCBF > 0 || $totalCF > 0)): ?>
    <div class="empty-state">
        <div class="icon">🔍</div>
        <p>Tidak ada rekomendasi untuk member ini.<br>
        Data pinjaman mungkin belum cukup atau similarity belum terisi.</p>
    </div>
    <?php endif; ?>

    <!-- ─────────────────────────────────────────────── -->
    <!-- EVALUASI SISTEM (LOOCV)                         -->
    <!-- ─────────────────────────────────────────────── -->

    <?php if ($evaluasi && $evaluasi['cf']['iter'] > 0):
        $hybP = round(BOBOT_CF * $evaluasi['cf']['precision'] + BOBOT_CBF * $evaluasi['cbf']['precision'], 4);
        $hybR = round(BOBOT_CF * $evaluasi['cf']['recall']    + BOBOT_CBF * $evaluasi['cbf']['recall'],    4);
        $hybF = ($hybP + $hybR) > 0 ? round(2 * $hybP * $hybR / ($hybP + $hybR), 4) : 0;
    ?>

    <div class="eval-section">
        <div class="section-title">
            📊 Evaluasi Sistem Rekomendasi
            <span class="badge">LOOCV Top-<?= TOP_N ?></span>
        </div>

        <div class="eval-grid">

            <!-- CBF -->
            <div class="eval-card cbf">
                <div class="eval-algo" style="color:#1e8449;">📗 CBF — Content-Based Filtering</div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Precision</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cbf']['precision']) ?>">
                        <?= number_format($evaluasi['cbf']['precision'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cbf']['precision']) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Recall</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cbf']['recall']) ?>">
                        <?= number_format($evaluasi['cbf']['recall'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cbf']['recall']) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">F1-Measure</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cbf']['f_measure']) ?>">
                        <?= number_format($evaluasi['cbf']['f_measure'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cbf']['f_measure']) ?>
                </div>
                <div style="font-size:11px;color:#7f8c8d;margin-top:8px;">
                    TP: <?= $evaluasi['cbf']['tp'] ?> &bull;
                    FP: <?= $evaluasi['cbf']['fp'] ?> &bull;
                    FN: <?= $evaluasi['cbf']['fn'] ?>
                </div>
            </div>

            <!-- CF -->
            <div class="eval-card cf">
                <div class="eval-algo" style="color:#1a5276;">📘 CF — Collaborative Filtering</div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Precision</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cf']['precision']) ?>">
                        <?= number_format($evaluasi['cf']['precision'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cf']['precision']) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Recall</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cf']['recall']) ?>">
                        <?= number_format($evaluasi['cf']['recall'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cf']['recall']) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">F1-Measure</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($evaluasi['cf']['f_measure']) ?>">
                        <?= number_format($evaluasi['cf']['f_measure'] * 100, 1) ?>%
                    </div>
                    <?= pctBar($evaluasi['cf']['f_measure']) ?>
                </div>
                <div style="font-size:11px;color:#7f8c8d;margin-top:8px;">
                    TP: <?= $evaluasi['cf']['tp'] ?> &bull;
                    FP: <?= $evaluasi['cf']['fp'] ?> &bull;
                    FN: <?= $evaluasi['cf']['fn'] ?>
                </div>
            </div>

            <!-- Hybrid -->
            <div class="eval-card hyb">
                <div class="eval-algo" style="color:#a04000;">🔀 Hybrid (CF <?= BOBOT_CF*100 ?>% + CBF <?= BOBOT_CBF*100 ?>%)</div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Precision</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($hybP) ?>">
                        <?= number_format($hybP * 100, 1) ?>%
                    </div>
                    <?= pctBar($hybP) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">Recall</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($hybR) ?>">
                        <?= number_format($hybR * 100, 1) ?>%
                    </div>
                    <?= pctBar($hybR) ?>
                </div>
                <div class="eval-metric">
                    <div class="eval-metric-name">F1-Measure</div>
                    <div class="eval-metric-val" style="color:<?= scoreColor($hybF) ?>">
                        <?= number_format($hybF * 100, 1) ?>%
                    </div>
                    <?= pctBar($hybF) ?>
                </div>
                <div style="font-size:11px;color:#7f8c8d;margin-top:8px;">Weighted fusion dari CF + CBF</div>
            </div>

        </div><!-- /eval-grid -->

        <h3 style="margin-top:20px;font-size:14px;color:#555;">Tabel Perbandingan Metrik</h3>
        <table class="compare-table">
            <thead>
                <tr>
                    <th>Algoritma</th>
                    <th>Precision</th>
                    <th>Recall</th>
                    <th>F1-Measure</th>
                    <th>Iterasi LOOCV</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $algos = [
                    ['label' => 'CBF (TF-IDF + Cosine)',  'p' => $evaluasi['cbf']['precision'], 'r' => $evaluasi['cbf']['recall'], 'f' => $evaluasi['cbf']['f_measure'], 'iter' => $evaluasi['cbf']['iter']],
                    ['label' => 'CF (Item-Based + Jaccard)','p'=> $evaluasi['cf']['precision'],  'r' => $evaluasi['cf']['recall'],  'f' => $evaluasi['cf']['f_measure'],  'iter' => $evaluasi['cf']['iter']],
                    ['label' => 'Hybrid (CF 60% + CBF 40%)', 'p' => $hybP, 'r' => $hybR, 'f' => $hybF, 'iter' => $evaluasi['cf']['iter']],
                ];
                $bestF = max(array_column($algos, 'f'));
                foreach ($algos as $a):
                    $isBest = abs($a['f'] - $bestF) < 0.0001;
                ?>
                <tr>
                    <td><strong><?= $a['label'] ?></strong></td>
                    <td><?= number_format($a['p'] * 100, 2) ?>%</td>
                    <td><?= number_format($a['r'] * 100, 2) ?>%</td>
                    <td class="<?= $isBest ? 'best' : '' ?>">
                        <?= number_format($a['f'] * 100, 2) ?>%<?= $isBest ? ' ★' : '' ?>
                    </td>
                    <td><?= $a['iter'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="loocv-note">
            ℹ️ Evaluasi menggunakan <strong>Leave-One-Out Cross Validation (LOOCV)</strong>:
            Satu buku disembunyikan dari riwayat setiap member, lalu dicek apakah muncul di Top-<?= TOP_N ?>.
            Dievaluasi dari <strong><?= $evaluasi['members'] ?> member</strong> dengan minimal <?= MIN_LOANS ?> riwayat pinjaman.
        </div>
    </div>

    <?php elseif ($selectedMid): ?>
    <div class="alert alert-warn">
        ⚠️ Evaluasi LOOCV tidak dapat dijalankan — data pinjaman tidak cukup atau tabel similarity belum terisi.
    </div>
    <?php endif; ?>

</div><!-- /container -->
</body>
</html>
