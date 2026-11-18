<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

Auth::requireAdmin();
$msg = '';
$msgType = '';
$checkResult = null;

$selectedFolder = trim($_GET['folder'] ?? 'conf31');
if (!preg_match('/^conf[a-zA-Z0-9_-]+$/', $selectedFolder)) {
    $selectedFolder = 'conf31';
}

// Cek Metadata Server Luar
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    try {
        $checkResult = SyncEngine::checkRemoteMetadata($selectedFolder);
        $msg = "Metadata server luar untuk paket {$selectedFolder}.zip berhasil diperoleh.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Gagal memeriksa server luar: " . $e->getMessage();
        $msgType = 'danger';
    }
}

// Unduh dan Ekstrak ZIP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync') {
    $syncFolder = trim($_POST['folder'] ?? 'conf31');
    try {
        $res = SyncEngine::downloadAndExtract($syncFolder);
        $msg = "Sinkronisasi berhasil! {$res['files_extracted']} file dari paket {$syncFolder}.zip berhasil diekstrak ke folder soal/{$syncFolder}/.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = "Gagal melakukan sinkronisasi: " . $e->getMessage();
        $msgType = 'danger';
    }
}

$localPackages = SyncEngine::getLocalPackages();

require_once __DIR__ . '/layout.php';
admin_header('Sinkronisasi Soal Server Luar', 'sinkron');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2>Pemeriksaan & Sinkronisasi Paket Soal</h2>
            <p>Sumber Resmi Dindikbud: <code>https://smp1bonang.sch.id/demak/[paket].zip</code></p>
        </div>
    </div>

    <!-- Pilih Paket -->
    <div style="background: #f8fafc; border-radius: 10px; padding: 20px; margin-bottom: 24px; border: 1px solid var(--border);">
        <form method="get" action="" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; min-width: 260px;">
                <label for="folder">Nama Paket Soal (ZIP Server Dinas)</label>
                <input type="text" name="folder" id="folder" list="folder-presets" class="form-control" value="<?php echo e($selectedFolder); ?>" placeholder="Contoh: conf33" required style="font-family: monospace; font-size: 14px; font-weight: 700;">
                <datalist id="folder-presets">
                    <option value="conf31">Paket conf31 (Matematika)</option>
                    <option value="conf32">Paket conf32 (Bahasa Indonesia)</option>
                    <option value="conf33">Paket conf33 (Paket Baru)</option>
                    <option value="conf34">Paket conf34 (Paket Baru)</option>
                    <?php foreach ($localPackages as $lp): ?>
                        <option value="<?php echo $lp['folder']; ?>">Paket <?php echo $lp['folder']; ?> (Lokal)</option>
                    <?php endforeach; ?>
                </datalist>
                <span style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">Ketik nama paket seperti <code>conf33</code>, <code>conf34</code>, dst. untuk cek ketersediaan file ZIP.</span>
            </div>
            <input type="hidden" name="action" value="check">
            <button type="submit" class="btn btn-primary">🔍 Periksa Server Luar</button>
        </form>
    </div>

    <?php if ($checkResult): ?>
        <div style="border: 2px solid #0284c7; border-radius: 12px; padding: 24px; background: #f0f9ff; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0369a1; margin-bottom: 12px;">Hasil Pemeriksaan Server Luar</h3>
            <table class="detail-table" style="max-width: 500px; margin-bottom: 18px;">
                <tr>
                    <td style="color: #64748b; font-weight: 600; padding: 4px 0;">URL Paket Soal:</td>
                    <td style="font-weight: 700; color: #0f172a;"><?php echo e($checkResult['url']); ?></td>
                </tr>
                <tr>
                    <td style="color: #64748b; font-weight: 600; padding: 4px 0;">Ukuran Berkas ZIP:</td>
                    <td style="font-weight: 700; color: #0f172a;"><?php echo e($checkResult['size_formatted']); ?> (<?php echo number_format($checkResult['size']); ?> bytes)</td>
                </tr>
                <tr>
                    <td style="color: #64748b; font-weight: 600; padding: 4px 0;">Waktu Update Terakhir:</td>
                    <td style="font-weight: 700; color: #0f172a;"><?php echo e($checkResult['last_modified']); ?></td>
                </tr>
            </table>

            <form method="post" action="">
                <input type="hidden" name="action" value="sync">
                <input type="hidden" name="folder" value="<?php echo e($selectedFolder); ?>">
                <button type="submit" class="btn btn-success" style="padding: 11px 24px; font-size: 14px;" onclick="return confirm('Mulai mengunduh dan memasang paket soal <?php echo e($selectedFolder); ?>.zip?')">
                    ⬇️ Unduh & Pasang Paket Soal Ini Sekarang
                </button>
            </form>
        </div>
    <?php endif; ?>

    <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 14px;">Status Paket Soal di Server Lokal</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Folder Paket</th>
                    <th>Jumlah File Soal (.php)</th>
                    <th>Jalur Direktori</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($localPackages as $lp): ?>
                    <tr>
                        <td><span class="badge-mono" style="font-size: 13px;"><?php echo $lp['folder']; ?></span></td>
                        <td><strong><?php echo $lp['total_soal']; ?></strong> / 50 file soal</td>
                        <td><code>soal/<?php echo $lp['folder']; ?>/</code></td>
                        <td>
                            <?php if ($lp['total_soal'] >= 50): ?>
                                <span class="badge badge-green">Lengkap (50 Soal)</span>
                            <?php else: ?>
                                <span class="badge badge-amber"><?php echo $lp['total_soal']; ?> Soal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo base_url('admin/sinkron_soal.php?folder=' . $lp['folder'] . '&action=check'); ?>" class="btn btn-secondary btn-sm">Cek Server</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_footer(); ?>
