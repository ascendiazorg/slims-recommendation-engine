<?php
/**
 * Mining Script - CLI version (tanpa cek session)
 * Jalankan via: php mine_cli.php
 */
if (php_sapi_name() !== 'cli') exit('CLI only. Gunakan mine.php untuk akses web.' . PHP_EOL);
define('INDEX_AUTH', 1);
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST']   = 'localhost';

require_once __DIR__ . '/../../sysconfig.inc.php';
require_once __DIR__ . '/classes/CollaborativeFilter.php';
require_once __DIR__ . '/classes/ContentBasedFilter.php';

echo str_repeat('=', 50) . "\n";
echo "RECOMMENDATION ENGINE - MINING\n";
echo date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 50) . "\n\n";

// CF Mining
echo ">> CF Mining (Item-Based Jaccard)...\n";
$start   = microtime(true);
$cf      = new CollaborativeFilter($dbs);
$cfCount = $cf->runMining();
$cfTime  = round(microtime(true) - $start, 2);
echo "   OK: {$cfCount} pasang buku, {$cfTime} detik\n\n";

// CBF Mining
echo ">> CBF Mining (TF-IDF + Cosine)...\n";
$start    = microtime(true);
$cbf      = new ContentBasedFilter($dbs);
$cbfCount = $cbf->runMining();
$cbfTime  = round(microtime(true) - $start, 2);
echo "   OK: {$cbfCount} pasang buku, {$cbfTime} detik\n\n";

// Log
$dbs->query(sprintf(
    "INSERT INTO rec_mining_log (algorithm, status, records_created, duration_seconds)
     VALUES ('CF', 'done', %d, %.2f), ('CBF', 'done', %d, %.2f)",
    $cfCount, $cfTime, $cbfCount, $cbfTime
));

echo str_repeat('=', 50) . "\n";
echo "SELESAI!\n";
echo "CF  : {$cfCount} records\n";
echo "CBF : {$cbfCount} records\n";
echo str_repeat('=', 50) . "\n";
