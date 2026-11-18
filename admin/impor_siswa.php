<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$msg = '';
$msgType = '';
$importedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Terjadi kesalahan saat mengunggah file Excel.';
        $msgType = 'danger';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'], true)) {
            $msg = 'Format file harus berupa Excel (.xlsx atau .xls).';
            $msgType = 'danger';
        } else {
            $targetPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import_siswa_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                require_once dirname(__DIR__) . '/app/vendor/php-excel-reader/excel_reader2.php';
                require_once dirname(__DIR__) . '/app/vendor/SpreadsheetReader.php';

                try {
                    $reader = new SpreadsheetReader($targetPath);
                    $sheets = $reader->Sheets();
                    $reader->ChangeSheet(0);

                    $cols = "kelas, abs, nama, user, pass, sesi, ruang, status";
                    for ($i = 1; $i <= 50; $i++) $cols .= ", a{$i}";

                    foreach ($reader as $row) {
                        $kelas = isset($row[0]) ? trim((string) $row[0]) : '';
                        $abs   = isset($row[1]) ? trim((string) $row[1]) : '';
                        $nama  = isset($row[2]) ? trim((string) $row[2]) : '';
                        $user  = isset($row[3]) ? trim((string) $row[3]) : '';
                        $pass  = isset($row[4]) ? trim((string) $row[4]) : '';
                        $sesi  = isset($row[5]) ? trim((string) $row[5]) : '1';
                        $ruang = isset($row[6]) ? trim((string) $row[6]) : '1';

                        // Lewati baris header
                        if (strtolower($kelas) === 'kelas' || strtolower($nama) === 'nama' || $nama === '') {
                            continue;
                        }

                        // Generate acak nomor 1-50
                        $order = range(1, 50);
                        shuffle($order);
                        $orderVals = implode(', ', $order);

                        $stmt = mysqli_prepare($db, "INSERT INTO account ({$cols}) VALUES (?, ?, ?, ?, ?, ?, ?, '0', {$orderVals}) ON DUPLICATE KEY UPDATE kelas=VALUES(kelas), abs=VALUES(abs), nama=VALUES(nama), pass=VALUES(pass), sesi=VALUES(sesi), ruang=VALUES(ruang)");
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 'sssssss', $kelas, $abs, $nama, $user, $pass, $sesi, $ruang);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            $importedCount++;
                        }
                    }

                    @unlink($targetPath);
                    $msg = "Berhasil mengimpor {$importedCount} data peserta dari Excel.";
                    $msgType = 'success';
                } catch (Exception $e) {
                    @unlink($targetPath);
                    $msg = "Gagal memproses file Excel: " . $e->getMessage();
                    $msgType = 'danger';
                }
            }
        }
    }
}

admin_header('Impor Peserta dari Excel', 'impor');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<div class="panel-card" style="max-width: 680px;">
    <div class="panel-header">
        <div>
            <h2>Unggah Berkas Excel</h2>
            <p>Masukkan data akun siswa secara massal menggunakan file Excel.</p>
        </div>
    </div>

    <form method="post" action="" enctype="multipart/form-data">
        <div class="form-group" style="margin-bottom: 24px;">
            <label for="file_excel">Pilih File Excel (.xlsx / .xls)</label>
            <input type="file" name="file_excel" id="file_excel" class="form-control" accept=".xlsx, .xls" required>
            <small style="color: #64748b; margin-top: 6px; display: block;">Format kolom: <strong>Kolom A:</strong> Kelas, <strong>B:</strong> No Absen, <strong>C:</strong> Nama, <strong>D:</strong> Username, <strong>E:</strong> Password, <strong>F:</strong> Sesi, <strong>G:</strong> Ruang.</small>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
            <button type="submit" class="btn btn-primary">Mulai Impor Data &rarr;</button>
            <a href="<?php echo base_url('admin/siswa.php'); ?>" class="btn btn-secondary">Kembali ke Data Peserta</a>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
