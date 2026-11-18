<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Auth.php';
require_once __DIR__ . '/app/Helper.php';

$school = get_school_profile();
$error = '';

// Cek sesi aktif
$isAdminLoggedIn = !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['nama_admin']);
$isStudentLoggedIn = !empty($_SESSION['student_logged_in']) && !empty($_SESSION['id']);

// Proses Login SSO Otomatis (Tanpa Pilih Mode)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Harap isi Username/Nomor Peserta dan Password Anda.';
    } else {
        $loginSuccess = false;

        // 1. Cek Kredensial Administrator / Proktor
        if (Auth::attemptAdmin($username, $password)) {
            $loginSuccess = true;
            header('Location: ' . base_url('admin/index.php'));
            exit;
        }

        // 2. Cek Kredensial Peserta Ujian
        $studentRes = Auth::attemptStudent($username, $password);
        if ($studentRes['success']) {
            $loginSuccess = true;
            header('Location: ' . base_url('ujian/konfirmasi.php'));
            exit;
        } elseif ($studentRes['message'] !== 'Username atau Password salah.') {
            // Misalnya jika status siswa sudah selesai tes
            $error = $studentRes['message'];
            $loginSuccess = true;
        }

        // 3. Jika Tidak Ada yang Cocok
        if (!$loginSuccess) {
            $error = 'Username atau Password tidak cocok. Pastikan Anda memasukkan data akun dengan benar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk SSO Terpadu | <?php echo e($school['sekolah']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
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
<body class="bg-slate-950 font-sans antialiased text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 relative overflow-x-hidden selection:bg-sky-500 selection:text-white">

    <!-- Ambient Glow & Pattern -->
    <div class="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-sky-900/40 via-slate-950 to-slate-950 pointer-events-none"></div>
    <div class="fixed inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:3rem_3rem] opacity-20 pointer-events-none"></div>

    <div class="relative w-full max-w-md z-10">
        <!-- Back to Home -->
        <div class="mb-5 text-center">
            <a href="<?php echo base_url('index.php'); ?>" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-sky-400 transition">
                <span>&larr;</span>
                <span>Kembali ke Halaman Beranda</span>
            </a>
        </div>

        <!-- SSO Card -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl shadow-black/60 overflow-hidden backdrop-blur-2xl">
            <!-- Header Banner -->
            <div class="bg-gradient-to-br from-sky-600 via-sky-700 to-blue-800 p-8 text-center text-white relative">
                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-white/15 border border-white/20 mb-3 text-sky-100">
                    <span>🏛️</span>
                    <span><?php echo e($school['sekolah']); ?></span>
                </div>
                <h1 class="text-xl font-extrabold tracking-tight">PORTAL SSO ASESMEN</h1>
                <p class="text-xs text-sky-100/80 mt-1">Satu pintu masuk untuk Peserta Ujian & Proktor</p>
            </div>

            <div class="p-6 sm:p-8">
                <!-- Active Sessions Notice -->
                <?php if ($isAdminLoggedIn): ?>
                    <div class="mb-6 p-3.5 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-between text-xs text-sky-300">
                        <div>Sesi Aktif: <strong><?php echo e($_SESSION['nama_admin']); ?></strong> (Admin)</div>
                        <a href="<?php echo base_url('admin/index.php'); ?>" class="font-bold underline hover:text-white">Dashboard &rarr;</a>
                    </div>
                <?php elseif ($isStudentLoggedIn): ?>
                    <div class="mb-6 p-3.5 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-between text-xs text-sky-300">
                        <div>Sesi Aktif: <strong><?php echo e($_SESSION['nama']); ?></strong> (Peserta)</div>
                        <a href="<?php echo base_url('ujian/konfirmasi.php'); ?>" class="font-bold underline hover:text-white">Ujian &rarr;</a>
                    </div>
                <?php endif; ?>

                <!-- Error Alert -->
                <?php if ($error !== ''): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-xs font-medium text-red-300 flex items-start gap-3">
                        <span class="text-base leading-none">⚠️</span>
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" class="space-y-5">
                    <div>
                        <label for="username" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Username / Nomor Peserta</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">👤</span>
                            <input type="text" id="username" name="username" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition" placeholder="Masukkan username atau no. peserta" required autofocus autocomplete="username">
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5">Gunakan Username yang tertera pada kartu ujian atau akun proktor Anda.</p>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Password / Kata Sandi</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">🔒</span>
                            <input type="password" id="password" name="password" class="w-full pl-10 pr-11 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition" placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" onclick="togglePass()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white" title="Lihat Password">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-2 py-3.5 px-6 rounded-xl font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 shadow-lg shadow-sky-500/25 hover:shadow-sky-500/40 hover:-translate-y-0.5 active:translate-y-0 transition duration-200 flex items-center justify-center gap-2">
                        <span>Masuk ke Sistem CBT</span>
                        <span>&rarr;</span>
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-slate-800 text-center text-xs text-slate-500">
                    Sistem otomatis mengarahkan ke lembar ujian atau dashboard sesuai hak akses akun.
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePass() {
        const pass = document.getElementById('password');
        pass.type = pass.type === 'password' ? 'text' : 'password';
    }
    </script>
</body>
</html>