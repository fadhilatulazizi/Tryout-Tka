<?php
/**
 * Konfigurasi Utama Aplikasi CBT
 */

date_default_timezone_set('Asia/Jakarta');

if (!defined('APP_NAME')) define('APP_NAME', 'CBT Asesmen Demak');
if (!defined('APP_VERSION')) define('APP_VERSION', '4.0.0 (Modern Edition)');
if (!defined('APP_YEAR')) define('APP_YEAR', date('Y'));

// Inisialisasi Session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Deteksi Base URL / Base Path aplikasi
 */
function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($scriptName);
        if ($dir === '/' || $dir === '\\') {
            $base = '';
        } else {
            // Jika script ada di /admin/ atau /ujian/, ambil root project
            $segments = explode('/', trim($dir, '/'));
            if (!empty($segments)) {
                // Ambil segmen pertama jika htdocs subfolder (contoh: /Tryout-Tka)
                $first = $segments[0];
                if ($first === 'admin' || $first === 'ujian' || $first === 'assets') {
                    $base = '';
                } else {
                    $base = '/' . $first;
                }
            } else {
                $base = '';
            }
        }
    }

    $cleanPath = '/' . ltrim($path, '/');
    return ($base === '' ? '' : $base) . $cleanPath;
}
