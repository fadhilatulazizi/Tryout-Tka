<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';

// Jika admin sudah login, langsung ke dashboard
if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['nama_admin'])) {
    header('Location: ' . base_url('admin/index.php'));
    exit;
}

$error = '';
$school = get_school_profile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === '' || $pass === '') {
        $error = 'Harap isi Username dan Kata Sandi admin.';
    } elseif (Auth::attemptAdmin($user, $pass)) {
        header('Location: ' . base_url('admin/index.php'));
        exit;
    } else {
        $error = 'Username atau Kata Sandi admin tidak sesuai.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Administrator CBT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 font-sans antialiased text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 relative selection:bg-sky-500 selection:text-white">

    <div class="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/40 via-slate-950 to-slate-950 pointer-events-none"></div>

    <div class="relative w-full max-w-md z-10">
        <div class="mb-5 text-center">
            <a href="<?php echo base_url('index.php'); ?>" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-sky-400 transition">
                <span>&larr;</span>
                <span>Kembali ke Halaman Beranda</span>
            </a>
        </div>

        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl shadow-black/60 overflow-hidden backdrop-blur-2xl">
            <!-- Header Banner -->
            <div class="bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950 p-8 text-center text-white border-b border-slate-800 relative">
                <div class="w-14 h-14 mx-auto mb-3 bg-sky-500/10 border border-sky-500/20 text-sky-400 rounded-2xl flex items-center justify-center text-2xl shadow-lg">
                    ⚙️
                </div>
                <h1 class="text-xl font-extrabold tracking-tight">PANEL ADMINISTRATOR</h1>
                <p class="text-xs text-slate-400 mt-1"><?php echo e($school['sekolah']); ?></p>
            </div>

            <div class="p-6 sm:p-8">
                <?php if ($error !== ''): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-xs font-medium text-red-300 flex items-start gap-3">
                        <span class="text-base leading-none">⚠️</span>
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="space-y-4">
                    <div>
                        <label for="username" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Username Administrator</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">👤</span>
                            <input type="text" id="username" name="username" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition" placeholder="admin" required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Kata Sandi</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">🔒</span>
                            <input type="password" id="password" name="password" class="w-full pl-10 pr-11 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition" placeholder="••••••••" required autocomplete="current-password">
                            <button type="button" onclick="const p = document.getElementById('password'); p.type = p.type === 'password' ? 'text' : 'password';" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white" title="Lihat Password">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-2 py-3.5 px-6 rounded-xl font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 shadow-lg shadow-sky-500/25 hover:shadow-sky-500/40 hover:-translate-y-0.5 active:translate-y-0 transition duration-200 flex items-center justify-center gap-2">
                        <span>Masuk Administrator</span>
                        <span>&rarr;</span>
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-slate-800 text-center text-xs text-slate-500 space-y-2">
                    <p>
                        <a href="<?php echo base_url('login.php'); ?>" class="text-sky-400 font-bold hover:underline">🔑 Masuk melalui Portal SSO Terpadu &rarr;</a>
                    </p>
                    <p>
                        <a href="<?php echo base_url('ujian/index.php'); ?>" class="text-slate-400 hover:text-slate-200">&larr; Ke Halaman Ujian Siswa</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
