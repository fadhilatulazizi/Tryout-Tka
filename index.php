<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helper.php';
require_once __DIR__ . '/app/SyncEngine.php';

// Endpoint AJAX untuk transparansi data real-time halaman depan
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    json_response(get_public_exam_statistics());
}

$school = get_school_profile();
$db = get_db();
$stats = get_public_exam_statistics();

$hari = $stats['hari'];
$mapelName = $stats['mapel'];
$totalSiswa = $stats['total_peserta'];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Asesmen CBT - <?php echo e($school['sekolah']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased selection:bg-sky-500 selection:text-white min-h-screen relative overflow-x-hidden">

    <!-- Background Grid Pattern -->
    <div class="fixed inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-30 pointer-events-none"></div>

    <!-- Glassmorphism Navigation Bar -->
    <header class="fixed top-0 inset-x-0 h-20 bg-slate-950/80 backdrop-blur-xl border-b border-slate-800/80 z-50 flex items-center justify-between px-6 lg:px-12">
        <a href="<?php echo base_url('index.php'); ?>" class="flex items-center gap-3.5 group">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-sky-600 to-cyan-400 flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition duration-300">
                <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3L1 9L12 15L21 10.09V17H23V9M5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z"/>
                </svg>
            </div>
            <div>
                <span class="text-base font-extrabold tracking-wide text-white block leading-tight">CBT DEMAK</span>
                <span class="text-xs text-slate-400 font-medium block truncate max-w-[200px] sm:max-w-none"><?php echo e($school['sekolah']); ?></span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="hidden md:flex items-center gap-8 text-sm font-semibold text-slate-300">
            <a href="#beranda" class="hover:text-sky-400 transition">Beranda</a>
            <a href="#statistik" class="text-sky-400 flex items-center gap-1.5 hover:text-sky-300 transition">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>Statistik Live</span>
            </a>
            <a href="#jadwal" class="hover:text-sky-400 transition">Jadwal Ujian</a>
            <a href="#fitur" class="hover:text-sky-400 transition">Fitur Unggulan</a>
            <a href="#panduan" class="hover:text-sky-400 transition">Panduan</a>
        </nav>

        <!-- CTA Buttons -->
        <div class="flex items-center gap-3">
            <a href="<?php echo base_url('login.php'); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 shadow-md shadow-sky-500/25 hover:shadow-sky-500/40 hover:-translate-y-0.5 active:translate-y-0 transition duration-200">
                <span>🔑</span>
                <span>Masuk SSO</span>
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="beranda" class="relative pt-36 pb-20 lg:pt-48 lg:pb-32 px-6 flex flex-col items-center justify-center text-center">
        <!-- Glow Orb Background -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-96 h-96 bg-sky-500/20 blur-[120px] rounded-full pointer-events-none"></div>

        <!-- Pill Badge -->
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold bg-sky-500/10 text-sky-400 border border-sky-500/20 mb-6 backdrop-blur-md animate-fade-in">
            <span class="flex h-2 w-2 rounded-full bg-sky-400 animate-pulse"></span>
            <span>ASESMEN BERBASIS KOMPUTER &bull; TAHUN 2026</span>
        </div>

        <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-[1.1] mb-6">
            Platform Ujian Digital Modern, <span class="text-transparent bg-clip-text bg-gradient-to-r from-sky-400 via-cyan-300 to-blue-500">Standar ANBK</span>
        </h1>

        <p class="text-base sm:text-lg text-slate-400 max-w-2xl mx-auto mb-10 leading-relaxed font-normal">
            Sistem asesmen terpadu <strong><?php echo e($school['sekolah']); ?></strong> dirancang transparan, cepat, aman dengan proteksi anti-cheat, ketahanan offline, dan pemantauan proktor langsung.
        </p>

        <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto mb-12">
            <a href="<?php echo base_url('login.php'); ?>" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 shadow-xl shadow-sky-500/30 hover:shadow-sky-500/50 hover:-translate-y-0.5 transition duration-200">
                <span class="text-lg">🚀</span>
                <span>Masuk Portal SSO</span>
                <span class="text-sky-200">&rarr;</span>
            </a>

            <a href="#statistik" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-4 rounded-xl font-bold text-slate-200 bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 backdrop-blur-md hover:-translate-y-0.5 transition duration-200">
                <span class="text-lg">📊</span>
                <span>Pantau Statistik Live</span>
            </a>
        </div>

        <!-- School Badge -->
        <div class="inline-flex items-center gap-2 px-5 py-2 rounded-full text-xs font-semibold text-slate-400 bg-slate-900/60 border border-slate-800/80 backdrop-blur-sm">
            <span>🏛️</span>
            <span>Server: <strong class="text-slate-200"><?php echo e($school['sekolah']); ?></strong> &bull; NPSN: <strong class="text-slate-200"><?php echo e($school['npsn']); ?></strong></span>
        </div>
    </section>

    <!-- Telemetry Status Bar -->
    <section class="relative z-20 -mt-10 px-6 max-w-6xl mx-auto">
        <div class="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl shadow-black/50 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-xl text-emerald-400">
                    🟢
                </div>
                <div>
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider block">Status Server</span>
                    <span class="text-sm font-extrabold text-white flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        ONLINE (Siap)
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-xl text-sky-400">
                    📖
                </div>
                <div>
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider block">Mata Pelajaran Aktif</span>
                    <span class="text-sm font-extrabold text-white" id="tele-mapel"><?php echo e($mapelName); ?></span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-xl text-amber-400">
                    👥
                </div>
                <div>
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider block">Peserta Terdaftar</span>
                    <span class="text-sm font-extrabold text-white"><span id="tele-total-peserta"><?php echo $totalSiswa; ?></span> Siswa Aktif</span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-xl text-purple-400">
                    ⏱️
                </div>
                <div>
                    <span class="text-xs uppercase font-bold text-slate-400 tracking-wider block">Waktu Server</span>
                    <span class="text-sm font-extrabold text-white font-mono" id="live-clock"><?php echo date('H:i:s'); ?> WIB</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- SECTION BARU: TRANSPARANSI DATA & STATISTIK ASESMEN (REAL-TIME AJAX)     -->
    <!-- ========================================================================= -->
    <section id="statistik" class="py-24 px-6 max-w-6xl mx-auto scroll-mt-20">
        <!-- Section Title & Live Refresh Toolbar -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
            <div>
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>PAPAN TRANSPARANSI TERBUKA</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">Statistik Pelaksanaan & Hasil Ujian</h2>
                <p class="text-slate-400 text-sm sm:text-base mt-2 max-w-2xl">
                    Data partisipasi siswa, progres pengerjaan soal, dan integritas asesmen disajikan secara terbuka, akurat, dan diperbarui seketika (*real-time*).
                </p>
            </div>

            <!-- Live Status & Refresh Button -->
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 text-xs font-semibold text-slate-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span id="stat-refresh-badge">Live (20s)</span>
                </div>
                <button type="button" onclick="fetchLiveStats(true)" id="btn-manual-stat" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold text-white transition duration-200">
                    <span id="stat-spin-icon">🔄</span>
                    <span>Segarkan Data</span>
                </button>
            </div>
        </div>

        <!-- 4 KPI Card Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <!-- 1. Partisipasi Kehadiran -->
            <div class="bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-sky-500/50 transition duration-300">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tingkat Partisipasi</span>
                    <span class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-lg text-sky-400">📊</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-4xl font-black text-white" id="stat-partisipasi-pct"><?php echo $stats['tingkat_partisipasi']; ?>%</span>
                    <span class="text-xs text-sky-400 font-semibold">Kehadiran</span>
                </div>
                <p class="text-xs text-slate-400 mb-4">
                    <span id="stat-sudah-mulai" class="text-slate-200 font-bold"><?php echo $stats['sudah_mulai']; ?></span> dari <span id="stat-total-peserta" class="text-slate-200 font-bold"><?php echo $stats['total_peserta']; ?></span> peserta ujian telah hadir.
                </p>
                <!-- Mini Progress Bar -->
                <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                    <div id="stat-partisipasi-bar" class="h-full bg-gradient-to-r from-sky-500 to-blue-500 rounded-full transition-all duration-700" style="width: <?php echo $stats['tingkat_partisipasi']; ?>%;"></div>
                </div>
            </div>

            <!-- 2. Progres Status Pengerjaan -->
            <div class="bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-amber-500/50 transition duration-300">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Progres Pengerjaan</span>
                    <span class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-lg text-amber-400">⏳</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-4xl font-black text-amber-400" id="stat-sedang"><?php echo $stats['sedang_ujian']; ?></span>
                    <span class="text-xs text-slate-400 font-semibold">Sedang Mengerjakan</span>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-400 pt-2 border-t border-slate-800/80">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> <strong id="stat-selesai" class="text-white"><?php echo $stats['selesai']; ?></strong> Selesai</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-slate-500"></span> <strong id="stat-belum" class="text-white"><?php echo $stats['belum_login']; ?></strong> Belum Login</span>
                </div>
            </div>

            <!-- 3. Indeks Integritas Ujian (Anti-Curang) -->
            <div class="bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-emerald-500/50 transition duration-300">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Integritas Ujian</span>
                    <span class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-lg text-emerald-400">🛡️</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-4xl font-black text-emerald-400" id="stat-integritas"><?php echo $stats['indeks_integritas']; ?>%</span>
                    <span class="text-xs text-emerald-300 font-semibold">Tertib</span>
                </div>
                <p class="text-xs text-slate-400 mb-4">
                    <span id="stat-bersih" class="text-slate-200 font-bold"><?php echo $stats['siswa_bersih']; ?></span> peserta ujian tertib tanpa catatan pelanggaran tab lepas.
                </p>
                <div class="w-full h-2 rounded-full bg-slate-800 overflow-hidden">
                    <div id="stat-integritas-bar" class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-700" style="width: <?php echo $stats['indeks_integritas']; ?>%;"></div>
                </div>
            </div>

            <!-- 4. Rata-Rata Capaian / Hasil -->
            <div class="bg-gradient-to-br from-slate-900/90 to-slate-900/40 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl relative overflow-hidden group hover:border-purple-500/50 transition duration-300">
                <div class="flex justify-between items-start mb-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Capaian Asesmen</span>
                    <span class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-lg text-purple-400">📈</span>
                </div>
                <div class="flex items-baseline gap-2 mb-2">
                    <span class="text-4xl font-black text-purple-400" id="stat-rata-rata"><?php echo $stats['count_nilai'] > 0 ? $stats['rata_rata'] : '-'; ?></span>
                    <span class="text-xs text-slate-400 font-semibold">Skor Rata-Rata</span>
                </div>
                <div class="flex justify-between items-center text-xs text-slate-400 pt-2 border-t border-slate-800/80">
                    <span>Tertinggi: <strong id="stat-tertinggi" class="text-white"><?php echo $stats['count_nilai'] > 0 ? $stats['nilai_tertinggi'] : '-'; ?></strong></span>
                    <span>Terendah: <strong id="stat-terendah" class="text-white"><?php echo $stats['count_nilai'] > 0 ? $stats['nilai_terendah'] : '-'; ?></strong></span>
                </div>
            </div>
        </div>

        <!-- 2 Column Sub-Grid: Transparansi Rombel Kelas & Status Sesi Lab -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Kolom 1 & 2: Tabel Transparansi per Kelas -->
            <div class="lg:col-span-2 bg-slate-900/70 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>🏫</span>
                            <span>Transparansi Progres Rombongan Belajar (Kelas)</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">Pantau keikutsertaan dan penyelesaian tugas per rombel kelas secara terbuka.</p>
                    </div>
                    <span class="text-xs font-bold px-3 py-1 rounded-full bg-slate-800 text-slate-300">
                        Mapel: <?php echo e($mapelName); ?>
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-800 text-[11px] uppercase tracking-wider">
                                <th class="pb-3 font-bold">Rombel Kelas</th>
                                <th class="pb-3 font-bold text-center">Total Siswa</th>
                                <th class="pb-3 font-bold text-center">Belum Login</th>
                                <th class="pb-3 font-bold text-center">Sedang Ujian</th>
                                <th class="pb-3 font-bold text-center">Selesai</th>
                                <th class="pb-3 font-bold text-right">Progres Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody id="stat-kelas-tbody" class="divide-y divide-slate-800/60">
                            <?php if (!empty($stats['per_kelas'])): ?>
                                <?php foreach ($stats['per_kelas'] as $pk): ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="py-3.5 font-bold text-white"><?php echo e($pk['kelas']); ?></td>
                                        <td class="py-3.5 text-center text-slate-300 font-semibold"><?php echo $pk['total']; ?></td>
                                        <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-bold text-xs"><?php echo $pk['belum']; ?></span></td>
                                        <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 font-bold text-xs"><?php echo $pk['sedang']; ?></span></td>
                                        <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold text-xs"><?php echo $pk['selesai']; ?></span></td>
                                        <td class="py-3.5 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <div class="w-24 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                                                    <div class="h-full bg-sky-500 rounded-full" style="width: <?php echo $pk['progres']; ?>%;"></div>
                                                </div>
                                                <span class="font-bold text-white text-xs w-10 text-right"><?php echo $pk['progres']; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada data rombel kelas terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Kolom 3: Status Sesi & Ruang Lab -->
            <div class="bg-slate-900/70 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-xl shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-800">
                        <div>
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <span>🏢</span>
                                <span>Ruang Lab & Sesi</span>
                            </h3>
                            <p class="text-xs text-slate-400 mt-1">Status ruang ujian dan jadwal aktif.</p>
                        </div>
                    </div>

                    <div class="space-y-3.5" id="stat-sesi-list">
                        <?php if (!empty($stats['sesi_list'])): ?>
                            <?php foreach ($stats['sesi_list'] as $sl): ?>
                                <div class="p-3.5 rounded-2xl bg-slate-950/60 border <?php echo $sl['aktif'] ? 'border-emerald-500/40 bg-emerald-950/10' : 'border-slate-800'; ?> flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-bold text-white block">Sesi <?php echo e($sl['sesi']); ?> &bull; Ruang <?php echo e($sl['ruang']); ?></span>
                                        <span class="text-[11px] text-slate-400"><?php echo e($sl['ket']); ?> &bull; <?php echo $sl['durasi']; ?> Menit</span>
                                    </div>
                                    <div>
                                        <?php if ($sl['aktif']): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                                <span>DIBUKA</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                                                <span>DITUTUP</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-slate-500 text-center py-4">Belum ada sesi dikonfigurasi.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Informasi Server Transparan -->
                <div class="mt-6 pt-4 border-t border-slate-800/80 text-[11px] text-slate-400 flex items-center justify-between">
                    <span>Terakhir diperbarui:</span>
                    <strong class="text-slate-200 font-mono" id="stat-last-time"><?php echo date('H:i:s'); ?> WIB</strong>
                </div>
            </div>
        </div>
    </section>

    <!-- Jadwal Asesmen Section -->
    <section id="jadwal" class="py-24 px-6 max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-xs font-extrabold tracking-widest text-sky-400 uppercase block mb-3">JADWAL ASESMEN</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-4">Periode Mata Pelajaran Tes</h2>
            <p class="text-slate-400 max-w-xl mx-auto text-sm sm:text-base">Periksa alokasi waktu dan paket soal pada tiap hari pelaksanaan ujian.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Hari 1 -->
            <div class="relative bg-slate-900/60 border <?php echo $hari === '1' ? 'border-sky-500/80 shadow-2xl shadow-sky-500/20 ring-1 ring-sky-500/40' : 'border-slate-800'; ?> rounded-3xl p-8 transition duration-300 hover:border-sky-500/50">
                <?php if ($hari === '1'): ?>
                    <span class="absolute top-6 right-6 px-3 py-1 rounded-full text-[11px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">AKTIF HARI INI</span>
                <?php else: ?>
                    <span class="absolute top-6 right-6 px-3 py-1 rounded-full text-[11px] font-extrabold bg-slate-800 text-slate-400">PERIODE 1</span>
                <?php endif; ?>
                <span class="text-xs font-bold uppercase tracking-wider text-sky-400 block mb-2">Hari ke-1</span>
                <h3 class="text-2xl font-black text-white mb-6">Matematika</h3>
                <ul class="space-y-3.5 mb-8 text-sm text-slate-300">
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">🔢</span>
                        <span><strong><?php echo $stats['hari'] === '1' ? $stats['total_soal'] : 50; ?> Butir Soal</strong> Pilihan Ganda</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">⏳</span>
                        <span><strong>120 Menit</strong> Alokasi Waktu Pengerjaan</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">📁</span>
                        <span>Paket Soal Terverifikasi: <code class="px-2 py-0.5 rounded bg-slate-800 text-sky-300 font-mono text-xs"><?php echo e($school['soal_hari1'] ?? 'conf31'); ?></code></span>
                    </li>
                </ul>
                <a href="<?php echo base_url('login.php'); ?>" class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-500 transition duration-200">
                    <span>Mulai Ujian Hari ke-1</span>
                    <span>&rarr;</span>
                </a>
            </div>

            <!-- Hari 2 -->
            <div class="relative bg-slate-900/60 border <?php echo $hari === '2' ? 'border-sky-500/80 shadow-2xl shadow-sky-500/20 ring-1 ring-sky-500/40' : 'border-slate-800'; ?> rounded-3xl p-8 transition duration-300 hover:border-sky-500/50">
                <?php if ($hari === '2'): ?>
                    <span class="absolute top-6 right-6 px-3 py-1 rounded-full text-[11px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">AKTIF HARI INI</span>
                <?php else: ?>
                    <span class="absolute top-6 right-6 px-3 py-1 rounded-full text-[11px] font-extrabold bg-slate-800 text-slate-400">PERIODE 2</span>
                <?php endif; ?>
                <span class="text-xs font-bold uppercase tracking-wider text-sky-400 block mb-2">Hari ke-2</span>
                <h3 class="text-2xl font-black text-white mb-6">Bahasa Indonesia</h3>
                <ul class="space-y-3.5 mb-8 text-sm text-slate-300">
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">📝</span>
                        <span><strong><?php echo $stats['hari'] === '2' ? $stats['total_soal'] : 50; ?> Butir Soal</strong> Pilihan Ganda</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">⏳</span>
                        <span><strong>120 Menit</strong> Alokasi Waktu Pengerjaan</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-sky-400">📁</span>
                        <span>Paket Soal Terverifikasi: <code class="px-2 py-0.5 rounded bg-slate-800 text-sky-300 font-mono text-xs"><?php echo e($school['soal_hari2'] ?? 'conf32'); ?></code></span>
                    </li>
                </ul>
                <a href="<?php echo base_url('login.php'); ?>" class="w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 rounded-xl font-bold text-white <?php echo $hari === '2' ? 'bg-sky-600 hover:bg-sky-500' : 'bg-slate-800 hover:bg-slate-700 text-slate-300'; ?> transition duration-200">
                    <span>Mulai Ujian Hari ke-2</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Fitur Unggulan Section -->
    <section id="fitur" class="py-24 px-6 max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-xs font-extrabold tracking-widest text-sky-400 uppercase block mb-3">TEKNOLOGI CBT</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-4">Fitur Modern untuk Ujian Tanpa Kendala</h2>
            <p class="text-slate-400 max-w-xl mx-auto text-sm sm:text-base">Mendukung kelancaran pelaksanaan tes berbasis komputer dengan fitur keandalan tinggi.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- 1 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-2xl text-sky-400 mb-5">
                    💻
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Standar ANBK / Pusmendik</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Navigasi butir fleksibel dengan kode warna hijau/kuning/abu-abu, kontrol ragu-ragu, dan pembesar teks soal.</p>
            </div>

            <!-- 2 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-2xl text-red-400 mb-5">
                    🛡️
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Anti-Cheat Integrity</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Deteksi perpindahan jendela atau tab otomatis, pemblokiran klik kanan, dan pencegahan tombol pintas salin/inspeksi.</p>
            </div>

            <!-- 3 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl text-emerald-400 mb-5">
                    ⚡
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Offline Resilience</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Setiap jawaban tersimpan secara instan di backend dan dicadangkan lokal. Otomatis sinkron jika koneksi lab sekolah putus.</p>
            </div>

            <!-- 4 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-2xl text-amber-400 mb-5">
                    👁️
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Live Proctoring</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Pengawas dapat memantau progres siswa secara real-time, reset login siswa jika PC macet, dan paksa selesai terpusat.</p>
            </div>

            <!-- 5 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-2xl text-purple-400 mb-5">
                    🖨️
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Cetak Kartu Ujian Presisi A4</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Layout cetak memuat pas 6 kartu pada kertas A4 portrait lengkap dengan kop resmi sekolah, foto 3x4, dan tanda tangan.</p>
            </div>

            <!-- 6 -->
            <div class="bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/50 rounded-2xl p-7 transition duration-300 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-2xl text-cyan-400 mb-5">
                    📈
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Analisis Butir & Ekspor</h3>
                <p class="text-sm text-slate-400 leading-relaxed">Rekapitulasi sebaran opsi jawaban A–E per butir soal dengan dukungan ekspor instan ke berkas Excel.</p>
            </div>
        </div>
    </section>

    <!-- Panduan Ujian Section -->
    <section id="panduan" class="py-24 px-6 max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-xs font-extrabold tracking-widest text-sky-400 uppercase block mb-3">ALUR PENGERJAAN</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight mb-4">Panduan Mudah Mengikuti Ujian</h2>
            <p class="text-slate-400 max-w-xl mx-auto text-sm sm:text-base">Empat langkah sederhana bagi peserta didik untuk melaksanakan asesmen.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 text-center">
                <div class="w-10 h-10 rounded-full bg-sky-600 text-white font-extrabold flex items-center justify-center mx-auto mb-4 text-base">1</div>
                <h4 class="font-bold text-white mb-2 text-base">Masuk Akun</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Gunakan Username dan Password yang tertera pada kartu peserta ujian Anda.</p>
            </div>

            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 text-center">
                <div class="w-10 h-10 rounded-full bg-sky-600 text-white font-extrabold flex items-center justify-center mx-auto mb-4 text-base">2</div>
                <h4 class="font-bold text-white mb-2 text-base">Konfirmasi & Token</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Periksa data identitas dan masukkan Token Ujian yang diberikan pengawas.</p>
            </div>

            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 text-center">
                <div class="w-10 h-10 rounded-full bg-sky-600 text-white font-extrabold flex items-center justify-center mx-auto mb-4 text-base">3</div>
                <h4 class="font-bold text-white mb-2 text-base">Kerjakan Soal</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Pilih jawaban butir soal dan perhatikan timer waktu yang berjalan.</p>
            </div>

            <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-6 text-center">
                <div class="w-10 h-10 rounded-full bg-sky-600 text-white font-extrabold flex items-center justify-center mx-auto mb-4 text-base">4</div>
                <h4 class="font-bold text-white mb-2 text-base">Selesai & Kumpulkan</h4>
                <p class="text-xs text-slate-400 leading-relaxed">Periksa kembali lembar jawaban, konfirmasi selesai, dan keluar aplikasi.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 bg-slate-950 py-10 px-6 text-center text-xs text-slate-500">
        <p class="font-semibold text-slate-400 mb-1">Dinas Pendidikan dan Kebudayaan Kabupaten Demak</p>
        <p><?php echo e($school['sekolah']); ?> &bull; Sistem Asesmen Berbasis Komputer &bull; Versi 4.0 (2026)</p>
    </footer>

    <!-- Jam Server Live & Real-Time Stats AJAX Script -->
    <script>
    // 1. Live Clock
    setInterval(() => {
        const d = new Date();
        const h = String(d.getHours()).padStart(2, '0');
        const m = String(d.getMinutes()).padStart(2, '0');
        const s = String(d.getSeconds()).padStart(2, '0');
        const el = document.getElementById('live-clock');
        if (el) el.textContent = h + ':' + m + ':' + s + ' WIB';
    }, 1000);

    // 2. Real-Time AJAX Public Stats Polling
    let statCountdown = 20;
    let isFetchingStats = false;

    function fetchLiveStats(isManual = false) {
        if (isFetchingStats) return;
        isFetchingStats = true;

        const spinIcon = document.getElementById('stat-spin-icon');
        const refreshBadge = document.getElementById('stat-refresh-badge');
        if (spinIcon) spinIcon.classList.add('animate-spin');
        if (refreshBadge && isManual) refreshBadge.textContent = 'Memperbarui...';

        fetch('<?php echo base_url('index.php?ajax=stats'); ?>')
        .then(r => r.json())
        .then(d => {
            isFetchingStats = false;
            statCountdown = 20;
            if (spinIcon) spinIcon.classList.remove('animate-spin');

            // A. Update Top Telemetry
            const teleMapel = document.getElementById('tele-mapel');
            const teleTotal = document.getElementById('tele-total-peserta');
            if (teleMapel) teleMapel.textContent = d.mapel;
            if (teleTotal) teleTotal.textContent = d.total_peserta;

            // B. Update 4 Metric Cards
            document.getElementById('stat-partisipasi-pct').textContent = d.tingkat_partisipasi + '%';
            document.getElementById('stat-sudah-mulai').textContent = d.sudah_mulai;
            document.getElementById('stat-total-peserta').textContent = d.total_peserta;
            document.getElementById('stat-partisipasi-bar').style.width = d.tingkat_partisipasi + '%';

            document.getElementById('stat-sedang').textContent = d.sedang_ujian;
            document.getElementById('stat-selesai').textContent = d.selesai;
            document.getElementById('stat-belum').textContent = d.belum_login;

            document.getElementById('stat-integritas').textContent = d.indeks_integritas + '%';
            document.getElementById('stat-bersih').textContent = d.siswa_bersih;
            document.getElementById('stat-integritas-bar').style.width = d.indeks_integritas + '%';

            document.getElementById('stat-rata-rata').textContent = d.count_nilai > 0 ? d.rata_rata : '-';
            document.getElementById('stat-tertinggi').textContent = d.count_nilai > 0 ? d.nilai_tertinggi : '-';
            document.getElementById('stat-terendah').textContent = d.count_nilai > 0 ? d.nilai_terendah : '-';

            // C. Update Tabel Per Kelas
            const tbody = document.getElementById('stat-kelas-tbody');
            if (tbody && Array.isArray(d.per_kelas)) {
                let html = '';
                if (d.per_kelas.length > 0) {
                    d.per_kelas.forEach(pk => {
                        html += `
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="py-3.5 font-bold text-white">${pk.kelas}</td>
                            <td class="py-3.5 text-center text-slate-300 font-semibold">${pk.total}</td>
                            <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-bold text-xs">${pk.belum}</span></td>
                            <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 font-bold text-xs">${pk.sedang}</span></td>
                            <td class="py-3.5 text-center"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold text-xs">${pk.selesai}</span></td>
                            <td class="py-3.5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <div class="w-24 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                                        <div class="h-full bg-sky-500 rounded-full" style="width: ${pk.progres}%;"></div>
                                    </div>
                                    <span class="font-bold text-white text-xs w-10 text-right">${pk.progres}%</span>
                                </div>
                            </td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada data rombel kelas terdaftar.</td></tr>';
                }
                tbody.innerHTML = html;
            }

            // D. Update Sesi List
            const sesiList = document.getElementById('stat-sesi-list');
            if (sesiList && Array.isArray(d.sesi_list)) {
                let sHtml = '';
                d.sesi_list.forEach(sl => {
                    const badge = sl.aktif 
                        ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>DIBUKA</span>
                           </span>`
                        : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700">
                            <span>DITUTUP</span>
                           </span>`;
                    
                    const borderClass = sl.aktif ? 'border-emerald-500/40 bg-emerald-950/10' : 'border-slate-800';

                    sHtml += `
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border ${borderClass} flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-white block">Sesi ${sl.sesi} &bull; Ruang ${sl.ruang}</span>
                            <span class="text-[11px] text-slate-400">${sl.ket} &bull; ${sl.durasi} Menit</span>
                        </div>
                        <div>${badge}</div>
                    </div>`;
                });
                sesiList.innerHTML = sHtml;
            }

            // E. Update Timestamp
            const timeEl = document.getElementById('stat-last-time');
            if (timeEl) timeEl.textContent = (d.timestamp || '') + ' WIB';
        })
        .catch(() => {
            isFetchingStats = false;
            statCountdown = 20;
            if (spinIcon) spinIcon.classList.remove('animate-spin');
        });
    }

    // Interval Polling 20 Detik (Hemat Bandwidth & Pause saat tab hidden)
    setInterval(() => {
        if (document.hidden) return;
        statCountdown--;
        const badge = document.getElementById('stat-refresh-badge');
        if (badge && !isFetchingStats) {
            badge.textContent = 'Live (' + statCountdown + 's)';
        }
        if (statCountdown <= 0) {
            fetchLiveStats(false);
        }
    }, 1000);
    </script>

</body>
</html>
