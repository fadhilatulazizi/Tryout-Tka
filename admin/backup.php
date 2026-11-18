<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';

Auth::requireAdmin();
$db = get_db();

// Jika aksi download SQL dump
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $tables = [];
    $qAllTables = mysqli_query($db, "SHOW TABLES");
    if ($qAllTables) {
        while ($tRow = mysqli_fetch_row($qAllTables)) {
            $tables[] = $tRow[0];
        }
    }
    if (empty($tables)) {
        $tables = ['admin', 'mapel', 'jadwal_mapel', 'account', 'atur', 'pg_tb', 'pg_tb_2', 'pg_tb_3', 'pg_tb_4', 'pg_tb_5', 'daft_sklh', 'kunci_jawaban', 'broadcast_pesan', 'token_tb'];
    }
    $dump = "-- ========================================================\n";
    $dump .= "-- CBT Asesmen Demak - Full Database Dump\n";
    $dump .= "-- Tanggal Backup: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "-- Versi Sistem: " . APP_VERSION . "\n";
    $dump .= "-- ========================================================\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach ($tables as $table) {
        $checkTable = mysqli_query($db, "SHOW TABLES LIKE '{$table}'");
        if (!$checkTable || mysqli_num_rows($checkTable) === 0) continue;

        $dump .= "-- --------------------------------------------------------\n";
        $dump .= "-- Struktur tabel `{$table}`\n";
        $dump .= "-- --------------------------------------------------------\n";
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";

        $createRes = mysqli_query($db, "SHOW CREATE TABLE `{$table}`");
        $createRow = mysqli_fetch_row($createRes);
        $dump .= $createRow[1] . ";\n\n";

        $dump .= "-- Data tabel `{$table}`\n";
        $dataRes = mysqli_query($db, "SELECT * FROM `{$table}`");
        $numRows = mysqli_num_rows($dataRes);

        if ($numRows > 0) {
            $dump .= "INSERT INTO `{$table}` VALUES\n";
            $rowIndex = 0;
            while ($row = mysqli_fetch_row($dataRes)) {
                $rowIndex++;
                $dump .= "(";
                for ($j = 0; $j < count($row); $j++) {
                    if (is_null($row[$j])) {
                        $dump .= "NULL";
                    } else {
                        $dump .= "'" . mysqli_real_escape_string($db, $row[$j]) . "'";
                    }
                    if ($j < count($row) - 1) $dump .= ", ";
                }
                $dump .= ($rowIndex === $numRows) ? ");\n\n" : "),\n";
            }
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $dump .= "-- Selesai.\n";

    $filename = "Backup_CBT_Demak_" . date('Ymd_His') . ".sql";
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

require_once __DIR__ . '/layout.php';
admin_header('Cadangkan Database', 'backup');
?>

<div class="panel-card" style="max-width: 680px;">
    <div class="panel-header">
        <div>
            <h2>Backup & Salinan Database</h2>
            <p>Unduh seluruh data peserta, hasil ujian, konfigurasi sesi, dan sekolah dalam 1 berkas SQL.</p>
        </div>
    </div>

    <div style="background: #f8fafc; border-radius: 10px; padding: 24px; border: 1px solid var(--border); margin-bottom: 24px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Unduh Berkas SQL Lengkap</h3>
        <p style="font-size: 13.5px; color: #64748b; line-height: 1.6; margin-bottom: 20px;">
            File backup mencakup struktur dan data tabel <code>admin</code>, <code>mapel</code>, <code>account</code>, <code>atur</code>, <code>pg_tb</code>, <code>pg_tb_2</code>, dan <code>daft_sklh</code>. Berkas ini dapat langsung di-restore kembali melalui phpMyAdmin sewaktu-waktu.
        </p>

        <a href="<?php echo base_url('admin/backup.php?action=download'); ?>" class="btn btn-primary" style="padding: 12px 24px; font-size: 14.5px;">
            💾 Unduh File Backup Database (.sql) &rarr;
        </a>
    </div>

    <div style="font-size: 12.5px; color: #64748b; line-height: 1.6;">
        💡 <strong>Saran Rutin:</strong> Selalu unduh backup database sebelum melakukan update aplikasi atau pergantian sesi ujian agar seluruh rekaman nilai siswa aman.
    </div>
</div>

<?php admin_footer(); ?>
