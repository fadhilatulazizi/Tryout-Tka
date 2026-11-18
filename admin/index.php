<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$school = get_school_profile();
$activeJadwal = get_active_jadwal();
$hariAktif = (string)($activeJadwal['hari'] ?? $school['hari'] ?? '1');
$mapelAktif = !empty($activeJadwal['mapel']) ? $activeJadwal['mapel'] : ($hariAktif === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : 'Bahasa Indonesia');
$namaHariAktif = !empty($activeJadwal['nama_hari']) ? $activeJadwal['nama_hari'] : ('Hari ke-' . $hariAktif);

// Ambil Statistik Siswa
$qTotal = mysqli_query($db, "SELECT COUNT(*) as c FROM account");
$totalSiswa = (int) (mysqli_fetch_assoc($qTotal)['c'] ?? 0);

$qOnline = mysqli_query($db, "SELECT COUNT(*) as c FROM account WHERE status = '1'");
$siswaOnline = (int) (mysqli_fetch_assoc($qOnline)['c'] ?? 0);

$qSelesai = mysqli_query($db, "SELECT COUNT(*) as c FROM account WHERE status = '2'");
$siswaSelesai = (int) (mysqli_fetch_assoc($qSelesai)['c'] ?? 0);

$qBelum = mysqli_query($db, "SELECT COUNT(*) as c FROM account WHERE status = '0'");
$siswaBelum = (int) (mysqli_fetch_assoc($qBelum)['c'] ?? 0);

// Sesi Ujian Aktif
$qSesi = mysqli_query($db, "SELECT * FROM atur ORDER BY sesi ASC, ruang ASC");

admin_header('Dashboard Ringkasan', 'dashboard');
?>

<!-- Metric Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Siswa -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-sky-50 border border-sky-100 flex items-center justify-center text-2xl text-sky-600 flex-shrink-0">
            👥
        </div>
        <div>
            <h3 class="text-3xl font-extrabold text-slate-900 leading-none mb-1"><?php echo $totalSiswa; ?></h3>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Peserta</p>
        </div>
    </div>

    <!-- Sedang Ujian -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-2xl text-amber-600 flex-shrink-0">
            ⏳
        </div>
        <div>
            <h3 class="text-3xl font-extrabold text-amber-600 leading-none mb-1"><?php echo $siswaOnline; ?></h3>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Sedang Ujian</p>
        </div>
    </div>

    <!-- Selesai Ujian -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-2xl text-emerald-600 flex-shrink-0">
            ✓
        </div>
        <div>
            <h3 class="text-3xl font-extrabold text-emerald-600 leading-none mb-1"><?php echo $siswaSelesai; ?></h3>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Telah Selesai</p>
        </div>
    </div>

    <!-- Belum Login -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition duration-200 flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-purple-50 border border-purple-100 flex items-center justify-center text-2xl text-purple-600 flex-shrink-0">
            ⚪
        </div>
        <div>
            <h3 class="text-3xl font-extrabold text-slate-700 leading-none mb-1"><?php echo $siswaBelum; ?></h3>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Belum Masuk</p>
        </div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm mb-8">
    <div class="pb-5 mb-6 border-b border-slate-100">
        <h2 class="text-lg font-bold text-slate-900 tracking-tight">Akses Cepat Pengelolaan</h2>
        <p class="text-xs text-slate-500 mt-0.5">Pintas langsung ke modul utama pengawasan dan operasional server asesmen.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pantau Ujian Live -->
        <a href="<?php echo base_url('admin/pantau.php'); ?>" class="flex items-center gap-4 p-5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-sky-50/70 hover:border-sky-300 transition duration-200 group">
            <div class="w-12 h-12 rounded-xl bg-sky-600 text-white flex items-center justify-center text-xl shadow-md shadow-sky-500/20 group-hover:scale-105 transition">
                👁️
            </div>
            <div>
                <strong class="text-sm font-bold text-slate-900 group-hover:text-sky-700 block">Pantau Ujian Live</strong>
                <span class="text-xs text-slate-500 block">Monitoring & reset login</span>
            </div>
        </a>

        <!-- Cetak Kartu Peserta -->
        <a href="<?php echo base_url('admin/kartu.php'); ?>" class="flex items-center gap-4 p-5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-sky-50/70 hover:border-sky-300 transition duration-200 group">
            <div class="w-12 h-12 rounded-xl bg-slate-800 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition">
                🖨️
            </div>
            <div>
                <strong class="text-sm font-bold text-slate-900 group-hover:text-sky-700 block">Cetak Kartu Ujian</strong>
                <span class="text-xs text-slate-500 block">Format 6 kartu / lembar A4</span>
            </div>
        </a>

        <!-- Sinkron Soal Luar -->
        <a href="<?php echo base_url('admin/sinkron_soal.php'); ?>" class="flex items-center gap-4 p-5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-sky-50/70 hover:border-sky-300 transition duration-200 group">
            <div class="w-12 h-12 rounded-xl bg-slate-800 text-white flex items-center justify-center text-xl shadow-md group-hover:scale-105 transition">
                🔄
            </div>
            <div>
                <strong class="text-sm font-bold text-slate-900 group-hover:text-sky-700 block">Sinkron Soal Luar</strong>
                <span class="text-xs text-slate-500 block">Unduh paket ZIP dari dinas</span>
            </div>
        </a>

        <!-- Hasil & Rekap Excel -->
        <a href="<?php echo base_url('admin/hasil.php'); ?>" class="flex items-center gap-4 p-5 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-sky-50/70 hover:border-sky-300 transition duration-200 group">
            <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-500/20 group-hover:scale-105 transition">
                📊
            </div>
            <div>
                <strong class="text-sm font-bold text-slate-900 group-hover:text-sky-700 block">Hasil & Ekspor Excel</strong>
                <span class="text-xs text-slate-500 block">Rekap nilai jawaban siswa</span>
            </div>
        </a>
    </div>
</div>

<!-- Sesi Aktif Panel -->
<div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 mb-6 border-b border-slate-100 gap-4">
        <div>
            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Konfigurasi Jadwal, Sesi & Ruang</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Hari Tes Aktif: <strong class="text-sky-600"><?php echo e($namaHariAktif); ?> (<?php echo e($mapelAktif); ?>)</strong>
            </p>
        </div>
        <a href="<?php echo base_url('admin/sesi_token.php'); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-sky-600 hover:bg-sky-500 transition shadow-sm">
            <span>Kelola Jadwal, Sesi & Token</span>
            <span>&rarr;</span>
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/70 text-slate-600 text-xs font-bold uppercase tracking-wider">
                    <th class="py-3 px-4">Sesi</th>
                    <th class="py-3 px-4">Ruang</th>
                    <th class="py-3 px-4">Token Ujian</th>
                    <th class="py-3 px-4">Paket Soal</th>
                    <th class="py-3 px-4">Status Ujian</th>
                    <th class="py-3 px-4">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($qSesi && mysqli_num_rows($qSesi) > 0): ?>
                    <?php while ($sesi = mysqli_fetch_assoc($qSesi)): ?>
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">Sesi <?php echo e($sesi['sesi']); ?></td>
                            <td class="py-3.5 px-4 text-slate-700">Ruang <?php echo e($sesi['ruang']); ?></td>
                            <td class="py-3.5 px-4">
                                <span class="font-mono font-bold text-xs bg-sky-50 border border-sky-200 text-sky-700 px-2.5 py-1 rounded-md tracking-wider">
                                    <?php echo e($sesi['token']); ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-600">
                                <code><?php echo e($sesi['folder_soal']); ?></code>
                            </td>
                            <td class="py-3.5 px-4">
                                <?php if ($sesi['ujian_aktif'] === '1'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif / Dibuka
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">
                                        Ditutup
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500"><?php echo e($sesi['ket'] ?? '-'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400 text-xs">Belum ada sesi ujian yang diatur.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_footer(); ?>
