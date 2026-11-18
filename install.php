<?php
/**
 * CBT Modern Assessment - Web Installer
 * Memandu instalasi sistem dan inisialisasi seluruh tabel database CBT secara otomatis.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$configFile = __DIR__ . '/config/database.php';
$lockFile   = __DIR__ . '/config/installed.lock';
$isInstalled = file_exists($lockFile);

// Helper deteksi base URL
function get_installer_base_url(): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    $segments = explode('/', trim($dir, '/'));
    if (!empty($segments)) {
        $first = $segments[0];
        if (in_array($first, ['admin', 'ujian', 'assets', 'config', '.'], true)) {
            return '';
        }
        return '/' . $first;
    }
    return '';
}

$baseUrl = rtrim(get_installer_base_url(), '/');
$adminLink = $baseUrl !== '' ? $baseUrl . '/admin/login.php' : 'admin/login.php';
$homeLink  = $baseUrl !== '' ? $baseUrl . '/index.php' : 'index.php';

// 1. Pemeriksaan Kebutuhan Server
$checks = [
    'php' => [
        'title' => 'Versi PHP (Minimal 7.4, Direkomendasikan 8.0+)',
        'pass'  => version_compare(PHP_VERSION, '7.4.0', '>='),
        'curr'  => PHP_VERSION
    ],
    'mysqli' => [
        'title' => 'Ekstensi MySQLi (Database Driver)',
        'pass'  => extension_loaded('mysqli'),
        'curr'  => extension_loaded('mysqli') ? 'Tersedia' : 'Tidak Ditemukan'
    ],
    'pdo' => [
        'title' => 'Ekstensi PDO MySQL',
        'pass'  => extension_loaded('pdo_mysql'),
        'curr'  => extension_loaded('pdo_mysql') ? 'Tersedia' : 'Tidak Ditemukan'
    ],
    'mbstring' => [
        'title' => 'Ekstensi Multibyte String (mbstring)',
        'pass'  => extension_loaded('mbstring'),
        'curr'  => extension_loaded('mbstring') ? 'Tersedia' : 'Tidak Ditemukan'
    ],
    'json' => [
        'title' => 'Ekstensi JSON',
        'pass'  => extension_loaded('json'),
        'curr'  => extension_loaded('json') ? 'Tersedia' : 'Tidak Ditemukan'
    ],
    'session' => [
        'title' => 'Ekstensi Session',
        'pass'  => extension_loaded('session'),
        'curr'  => extension_loaded('session') ? 'Tersedia' : 'Tidak Ditemukan'
    ],
    'writable_config' => [
        'title' => 'Izin Tulis Direktori /config',
        'pass'  => is_writable(__DIR__ . '/config'),
        'curr'  => is_writable(__DIR__ . '/config') ? 'Dapat Ditulis (Writable)' : 'Tidak Writable'
    ],
    'writable_cache' => [
        'title' => 'Izin Tulis Direktori /cache',
        'pass'  => is_dir(__DIR__ . '/cache') && is_writable(__DIR__ . '/cache'),
        'curr'  => (is_dir(__DIR__ . '/cache') && is_writable(__DIR__ . '/cache')) ? 'Dapat Ditulis (Writable)' : 'Tidak Writable'
    ]
];

$allPassed = true;
foreach ($checks as $c) {
    if (!$c['pass']) {
        $allPassed = false;
        break;
    }
}

// 2. Pemrosesan Instalasi (POST)
$errorMsg = '';
$successLogs = [];
$installSuccess = false;

// Aksi Buka Kunci Installer (Reinstall)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock') {
    @unlink($lockFile);
    header('Location: ' . ($baseUrl !== '' ? $baseUrl . '/install.php' : 'install.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    if ($isInstalled && empty($_POST['force_reinstall'])) {
        $errorMsg = 'Sistem sudah terinstall sebelumnya. Hapus file `config/installed.lock` untuk menginstall ulang.';
    } elseif (!$allPassed) {
        $errorMsg = 'Persyaratan sistem belum terpenuhi. Silakan periksa dan perbaiki komponen yang bertanda silang merah.';
    } else {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbUser = trim($_POST['db_user'] ?? 'root');
        $dbPass = $_POST['db_pass'] ?? '';
        $dbName = trim($_POST['db_name'] ?? 'tryout_tka');
        $createDb = !empty($_POST['create_db']);

        // Data Sekolah
        $sekolah = trim($_POST['sekolah'] ?? 'SMP NEGERI 1 DEMAK');
        $npsn    = trim($_POST['npsn'] ?? '20319280');
        $ks      = trim($_POST['ks'] ?? 'Drs. H. Sugiyanto, M.Pd.');

        // Akun Admin
        $adminUser = trim($_POST['admin_user'] ?? 'admin');
        $adminPass = trim($_POST['admin_pass'] ?? 'admin');
        $adminNama = trim($_POST['admin_nama'] ?? 'Administrator CBT');

        if ($dbHost === '' || $dbUser === '' || $dbName === '') {
            $errorMsg = 'Host, Username, dan Nama Database wajib diisi.';
        } elseif ($adminUser === '' || $adminPass === '') {
            $errorMsg = 'Username dan Password Administrator tidak boleh kosong.';
        } else {
            // Coba koneksi ke MySQL Server
            mysqli_report(MYSQLI_REPORT_OFF);
            $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, '', $dbPort);
            
            if (!$conn) {
                $errorMsg = 'Koneksi ke MySQL Server gagal: ' . mysqli_connect_error() . '. Pastikan Host, Port, Username, dan Password benar.';
            } else {
                // Buat Database jika diminta
                if ($createDb) {
                    $escDbName = str_replace('`', '``', $dbName);
                    $qCreate = mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `{$escDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    if (!$qCreate) {
                        $errorMsg = 'Gagal membuat database `' . htmlspecialchars($dbName) . '`: ' . mysqli_error($conn);
                    }
                }

                if ($errorMsg === '') {
                    // Pilih database
                    if (!@mysqli_select_db($conn, $dbName)) {
                        $errorMsg = 'Database `' . htmlspecialchars($dbName) . '` tidak ditemukan di MySQL server. Centang opsi "Buat database otomatis jika belum ada" atau buat database terlebih dahulu di phpMyAdmin.';
                    } else {
                        mysqli_set_charset($conn, 'utf8mb4');

                        // Jalankan Inisialisasi Skema Lengkap
                        require_once __DIR__ . '/app/DatabaseInit.php';
                        $customData = [
                            'sekolah'    => $sekolah,
                            'npsn'       => $npsn,
                            'ks'         => $ks,
                            'admin_user' => $adminUser,
                            'admin_pass' => $adminPass,
                            'admin_nama' => $adminNama,
                        ];

                        try {
                            $successLogs = reset_cbt_database($conn, $customData);

                            // Tulis file config/database.php baru
                            $dbConfigCode = "<?php\n"
                                . "/**\n"
                                . " * Konfigurasi Database CBT Demak\n"
                                . " * Dihasilkan otomatis oleh CBT Installer pada " . date('Y-m-d H:i:s') . "\n"
                                . " */\n\n"
                                . "if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_SERVER') ?: '" . addslashes($dbHost) . "');\n"
                                . "if (!defined('DB_USER')) define('DB_USER', getenv('DB_USERNAME') ?: '" . addslashes($dbUser) . "');\n"
                                . "if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '" . addslashes($dbPass) . "');\n"
                                . "if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: '" . addslashes($dbName) . "');\n"
                                . "if (!defined('DB_PORT')) define('DB_PORT', (int)(getenv('DB_PORT') ?: " . $dbPort . "));\n\n"
                                . "function get_db(): mysqli\n"
                                . "{\n"
                                . "    static \$db = null;\n"
                                . "    if (\$db instanceof mysqli && @mysqli_ping(\$db)) {\n"
                                . "        return \$db;\n"
                                . "    }\n\n"
                                . "    mysqli_report(MYSQLI_REPORT_OFF);\n"
                                . "    \$port = defined('DB_PORT') ? DB_PORT : 3306;\n"
                                . "    \$db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, \$port);\n"
                                . "    if (!\$db) {\n"
                                . "        http_response_code(503);\n"
                                . "        die('Koneksi database gagal. Silakan periksa konfigurasi MySQL atau buka install.php.');\n"
                                . "    }\n\n"
                                . "    mysqli_set_charset(\$db, 'utf8mb4');\n"
                                . "    return \$db;\n"
                                . "}\n\n"
                                . "function get_pdo(): PDO\n"
                                . "{\n"
                                . "    static \$pdo = null;\n"
                                . "    if (\$pdo instanceof PDO) {\n"
                                . "        return \$pdo;\n"
                                . "    }\n\n"
                                . "    \$port = defined('DB_PORT') ? DB_PORT : 3306;\n"
                                . "    \$dsn = 'mysql:host=' . DB_HOST . ';port=' . \$port . ';dbname=' . DB_NAME . ';charset=utf8mb4';\n"
                                . "    \$options = [\n"
                                . "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
                                . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                                . "        PDO::ATTR_EMULATE_PREPARES   => false,\n"
                                . "    ];\n\n"
                                . "    try {\n"
                                . "        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);\n"
                                . "        return \$pdo;\n"
                                . "    } catch (PDOException \$e) {\n"
                                . "        http_response_code(503);\n"
                                . "        die('Koneksi PDO database gagal: ' . \$e->getMessage());\n"
                                . "    }\n"
                                . "}\n";

                            file_put_contents($configFile, $dbConfigCode);

                            // Kunci installer
                            $lockData = json_encode([
                                'installed_at' => date('Y-m-d H:i:s'),
                                'php_version'  => PHP_VERSION,
                                'school'       => $sekolah,
                                'db_name'      => $dbName,
                                'app_version'  => '4.0.0'
                            ], JSON_PRETTY_PRINT);
                            file_put_contents($lockFile, $lockData);

                            $installSuccess = true;
                            $isInstalled = true;
                        } catch (Throwable $t) {
                            $errorMsg = 'Terjadi kesalahan saat mengeksekusi skema database: ' . $t->getMessage();
                        }
                    }
                }
                mysqli_close($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installer CBT Asesmen Modern</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 text-slate-100 font-sans min-h-screen py-10 px-4 flex flex-col items-center justify-center selection:bg-sky-500 selection:text-white">

    <!-- Card Container -->
    <div class="w-full max-w-3xl bg-slate-950 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-sky-600 via-blue-600 to-indigo-700 p-8 text-white relative">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-3xl shadow-inner border border-white/20">
                    ⚡
                </div>
                <div>
                    <span class="text-xs font-bold tracking-widest uppercase text-sky-200">System Setup & Database Migrator</span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">CBT Asesmen Modern</h1>
                    <p class="text-xs sm:text-sm text-sky-100 mt-1">Panduan instalasi instan & konfigurasi database MySQL aplikasi CBT.</p>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-10 space-y-8">

            <?php if ($installSuccess): ?>
                <!-- SCREEN: SUKSES INSTALASI -->
                <div class="bg-emerald-950/60 border border-emerald-500/50 rounded-2xl p-6 sm:p-8 text-center space-y-4">
                    <div class="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center text-3xl border border-emerald-500/30">
                        ✓
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-white">Instalasi Berhasil Diselesaikan!</h2>
                        <p class="text-sm text-emerald-200/90 mt-1">
                            Seluruh tabel database (14 tabel) telah berhasil dibuat dan dikonfigurasi. Kredensial MySQL telah tersimpan di <code>config/database.php</code>.
                        </p>
                    </div>

                    <div class="text-left bg-slate-900 border border-slate-800 rounded-xl p-4 text-xs font-mono text-slate-300 max-h-48 overflow-y-auto space-y-1">
                        <strong class="text-emerald-400 block mb-2 font-sans text-xs uppercase tracking-wider">Log Pembuatan Database:</strong>
                        <?php foreach ($successLogs as $log): ?>
                            <div>✓ <?php echo htmlspecialchars($log); ?></div>
                        <?php endforeach; ?>
                        <div class="text-sky-400">✓ File config/installed.lock telah dibuat (sistem terkunci aman).</div>
                    </div>

                    <div class="pt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="<?php echo htmlspecialchars($adminLink); ?>" class="px-6 py-3 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-500 transition shadow-lg shadow-sky-600/30 text-sm">
                            🔑 Masuk ke Panel Admin (Proktor) &rarr;
                        </a>
                        <a href="<?php echo htmlspecialchars($homeLink); ?>" class="px-6 py-3 rounded-xl font-bold text-slate-300 bg-slate-800 hover:bg-slate-700 transition text-sm">
                            🌐 Buka Beranda Siswa
                        </a>
                    </div>
                </div>

            <?php elseif ($isInstalled): ?>
                <!-- SCREEN: SUDAH TERINSTALL -->
                <div class="bg-sky-950/40 border border-sky-500/40 rounded-2xl p-6 sm:p-8 text-center space-y-4">
                    <div class="w-16 h-16 rounded-full bg-sky-500/20 text-sky-400 mx-auto flex items-center justify-center text-3xl border border-sky-500/30">
                        🔒
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-white">Sistem CBT Telah Terinstall</h2>
                        <p class="text-sm text-slate-300 mt-1">
                            Aplikasi CBT sudah terpasang dan dalam status terkunci untuk mencegah penimpaan data yang tidak disengaja.
                        </p>
                    </div>

                    <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-400 text-left">
                        💡 <strong>Catatan Pengelola:</strong> Jika Anda ingin menjalankan ulang proses instalasi dari awal (reset ulang database), silakan hapus file lock berikut pada server:
                        <code class="block mt-1.5 p-2 bg-slate-950 rounded text-sky-300 font-mono">config/installed.lock</code>
                    </div>

                    <div class="pt-4 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="<?php echo htmlspecialchars($adminLink); ?>" class="px-6 py-3 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-500 transition shadow-lg shadow-sky-600/30 text-sm">
                            🔑 Masuk Panel Admin &rarr;
                        </a>
                        <a href="<?php echo htmlspecialchars($homeLink); ?>" class="px-6 py-3 rounded-xl font-bold text-slate-300 bg-slate-800 hover:bg-slate-700 transition text-sm">
                            🌐 Buka Beranda Utama
                        </a>
                    </div>

                    <div class="pt-4 border-t border-slate-800/80 mt-6">
                        <form method="POST" action="" onsubmit="return confirm('PERINGATAN: Membuka kunci installer akan memungkinkan instalasi ulang skema database. Lanjutkan?')">
                            <input type="hidden" name="action" value="unlock">
                            <button type="submit" class="px-4 py-2 rounded-xl font-bold text-rose-300 bg-rose-950/40 border border-rose-800/60 hover:bg-rose-900/60 transition text-xs flex items-center gap-1.5 mx-auto">
                                <span>🔓</span>
                                <span>Buka Kunci untuk Konfigurasi / Install Ulang</span>
                            </button>
                        </form>
                    </div>
                </div>

            <?php else: ?>

                <!-- TAMPILKAN ERROR JIKA ADA -->
                <?php if ($errorMsg !== ''): ?>
                    <div class="bg-rose-950/70 border border-rose-500/60 rounded-2xl p-4 sm:p-5 flex items-start gap-3 text-rose-200 text-sm">
                        <span class="text-xl leading-none">⚠️</span>
                        <div>
                            <strong class="font-bold text-white block">Instalasi Belum Dapat Dilanjutkan</strong>
                            <span><?php echo htmlspecialchars($errorMsg); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LANGKAH 1: PEMERIKSAAN SISTEM -->
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-7 h-7 rounded-full bg-sky-500 text-slate-950 font-extrabold flex items-center justify-center text-xs">1</span>
                        <h2 class="text-lg font-bold text-white">Pemeriksaan Persyaratan Server</h2>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-2xl divide-y divide-slate-800/80 text-xs">
                        <?php foreach ($checks as $key => $c): ?>
                            <div class="p-3.5 flex items-center justify-between">
                                <span class="font-medium text-slate-300"><?php echo htmlspecialchars($c['title']); ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-slate-400"><?php echo htmlspecialchars($c['curr']); ?></span>
                                    <?php if ($c['pass']): ?>
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-bold">OK</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-400 font-bold">GAGAL</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- FORM KONFIGURASI -->
                <form method="POST" action="" class="space-y-8">
                    <input type="hidden" name="action" value="install">

                    <!-- LANGKAH 2: KONEKSI DATABASE -->
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-7 h-7 rounded-full bg-sky-500 text-slate-950 font-extrabold flex items-center justify-center text-xs">2</span>
                            <h2 class="text-lg font-bold text-white">Konfigurasi Database MySQL</h2>
                        </div>

                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Host Database</label>
                                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Port MySQL</label>
                                    <input type="number" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Username Database</label>
                                    <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Password Database</label>
                                    <input type="password" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>" placeholder="Kosongkan jika tanpa password" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Nama Database</label>
                                <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'tryout_tka'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                            </div>

                            <div class="pt-2">
                                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-sky-400 font-medium">
                                    <input type="checkbox" name="create_db" value="1" checked class="rounded border-slate-700 bg-slate-950 text-sky-500 focus:ring-sky-500">
                                    <span>Buat database otomatis jika database belum ada di server (CREATE DATABASE IF NOT EXISTS)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- LANGKAH 3: IDENTITAS SEKOLAH -->
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-7 h-7 rounded-full bg-sky-500 text-slate-950 font-extrabold flex items-center justify-center text-xs">3</span>
                            <h2 class="text-lg font-bold text-white">Identitas Sekolah Penyelenggara</h2>
                        </div>

                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Nama Sekolah</label>
                                    <input type="text" name="sekolah" value="<?php echo htmlspecialchars($_POST['sekolah'] ?? 'SMP NEGERI 1 DEMAK'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">NPSN</label>
                                    <input type="text" name="npsn" value="<?php echo htmlspecialchars($_POST['npsn'] ?? '20319280'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Nama Kepala Sekolah</label>
                                <input type="text" name="ks" value="<?php echo htmlspecialchars($_POST['ks'] ?? 'Drs. H. Sugiyanto, M.Pd.'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500">
                            </div>
                        </div>
                    </div>

                    <!-- LANGKAH 4: AKUN ADMINISTRATOR -->
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="w-7 h-7 rounded-full bg-sky-500 text-slate-950 font-extrabold flex items-center justify-center text-xs">4</span>
                            <h2 class="text-lg font-bold text-white">Akun Administrator / Proktor Utama</h2>
                        </div>

                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Username Admin</label>
                                    <input type="text" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Password Admin</label>
                                    <input type="text" name="admin_pass" value="<?php echo htmlspecialchars($_POST['admin_pass'] ?? 'admin'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500 font-mono">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Nama Lengkap Administrator</label>
                                <input type="text" name="admin_nama" value="<?php echo htmlspecialchars($_POST['admin_nama'] ?? 'Administrator CBT'); ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-sky-500">
                            </div>
                        </div>
                    </div>

                    <!-- DAFTAR SKEMA DATABASE -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <span class="w-7 h-7 rounded-full bg-emerald-500 text-slate-950 font-extrabold flex items-center justify-center text-xs">✓</span>
                                <h2 class="text-lg font-bold text-white">Daftar Skema Database yang Diimpor</h2>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-xs border border-emerald-500/30">14 Tabel Lengkap</span>
                        </div>

                        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5">
                            <p class="text-xs text-slate-400 mb-3">
                                Seluruh skema tabel berikut akan dibuat dan diimpor lengkap dengan indeks, relasi, struktur butir soal acak (a1..a100), dan opsi multi-jadwal:
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">1. admin</span>
                                    <span class="text-slate-400 text-[11px]">Akun Pengelola & Proktor</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">2. mapel</span>
                                    <span class="text-slate-400 text-[11px]">Profil Sekolah & Setting</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">3. jadwal_mapel</span>
                                    <span class="text-slate-400 text-[11px]">Jadwal & Mapel Dinamis</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">4. atur</span>
                                    <span class="text-slate-400 text-[11px]">Sesi, Ruang, Token & Durasi</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">5. account</span>
                                    <span class="text-slate-400 text-[11px]">Siswa & Random Soal a1-a100</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">6. pg_tb</span>
                                    <span class="text-slate-400 text-[11px]">Jawaban Siswa Hari 1</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">7. pg_tb_2</span>
                                    <span class="text-slate-400 text-[11px]">Jawaban Siswa Hari 2</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">8. pg_tb_3</span>
                                    <span class="text-slate-400 text-[11px]">Jawaban Siswa Hari 3</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">9. pg_tb_4</span>
                                    <span class="text-slate-400 text-[11px]">Jawaban Siswa Hari 4</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">10. pg_tb_5</span>
                                    <span class="text-slate-400 text-[11px]">Jawaban Siswa Hari 5</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">11. daft_sklh</span>
                                    <span class="text-slate-400 text-[11px]">Master Referensi Sekolah</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">12. kunci_jawaban</span>
                                    <span class="text-slate-400 text-[11px]">Kunci Jawaban Soal</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">13. broadcast_pesan</span>
                                    <span class="text-slate-400 text-[11px]">Siaran Pesan Proktor</span>
                                </div>
                                <div class="p-2.5 bg-slate-950 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                    <span class="font-mono text-sky-400 font-bold">14. token_tb</span>
                                    <span class="text-slate-400 text-[11px]">Token Fallback Sesi</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TOMBOL PROSES INSTALASI -->
                    <div class="pt-4 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs text-slate-400">
                            Proses ini akan menginisialisasi 14 tabel database lengkap dan data default.
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-xl font-extrabold text-white bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 transition shadow-lg shadow-sky-500/25 active:scale-95 text-sm flex items-center justify-center gap-2">
                            <span>🚀</span>
                            <span>Pasang & Buat Database Sekarang</span>
                        </button>
                    </div>

                </form>

            <?php endif; ?>

        </div>

        <!-- Footer -->
        <div class="bg-slate-950/80 border-t border-slate-800/80 py-4 px-8 text-center text-xs text-slate-500">
            &copy; <?php echo date('Y'); ?> CBT Asesmen Modern &bull; Sistem Asesmen Ujian Berbasis Komputer
        </div>

    </div>

</body>
</html>
