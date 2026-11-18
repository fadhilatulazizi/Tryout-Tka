<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/ExamEngine.php';

// Jika request logout
if (isset($_GET['logout'])) {
    Auth::logoutStudent();
    header('Location: ' . base_url('login.php'));
    exit;
}

// Jika submit selesai via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'submit') {
    if (!empty($_SESSION['id'])) {
        ExamEngine::finishExam((int) $_SESSION['id']);
        json_response(['success' => true]);
    }
}

// Selesaikan di database jika sesi siswa aktif
if (!empty($_SESSION['id'])) {
    ExamEngine::finishExam((int) $_SESSION['id']);
}

$studentId = (int) ($_SESSION['id'] ?? 0);
$nama = $_SESSION['nama'] ?? 'Peserta Ujian';
$kelas = $_SESSION['kelas'] ?? '';
$school = get_school_profile();

$hasilTersedia = false;
$skor = 0;
$benar = 0;
$salah = 0;
$kosong = 0;
$totalSoal = 0;
$mapelName = 'Ujian';
$predikat = 'Cukup';
$predikatClass = 'text-amber-400 border-amber-500/30 bg-amber-500/10';

if ($studentId > 0) {
    try {
        $state = ExamEngine::getStudentState($studentId);
        $totalSoal = (int) ($state['totalQuestions'] ?? 50);
        $folderSoal = $state['folderSoal'];
        $mapelName = $state['mapelName'];

        $db = get_db();
        $qKunci = mysqli_query($db, "SELECT kunci FROM kunci_jawaban WHERE paket = '{$folderSoal}' LIMIT 1");
        if ($qKunci && $rowK = mysqli_fetch_assoc($qKunci)) {
            $kunci = json_decode($rowK['kunci'], true) ?: [];
            $kunciTerisi = array_filter($kunci);
            if (!empty($kunciTerisi)) {
                $hasilTersedia = true;
                $normJawaban = function($val) {
                    $v = strtoupper(trim((string)$val));
                    $v = preg_replace('/\s+/', '', $v);
                    if (preg_match('/^(\d+,)+\d+$/', $v)) {
                        $parts = explode(',', $v);
                        sort($parts, SORT_NUMERIC);
                        $v = implode(',', $parts);
                    }
                    return $v;
                };

                for ($i = 1; $i <= $totalSoal; $i++) {
                    $ans = $normJawaban($state['answers'][$i] ?? '');
                    $k = $normJawaban($kunci[$i] ?? '');
                    if ($ans === '') {
                        $kosong++;
                    } elseif ($ans === $k) {
                        $benar++;
                    } else {
                        $salah++;
                    }
                }
                $totalSoalDinilai = count($kunciTerisi);
                if ($totalSoalDinilai === 0) $totalSoalDinilai = $totalSoal;
                $skor = $totalSoalDinilai > 0 ? round(($benar / $totalSoalDinilai) * 100, 1) : 0;
                
                if ($skor >= 85) {
                    $predikat = 'Sangat Baik (A)';
                    $predikatClass = 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10';
                } elseif ($skor >= 70) {
                    $predikat = 'Baik (B)';
                    $predikatClass = 'text-sky-400 border-sky-500/30 bg-sky-500/10';
                } elseif ($skor >= 55) {
                    $predikat = 'Cukup (C)';
                    $predikatClass = 'text-amber-400 border-amber-500/30 bg-amber-500/10';
                } else {
                    $predikat = 'Perlu Peningkatan (D)';
                    $predikatClass = 'text-rose-400 border-rose-500/30 bg-rose-500/10';
                }
            }
        }
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ujian Selesai - <?php echo e($school['sekolah']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 font-sans antialiased text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 relative selection:bg-sky-500 selection:text-white">

    <div class="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-emerald-900/30 via-slate-950 to-slate-950 pointer-events-none"></div>

    <div class="relative w-full max-w-lg z-10 text-center">
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl shadow-black/60 backdrop-blur-2xl">
            <div class="w-20 h-20 bg-emerald-500/10 border-2 border-emerald-500/30 text-emerald-400 rounded-3xl flex items-center justify-center text-4xl mx-auto mb-6 shadow-xl shadow-emerald-500/10 animate-bounce">
                ✓
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mb-2">Ujian Telah Selesai!</h1>

            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed mb-6">
                Terima kasih, <strong class="text-white"><?php echo e($nama); ?></strong> <?php if ($kelas): ?>(Kelas <?php echo e($kelas); ?>)<?php endif; ?>.<br>
                Lembar ujian <strong><?php echo e($mapelName); ?></strong> telah tersimpan di server <strong><?php echo e($school['sekolah']); ?></strong>.
            </p>

            <?php if ($hasilTersedia): ?>
                <!-- Card Hasil / Skor Akhir -->
                <div class="mb-6 p-6 rounded-2xl bg-slate-950/70 border border-slate-800 text-center shadow-inner">
                    <span class="text-[11px] uppercase font-extrabold tracking-widest text-slate-400">Hasil & Skor Akhir Anda</span>
                    <div class="my-3 flex items-baseline justify-center gap-1.5">
                        <span class="text-5xl sm:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-sky-400 tracking-tight"><?php echo $skor; ?></span>
                        <span class="text-slate-500 font-extrabold text-lg">/ 100</span>
                    </div>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-extrabold border <?php echo $predikatClass; ?>">
                        <span>Predikat: <?php echo e($predikat); ?></span>
                    </div>

                    <!-- Breakdown 3 Kotak: Benar / Salah / Kosong -->
                    <div class="grid grid-cols-3 gap-2.5 mt-5 pt-5 border-t border-slate-800">
                        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center">
                            <div class="text-[11px] text-emerald-400 font-bold uppercase tracking-wider">Benar</div>
                            <div class="text-2xl font-black text-emerald-300 mt-0.5"><?php echo $benar; ?></div>
                        </div>
                        <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-center">
                            <div class="text-[11px] text-rose-400 font-bold uppercase tracking-wider">Salah</div>
                            <div class="text-2xl font-black text-rose-300 mt-0.5"><?php echo $salah; ?></div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-center">
                            <div class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Kosong</div>
                            <div class="text-2xl font-black text-slate-300 mt-0.5"><?php echo $kosong; ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Pemberitahuan jika kunci belum diaktifkan -->
                <div class="mb-6 p-4 rounded-2xl bg-sky-950/40 border border-sky-800/40 text-xs text-sky-200 text-left flex items-start gap-3">
                    <span class="text-xl leading-none">📢</span>
                    <div class="leading-relaxed">
                        <strong class="text-sky-100 block mb-0.5">Penilaian Sedang Diproses:</strong> Kunci jawaban resmi belum diaktifkan oleh proktor. Nilai dan rekap hasil akhir akan diumumkan setelah seluruh sesi selesai.
                    </div>
                </div>
            <?php endif; ?>

            <a href="selesai.php?logout=1" class="w-full inline-flex items-center justify-center gap-2 py-4 px-6 rounded-xl font-bold text-white bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 shadow-xl shadow-sky-500/25 hover:-translate-y-0.5 active:translate-y-0 transition duration-200">
                <span>Keluar dari Akun Peserta</span>
                <span>&rarr;</span>
            </a>
        </div>
    </div>

</body>
</html>
