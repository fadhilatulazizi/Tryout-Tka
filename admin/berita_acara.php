<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$school = get_school_profile();
$allJadwal = get_all_jadwal();
$activeJadwal = get_active_jadwal();

// Filter & Parameter
$mode   = trim($_GET['mode'] ?? 'berita'); // 'berita' atau 'hadir'
$sesi   = trim($_GET['sesi'] ?? '1');
$ruang  = trim($_GET['ruang'] ?? '1');
$hari   = trim($_GET['hari'] ?? ($activeJadwal['hari'] ?? $school['hari'] ?? '1'));
$pengawas = trim($_GET['pengawas'] ?? 'Pengawas Ruang ' . $ruang);
$tanpaKop = isset($_GET['tanpa_kop']) ? (int)$_GET['tanpa_kop'] : 1; // Default 1 (Tanpa Kop)

$curJadwal = get_jadwal_by_hari($hari);
$mapelName = !empty($curJadwal['mapel']) ? $curJadwal['mapel'] : ($hari === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : ($school['mapel_hari2'] ?? 'Bahasa Indonesia'));

// Ambil daftar sesi & ruang yang tersedia untuk dropdown
$qOptSesi = mysqli_query($db, "SELECT DISTINCT sesi FROM account WHERE sesi != '' ORDER BY sesi ASC");
$qOptRuang = mysqli_query($db, "SELECT DISTINCT ruang FROM account WHERE ruang != '' ORDER BY ruang ASC");

// Ambil peserta pada sesi & ruang ini
$sqlPeserta = "SELECT * FROM account WHERE sesi = ? AND ruang = ? ORDER BY kelas ASC, abs ASC, nama ASC";
$stmtP = mysqli_prepare($db, $sqlPeserta);
mysqli_stmt_bind_param($stmtP, 'ss', $sesi, $ruang);
mysqli_stmt_execute($stmtP);
$resPeserta = mysqli_stmt_get_result($stmtP);

$pesertaList = [];
$totalSiswa = 0;
$totalHadir = 0;
$totalTidakHadir = 0;

if ($resPeserta) {
    while ($row = mysqli_fetch_assoc($resPeserta)) {
        $pesertaList[] = $row;
        $totalSiswa++;
        if ($row['status'] === '1' || $row['status'] === '2') {
            $totalHadir++;
        } else {
            $totalTidakHadir++;
        }
    }
}

$tglPelaksanaan = tgl_indo(date('Y-m-d'));

admin_header('Berita Acara & Daftar Hadir Ujian', 'berita_acara');
?>

<style>
/* Kontrol Dokumen */
.doc-control-card {
    background: #ffffff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
}

/* Kertas Cetak A4 */
.paper-sheet {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    max-width: 820px;
    margin: 0 auto 30px auto;
    padding: 36px 42px;
    box-sizing: border-box;
    color: #0f172a;
    font-family: 'Times New Roman', Times, serif;
    line-height: 1.4;
}

.kop-wrapper {
    display: flex;
    align-items: center;
    border-bottom: 3px double #000000;
    padding-bottom: 12px;
    margin-bottom: 18px;
    text-align: center;
}

.kop-logo {
    width: 65px;
    height: 65px;
    flex-shrink: 0;
}

.kop-text {
    flex: 1;
}

.kop-text h3 {
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    margin: 0;
    letter-spacing: 0.5px;
}

.kop-text h2 {
    font-size: 18px;
    font-weight: 800;
    text-transform: uppercase;
    margin: 2px 0;
    color: #000;
}

.kop-text p {
    font-size: 11px;
    margin: 0;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
}

.doc-title {
    text-align: center;
    font-size: 15px;
    font-weight: 800;
    text-transform: uppercase;
    text-decoration: underline;
    margin-bottom: 4px;
}

.doc-subtitle {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 18px;
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
}

.meta-table {
    width: 100%;
    margin-bottom: 16px;
    font-size: 12.5px;
    border-collapse: collapse;
}

.meta-table td {
    padding: 3px 4px;
    vertical-align: top;
}

.formal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
    margin: 14px 0;
}

.formal-table th, .formal-table td {
    border: 1px solid #000000;
    padding: 5px 6px;
}

.formal-table th {
    background: #f1f5f9;
    text-align: center;
    font-weight: 800;
}

.ttd-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-top: 36px;
    text-align: center;
    font-size: 12px;
    page-break-inside: avoid;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 10mm;
    }
    .doc-control-card, .admin-sidebar, .admin-topbar, .no-print {
        display: none !important;
    }
    .admin-main {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .admin-content {
        padding: 0 !important;
    }
    .paper-sheet {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
}
</style>

<!-- KONTROL & FILTER DOKUMEN -->
<div class="doc-control-card no-print">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
        <div>
            <h2 style="font-size: 16px; font-weight: 800; color: #0f172a;">Cetak Berita Acara & Daftar Hadir Ujian</h2>
            <p style="font-size: 12px; color: #64748b;">Dokumen resmi administrasi pelaksanaan ruang asesmen berbasis komputer.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                🖨️ Cetak Dokumen Ini (A4)
            </button>
        </div>
    </div>

    <form method="get" action="" style="display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 12px; font-weight: 700;">Jenis Dokumen</label>
            <select name="mode" class="form-control" onchange="this.form.submit()">
                <option value="berita" <?php echo $mode === 'berita' ? 'selected' : ''; ?>>📄 Berita Acara Ujian</option>
                <option value="hadir" <?php echo $mode === 'hadir' ? 'selected' : ''; ?>>📋 Daftar Hadir Peserta</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 12px; font-weight: 700;">Sesi</label>
            <select name="sesi" class="form-control" onchange="this.form.submit()">
                <?php if ($qOptSesi && mysqli_num_rows($qOptSesi) > 0): ?>
                    <?php while ($os = mysqli_fetch_assoc($qOptSesi)): ?>
                        <option value="<?php echo e($os['sesi']); ?>" <?php echo $sesi === $os['sesi'] ? 'selected' : ''; ?>>Sesi <?php echo e($os['sesi']); ?></option>
                    <?php endwhile; ?>
                <?php else: ?>
                    <option value="1">Sesi 1</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 12px; font-weight: 700;">Ruang</label>
            <select name="ruang" class="form-control" onchange="this.form.submit()">
                <?php if ($qOptRuang && mysqli_num_rows($qOptRuang) > 0): ?>
                    <?php while ($or = mysqli_fetch_assoc($qOptRuang)): ?>
                        <option value="<?php echo e($or['ruang']); ?>" <?php echo $ruang === $or['ruang'] ? 'selected' : ''; ?>>Ruang <?php echo e($or['ruang']); ?></option>
                    <?php endwhile; ?>
                <?php else: ?>
                    <option value="1">Ruang 1</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 12px; font-weight: 700;">Hari / Mapel</label>
            <select name="hari" class="form-control" onchange="this.form.submit()">
                <?php if (!empty($allJadwal)): ?>
                    <?php foreach ($allJadwal as $j): ?>
                        <?php $jHari = (string)$j['hari']; ?>
                        <option value="<?php echo e($jHari); ?>" <?php echo (string)$hari === $jHari ? 'selected' : ''; ?>>
                            <?php echo e($j['nama_hari'] ?: ('Hari ke-' . $jHari)); ?> (<?php echo e($j['mapel']); ?>)
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="1" <?php echo $hari === '1' ? 'selected' : ''; ?>>Hari ke-1 (<?php echo e($school['mapel_hari1'] ?? 'Matematika'); ?>)</option>
                    <option value="2" <?php echo $hari === '2' ? 'selected' : ''; ?>>Hari ke-2 (<?php echo e($school['mapel_hari2'] ?? 'Bahasa Indonesia'); ?>)</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 180px;">
            <label style="font-size: 12px; font-weight: 700;">Nama Pengawas Ruang</label>
            <input type="text" name="pengawas" class="form-control" value="<?php echo e($pengawas); ?>" placeholder="Nama Guru Pengawas">
        </div>

        <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
            <label style="font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1.5px solid #cbd5e1;">
                <input type="hidden" name="tanpa_kop" value="0">
                <input type="checkbox" name="tanpa_kop" value="1" <?php echo $tanpaKop === 1 ? 'checked' : ''; ?> onchange="this.form.submit()">
                <span>📄 Cetak Tanpa Kop</span>
            </label>
        </div>

        <button type="submit" class="btn btn-secondary">Terapkan Filter</button>
    </form>
</div>

<!-- LEMBAR KERTAS FISIK A4 -->
<div class="paper-sheet">

    <?php if ($tanpaKop === 0): ?>
    <!-- KOP RESMI (Opsional) -->
    <div class="kop-wrapper">
        <div class="kop-logo">
            <svg viewBox="0 0 24 24" fill="#0f172a" style="width: 100%; height: 100%;">
                <path d="M12 3L1 9L12 15L21 10.09V17H23V9M5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z"/>
            </svg>
        </div>
        <div class="kop-text">
            <h3>DINAS PENDIDIKAN DAN KEBUDAYAAN</h3>
            <h2><?php echo e($school['sekolah']); ?></h2>
            <p>NPSN: <?php echo e($school['npsn']); ?> &bull; Tahun Ajaran <?php echo date('Y'); ?>/<?php echo (date('Y') + 1); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mode === 'berita'): ?>
        <!-- DOKUMEN 1: BERITA ACARA UJIAN -->
        <div class="doc-title" style="<?php echo $tanpaKop === 1 ? 'margin-top: 10px;' : ''; ?>">BERITA ACARA PELAKSANAAN ASESMEN</div>
        <div class="doc-subtitle">UJIAN BERBASIS KOMPUTER (CBT) &bull; TAHUN <?php echo date('Y'); ?></div>

        <p style="font-size: 12.5px; text-indent: 32px; margin-bottom: 14px; text-align: justify;">
            Pada hari ini <strong><?php echo tgl_indo(date('Y-m-d')); ?></strong>, di <strong><?php echo e($school['sekolah']); ?></strong> telah diselenggarakan kegiatan Asesmen Berbasis Komputer (CBT) dengan ketentuan pelaksanaan sebagai berikut:
        </p>

        <table class="meta-table">
            <tr>
                <td style="width: 180px;">1. Sekolah / Penyelenggara</td>
                <td style="width: 10px;">:</td>
                <td><strong><?php echo e($school['sekolah']); ?></strong> (NPSN: <?php echo e($school['npsn']); ?>)</td>
            </tr>
            <tr>
                <td>2. Ruang Ujian</td>
                <td>:</td>
                <td><strong>Ruang <?php echo e($ruang); ?></strong></td>
            </tr>
            <tr>
                <td>3. Sesi Ujian</td>
                <td>:</td>
                <td><strong>Sesi <?php echo e($sesi); ?></strong></td>
            </tr>
            <tr>
                <td>4. Mata Pelajaran</td>
                <td>:</td>
                <td><strong><?php echo e($mapelName); ?> (Hari ke-<?php echo e($hari); ?>)</strong></td>
            </tr>
            <tr>
                <td>5. Alokasi Waktu</td>
                <td>:</td>
                <td>120 Menit</td>
            </tr>
            <tr>
                <td>6. Jumlah Peserta Seharusnya</td>
                <td>:</td>
                <td><strong><?php echo $totalSiswa; ?></strong> orang</td>
            </tr>
            <tr>
                <td>7. Jumlah Peserta Hadir</td>
                <td>:</td>
                <td><strong><?php echo $totalHadir; ?></strong> orang</td>
            </tr>
            <tr>
                <td>8. Jumlah Peserta Tidak Hadir</td>
                <td>:</td>
                <td><strong><?php echo $totalTidakHadir; ?></strong> orang</td>
            </tr>
        </table>
                <td><strong><?php echo $totalHadir; ?></strong> orang</td>
            </tr>
            <tr>
                <td>9. Jumlah Peserta Tidak Hadir</td>
                <td>:</td>
                <td><strong><?php echo $totalTidakHadir; ?></strong> orang</td>
            </tr>
        </table>

        <div style="font-size: 12.5px; margin-top: 14px;">
            <strong>Catatan / Kejadian Khusus Selama Pelaksanaan Ujian:</strong>
            <div style="border: 1px solid #000; min-height: 80px; padding: 8px; margin-top: 6px; font-size: 11.5px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #fafafa;">
                Pelaksanaan ujian di Ruang <?php echo e($ruang); ?> Sesi <?php echo e($sesi); ?> berlangsung dengan tertib, aman, dan lancar tanpa kendala teknis jaringan maupun server.
            </div>
        </div>

        <p style="font-size: 12px; margin-top: 18px; text-align: justify;">
            Demikian berita acara ini dibuat dengan sebenarnya sesuai pelaksanaan ujian di lapangan untuk dapat dipergunakan sebagaimana mestinya.
        </p>

        <!-- TANDA TANGAN 3 PIHAK -->
        <div class="ttd-grid">
            <div>
                <span>Proktor Server,</span>
                <div style="height: 60px;"></div>
                <strong><u><?php echo e($school['proktor'] ?? 'Proktor Utama'); ?></u></strong><br>
                <span>NIP. -</span>
            </div>
            <div>
                <span>Teknisi Lab,</span>
                <div style="height: 60px;"></div>
                <strong><u><?php echo e($school['teknisi'] ?? 'Teknisi CBT'); ?></u></strong><br>
                <span>NIP. -</span>
            </div>
            <div>
                <span>Pengawas Ruang,</span>
                <div style="height: 60px;"></div>
                <strong><u><?php echo e($pengawas); ?></u></strong><br>
                <span>NIP. -</span>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center; font-size: 12px;">
            <span>Mengetahui, Kepala Sekolah</span><br>
            <strong><?php echo e($school['sekolah']); ?></strong>
            <div style="height: 60px;"></div>
            <strong><u><?php echo e($school['ks']); ?></u></strong><br>
            <span>NIP. -</span>
        </div>

    <?php else: ?>
        <!-- DOKUMEN 2: DAFTAR HADIR PESERTA -->
        <div class="doc-title">DAFTAR HADIR PESERTA UJIAN</div>
        <div class="doc-subtitle">ASESMEN BERBASIS KOMPUTER &bull; <?php echo e($mapelName); ?></div>

        <table class="meta-table" style="margin-bottom: 10px;">
            <tr>
                <td style="width: 140px;">Ruang Lab</td>
                <td style="width: 10px;">:</td>
                <td><strong>Ruang <?php echo e($ruang); ?></strong></td>
                <td style="width: 120px;">Sesi</td>
                <td style="width: 10px;">:</td>
                <td><strong>Sesi <?php echo e($sesi); ?></strong></td>
            </tr>
            <tr>
                <td>Hari / Tanggal</td>
                <td>:</td>
                <td><?php echo $tglPelaksanaan; ?></td>
                <td>Mata Pelajaran</td>
                <td>:</td>
                <td><strong><?php echo e($mapelName); ?></strong></td>
            </tr>
        </table>

        <table class="formal-table">
            <thead>
                <tr>
                    <th style="width: 32px;">No</th>
                    <th style="width: 95px;">No. Peserta</th>
                    <th>Nama Peserta Didik</th>
                    <th style="width: 65px;">Kelas</th>
                    <th style="width: 45px;">Abs</th>
                    <th style="width: 130px;" colspan="2">Tanda Tangan / Paraf</th>
                    <th style="width: 80px;">Ket</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pesertaList)): ?>
                    <?php $no = 1; foreach ($pesertaList as $p): ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $no; ?></td>
                            <td style="font-family: monospace; font-weight: 700; text-align: center;"><?php echo e($p['user']); ?></td>
                            <td><strong><?php echo e($p['nama']); ?></strong></td>
                            <td style="text-align: center;"><?php echo e($p['kelas']); ?></td>
                            <td style="text-align: center;"><?php echo e($p['abs']); ?></td>
                            
                            <?php if ($no % 2 !== 0): ?>
                                <td style="width: 65px; height: 32px; vertical-align: top; font-size: 10px;"><?php echo $no; ?>.</td>
                                <td style="width: 65px; background: #fafafa;"></td>
                            <?php else: ?>
                                <td style="width: 65px; background: #fafafa;"></td>
                                <td style="width: 65px; height: 32px; vertical-align: top; font-size: 10px;"><?php echo $no; ?>.</td>
                            <?php endif; ?>

                            <td style="text-align: center; font-size: 11px;">
                                <?php echo ($p['status'] === '1' || $p['status'] === '2') ? 'Hadir' : 'Tidak'; ?>
                            </td>
                        </tr>
                    <?php $no++; endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">Tidak ada peserta terdaftar di Ruang <?php echo e($ruang); ?> Sesi <?php echo e($sesi); ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; margin-top: 24px; font-size: 12px; page-break-inside: avoid;">
            <div style="text-align: center; width: 220px;">
                <span>Proktor Server,</span>
                <div style="height: 55px;"></div>
                <strong><u><?php echo e($school['proktor'] ?? 'Proktor Utama'); ?></u></strong>
            </div>
            <div style="text-align: center; width: 220px;">
                <span>Pengawas Ruang <?php echo e($ruang); ?>,</span>
                <div style="height: 55px;"></div>
                <strong><u><?php echo e($pengawas); ?></u></strong>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php admin_footer(); ?>