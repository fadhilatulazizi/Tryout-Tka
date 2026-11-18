<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$school = get_school_profile();
$namaSekolah = $school['sekolah'];
$npsnSekolah = $school['npsn'];
$ksSekolah   = $school['ks'];

// Filter
$filterKelas = trim($_GET['kelas'] ?? '');
$filterRuang = trim($_GET['ruang'] ?? '');
$filterSesi  = trim($_GET['sesi'] ?? '');
$filterCari  = trim($_GET['cari'] ?? '');

$optKelas = mysqli_query($db, "SELECT DISTINCT kelas FROM account WHERE kelas != '' ORDER BY kelas ASC");
$optRuang = mysqli_query($db, "SELECT DISTINCT ruang FROM account WHERE ruang != '' ORDER BY ruang ASC");
$optSesi  = mysqli_query($db, "SELECT DISTINCT sesi FROM account WHERE sesi != '' ORDER BY sesi ASC");

$sql = "SELECT * FROM account WHERE 1=1";
$params = [];
$types = '';

if ($filterKelas !== '') {
    $sql .= " AND kelas = ?";
    $params[] = $filterKelas;
    $types .= 's';
}
if ($filterRuang !== '') {
    $sql .= " AND ruang = ?";
    $params[] = $filterRuang;
    $types .= 's';
}
if ($filterSesi !== '') {
    $sql .= " AND sesi = ?";
    $params[] = $filterSesi;
    $types .= 's';
}
if ($filterCari !== '') {
    $sql .= " AND (nama LIKE ? OR user LIKE ?)";
    $like = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$sql .= " ORDER BY kelas ASC, abs ASC, nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($db, $sql);
}

$totalCards = $result ? mysqli_num_rows($result) : 0;
$tglCetak = tgl_indo(date('Y-m-d'));
$tahunAjaran = date('Y') . '/' . (date('Y') + 1);

admin_header('Cetak Kartu Ujian', 'kartu');
?>

<style>
/* Styling Grid Kartu Peserta */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(460px, 1fr));
    gap: 18px;
    max-width: 1200px;
    margin: 0 auto;
}

.card-box {
    background: #ffffff;
    border: 1.5px dashed #94a3b8;
    border-radius: 6px;
    padding: 14px 16px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    page-break-inside: avoid;
    break-inside: avoid;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.card-header-clean {
    text-align: center;
    border-bottom: 1.5px solid #0f172a;
    padding-bottom: 5px;
    margin-bottom: 8px;
}

.card-title-clean {
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
}

.card-sub-clean {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    margin-top: 1px;
    display: block;
}

.card-body-content {
    display: flex;
    gap: 12px;
    margin-bottom: 8px;
}

.card-data-table {
    flex-grow: 1;
    border-collapse: collapse;
    font-size: 12px;
}

.card-data-table td {
    padding: 3px 2px;
    vertical-align: top;
}

.card-data-table .lbl {
    width: 100px;
    color: #334155;
    font-weight: 600;
}

.card-data-table .val {
    color: #0f172a;
    font-weight: 600;
}

.badge-mono-box {
    font-family: 'JetBrains Mono', monospace;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 700;
    color: #0f172a;
    display: inline-block;
}

.badge-pwd-box {
    background: #fef3c7;
    border-color: #fde68a;
    color: #92400e;
}

.card-photo-box {
    width: 85px;
    height: 105px;
    flex-shrink: 0;
    border: 1px dashed #94a3b8;
    background: #f8fafc;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 10px;
    font-weight: 600;
}

.card-footer-box {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 9.5px;
    line-height: 1.3;
    border-top: 1px dotted #cbd5e1;
    padding-top: 6px;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 6mm;
    }
    .cards-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 4mm 6mm !important;
        max-width: 100% !important;
        margin: 0 !important;
    }
    .card-box {
        height: 87mm !important;
        max-height: 87mm !important;
        border: 1px dashed #64748b !important;
        padding: 3.5mm 4mm !important;
        box-shadow: none !important;
    }
    .badge-mono-box {
        background: #f1f5f9 !important;
        border: 1px solid #94a3b8 !important;
        color: #000000 !important;
    }
}
</style>

<!-- Toolbar & Filter Bar -->
<div class="no-print mb-6">
    <div class="panel-card mb-0">
        <div class="panel-header">
            <div>
                <h2>Cetak Kartu Peserta Ujian</h2>
                <p><?php echo e($namaSekolah); ?> &bull; Tahun Ajaran <?php echo $tahunAjaran; ?></p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span class="badge badge-blue">Total <?php echo (int) $totalCards; ?> Kartu</span>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    🖨️ Cetak / Simpan PDF
                </button>
            </div>
        </div>

        <form method="get" action="" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="kelas">Filter Kelas</label>
                <select name="kelas" id="kelas" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Semua Kelas --</option>
                    <?php if ($optKelas): while ($k = mysqli_fetch_assoc($optKelas)): ?>
                        <option value="<?php echo e($k['kelas']); ?>" <?php echo $filterKelas === $k['kelas'] ? 'selected' : ''; ?>>
                            Kelas <?php echo e($k['kelas']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="ruang">Filter Ruang</label>
                <select name="ruang" id="ruang" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Semua Ruang --</option>
                    <?php if ($optRuang): while ($r = mysqli_fetch_assoc($optRuang)): ?>
                        <option value="<?php echo e($r['ruang']); ?>" <?php echo $filterRuang === $r['ruang'] ? 'selected' : ''; ?>>
                            Ruang <?php echo e($r['ruang']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="sesi">Filter Sesi</label>
                <select name="sesi" id="sesi" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Semua Sesi --</option>
                    <?php if ($optSesi): while ($s = mysqli_fetch_assoc($optSesi)): ?>
                        <option value="<?php echo e($s['sesi']); ?>" <?php echo $filterSesi === $s['sesi'] ? 'selected' : ''; ?>>
                            Sesi <?php echo e($s['sesi']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="cari">Cari Nama / User</label>
                <input type="text" name="cari" id="cari" class="form-control" placeholder="Ketik kata kunci..." value="<?php echo e($filterCari); ?>">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($filterKelas !== '' || $filterRuang !== '' || $filterSesi !== '' || $filterCari !== ''): ?>
                    <a href="<?php echo base_url('admin/kartu.php'); ?>" class="btn btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div style="margin-top: 14px; padding: 10px 14px; background: #f8fafc; border-left: 4px solid #0284c7; border-radius: 4px; font-size: 12px; color: #475569;">
            💡 <strong>Petunjuk Cetak:</strong> Gunakan kertas <strong>A4</strong> Portrait. Format tata letak dirancang pas memuat <strong>6 kartu per halaman A4</strong> lengkap dengan garis putus-putus panduan potong gunting.
        </div>
    </div>
</div>

<!-- Container Grid Kartu -->
<div class="cards-grid">
<?php if ($result && mysqli_num_rows($result) > 0): ?>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <div class="card-box">
            <div class="card-header-clean">
                <span class="card-title-clean">KARTU PESERTA UJIAN</span>
                <span class="card-sub-clean"><?php echo e($namaSekolah); ?> &bull; TP. <?php echo $tahunAjaran; ?></span>
            </div>

            <div class="card-body-content">
                <table class="card-data-table">
                    <tr>
                        <td class="lbl">No. / User</td>
                        <td style="width: 10px;">:</td>
                        <td class="val"><span class="badge-mono-box"><?php echo e($row['user']); ?></span></td>
                    </tr>
                    <tr>
                        <td class="lbl">Nama Lengkap</td>
                        <td>:</td>
                        <td class="val" style="font-size: 12.5px; font-weight: 700;"><?php echo e($row['nama']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Kelas / Absen</td>
                        <td>:</td>
                        <td class="val"><?php echo e($row['kelas']); ?> &bull; No. <?php echo e($row['abs']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Ruang / Sesi</td>
                        <td>:</td>
                        <td class="val">Ruang <?php echo e($row['ruang']); ?> &bull; Sesi <?php echo e($row['sesi']); ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Password</td>
                        <td>:</td>
                        <td class="val"><span class="badge-mono-box badge-pwd-box"><?php echo e($row['pass']); ?></span></td>
                    </tr>
                </table>

                <div class="card-photo-box">
                    <span>FOTO<br>3 x 4</span>
                </div>
            </div>

            <div class="card-footer-box">
                <div style="color: #64748b;">
                    * Wajib dibawa saat pelaksanaan ujian.<br>
                    * Jaga kerahasiaan username & password.
                </div>
                <div style="text-align: center; min-width: 140px;">
                    <div>Kepala Sekolah,</div>
                    <div style="height: 28px;"></div>
                    <div style="font-weight: 700; text-decoration: underline;"><?php echo e($ksSekolah); ?></div>
                    <div style="color: #64748b;">NIP. ........................................</div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <div style="grid-column: 1 / -1; background: #ffffff; padding: 40px; text-align: center; border-radius: 12px; border: 1px dashed #cbd5e1; color: #64748b;">
        <p style="font-size: 16px; font-weight: 600;">Tidak ada data peserta ditemukan.</p>
    </div>
<?php endif; ?>
</div>

<?php admin_footer(); ?>
