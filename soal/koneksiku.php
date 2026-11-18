<?php
/**
 * Jembatan Kompatibilitas Koneksi Database untuk Paket Soal
 * Mendukung include("../koneksiku.php") dari folder soal/confXX/ manapun (conf31, conf32, conf33, dst.)
 */

// Sembunyikan notice/warning sintaks lawas PHP 5/7 pada berkas soal eksternal
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// Otomatis sembunyikan tombol kirim jawaban legacy tanpa menghilangkan tabel pernyataan Benar/Salah atau Checkbox
ob_start(function($buffer) {
    // 1. Sinkronkan nomor butir soal pada badge .nomor agar selalu sama dengan nomor file soal yang sedang dibuka
    // (Mencegah nomor acak lawas dari array $nomor[] tabel account membingungkan siswa)
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptNo = preg_replace('/[^0-9]/', '', $scriptName);
    if (!empty($scriptNo)) {
        $buffer = preg_replace('/<div\s+class=["\']nomor["\']>.*?<\/div>/is', '<div class="nomor">' . $scriptNo . '</div>', $buffer);
    }

    $hideStudentStyle = '';
    // Sembunyikan seluruh form bawaan soal saat ujian siswa agar pemilihan jawaban 100% menggunakan aplikasi CBT
    if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['preview_mode']) && !isset($_GET['preview'])) {
        $hideStudentStyle = '
        /* Sembunyikan form dan elemen input lawas di dalam lembar soal saat ujian siswa */
        form[name="form1"], form {
            display: none !important;
        }
        input[type="radio"],
        input[type="checkbox"],
        input[type="submit"],
        input[name="jawab"] {
            display: none !important;
        }';
    }

    $hideStyle = '<style>
    /* Styling modern badge nomor butir soal agar seragam dengan tema ANBK */
    .nomor {
        display: inline-flex !important;
        min-width: 36px !important;
        height: 36px !important;
        padding: 0 8px !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 8px !important;
        background: #e0f2fe !important;
        color: #0369a1 !important;
        font-weight: 800 !important;
        font-size: 16px !important;
        border: 1.5px solid #bae6fd !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    }
    /* Sembunyikan tombol submit lawas */
    input[name="jawab"],
    input[value="Kirim Jawaban"],
    input[type="submit"],
    button[type="submit"] {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    ' . $hideStudentStyle . '
    body {
        padding-bottom: 30px !important;
    }
    </style>';

    if (stripos($buffer, '</head>') !== false) {
        return str_ireplace('</head>', $hideStyle . '</head>', $buffer);
    }
    return $hideStyle . $buffer;
});

require_once dirname(__DIR__) . '/config/database.php';

$db = get_db();
$koneksi = $db;
$conn = $db;

// Pastikan baris sesi siswa ada di tabel lembar jawaban (pg_tb s.d. pg_tb_5) jika siswa sedang login
if (!empty($_SESSION['id'])) {
    $sId = (int) $_SESSION['id'];
    $sNama = mysqli_real_escape_string($db, $_SESSION['nama'] ?? 'Peserta');
    $sKelas = mysqli_real_escape_string($db, $_SESSION['kelas'] ?? '');
    $sAbs = mysqli_real_escape_string($db, $_SESSION['abs'] ?? '');

    foreach (['pg_tb', 'pg_tb_2', 'pg_tb_3', 'pg_tb_4', 'pg_tb_5'] as $tb) {
        $c = mysqli_query($db, "SELECT id FROM {$tb} WHERE id = {$sId} LIMIT 1");
        if ($c && mysqli_num_rows($c) === 0) {
            mysqli_query($db, "INSERT INTO {$tb} (id, nama, kelas, abs) VALUES ({$sId}, '{$sNama}', '{$sKelas}', '{$sAbs}')");
        }
    }
}
