<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

Auth::requireAdmin();

// Set flag sesi pratinjau agar file soal dapat ditampilkan dengan opsi lengkap
$_SESSION['preview_mode'] = true;
if (empty($_SESSION['nama'])) {
    $_SESSION['nama'] = 'PROKTOR (PREVIEW)';
    $_SESSION['id'] = 1;
}

$db = get_db();
$school = get_school_profile();
$localPackages = SyncEngine::getLocalPackages();

$selectedFolder = trim($_GET['folder'] ?? ($school['soal_hari1'] ?? 'conf31'));
if (!preg_match('/^conf[a-zA-Z0-9_-]+$/', $selectedFolder)) {
    $selectedFolder = 'conf31';
}

$totalSoal = SyncEngine::getPackageQuestionCount($selectedFolder);

$selectedNo = (int) ($_GET['no'] ?? 1);
if ($selectedNo < 1) $selectedNo = 1;
if ($selectedNo > $totalSoal) $selectedNo = $totalSoal;

// Ambil Kunci Jawaban untuk paket ini jika ada
$qKunci = mysqli_query($db, "SELECT kunci FROM kunci_jawaban WHERE paket = '{$selectedFolder}' LIMIT 1");
$kunciList = [];
if ($qKunci && $rowK = mysqli_fetch_assoc($qKunci)) {
    $kunciList = json_decode($rowK['kunci'], true) ?: [];
}

$iframeUrl = base_url("soal/{$selectedFolder}/{$selectedNo}.php?preview=1");

require_once __DIR__ . '/layout.php';
admin_header('Pratinjau Butir Soal', 'preview_soal');
?>

<div class="panel-card" style="margin-bottom: 20px;">
    <div class="panel-header">
        <div>
            <h2>🔍 Pratinjau Butir Soal Ujian (TKA / ANBK)</h2>
            <p>Periksa wacana cerita, gambar, tabel matriks Benar/Salah, opsi centang kotak, serta pilihan ganda secara utuh.</p>
        </div>
        <div>
            <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
                <label style="font-size: 13px; font-weight: 700;">Pilih Paket Soal:</label>
                <select name="folder" class="form-control" onchange="this.form.submit()" style="font-weight: 700; width: auto;">
                    <?php foreach ($localPackages as $lp): ?>
                        <option value="<?php echo $lp['folder']; ?>" <?php echo $selectedFolder === $lp['folder'] ? 'selected' : ''; ?>>
                            Paket <?php echo $lp['folder']; ?> (<?php echo $lp['total_soal']; ?> Butir Soal)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Navigasi Nomor Soal 1 s.d. Total Soal -->
    <div style="margin-bottom: 16px;">
        <div style="font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 8px;">PILIH NOMOR BUTIR SOAL (1 s.d. <?php echo $totalSoal; ?>):</div>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="preview-palette">
            <?php for ($i = 1; $i <= $totalSoal; $i++): ?>
                <?php 
                $isActive = ($i === $selectedNo);
                $btnClass = $isActive ? 'btn-primary' : 'btn-secondary';
                $hasKey = !empty($kunciList[$i]);
                ?>
                <button type="button" 
                   id="btn-pno-<?php echo $i; ?>"
                   onclick="switchQuestion(<?php echo $i; ?>)"
                   class="btn <?php echo $btnClass; ?> btn-sm btn-pno" 
                   data-no="<?php echo $i; ?>"
                   style="min-width: 40px; text-align: center; padding: 6px 8px; font-weight: <?php echo $isActive ? '800' : '600'; ?>; position: relative;">
                    <?php echo $i; ?>
                    <?php if ($hasKey): ?>
                        <span style="position: absolute; top: 2px; right: 2px; width: 6px; height: 6px; background: #10b981; border-radius: 50%;"></span>
                    <?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Header Toolbar Soal -->
    <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span class="badge badge-blue" style="font-size: 13px; padding: 4px 10px;">
                Paket: <?php echo e($selectedFolder); ?>
            </span>
            <span id="preview-title" style="font-size: 14px; font-weight: 800; color: #0f172a;">
                Butir Soal Nomor <?php echo $selectedNo; ?> dari <?php echo $totalSoal; ?>
            </span>
            <span id="preview-type-badge" class="badge badge-gray" style="font-size: 12px; padding: 4px 8px;">
                Mendeteksi Tipe...
            </span>
            <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                <span style="color: #64748b; font-weight: 600;">Kunci:</span>
                <span id="preview-kunci-val" style="padding: 2px 8px; border-radius: 6px; font-weight: 800; font-family: monospace; font-size: 13px; background: #f1f5f9; color: #64748b;">
                    -
                </span>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="button" id="btn-prev-p" onclick="prevQuestion()" class="btn btn-secondary btn-sm" <?php echo ($selectedNo <= 1) ? 'disabled' : ''; ?>>
                ◀ Soal Sebelumnya
            </button>
            <button type="button" id="btn-next-p" onclick="nextQuestion()" class="btn btn-primary btn-sm" <?php echo ($selectedNo >= $totalSoal) ? 'disabled' : ''; ?>>
                Soal Berikutnya ▶
            </button>
        </div>
    </div>

    <!-- Frame Tampilan Soal Asli -->
    <div style="position: relative; background: #ffffff; border: 2px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); min-height: 560px;">
        <div id="preview-loader" style="display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.88); z-index: 10; align-items: center; justify-content: center; gap: 10px;">
            <div style="width: 22px; height: 22px; border: 3px solid #e2e8f0; border-top-color: #0284c7; border-radius: 50%; animation: spin 0.7s linear infinite;"></div>
            <span style="font-size: 13px; font-weight: 700; color: #0284c7;">Memuat Berkas Soal...</span>
        </div>
        <iframe id="preview-iframe" src="<?php echo $iframeUrl; ?>" style="width: 100%; min-height: 580px; border: none; background: #ffffff;"></iframe>
    </div>
</div>

<script>
const folder = '<?php echo e($selectedFolder); ?>';
const totalQuestions = <?php echo (int) $totalSoal; ?>;
const baseUrl = '<?php echo base_url('soal/'); ?>' + folder + '/';
const kunciMap = <?php echo json_encode($kunciList); ?>;
let currentNo = <?php echo (int) $selectedNo; ?>;

const iframe = document.getElementById('preview-iframe');
const loader = document.getElementById('preview-loader');
const titleEl = document.getElementById('preview-title');
const btnPrev = document.getElementById('btn-prev-p');
const btnNext = document.getElementById('btn-next-p');
const typeBadge = document.getElementById('preview-type-badge');
const kunciValEl = document.getElementById('preview-kunci-val');

iframe.addEventListener('load', () => {
    loader.style.display = 'none';
    try {
        const doc = iframe.contentDocument || iframe.contentWindow?.document;
        if (!doc) return;

        // 1. Sembunyikan HANYA tombol submit legacy (Form dan seluruh opsi tetap tampil)
        doc.querySelectorAll('input[type="submit"], input[name="jawab"], button[type="submit"], input[value="Kirim Jawaban"]').forEach(el => {
            el.style.display = 'none';
        });

        // 2. Pastikan form, tabel matriks, dan container opsi tampil utuh
        doc.querySelectorAll('form[name="form1"], form, .text_normal, table, label').forEach(el => {
            el.style.display = '';
        });

        // 3. Inject CSS khusus pratinjau agar radio button, checkbox, dan tabel rapi & terbaca
        let pStyle = doc.getElementById('preview-custom-style');
        if (!pStyle) {
            pStyle = doc.createElement('style');
            pStyle.id = 'preview-custom-style';
            pStyle.innerHTML = `
                input[type="radio"][name="jawaban"] {
                    display: inline-block !important;
                    margin-right: 6px !important;
                    cursor: default !important;
                }
                input[type="radio"], input[type="checkbox"] {
                    cursor: default !important;
                }
                .nomor {
                    font-size: 14px !important;
                    font-weight: 800 !important;
                    color: #0284c7 !important;
                }
                body {
                    padding: 16px !important;
                    font-family: system-ui, -apple-system, sans-serif !important;
                    color: #1e293b !important;
                }
                table {
                    border-collapse: collapse !important;
                }
                td, th {
                    border: 1px solid #cbd5e1 !important;
                    padding: 6px 10px !important;
                }
                table[width="100%"][border="0"] td {
                    border: none !important;
                }
            `;
            doc.head.appendChild(pStyle);
        }

        // 4. Pastikan nomor soal di badge soal sesuai dengan nomor pratinjau
        doc.querySelectorAll('.nomor').forEach(el => {
            el.textContent = currentNo;
        });

        // 5. Deteksi Tipe Soal
        const isBS = doc.querySelector('input[name="jawaban1"]') !== null;
        const isCheckbox = doc.querySelector('input[type="checkbox"][name="jawaban[]"]') !== null;
        const isText = doc.querySelector('input[type="text"][name="jawaban"], textarea[name="jawaban"]') !== null;
        const isLewati = (doc.body.textContent || '').includes('Lewati soal ini');

        let tipeText = 'Pilihan Ganda Biasa (A, B, C, D)';
        let tipeBadgeClass = 'badge-blue';

        if (isLewati) {
            tipeText = 'Soal Dilewati (Placeholder)';
            tipeBadgeClass = 'badge-amber';
        } else if (isBS) {
            tipeText = 'PG Kompleks: Tabel Benar / Salah';
            tipeBadgeClass = 'badge-emerald';
        } else if (isCheckbox) {
            tipeText = 'PG Kompleks: Centang Kotak';
            tipeBadgeClass = 'badge-sky';
        } else if (isText) {
            tipeText = 'Isian Singkat / Uraian';
            tipeBadgeClass = 'badge-purple';
        }

        if (typeBadge) {
            typeBadge.textContent = tipeText;
            typeBadge.className = 'badge ' + tipeBadgeClass;
        }

        // 6. Tampilkan Kunci Jawaban Resmi
        const currentKey = (kunciMap[currentNo] || '').trim();
        if (kunciValEl) {
            if (currentKey !== '') {
                kunciValEl.textContent = currentKey;
                kunciValEl.style.background = '#dcfce7';
                kunciValEl.style.color = '#15803d';
                kunciValEl.style.border = '1px solid #86efac';
            } else {
                kunciValEl.textContent = 'Belum diatur';
                kunciValEl.style.background = '#f1f5f9';
                kunciValEl.style.color = '#64748b';
                kunciValEl.style.border = '1px solid #cbd5e1';
            }
        }

        // 7. Berikan centang/pilihan otomatis sesuai Kunci Jawaban agar proktor langsung tahu jawabannya
        if (currentKey) {
            if (isBS) {
                currentKey.split(',').forEach(v => {
                    const r = doc.querySelector('input[type="radio"][value="' + v.trim() + '"]');
                    if (r) r.checked = true;
                });
            } else if (isCheckbox) {
                const parts = currentKey.split(',').map(s => s.trim());
                doc.querySelectorAll('input[type="checkbox"][name="jawaban[]"]').forEach(chk => {
                    chk.checked = parts.includes(chk.value);
                });
            } else {
                const r = doc.querySelector('input[type="radio"][value="' + currentKey + '"]');
                if (r) r.checked = true;
            }
        }

        // 8. Auto resize iframe height
        setTimeout(() => {
            try {
                const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight, 560);
                iframe.style.height = (h + 30) + 'px';
            } catch (e) {}
        }, 80);

    } catch (e) {
        console.warn('Iframe load listener error:', e);
    }
});

function switchQuestion(num) {
    if (num < 1) num = 1;
    if (num > totalQuestions) num = totalQuestions;
    currentNo = num;

    // Tampilkan loader spinner
    loader.style.display = 'flex';
    iframe.src = baseUrl + currentNo + '.php?preview=1';

    // Update Title
    titleEl.textContent = 'Butir Soal Nomor ' + currentNo + ' dari ' + totalQuestions;

    // Update Nav Buttons
    btnPrev.disabled = (currentNo === 1);
    btnNext.disabled = (currentNo === totalQuestions);

    // Update Palette Buttons
    document.querySelectorAll('.btn-pno').forEach(b => {
        const no = parseInt(b.getAttribute('data-no'), 10);
        if (no === currentNo) {
            b.className = 'btn btn-primary btn-sm btn-pno';
            b.style.fontWeight = '800';
        } else {
            b.className = 'btn btn-secondary btn-sm btn-pno';
            b.style.fontWeight = '600';
        }
    });

    // Update URL query string tanpa reload
    const newUrl = window.location.pathname + '?folder=' + encodeURIComponent(folder) + '&no=' + currentNo;
    window.history.replaceState({ no: currentNo }, '', newUrl);
}

function prevQuestion() {
    if (currentNo > 1) switchQuestion(currentNo - 1);
}

function nextQuestion() {
    if (currentNo < totalQuestions) switchQuestion(currentNo + 1);
}

// Keyboard arrow navigation di preview
document.addEventListener('keydown', (e) => {
    if (e.target.tagName.toLowerCase() === 'input' || e.target.tagName.toLowerCase() === 'select') return;
    if (e.key === 'ArrowLeft') {
        prevQuestion();
    } else if (e.key === 'ArrowRight') {
        nextQuestion();
    }
});
</script>

<?php admin_footer(); ?>
