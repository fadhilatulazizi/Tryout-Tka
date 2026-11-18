<?php
/**
 * Konfigurasi Database CBT Demak
 */

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_SERVER') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USERNAME') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'tka');

function get_db(): mysqli
{
    static $db = null;
    if ($db instanceof mysqli && @mysqli_ping($db)) {
        return $db;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $port = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
    if (!$db) {
        $lock = dirname(__DIR__) . '/config/installed.lock';
        $installer = dirname(__DIR__) . '/install.php';
        if (!file_exists($lock) && file_exists($installer) && php_sapi_name() !== 'cli') {
            header('Location: ' . (function_exists('base_url') ? base_url('install.php') : 'install.php'));
            exit;
        }
        http_response_code(503);
        die('Koneksi database gagal. Silakan periksa konfigurasi MySQL atau buka install.php.');
    }

    mysqli_set_charset($db, 'utf8mb4');
    return $db;
}

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(503);
        die('Koneksi PDO database gagal: ' . $e->getMessage());
    }
}
