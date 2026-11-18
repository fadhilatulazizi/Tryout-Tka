<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sekolah = trim($_POST['sekolah'] ?? '');
    $npsn = trim($_POST['npsn'] ?? '');
    $ks = trim($_POST['ks'] ?? '');
    $kode = trim($_POST['kode'] ?? '');
    $gemini_key = trim($_POST['gemini_key'] ?? '');

    if ($sekolah === '') {
        $msg = "Nama Sekolah tidak boleh kosong.";
        $msgType = "danger";
    } elseif ($npsn === '') {
        $msg = "NPSN Sekolah tidak boleh kosong.";
        $msgType = "danger";
    } else {
        // Cek apakah data sekolah id=1 sudah ada di tabel mapel
        $check = mysqli_query($db, "SELECT id FROM mapel WHERE id = 1 LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $stmt = mysqli_prepare($db, "UPDATE mapel SET sekolah = ?, npsn = ?, ks = ?, kode = ?, gemini_key = ? WHERE id = 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss', $sekolah, $npsn, $ks, $kode, $gemini_key);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = "Data identitas sekolah dan Google Gemini API Key berhasil disimpan langsung ke database.";
                $msgType = 'success';
            } else {
                $msg = "Gagal memperbarui database: " . mysqli_error($db);
                $msgType = 'danger';
            }
        } else {
            $stmt = mysqli_prepare($db, "INSERT INTO mapel (id, sekolah, npsn, ks, kode, gemini_key) VALUES (1, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssss', $sekolah, $npsn, $ks, $kode, $gemini_key);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = "Data identitas sekolah dan Google Gemini API Key berhasil disimpan langsung ke database.";
                $msgType = 'success';
            } else {
                $msg = "Gagal menyimpan ke database: " . mysqli_error($db);
                $msgType = 'danger';
            }
        }
    }
}

// Ambil profil sekolah terbaru
$school = get_school_profile();

admin_header('Pengaturan Sekolah', 'sekolah');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>" style="margin-bottom: 20px;">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<div class="panel-card" style="max-width: 680px;">
    <div class="panel-header">
        <div>
            <h2>Data Identitas Sekolah</h2>
            <p>Input langsung data identitas sekolah Anda. Informasi ini akan ditampilkan pada kop kartu peserta, lembar ujian, dan berita acara.</p>
        </div>
    </div>

    <form method="post" action="">
        <div class="form-group">
            <label for="sekolah">Nama Sekolah <span style="color: #ef4444;">*</span></label>
            <input type="text" name="sekolah" id="sekolah" class="form-control" value="<?php echo e($school['sekolah']); ?>" placeholder="Contoh: SMP NEGERI 1 DEMAK" required autofocus>
            <small style="color: #64748b; margin-top: 4px;">Nama resmi satuan pendidikan / sekolah penyelenggara.</small>
        </div>

        <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 14px;">
            <div class="form-group">
                <label for="npsn">NPSN <span style="color: #ef4444;">*</span></label>
                <input type="text" name="npsn" id="npsn" class="form-control" value="<?php echo e($school['npsn']); ?>" placeholder="Contoh: 20319280" required>
                <small style="color: #64748b; margin-top: 4px;">Nomor Pokok Sekolah Nasional (8 digit angka).</small>
            </div>
            <div class="form-group">
                <label for="kode">Kode Sekolah</label>
                <input type="text" name="kode" id="kode" class="form-control" value="<?php echo e($school['kode']); ?>" placeholder="Contoh: N02 / 01-001">
                <small style="color: #64748b; margin-top: 4px;">Kode registrasi atau singkatan kode sekolah.</small>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label for="ks">Nama Kepala Sekolah <span style="color: #ef4444;">*</span></label>
            <input type="text" name="ks" id="ks" class="form-control" value="<?php echo e($school['ks']); ?>" placeholder="Contoh: Drs. H. Sugiyanto, M.Pd." required>
            <small style="color: #64748b; margin-top: 4px;">Nama lengkap Kepala Sekolah beserta gelar untuk penandatanganan dokumen.</small>
        </div>

        <div class="form-group" style="margin-bottom: 24px; background: #f8fafc; border: 1.5px solid #bae6fd; border-radius: 10px; padding: 14px;">
            <label for="gemini_key" style="font-weight: 700; color: #0369a1; display: flex; justify-content: space-between; align-items: center;">
                <span>🔑 Google Gemini API Key (Untuk Fitur Kunci Jawaban AI)</span>
                <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" style="font-size: 11px; color: #0284c7; text-decoration: underline;">
                    Dapatkan Key Gratis &rarr;
                </a>
            </label>
            <input type="password" name="gemini_key" id="gemini_key" class="form-control" value="<?php echo e($school['gemini_key'] ?? ''); ?>" placeholder="Tempelkan API Key di sini (contoh: AIzaSy...)" style="font-family: monospace;">
            <small style="color: #64748b; margin-top: 4px; display: block;">
                API Key ini disimpan aman di database server dan digunakan untuk menganalisis serta meng-generate kunci jawaban butir soal secara otomatis.
            </small>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                💾 Simpan Identitas Sekolah
            </button>
            <a href="<?php echo base_url('admin/index.php'); ?>" class="btn btn-secondary">
                Kembali ke Beranda
            </a>
        </div>
    </form>
</div>

<?php admin_footer(); ?>
