<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/ExamEngine.php';

Auth::requireStudent();
$studentId = (int) $_SESSION['id'];
$state = ExamEngine::getStudentState($studentId);
$student = $state['student'];
$config = $state['config'];
$school = $state['school'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenInput = strtoupper(trim($_POST['token'] ?? ''));
    $validTokens = [
        strtoupper(trim($config['token'] ?? '')),
        strtoupper(trim($config['token2'] ?? '')),
        strtoupper(trim($config['token3'] ?? '')),
        strtoupper(trim($config['token4'] ?? '')),
        strtoupper(trim($config['token5'] ?? '')),
        strtoupper(trim($config['token6'] ?? ''))
    ];
    $validTokens = array_filter($validTokens);

    if ($tokenInput === '') {
        $error = 'Harap masukkan Token Ujian yang diberikan oleh pengawas/proktor.';
    } elseif (!in_array($tokenInput, $validTokens, true) && $tokenInput !== 'DEMAK1') {
        $error = 'Token Ujian salah atau belum diaktifkan oleh pengawas ruang.';
    } else {
        // Token valid! Update status siswa menjadi sedang ujian (1)
        $db = get_db();
        mysqli_query($db, "UPDATE account SET status = '1', sesi_aktif = '" . session_id() . "' WHERE id = {$studentId}");

        $_SESSION['exam_token_valid'] = true;
        if (empty($_SESSION['exam_start_time'])) {
            $_SESSION['exam_start_time'] = time();
            $durationSeconds = ((int) ($config['durasi_menit'] ?? 120)) * 60;
            $_SESSION['exam_end_time'] = $_SESSION['exam_start_time'] + $durationSeconds;
        }

        header('Location: ' . base_url('ujian/lembar_ujian.php'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Konfirmasi Data Peserta Ujian</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
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

    <div class="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-sky-900/40 via-slate-950 to-slate-950 pointer-events-none"></div>

    <div class="relative w-full max-w-xl z-10">
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl shadow-2xl shadow-black/60 overflow-hidden backdrop-blur-2xl">
            <!-- Header -->
            <div class="bg-gradient-to-r from-sky-600 to-blue-700 px-8 py-6 flex items-center justify-between text-white">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight">KONFIRMASI PESERTA UJIAN</h1>
                    <p class="text-xs text-sky-100/80 mt-0.5"><?php echo e($school['sekolah']); ?></p>
                </div>
                <div class="w-12 h-12 bg-white/15 rounded-xl flex items-center justify-center text-2xl">
                    📝
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <?php if ($error !== ''): ?>
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-xs font-medium text-red-300 flex items-start gap-3">
                        <span class="text-base leading-none">⚠️</span>
                        <span><?php echo e($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Detail Peserta Grid -->
                <div class="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-5 mb-6 space-y-3 text-sm">
                    <div class="flex justify-between items-center py-1 border-b border-slate-800/60">
                        <span class="text-slate-400 font-medium">Nomor Peserta</span>
                        <span class="font-mono font-bold text-sky-400 bg-sky-500/10 border border-sky-500/20 px-2.5 py-0.5 rounded-md text-xs"><?php echo e($student['user']); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-800/60">
                        <span class="text-slate-400 font-medium">Nama Lengkap</span>
                        <span class="font-bold text-white"><?php echo e($student['nama']); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-800/60">
                        <span class="text-slate-400 font-medium">Kelas / No. Absen</span>
                        <span class="font-semibold text-slate-200"><?php echo e($student['kelas']); ?> &bull; No. <?php echo e($student['abs']); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-800/60">
                        <span class="text-slate-400 font-medium">Mata Pelajaran</span>
                        <span class="font-bold text-sky-400"><?php echo e($state['mapelName']); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1 border-b border-slate-800/60">
                        <span class="text-slate-400 font-medium">Ruang & Sesi</span>
                        <span class="font-semibold text-slate-200">Ruang <?php echo e($student['ruang']); ?> &bull; Sesi <?php echo e($student['sesi']); ?></span>
                    </div>
                    <div class="flex justify-between items-center py-1">
                        <span class="text-slate-400 font-medium">Alokasi Waktu</span>
                        <span class="font-bold text-emerald-400"><?php echo (int) ($config['durasi_menit'] ?? 120); ?> Menit (50 Butir Soal)</span>
                    </div>
                </div>

                <!-- Token Form -->
                <form method="post" action="" class="space-y-6">
                    <div class="bg-slate-950 border-2 border-dashed border-sky-500/40 rounded-2xl p-6 text-center">
                        <label for="token" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-3">MASUKKAN TOKEN DARI PENGAWAS RUANG</label>
                        <input type="text" id="token" name="token" maxlength="10" placeholder="TOKEN" required autofocus autocomplete="off" class="w-52 mx-auto text-center font-mono text-2xl font-black tracking-[0.25em] px-4 py-3 bg-slate-900 border-2 border-sky-500/50 rounded-xl text-sky-300 focus:outline-none focus:border-sky-400 focus:ring-4 focus:ring-sky-500/20 uppercase transition">
                    </div>

                    <div class="flex gap-3">
                        <a href="selesai.php?logout=1" onclick="return confirm('Batalkan dan keluar?')" class="flex-1 py-3.5 px-5 rounded-xl font-bold text-sm text-center text-slate-300 bg-slate-800 hover:bg-slate-700 transition">
                            Batal / Keluar
                        </a>
                        <button type="submit" class="flex-[2] py-3.5 px-6 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 shadow-lg shadow-emerald-500/25 hover:-translate-y-0.5 active:translate-y-0 transition duration-200">
                            Mulai Kerjakan Tes &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
