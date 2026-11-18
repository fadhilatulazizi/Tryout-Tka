<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/ExamEngine.php';

Auth::requireStudent();

// Pastikan token sudah divalidasi di konfirmasi.php
if (empty($_SESSION['exam_token_valid'])) {
    header('Location: ' . base_url('ujian/konfirmasi.php'));
    exit;
}

$studentId = (int) $_SESSION['id'];
$state = ExamEngine::getStudentState($studentId);

// Hitung sisa waktu
$now = time();
$endTime = $_SESSION['exam_end_time'] ?? ($now + 7200);
$remainingSeconds = max(0, $endTime - $now);

if ($remainingSeconds <= 0) {
    header('Location: ' . base_url('ujian/selesai.php'));
    exit;
}

$student = $state['student'];
$school = $state['school'];
$mapelName = $state['mapelName'];
$folderSoal = $state['folderSoal'];
$csrfToken = csrf_token();

// Cari nomor soal pertama yang belum dijawab agar siswa langsung melanjutkan
$totalQuestions = (int) ($state['totalQuestions'] ?? 30);
$initialNumber = 1;
for ($i = 1; $i <= $totalQuestions; $i++) {
    if (empty($state['answers'][$i])) {
        $initialNumber = $i;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lembar Ujian CBT - <?php echo e($mapelName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ujian.css'); ?>">
</head>
<body class="font-normal">

    <!-- Topbar ANBK -->
    <header class="exam-topbar">
        <div class="student-info">
            <div class="student-avatar">
                <?php echo strtoupper(substr($student['nama'], 0, 1)); ?>
            </div>
            <div class="student-meta">
                <h2><?php echo e($student['nama']); ?></h2>
                <p><?php echo e($student['kelas']); ?> &bull; No. <?php echo e($student['abs']); ?> &bull; <strong><?php echo e($mapelName); ?></strong></p>
            </div>
        </div>

        <div class="exam-controls">
            <!-- Status Sinkronisasi Real-Time AJAX -->
            <div class="sync-pill synced" id="sync-pill" title="Status penyimpanan jawaban ke server">
                <span class="sync-dot" id="sync-dot"></span>
                <span id="sync-text">Tersimpan</span>
            </div>

            <!-- Font Size -->
            <div class="control-btn-group" title="Ukuran Font">
                <button type="button" class="btn-ctrl" onclick="setFontSize('small')">A-</button>
                <button type="button" class="btn-ctrl active" onclick="setFontSize('normal')">A</button>
                <button type="button" class="btn-ctrl" onclick="setFontSize('large')">A+</button>
            </div>

            <!-- Dark Mode -->
            <button type="button" class="btn-ctrl" onclick="toggleTheme()" title="Mode Gelap / Terang">
                🌓
            </button>

            <!-- Fullscreen -->
            <button type="button" class="btn-ctrl" onclick="toggleFullscreen()" title="Layar Penuh">
                ⛶
            </button>

            <!-- Timer Countdown -->
            <div class="timer-pill" id="timer-pill">
                <span>⏱</span>
                <span id="timer-display">--:--:--</span>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="exam-body">
        <!-- Area Soal -->
        <main class="exam-main">
            <div class="question-card">
                <div class="question-header">
                    <span class="question-badge" id="q-current-badge">
                        Soal Nomor 1 dari <?php echo (int) ($state['totalQuestions'] ?? 50); ?>
                    </span>
                    <span id="q-instruction" style="font-size: 13px; font-weight: 600; color: #64748b;">
                        Pilihlah salah satu jawaban yang paling tepat
                    </span>
                </div>

                <!-- Iframe Soal Asli External -->
                <div style="flex: 1; min-height: 480px; position: relative;">
                    <!-- Loading Skeleton Overlay -->
                    <div id="iframe-loader" style="display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.85); backdrop-filter: blur(2px); z-index: 5; align-items: center; justify-content: center; gap: 10px;">
                        <span class="loader-spinner"></span>
                        <span style="font-size: 13px; font-weight: 700; color: #0284c7;">Memuat Soal...</span>
                    </div>
                    <iframe id="question-iframe" src="" style="width: 100%; height: 100%; min-height: 480px; border: none; background: #ffffff;"></iframe>
                </div>

                <!-- Pilihan Jawaban Cepat Modern (A - E / Dinamis Sesuai Tipe Soal) -->
                <div class="exam-options-bar" id="exam-options-bar" style="padding: 16px 24px; border-top: 1px solid var(--exam-border); background: var(--exam-card-bg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <p style="font-size: 13px; font-weight: 700; color: #475569;" id="opt-bar-title">PILIHAN JAWABAN ANDA:</p>
                        <span style="font-size: 11.5px; color: #94a3b8; font-weight: 600;" id="opt-bar-hint">⌨ Gunakan tombol keyboard A, B, C, D, E</span>
                    </div>
                    <!-- 1. Kontainer Tombol PG Tunggal (A - E) -->
                    <div id="pg-buttons-container" style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php foreach (['A', 'B', 'C', 'D', 'E'] as $opt): ?>
                            <button type="button" class="option-item" data-value="<?php echo $opt; ?>" onclick="selectAnswer('<?php echo $opt; ?>')" style="flex: 1; min-width: 80px; justify-content: center; padding: 10px 14px;">
                                <div class="option-circle"><?php echo $opt; ?></div>
                                <span class="option-shortcut">[<?php echo $opt; ?>]</span>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" class="option-item" data-value="" onclick="selectAnswer('')" style="flex: 1; min-width: 90px; justify-content: center; padding: 10px 14px;" title="Kosongkan Jawaban (Backspace / Delete)">
                            <span style="font-size: 13px; font-weight: 600; color: #64748b;">Hapus</span>
                            <span class="option-shortcut">[Del]</span>
                        </button>
                    </div>

                    <!-- 2. Kontainer Interaktif PG Kompleks: Centang Kotak / Checkbox -->
                    <div id="checkbox-buttons-container" style="display: none; flex-direction: column; gap: 8px;">
                        <!-- Di-generate dinamis oleh JS -->
                    </div>

                    <!-- 3. Kontainer Interaktif PG Kompleks: Tabel Benar / Salah -->
                    <div id="bs-buttons-container" style="display: none; flex-direction: column; gap: 8px;">
                        <!-- Di-generate dinamis oleh JS -->
                    </div>

                    <!-- 4. Kontainer Informasi Soal Dilewati -->
                    <div id="complex-hint-container" style="display: none; padding: 10px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; color: #166534; font-size: 12.5px; font-weight: 600;">
                        <!-- Pesan status -->
                    </div>
                </div>

                <!-- Footer Navigasi -->
                <footer class="exam-footer">
                    <button type="button" class="btn-nav btn-prev" id="btn-prev" onclick="prevQuestion()">
                        ◀ Soal Sebelumnya
                    </button>

                    <button type="button" class="btn-nav btn-doubt" id="btn-doubt" onclick="toggleDoubt()">
                        ☐ Ragu-ragu
                    </button>

                    <button type="button" class="btn-nav btn-next" id="btn-next" onclick="nextQuestion()">
                        Soal Berikutnya ▶
                    </button>

                    <button type="button" class="btn-nav btn-finish" id="btn-finish" onclick="openFinishModal()" style="display: none;">
                        ✓ Selesai Ujian
                    </button>
                </footer>
            </div>
        </main>

        <!-- Sidebar Palette Nomor Soal -->
        <aside class="exam-palette">
            <div class="palette-header">
                <h3>Daftar Soal (1–<?php echo (int) ($state['totalQuestions'] ?? 50); ?>)</h3>
                <span id="answered-count" style="font-size: 12px; font-weight: 700; color: #0284c7; background: #e0f2fe; padding: 3px 8px; border-radius: 9999px;">0/<?php echo (int) ($state['totalQuestions'] ?? 50); ?></span>
            </div>

            <div class="palette-grid" id="palette-grid">
                <!-- Grid di-render otomatis oleh JS -->
            </div>

            <div class="palette-legend">
                <div class="legend-item"><span class="legend-dot green"></span> Sudah Dijawab</div>
                <div class="legend-item"><span class="legend-dot yellow"></span> Ragu-ragu</div>
                <div class="legend-item"><span class="legend-dot gray"></span> Belum Dijawab</div>
            </div>

            <div style="padding: 14px 20px; border-top: 1px solid var(--exam-border); text-align: center;">
                <button type="button" class="btn-nav btn-finish" style="width: 100%;" onclick="openFinishModal()">
                    Akhiri Ujian Sekarang
                </button>
            </div>
        </aside>
    </div>

    <!-- Modal Konfirmasi Selesai Ujian -->
    <div class="modal-overlay" id="modal-finish" style="display: none;">
        <div class="modal-card">
            <div style="width: 60px; height: 60px; background: #d1fae5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 16px auto;">
                ✓
            </div>
            <h2 class="modal-title">Konfirmasi Selesai Ujian</h2>
            <p class="modal-desc">Periksa kembali ringkasan lembar pengerjaan Anda sebelum mengakhiri tes.</p>

            <div style="background: var(--exam-bg); border-radius: 10px; padding: 14px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                <div>
                    <span style="font-size: 11px; color: #64748b; font-weight: 600;">TERJAWAB</span>
                    <div id="sum-answered" style="font-size: 22px; font-weight: 800; color: #10b981;">0</div>
                </div>
                <div>
                    <span style="font-size: 11px; color: #64748b; font-weight: 600;">RAGU-RAGU</span>
                    <div id="sum-doubt" style="font-size: 22px; font-weight: 800; color: #f59e0b;">0</div>
                </div>
                <div>
                    <span style="font-size: 11px; color: #64748b; font-weight: 600;">KOSONG</span>
                    <div id="sum-unanswered" style="font-size: 22px; font-weight: 800; color: #ef4444;">0</div>
                </div>
            </div>

            <div style="text-align: left; margin-bottom: 20px; font-size: 13px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="chk-confirm-finish" style="width: 18px; height: 18px;">
                    <span>Saya yakin telah memeriksa seluruh jawaban dan ingin menyelesaikan tes ini.</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-nav btn-prev" style="flex: 1;" onclick="closeFinishModal()">Batal</button>
                <button type="button" class="btn-nav btn-finish" style="flex: 1;" onclick="confirmFinishExam()">Selesai & Kumpulkan</button>
            </div>
        </div>
    </div>

    <!-- Modal Peringatan Anti-Curang (Blur / Pindah Tab) -->
    <div class="modal-overlay" id="modal-cheat" style="display: none;">
        <div class="modal-card">
            <div class="modal-icon-danger">⚠️</div>
            <h2 class="modal-title" style="color: #ef4444;">PERINGATAN PELANGGARAN UJIAN</h2>
            <p class="modal-desc">
                Sistem mendeteksi Anda meninggalkan jendela ujian (berpindah tab / aplikasi). Pelanggaran ini dicatat oleh server pengawas proktor!
            </p>
            <div style="margin-bottom: 20px; font-size: 13px; font-weight: 700; color: #991b1b; background: #fee2e2; padding: 8px 12px; border-radius: 6px;">
                Total Pelanggaran: <span id="cheat-count">1</span> kali
            </div>
            <button type="button" class="btn-nav btn-next" style="width: 100%;" onclick="closeCheatModal()">
                Kembali ke Lembar Ujian
            </button>
        </div>
    </div>

    <!-- Modal Pengumuman Siaran Proktor (Broadcast) -->
    <div class="modal-overlay" id="modal-broadcast" style="display: none; z-index: 9999;">
        <div class="modal-card" style="border: 2px solid #0284c7; box-shadow: 0 25px 50px -12px rgba(2, 132, 199, 0.4);">
            <div style="width: 60px; height: 60px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 16px auto;">
                📢
            </div>
            <h2 class="modal-title" style="color: #0369a1;">PENGUMUMAN DARI PROKTOR</h2>
            <div id="broadcast-content" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 16px; margin: 16px 0; font-size: 14px; font-weight: 600; color: #1e293b; text-align: left; line-height: 1.5;">
                <!-- Pesan broadcast -->
            </div>
            <div style="font-size: 11px; color: #64748b; margin-bottom: 16px;" id="broadcast-time">
                Diterima dari Ruang Server
            </div>
            <button type="button" class="btn-nav btn-next" style="width: 100%;" onclick="closeBroadcastModal()">
                Saya Mengerti / Tutup
            </button>
        </div>
    </div>

    <!-- Inject Konfigurasi ke JS -->
    <script>
        window.CBT_CONFIG = {
            studentId: <?php echo $studentId; ?>,
            folderSoal: '<?php echo e($folderSoal); ?>',
            initialNumber: <?php echo $initialNumber; ?>,
            remainingSeconds: <?php echo (int) $remainingSeconds; ?>,
            answers: <?php echo json_encode($state['answers']); ?>,
            doubts: <?php echo json_encode($state['ragu']); ?>,
            apiUrl: '<?php echo base_url('ujian/api_jawaban.php'); ?>',
            finishUrl: '<?php echo base_url('ujian/selesai.php'); ?>',
            totalQuestions: <?php echo (int) ($state['totalQuestions'] ?? 30); ?>,
            basePath: '<?php echo rtrim(base_url(''), '/'); ?>/',
            csrfToken: '<?php echo $csrfToken; ?>'
        };
    </script>
    <script src="<?php echo base_url('assets/js/ujian.js'); ?>"></script>
</body>
</html>
