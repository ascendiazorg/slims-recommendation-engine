<?php
/**
 * KONFIGURASI KONEKSI DATABASE SLIMS
 * ─────────────────────────────────────────────────────────
 * Copy file ini menjadi config.php, lalu sesuaikan nilainya.
 *
 *   cp config.example.php config.php
 *
 * Default XAMPP: database=slims, user=root, password=(kosong)
 * ─────────────────────────────────────────────────────────
 */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'slims');   // ← ganti jika nama DB beda
define('DB_USER', 'root');
define('DB_PASS', '');        // ← isi jika ada password

function getSlimsConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8',
        DB_HOST, DB_PORT, DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $msg = "Gagal konek ke database SLIMS: " . $e->getMessage()
             . "\n   Cek DB_NAME='" . DB_NAME . "', DB_USER='" . DB_USER . "' di config.php\n";
        if (isset($_SERVER['HTTP_HOST'])) {
            die('<pre style="color:red;padding:20px;">' . htmlspecialchars($msg) . '</pre>');
        }
        die($msg);
    }
}
