<?php
/**
 * Inisialisasi & Reset Skema Database CBT Demak
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Eksekusi file SQL mentah secara berurutan dan aman
 */
function import_sql_file(mysqli $db, string $filePath): array
{
    if (!file_exists($filePath)) {
        throw new Exception("File SQL tidak ditemukan: " . basename($filePath));
    }

    $sqlContent = file_get_contents($filePath);
    if ($sqlContent === false) {
        throw new Exception("Gagal membaca file SQL: " . basename($filePath));
    }

    mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 0;");
    mysqli_query($db, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
    mysqli_set_charset($db, 'utf8mb4');

    // Parsing statement SQL per baris
    $statements = [];
    $lines = explode("\n", $sqlContent);
    $cur = '';
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '/*') === 0) {
            continue;
        }
        $cur .= $line . "\n";
        if (substr(rtrim($trim), -1) === ';') {
            $statements[] = trim($cur);
            $cur = '';
        }
    }
    if (trim($cur) !== '') {
        $statements[] = trim($cur);
    }

    $executed = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        if (!mysqli_query($db, $stmt)) {
            throw new Exception("Gagal mengeksekusi SQL: " . mysqli_error($db) . " [Cuplikan: " . substr($stmt, 0, 80) . "...]");
        }
        $executed++;
    }

    mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 1;");

    return [
        'statements' => $executed,
        'message' => "Berhasil mengimpor {$executed} perintah SQL dari " . basename($filePath)
    ];
}

/**
 * Memastikan dan memverifikasi integritas seluruh 14 tabel database
 */
function verify_cbt_tables(mysqli $db): array
{
    $expectedTables = [
        'account'         => 'Data Peserta Siswa & Random Soal',
        'admin'           => 'Akun Pengelola & Proktor',
        'atur'            => 'Pengaturan Sesi, Ruang, Token & Durasi',
        'broadcast_pesan' => 'Siaran Pengumuman Proktor',
        'daft_sklh'       => 'Master Referensi Sekolah',
        'jadwal_mapel'    => 'Jadwal & Mata Pelajaran Dinamis',
        'kunci_jawaban'   => 'Kunci Jawaban Soal Asesmen',
        'mapel'           => 'Profil Sekolah & Konfigurasi Hari Aktif',
        'pg_tb'           => 'Lembar Jawaban Siswa Hari 1',
        'pg_tb_2'         => 'Lembar Jawaban Siswa Hari 2',
        'pg_tb_3'         => 'Lembar Jawaban Siswa Hari 3',
        'pg_tb_4'         => 'Lembar Jawaban Siswa Hari 4',
        'pg_tb_5'         => 'Lembar Jawaban Siswa Hari 5',
        'token_tb'        => 'Penyimpan Token Fallback'
    ];

    $existingTables = [];
    $qCheck = mysqli_query($db, "SHOW TABLES");
    if ($qCheck) {
        while ($rT = mysqli_fetch_row($qCheck)) {
            $existingTables[] = $rT[0];
        }
    }

    $verified = [];
    $missing = [];

    foreach ($expectedTables as $tbl => $desc) {
        if (in_array($tbl, $existingTables, true)) {
            $verified[$tbl] = $desc;
        } else {
            $missing[] = $tbl;
        }
    }

    if (!empty($missing)) {
        throw new Exception("Inisialisasi skema belum lengkap! Tabel berikut belum terbuat: " . implode(', ', $missing));
    }

    return $verified;
}

/**
 * Reset dan inisialisasi skema database CBT lengkap
 */
function reset_cbt_database(?mysqli $customDb = null, array $customSchool = []): array
{
    $db = $customDb ?: get_db();
    $logs = [];

    $sqlPath = dirname(__DIR__) . '/database.sql';
    if (file_exists($sqlPath)) {
        // Impor skema resmi dari database.sql
        $importRes = import_sql_file($db, $sqlPath);
        $logs[] = $importRes['message'];
    } else {
        // Fallback jika database.sql tidak ditemukan
        mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 0;");
        mysqli_query($db, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

        // 1. admin
        mysqli_query($db, "DROP TABLE IF EXISTS admin;");
        mysqli_query($db, "CREATE TABLE admin (id INT AUTO_INCREMENT PRIMARY KEY, user VARCHAR(50) NOT NULL UNIQUE, pass VARCHAR(255) NOT NULL, nama VARCHAR(100) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        mysqli_query($db, "INSERT INTO admin (user, pass, nama) VALUES ('admin', 'admin', 'Administrator CBT');");

        // 2. mapel
        mysqli_query($db, "DROP TABLE IF EXISTS mapel;");
        mysqli_query($db, "CREATE TABLE mapel (id INT PRIMARY KEY, sekolah VARCHAR(150) NOT NULL, npsn VARCHAR(20) NOT NULL, ks VARCHAR(100) NOT NULL, hari VARCHAR(10) NOT NULL DEFAULT '1', kode VARCHAR(10) NOT NULL DEFAULT 'N02', gemini_key VARCHAR(255) NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, mapel_hari1 VARCHAR(100) DEFAULT 'Matematika', mapel_hari2 VARCHAR(100) DEFAULT 'Bahasa Indonesia', soal_hari1 VARCHAR(50) DEFAULT 'conf31', soal_hari2 VARCHAR(50) DEFAULT 'conf32', ip_server VARCHAR(50) DEFAULT '192.168.0.200', server_id VARCHAR(50) DEFAULT 'SRV-01', proktor VARCHAR(100) DEFAULT 'Proktor Utama', teknisi VARCHAR(100) DEFAULT 'Teknisi CBT') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        mysqli_query($db, "INSERT INTO mapel (id, sekolah, npsn, ks, hari, kode) VALUES (1, 'SMP NEGERI 1 DEMAK', '20319280', 'Drs. H. Sugiyanto, M.Pd.', '1', 'N02');");

        // 3. jadwal_mapel
        mysqli_query($db, "DROP TABLE IF EXISTS jadwal_mapel;");
        mysqli_query($db, "CREATE TABLE jadwal_mapel (id INT AUTO_INCREMENT PRIMARY KEY, hari VARCHAR(10) NOT NULL UNIQUE, nama_hari VARCHAR(50) DEFAULT NULL, mapel VARCHAR(100) NOT NULL, kode_mapel VARCHAR(20) DEFAULT NULL, folder_soal VARCHAR(50) NOT NULL DEFAULT 'conf31', durasi_menit INT NOT NULL DEFAULT 120, aktif TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        mysqli_query($db, "INSERT INTO jadwal_mapel (hari, nama_hari, mapel, kode_mapel, folder_soal, durasi_menit, aktif) VALUES ('1', 'Hari ke-1', 'Matematika', 'MAT', 'conf31', 120, 1), ('2', 'Hari ke-2', 'Bahasa Indonesia', 'BIN', 'conf32', 120, 0);");

        // 4. atur
        mysqli_query($db, "DROP TABLE IF EXISTS atur;");
        mysqli_query($db, "CREATE TABLE atur (id INT AUTO_INCREMENT PRIMARY KEY, sesi VARCHAR(10) NOT NULL, ruang VARCHAR(10) NOT NULL, token VARCHAR(10) NOT NULL DEFAULT 'DEMAK1', token2 VARCHAR(10) DEFAULT 'DEMAK2', token3 VARCHAR(10) DEFAULT 'DEMAK3', token4 VARCHAR(10) DEFAULT 'DEMAK4', token5 VARCHAR(10) DEFAULT 'DEMAK5', token6 VARCHAR(10) DEFAULT 'DEMAK6', ujian_aktif ENUM('0','1') NOT NULL DEFAULT '1', folder_soal VARCHAR(20) NOT NULL DEFAULT 'conf31', hari VARCHAR(10) NOT NULL DEFAULT '1', durasi_menit INT NOT NULL DEFAULT 120, ket VARCHAR(100) DEFAULT NULL, INDEX idx_sesi_ruang (sesi, ruang)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        mysqli_query($db, "INSERT INTO atur (sesi, ruang, token, ujian_aktif, folder_soal, hari, durasi_menit, ket) VALUES ('1', '1', 'DEMAK1', '1', 'conf31', '1', 120, 'Sesi 1 Ruang 1'), ('2', '1', 'DEMAK2', '1', 'conf31', '1', 120, 'Sesi 2 Ruang 1'), ('3', '1', 'DEMAK3', '1', 'conf31', '1', 120, 'Sesi 3 Ruang 1');");

        // 5. account
        mysqli_query($db, "DROP TABLE IF EXISTS account;");
        $colSoal = [];
        for ($i = 1; $i <= 100; $i++) $colSoal[] = "a{$i} INT NOT NULL DEFAULT {$i}";
        $colSoalSql = implode(",\n        ", $colSoal);
        mysqli_query($db, "CREATE TABLE account (id INT AUTO_INCREMENT PRIMARY KEY, kelas VARCHAR(20) NOT NULL, abs VARCHAR(10) NOT NULL, nama VARCHAR(120) NOT NULL, user VARCHAR(50) NOT NULL UNIQUE, pass VARCHAR(50) NOT NULL, sesi VARCHAR(10) NOT NULL DEFAULT '1', ruang VARCHAR(10) NOT NULL DEFAULT '1', status ENUM('0','1','2','3') NOT NULL DEFAULT '0', sesi_aktif VARCHAR(50) DEFAULT NULL, ragu TEXT DEFAULT NULL, pelanggaran INT DEFAULT 0, {$colSoalSql}, INDEX idx_kelas (kelas), INDEX idx_sesi_ruang (sesi, ruang), INDEX idx_status (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 6. pg_tb
        mysqli_query($db, "DROP TABLE IF EXISTS pg_tb;");
        $colJwb = [];
        for ($i = 1; $i <= 100; $i++) $colJwb[] = "j{$i} VARCHAR(30) DEFAULT NULL";
        $colJwbSql = implode(",\n        ", $colJwb);
        mysqli_query($db, "CREATE TABLE pg_tb (id INT PRIMARY KEY, sekolah VARCHAR(100) DEFAULT '', nama VARCHAR(120) DEFAULT '', kelas VARCHAR(20) DEFAULT '', abs VARCHAR(10) DEFAULT '', mapel VARCHAR(50) DEFAULT 'Matematika', {$colJwbSql}, waktu_mulai DATETIME DEFAULT NULL, waktu_selesai DATETIME DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 7. pg_tb_2 s.d. pg_tb_5
        for ($d = 2; $d <= 5; $d++) {
            $t = 'pg_tb_' . $d;
            mysqli_query($db, "DROP TABLE IF EXISTS `{$t}`;");
            mysqli_query($db, "CREATE TABLE `{$t}` LIKE pg_tb;");
        }

        // 8. daft_sklh
        mysqli_query($db, "DROP TABLE IF EXISTS daft_sklh;");
        mysqli_query($db, "CREATE TABLE daft_sklh (id INT AUTO_INCREMENT PRIMARY KEY, nama_sekolah VARCHAR(150) NOT NULL, npsn VARCHAR(20) NOT NULL, ks VARCHAR(100) NOT NULL, kode VARCHAR(10) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 9. kunci_jawaban
        mysqli_query($db, "DROP TABLE IF EXISTS kunci_jawaban;");
        mysqli_query($db, "CREATE TABLE kunci_jawaban (id INT AUTO_INCREMENT PRIMARY KEY, paket VARCHAR(50) NOT NULL UNIQUE, kunci LONGTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 10. broadcast_pesan
        mysqli_query($db, "DROP TABLE IF EXISTS broadcast_pesan;");
        mysqli_query($db, "CREATE TABLE broadcast_pesan (id INT AUTO_INCREMENT PRIMARY KEY, pesan TEXT NOT NULL, aktif TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 11. token_tb
        mysqli_query($db, "DROP TABLE IF EXISTS token_tb;");
        mysqli_query($db, "CREATE TABLE token_tb (id INT PRIMARY KEY, token VARCHAR(10) NOT NULL DEFAULT 'DEMAK1') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        mysqli_query($db, "INSERT INTO token_tb (id, token) VALUES (1, 'DEMAK1');");

        mysqli_query($db, "SET FOREIGN_KEY_CHECKS = 1;");
    }

    // Terapkan kustomisasi admin & sekolah jika ada
    if (!empty($customSchool['admin_user']) && !empty($customSchool['admin_pass'])) {
        $adminUser = $customSchool['admin_user'];
        $adminPass = $customSchool['admin_pass'];
        $adminNama = $customSchool['admin_nama'] ?? 'Administrator CBT';

        $stmtAdmin = mysqli_prepare($db, "INSERT INTO admin (id, user, pass, nama) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE user=VALUES(user), pass=VALUES(pass), nama=VALUES(nama)");
        if ($stmtAdmin) {
            mysqli_stmt_bind_param($stmtAdmin, 'sss', $adminUser, $adminPass, $adminNama);
            mysqli_stmt_execute($stmtAdmin);
            mysqli_stmt_close($stmtAdmin);
            $logs[] = "Akun Administrator ({$adminUser}) berhasil dikonfigurasi.";
        }
    }

    if (!empty($customSchool['sekolah'])) {
        $sekolah = $customSchool['sekolah'];
        $npsn    = $customSchool['npsn'] ?? '20319280';
        $ks      = $customSchool['ks'] ?? 'Kepala Sekolah';

        $stmtSchool = mysqli_prepare($db, "UPDATE mapel SET sekolah = ?, npsn = ?, ks = ? WHERE id = 1");
        if ($stmtSchool) {
            mysqli_stmt_bind_param($stmtSchool, 'sss', $sekolah, $npsn, $ks);
            mysqli_stmt_execute($stmtSchool);
            mysqli_stmt_close($stmtSchool);
            $logs[] = "Profil sekolah ({$sekolah}) berhasil dikonfigurasi.";
        }
    }

    // Jalankan verifikasi integritas 14 tabel
    $verified = verify_cbt_tables($db);
    foreach ($verified as $tbl => $desc) {
        $logs[] = "Tabel `{$tbl}` terverifikasi OK ({$desc}).";
    }

    return $logs;
}
