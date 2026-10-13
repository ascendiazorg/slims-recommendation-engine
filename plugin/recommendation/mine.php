<?php
/**
 * Mining Script - Hitung CF dan CBF Similarity
 * Akses: http://localhost/slims/plugins/recommendation/mine.php
 * (login admin SLIMS terlebih dahulu)
 */

define('INDEX_AUTH', 1);
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? 'localhost';

require_once __DIR__ . '/../../sysconfig.inc.php';

require_once __DIR__ . '/classes/CollaborativeFilter.php';
require_once __DIR__ . '/classes/ContentBasedFilter.php';

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    session_start();
    if (empty($_SESSION['adminuser'])) {
        die('<p style="font-family:Arial;padding:20px;color:#c00">
            Akses ditolak. Silakan <a href="../../index.php?mod=login">login sebagai admin</a> terlebih dahulu.
        </p>');
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Mining - Recommendation Engine</title>
    <style>
    body{font-family:Arial;padding:20px;max-width:640px}
    h2{color:#2c5f8a}.box{background:#f5f5f5;padding:16px;border-radius:6px;margin:10px 0;font-size:14px}
    .ok{color:green}.err{color:red}
    </style></head><body>';
    echo '<h2>Recommendation Engine &mdash; Mining Script</h2>';
}

$br = $isCli ? "\n" : "<br>";
$hr = $isCli ? str_repeat('=', 50) . "\n" : '<hr style="margin:12px 0">';

echo "<div class='box'>";
echo "Waktu mulai: " . date('Y-m-d H:i:s') . $br . $br;

// CF Mining
echo "&#9658; Memulai CF Mining (Item-Based Jaccard)..." . $br;
$start   = microtime(true);
$cf      = new CollaborativeFilter($dbs);
$cfCount = $cf->runMining();
$cfTime  = round(microtime(true) - $start, 2);
echo "<span class='ok'>&#10003; CF selesai: {$cfCount} pasang buku | {$cfTime} detik</span>" . $br . $br;

// CBF Mining
echo "&#9658; Memulai CBF Mining (TF-IDF + Cosine)..." . $br;
$start    = microtime(true);
$cbf      = new ContentBasedFilter($dbs);
$cbfCount = $cbf->runMining();
$cbfTime  = round(microtime(true) - $start, 2);
echo "<span class='ok'>&#10003; CBF selesai: {$cbfCount} pasang buku | {$cbfTime} detik</span>" . $br . $br;

// Simpan log
$dbs->query(sprintf(
    "INSERT INTO rec_mining_log (algorithm, status, records_created, duration_seconds)
     VALUES ('CF', 'done', %d, %.2f), ('CBF', 'done', %d, %.2f)",
    $cfCount, $cfTime, $cbfCount, $cbfTime
));

echo $hr;
echo "<b>&#10003; Mining selesai!</b>" . $br;
echo "CF  : {$cfCount} records" . $br;
echo "CBF : {$cbfCount} records" . $br;
echo "</div>";

if (!$isCli) {
    echo '<p style="margin-top:16px">
        <a href="../../index.php?p=member">&larr; Kembali ke OPAC Member</a>
    </p></body></html>';
}
