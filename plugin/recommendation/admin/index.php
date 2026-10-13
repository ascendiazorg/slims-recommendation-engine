<?php
/**
 * Recommendation Engine - Admin Panel
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');

require SB . 'admin/default/session.inc.php';

$can_read = utility::havePrivilege('bibliography', 'r');
if (!$can_read) {
    die('<div class="errorBox">Anda tidak memiliki akses ke halaman ini.</div>');
}

// -----------------------------------------------------------------------
// Handle aksi mining via POST
// -----------------------------------------------------------------------
$miningResult = null;
if (isset($_POST['action']) && $_POST['action'] === 'run_mining') {
    require_once SB . 'plugins/recommendation/classes/CollaborativeFilter.php';
    require_once SB . 'plugins/recommendation/classes/ContentBasedFilter.php';

    $cf      = new CollaborativeFilter($dbs);
    $cfCount = $cf->runMining();

    $cbf      = new ContentBasedFilter($dbs);
    $cbfCount = $cbf->runMining();

    $dbs->query(sprintf(
        "INSERT INTO rec_mining_log (algorithm, status, records_created, duration_seconds)
         VALUES ('CF', 'done', %d, 0), ('CBF', 'done', %d, 0)",
        $cfCount, $cbfCount
    ));

    $miningResult = compact('cfCount', 'cbfCount');
}

// -----------------------------------------------------------------------
// Statistik
// -----------------------------------------------------------------------
$stats = [];

$r = $dbs->query("SELECT COUNT(*) AS n FROM rec_cf_similarity");
$stats['cf_rules'] = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;

$r = $dbs->query("SELECT COUNT(*) AS n FROM rec_cbf_similarity");
$stats['cbf_rules'] = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;

$r = $dbs->query("SELECT COUNT(DISTINCT member_id) AS n FROM loan_history WHERE is_return = '1'");
$stats['members'] = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;

$r = $dbs->query("SELECT COUNT(*) AS n FROM biblio WHERE opac_hide = 0");
$stats['books'] = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;

$r = $dbs->query("SELECT MAX(created_at) AS last FROM rec_mining_log WHERE status = 'done'");
$stats['last_mining'] = ($r && ($row = $r->fetch_assoc())) ? ($row['last'] ?? null) : null;

// Log mining (10 terakhir)
$logResult = $dbs->query(
    "SELECT algorithm, status, records_created, duration_seconds, created_at
     FROM rec_mining_log ORDER BY created_at DESC LIMIT 10"
);
$logs = [];
if ($logResult) while ($row = $logResult->fetch_assoc()) $logs[] = $row;

// Format durasi detik → "X menit Y detik"
function rec_format_duration(float $secs): string {
    $s = (int)$secs;
    if ($s < 60) return $s . ' detik';
    return floor($s / 60) . ' menit ' . ($s % 60) . ' detik';
}
?>

<div class="menuBox">
  <div class="menuBoxInner">
    <div class="per_title">
      <h2>Recommendation Engine</h2>
    </div>

    <?php if ($miningResult): ?>
    <div class="alert alert-success mt-2" style="font-size:13px">
      <b>&#10003; Data rekomendasi berhasil diperbarui.</b><br>
      Ditemukan <b><?= number_format($miningResult['cfCount']) ?></b> pasang buku serupa (CF)
      dan <b><?= number_format($miningResult['cbfCount']) ?></b> pasang buku serupa (CBF).
      Rekomendasi untuk anggota akan diperbarui secara otomatis.
    </div>
    <?php endif; ?>

    <!-- Tab Buttons -->
    <div class="mt-3" style="border-bottom:2px solid #2c5f8a">
      <button class="rec-tab-btn active" onclick="recShowTab('dashboard', this)">Dashboard</button>
      <button class="rec-tab-btn" onclick="recShowTab('mining', this)">Mining</button>
      <button class="rec-tab-btn" onclick="recShowTab('log', this)">Log Mining</button>
    </div>

    <style>
      .rec-tab-btn {
        background: none; border: none; padding: 8px 18px;
        font-size: 13px; cursor: pointer; color: #555;
        border-bottom: 3px solid transparent; margin-bottom: -2px;
      }
      .rec-tab-btn.active {
        color: #2c5f8a; font-weight: bold;
        border-bottom: 3px solid #2c5f8a;
      }
      .rec-tab-pane { display: none; padding: 16px 4px; }
      .rec-tab-pane.active { display: block; }
      .rec-stat-card {
        border-radius: 6px; padding: 16px; color: #fff;
        text-align: center; margin-bottom: 12px;
      }
      .rec-stat-val { font-size: 28px; font-weight: bold; }
      .rec-stat-lbl { font-size: 12px; margin-top: 4px; opacity: 0.95; }
      .rec-stat-sub { font-size: 10px; margin-top: 3px; opacity: 0.75; }
    </style>

    <!-- TAB: DASHBOARD -->
    <div id="rec-tab-dashboard" class="rec-tab-pane active">
      <div class="row">
        <?php
        $cards = [
            [
                'label' => 'Pasang Buku Serupa (CF)',
                'sub'   => 'berdasarkan kesamaan peminjam',
                'val'   => number_format($stats['cf_rules']),
                'bg'    => '#2c5f8a',
            ],
            [
                'label' => 'Pasang Buku Serupa (CBF)',
                'sub'   => 'berdasarkan kesamaan konten',
                'val'   => number_format($stats['cbf_rules']),
                'bg'    => '#17a2b8',
            ],
            [
                'label' => 'Anggota Aktif',
                'sub'   => 'pernah meminjam buku',
                'val'   => $stats['members'],
                'bg'    => '#28a745',
            ],
            [
                'label' => 'Total Buku di OPAC',
                'sub'   => 'dapat direkomendasikan',
                'val'   => number_format($stats['books']),
                'bg'    => '#6c757d',
            ],
        ];
        foreach ($cards as $c): ?>
        <div class="col-md-3 col-sm-6">
          <div class="rec-stat-card" style="background:<?= $c['bg'] ?>">
            <div class="rec-stat-val"><?= $c['val'] ?></div>
            <div class="rec-stat-lbl"><?= $c['label'] ?></div>
            <div class="rec-stat-sub"><?= $c['sub'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="font-size:13px;color:#555;margin-top:8px">
        <b>Terakhir diperbarui:</b>
        <?= $stats['last_mining']
            ? date('d M Y, H:i', strtotime($stats['last_mining']))
            : '<span style="color:#dc3545">Belum pernah dijalankan</span>' ?>
      </div>
      <div style="font-size:12px;color:#777;margin-top:6px">
        Rekomendasi dihitung berdasarkan kesamaan pola peminjaman antar anggota
        dan kesamaan konten buku (judul, topik, klasifikasi DDC).
      </div>
    </div>

    <!-- TAB: MINING -->
    <div id="rec-tab-mining" class="rec-tab-pane">

      <!-- Status data saat ini -->
      <div style="background:#f8f9fa;border-radius:6px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#555">
        <b>Status Data Saat Ini</b><br>
        <span style="color:#555">Terakhir diperbarui:
          <b><?= $stats['last_mining']
              ? date('d M Y, H:i', strtotime($stats['last_mining']))
              : '<span style="color:#dc3545">Belum pernah dijalankan</span>' ?></b>
        </span><br>
        <span>Data sekarang:
          <b><?= number_format($stats['cf_rules']) ?></b> pasang buku serupa (CF) &nbsp;+&nbsp;
          <b><?= number_format($stats['cbf_rules']) ?></b> pasang buku serupa (CBF)
        </span>
      </div>

      <p style="font-size:13px;color:#555;margin-bottom:14px">
        Jalankan mining untuk memperbarui data rekomendasi.
        Disarankan setelah ada penambahan koleksi buku atau data peminjaman baru.
        Proses biasanya berlangsung 1&ndash;5 menit.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="run_mining">
        <button type="submit" class="btn btn-primary"
                onclick="this.disabled=true; this.textContent='Sedang berjalan, harap tunggu...'">
          &#9654;&nbsp; Jalankan Mining Sekarang
        </button>
      </form>
    </div>

    <!-- TAB: LOG MINING -->
    <div id="rec-tab-log" class="rec-tab-pane">
      <?php if (empty($logs)): ?>
        <p class="text-muted" style="font-size:13px">Belum ada riwayat mining.</p>
      <?php else: ?>
      <table class="table table-striped table-sm" style="font-size:13px">
        <thead class="thead-dark">
          <tr>
            <th>Jenis Mining</th>
            <th>Status</th>
            <th>Pasang Buku Serupa</th>
            <th>Durasi</th>
            <th>Waktu Proses</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log):
            $jenisLabel = $log['algorithm'] === 'CF'
                ? 'Collaborative Filtering'
                : 'Content-Based Filtering';
          ?>
          <tr>
            <td><?= htmlspecialchars($jenisLabel) ?></td>
            <td><span class="badge badge-success"><?= htmlspecialchars($log['status']) ?></span></td>
            <td><?= number_format((int)$log['records_created']) ?></td>
            <td><?= rec_format_duration((float)$log['duration_seconds']) ?></td>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function recShowTab(name, btn) {
    document.querySelectorAll('.rec-tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.rec-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('rec-tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
