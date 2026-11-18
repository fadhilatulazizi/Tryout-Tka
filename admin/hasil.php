<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

Auth::requireAdmin();
$db = get_db();
$school = get_school_profile();

$allJadwal = get_all_jadwal();
$activeJadwal = get_active_jadwal();

// Menentukan Hari / Jadwal yang dipilih
$p = isset($_GET['p']) ? preg_replace('/[^0-9a-zA-Z_-]/', '', (string)$_GET['p']) : '';
if ($p === '') {
    $p = !empty($activeJadwal['hari']) ? (string)$activeJadwal['hari'] : '1';
}

$curJadwal = get_jadwal_by_hari($p);
$table = $p === '1' ? 'pg_tb' : 'pg_tb_' . $p;
ensure_jawaban_table($table);

if ($curJadwal) {
    $defaultPaket = !empty($curJadwal['folder_soal']) ? $curJadwal['folder_soal'] : ($p === '1' ? ($school['soal_hari1'] ?? 'conf31') : 'conf32');
    $mapelTitle = !empty($curJadwal['mapel']) ? $curJadwal['mapel'] : ($p === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : 'Mata Pelajaran');
} else {
    $defaultPaket = $p === '1' ? ($school['soal_hari1'] ?? 'conf31') : ($school['soal_hari2'] ?? 'conf32');
    $mapelTitle = $p === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : ($school['mapel_hari2'] ?? 'Bahasa Indonesia');
}
$totalSoalAktif = SyncEngine::getPackageQuestionCount($defaultPaket);

// Aksi Bersihkan Hasil
if (isset($_GET['clear']) && $_GET['clear'] === $p) {
    mysqli_query($db, "DELETE FROM {$table}");
    header('Location: ' . base_url('admin/hasil.php?p=' . $p));
    exit;
}

// Aksi Simpan Kunci Jawaban
$msg = '';
$msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_kunci') {
    $paket = trim($_POST['paket'] ?? $defaultPaket);
    $kunciArr = [];
    for ($i = 1; $i <= $totalSoalAktif; $i++) {
        $kunciArr[$i] = strtoupper(trim($_POST['k_' . $i] ?? ''));
    }
    $kunciJson = json_encode($kunciArr);

    $stmt = mysqli_prepare($db, "INSERT INTO kunci_jawaban (paket, kunci) VALUES (?, ?) ON DUPLICATE KEY UPDATE kunci = VALUES(kunci)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $paket, $kunciJson);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = "Kunci jawaban untuk paket {$paket} berhasil disimpan.";
        $msgType = 'success';
    }
}

// Ambil Kunci Jawaban Aktif
$qKunci = mysqli_query($db, "SELECT kunci FROM kunci_jawaban WHERE paket = '{$defaultPaket}' LIMIT 1");
$kunci = [];
if ($qKunci && $rowK = mysqli_fetch_assoc($qKunci)) {
    $kunci = json_decode($rowK['kunci'], true) ?: [];
}
$hasKunci = !empty(array_filter($kunci));

// Fungsi normalisasi jawaban (menghilangkan spasi & mengurutkan checkbox)
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

// Ambil Data Jawaban Siswa
$sql = "SELECT * FROM {$table} ORDER BY kelas ASC, abs ASC, nama ASC";
$result = mysqli_query($db, $sql);

// Jumlah butir soal yang memiliki kunci (soal dinilai)
$totalSoalDinilai = count(array_filter($kunci));
if ($totalSoalDinilai === 0) {
    $totalSoalDinilai = $totalSoalAktif;
}

// Proses Data Peserta & Hitung Skor
$rekapSiswa = [];
$totalPeserta = 0;
$totalNilai = 0;
$nilaiTertinggi = 0;
$nilaiTerendah = 100;

if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $totalPeserta++;
        $benar = 0;
        $salah = 0;
        $kosong = 0;

        for ($i = 1; $i <= $totalSoalAktif; $i++) {
            $ans = $normJawaban($r['j' . $i] ?? '');
            $k = $normJawaban($kunci[$i] ?? '');

            if ($ans === '') {
                $kosong++;
            } elseif ($hasKunci && $ans === $k) {
                $benar++;
            } else {
                $salah++;
            }
        }

        $skor = ($hasKunci && $totalSoalDinilai > 0) ? round(($benar / $totalSoalDinilai) * 100, 1) : 0;
        $totalNilai += $skor;
        if ($skor > $nilaiTertinggi) $nilaiTertinggi = $skor;
        if ($skor < $nilaiTerendah) $nilaiTerendah = $skor;

        $r['benar'] = $benar;
        $r['salah'] = $salah;
        $r['kosong'] = $kosong;
        $r['skor'] = $skor;
        $rekapSiswa[] = $r;
    }
}

if ($totalPeserta === 0) {
    $nilaiTerendah = 0;
}
$rataRata = $totalPeserta > 0 ? round($totalNilai / $totalPeserta, 1) : 0;

// Ekspor ke Excel Lengkap dengan Kunci & Nilai
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $filename = "Hasil_Nilai_" . str_replace(' ', '_', $mapelTitle) . "_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}");
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body>";
    echo "<h3>REKAPITULASI HASIL ASESMEN - " . htmlspecialchars($school['sekolah'], ENT_QUOTES, 'UTF-8') . "</h3>";
    echo "<p>Mata Pelajaran: <strong>" . htmlspecialchars($mapelTitle, ENT_QUOTES, 'UTF-8') . "</strong> | Paket Soal: <strong>" . htmlspecialchars($defaultPaket, ENT_QUOTES, 'UTF-8') . "</strong> | Tanggal: " . date('d-m-Y H:i') . "</p>";
    
    echo "<table border='1'>";
    echo "<tr style='background: #e2e8f0;'>";
    echo "<th>No</th><th>Sekolah</th><th>Nama Siswa</th><th>Kelas</th><th>Absen</th><th>Benar</th><th>Salah</th><th>Kosong</th><th>Nilai (0-100)</th>";
    for ($i = 1; $i <= $totalSoalAktif; $i++) echo "<th>{$i}</th>";
    echo "</tr>";

    // Baris Kunci Jawaban
    if ($hasKunci) {
        echo "<tr style='background: #fef08a; font-weight: bold;'>";
        echo "<td colspan='5' align='center'>KUNCI JAWABAN RESMI</td><td colspan='4'></td>";
        for ($i = 1; $i <= $totalSoalAktif; $i++) {
            echo "<td align='center'>" . ($kunci[$i] ?? '-') . "</td>";
        }
        echo "</tr>";
    }

    $no = 1;
    foreach ($rekapSiswa as $s) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($s['sekolah'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($s['nama'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($s['kelas'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($s['abs'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td align='center'><strong>" . $s['benar'] . "</strong></td>";
        echo "<td align='center'>" . $s['salah'] . "</td>";
        echo "<td align='center'>" . $s['kosong'] . "</td>";
        echo "<td align='center' style='font-size: 14px; font-weight: bold;'>" . $s['skor'] . "</td>";
        for ($i = 1; $i <= $totalSoalAktif; $i++) {
            $ans = strtoupper(trim((string) ($s['j' . $i] ?? '')));
            echo "<td align='center'>" . htmlspecialchars($ans, ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table></body></html>";
    exit;
}

require_once __DIR__ . '/layout.php';
admin_header('Hasil Ujian & Penilaian', 'hasil');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<!-- PILIH HARI UJIAN -->
<div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <?php if (!empty($allJadwal)): ?>
        <?php foreach ($allJadwal as $j): ?>
            <?php $jHari = (string)$j['hari']; ?>
            <a href="<?php echo base_url('admin/hasil.php?p=' . urlencode($jHari)); ?>" class="btn <?php echo (string)$p === $jHari ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                <span><?php echo e($j['nama_hari'] ?: ('Hari ke-' . $jHari)); ?>: <strong><?php echo e($j['mapel']); ?></strong> (<?php echo e($j['folder_soal']); ?>)</span>
                <?php if (!empty($j['aktif'])): ?>
                    <span style="background: #10b981; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 9999px;">AKTIF</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <a href="<?php echo base_url('admin/hasil.php?p=1'); ?>" class="btn <?php echo $p === '1' ? 'btn-primary' : 'btn-secondary'; ?>">
            Hari ke-1: <?php echo e($school['mapel_hari1'] ?? 'Matematika'); ?> (<?php echo e($school['soal_hari1'] ?? 'conf31'); ?>)
        </a>
        <a href="<?php echo base_url('admin/hasil.php?p=2'); ?>" class="btn <?php echo $p === '2' ? 'btn-primary' : 'btn-secondary'; ?>">
            Hari ke-2: <?php echo e($school['mapel_hari2'] ?? 'Bahasa Indonesia'); ?> (<?php echo e($school['soal_hari2'] ?? 'conf32'); ?>)
        </a>
    <?php endif; ?>
</div>

<!-- METRIC STATISTIK HASIL -->
<div class="stat-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div class="stat-info">
            <h3><?php echo $totalPeserta; ?></h3>
            <p>Lembar Jawaban Masuk</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📊</div>
        <div class="stat-info">
            <h3><?php echo $rataRata; ?></h3>
            <p>Rata-rata Nilai Siswa</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">🏆</div>
        <div class="stat-info">
            <h3><?php echo $nilaiTertinggi; ?></h3>
            <p>Nilai Tertinggi</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">📉</div>
        <div class="stat-info">
            <h3><?php echo $nilaiTerendah; ?></h3>
            <p>Nilai Terendah</p>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2>Hasil Ujian <?php echo e($mapelTitle); ?></h2>
            <p>Paket Soal: <code><?php echo e($defaultPaket); ?></code> (<strong><?php echo $totalSoalAktif; ?> Butir Soal Aktif</strong>) &bull; Kunci Jawaban: <strong><?php echo $hasKunci ? '<span class="text-emerald-600">Terpasang (' . $totalSoalAktif . ' Butir)</span>' : '<span class="text-amber-600">Belum Diatur</span>'; ?></strong></p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openKunciModal()">
                🎯 <?php echo $hasKunci ? 'Ubah Kunci Jawaban' : '+ Masukkan Kunci Jawaban'; ?>
            </button>
            <a href="<?php echo base_url('admin/hasil.php?p=' . $p . '&export=1'); ?>" class="btn btn-success btn-sm">
                📥 Ekspor Rekap Nilai (.xls)
            </a>
            <a href="<?php echo base_url('admin/hasil.php?p=' . $p . '&clear=' . $p); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Kosongkan semua hasil ujian untuk periode ini?')">
                Bersihkan
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px;">No</th>
                    <th>Nama Peserta</th>
                    <th>Kelas</th>
                    <th>Abs</th>
                    <?php if ($hasKunci): ?>
                        <th style="text-align: center; background: #ecfdf5; color: #065f46;">Benar</th>
                        <th style="text-align: center; background: #fef2f2; color: #991b1b;">Salah</th>
                        <th style="text-align: center; background: #f8fafc;">Kosong</th>
                        <th style="text-align: center; background: #e0f2fe; color: #0369a1; font-size: 14px;">Skor Nilai</th>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalSoalAktif; $i++): ?>
                        <th style="padding: 4px 6px; text-align: center; font-size: 11px; min-width: 24px;">
                            <?php echo $i; ?>
                            <?php if ($hasKunci && !empty($kunci[$i])): ?>
                                <br><span style="color: #0284c7; font-weight: 800;"><?php echo $kunci[$i]; ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rekapSiswa)): ?>
                    <?php $no = 1; foreach ($rekapSiswa as $s): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo e($s['nama']); ?></strong></td>
                            <td><?php echo e($s['kelas']); ?></td>
                            <td><?php echo e($s['abs']); ?></td>
                            <?php if ($hasKunci): ?>
                                <td style="text-align: center; font-weight: 800; color: #059669;"><?php echo $s['benar']; ?></td>
                                <td style="text-align: center; font-weight: 700; color: #dc2626;"><?php echo $s['salah']; ?></td>
                                <td style="text-align: center; color: #64748b;"><?php echo $s['kosong']; ?></td>
                                <td style="text-align: center; font-size: 15px; font-weight: 800; color: #0284c7; background: #f0f9ff;">
                                    <?php echo $s['skor']; ?>
                                </td>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalSoalAktif; $i++): ?>
                                <?php 
                                $ansRaw = strtoupper(trim((string) ($s['j' . $i] ?? '')));
                                $ans = $normJawaban($ansRaw);
                                $k = $normJawaban($kunci[$i] ?? '');
                                $bg = '';
                                if ($hasKunci && $ans !== '') {
                                    $bg = ($ans === $k) ? 'background: #dcfce7; color: #15803d; font-weight: bold;' : 'background: #fee2e2; color: #b91c1c;';
                                }
                                ?>
                                <td style="padding: 4px; text-align: center; font-size: 11px; <?php echo $bg; ?>">
                                    <?php echo $ansRaw !== '' ? $ansRaw : '-'; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $hasKunci ? (8 + $totalSoalAktif) : (5 + $totalSoalAktif); ?>" style="text-align: center; padding: 24px; color: #64748b;">
                            Belum ada jawaban siswa yang dihimpun pada hari ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL ATUR KUNCI JAWABAN -->
<div id="modal-kunci" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 16px; width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
            <div>
                <h2 style="font-size: 18px; font-weight: 800; color: #0f172a;">Input Kunci Jawaban (<?php echo $totalSoalAktif; ?> Butir)</h2>
                <p style="font-size: 12px; color: #64748b;">Paket Soal: <strong><?php echo e($defaultPaket); ?></strong> (<?php echo e($mapelTitle); ?>)</p>
            </div>
            <button type="button" onclick="closeKunciModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
        </div>

        <!-- GOOGLE GEMINI AI GENERATOR BOX -->
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%); border: 2px solid #0284c7; border-radius: 14px; padding: 18px; margin-bottom: 22px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.12);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 20px;">✨</span>
                        <strong style="font-size: 15px; color: #0369a1;">Google Gemini AI Auto-Solver</strong>
                        <span style="font-size: 10.5px; font-weight: 800; background: linear-gradient(135deg, #0284c7, #2563eb); color: #fff; padding: 2px 8px; border-radius: 9999px;">Gemini 3.8 Flash</span>
                    </div>
                    <p style="font-size: 12px; color: #475569; margin-top: 4px; line-height: 1.4;">
                        Kirimkan butir soal paket <strong><?php echo e($defaultPaket); ?></strong> ke Google Gemini untuk dianalisis dan dirumuskan kunci jawabannya secara langsung.
                    </p>
                </div>
                <div>
                    <button type="button" id="btn-generate-ai" onclick="generateKunciGemini()" class="btn btn-primary" style="background: linear-gradient(135deg, #0284c7 0%, #059669 100%); border: none; font-weight: 800; padding: 8px 18px; font-size: 13px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);">
                        <span id="ai-btn-icon">⚡</span> <span id="ai-btn-text">Generate Kunci dengan Google Gemini</span>
                    </button>
                </div>
            </div>

            <!-- Gemini API Key Input Row -->
            <div style="background: #ffffff; border: 1px solid #bae6fd; border-radius: 10px; padding: 12px; margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 11.5px; font-weight: 700; color: #1e293b; margin: 0;">
                        🔑 GOOGLE GEMINI API KEY & MODEL:
                    </label>
                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" style="color: #0284c7; text-decoration: underline; font-weight: 600; font-size: 11px;">
                        Dapatkan API Key Gratis di Google AI Studio &rarr;
                    </a>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input type="password" id="gemini-api-key" class="form-control" style="font-size: 12px; font-family: monospace; flex: 1; min-width: 240px;" placeholder="Tempelkan API Key Anda di sini (contoh: AIzaSy...)" value="<?php echo e($school['gemini_key'] ?? ''); ?>">
                    <select id="gemini-model-choice" class="form-control" style="font-size: 12px; width: auto; min-width: 175px;">
                        <option value="auto">⚡ Mode: Auto Fallback Cerdas</option>
                        <option value="gemini-3.5-flash-lite">gemini-3.5-flash-lite (Kuota 500/hari - Rekomendasi)</option>
                        <option value="gemini-3.8-flash">gemini-3.8-flash (Tercanggih)</option>
                        <option value="gemini-3.1-flash-lite">gemini-3.1-flash-lite (Kuota 500/hari)</option>
                        <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite</option>
                        <option value="gemini-2.5-flash">gemini-2.5-flash</option>
                        <option value="gemini-3.6-flash">gemini-3.6-flash</option>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="saveGeminiKey()">Simpan Key</button>
                </div>
            </div>

            <!-- Status Box AI -->
            <div id="ai-status-box" style="display: none; margin-top: 12px; padding: 10px 14px; background: #ffffff; border-radius: 8px; font-size: 12px; border: 1px solid #bae6fd; line-height: 1.5;">
                <span id="ai-status-text"></span>
            </div>
        </div>

        <form method="post" action="">
            <input type="hidden" name="action" value="save_kunci">
            <input type="hidden" name="paket" value="<?php echo e($defaultPaket); ?>">

            <!-- Quick Paste String Input -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 700; color: #1e293b; display: block; margin-bottom: 4px;">PENGISIAN CEPAT (STRING KUNCI):</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="paste-kunci-str" class="form-control" placeholder="Tempel <?php echo $totalSoalAktif; ?> huruf sekaligus, contoh: ABCDABCD... atau A,B,C,D..." style="font-family: monospace; font-size: 12.5px; text-transform: uppercase;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="applyQuickString()">Terapkan</button>
                </div>
            </div>

            <!-- Datalist Rekomendasi Pilihan -->
            <datalist id="opts-list">
                <option value="A"></option>
                <option value="B"></option>
                <option value="C"></option>
                <option value="D"></option>
                <option value="E"></option>
            </datalist>

            <!-- Grid Butir Soal Aktif -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 10px; margin-bottom: 24px;">
                <?php for ($q = 1; $q <= $totalSoalAktif; $q++): ?>
                    <div style="border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; background: #fafafa; display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 12px; font-weight: 700; color: #475569;">No. <?php echo $q; ?></span>
                        <input type="text" name="k_<?php echo $q; ?>" id="k_<?php echo $q; ?>" list="opts-list" class="form-control" value="<?php echo e($kunci[$q] ?? ''); ?>" placeholder="A / 1B,2S,3B / 1,3" style="width: 105px; padding: 4px 6px; font-weight: 800; text-align: center; text-transform: uppercase; font-size: 11.5px;">
                    </div>
                <?php endfor; ?>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeKunciModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kunci & Hitung Nilai</button>
            </div>
        </form>
    </div>
</div>

<script>
function openKunciModal() {
    document.getElementById('modal-kunci').style.display = 'flex';
}
function closeKunciModal() {
    document.getElementById('modal-kunci').style.display = 'none';
}
function applyQuickString() {
    let str = document.getElementById('paste-kunci-str').value.toUpperCase().replace(/[^ABCDE]/g, '');
    if (str.length === 0) {
        alert('Harap masukkan rangkaian huruf kunci A, B, C, D, atau E.');
        return;
    }
    const maxQ = <?php echo (int) $totalSoalAktif; ?>;
    for (let i = 1; i <= maxQ; i++) {
        if (i <= str.length) {
            const char = str[i - 1];
            const selectEl = document.getElementById('k_' + i);
            if (selectEl) selectEl.value = char;
        }
    }
}

function generateKunciGemini() {
    const btn = document.getElementById('btn-generate-ai');
    const icon = document.getElementById('ai-btn-icon');
    const text = document.getElementById('ai-btn-text');
    const statusBox = document.getElementById('ai-status-box');
    const statusText = document.getElementById('ai-status-text');
    const apiKeyInput = document.getElementById('gemini-api-key');
    const apiKey = apiKeyInput?.value.trim() || '';

    if (!apiKey) {
        statusBox.style.display = 'block';
        statusText.innerHTML = '<span style="color: #dc2626; font-weight: 700;">⚠️ Harap masukkan Google Gemini API Key Anda terlebih dahulu pada kotak di atas.</span> Dapatkan key gratis di <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color: #0284c7; text-decoration: underline;">Google AI Studio</a>.';
        apiKeyInput?.focus();
        return;
    }

    btn.disabled = true;
    icon.textContent = '⏳';
    text.textContent = 'Google Gemini Menganalisis...';
    statusBox.style.display = 'block';
    statusText.innerHTML = '<strong>Sedang memproses:</strong> Mengirimkan butir-butir soal ke Google Gemini untuk dianalisis dan di-generate...';

    const formData = new FormData();
    formData.append('paket', '<?php echo e($defaultPaket); ?>');
    formData.append('gemini_key', apiKey);
    formData.append('model', document.getElementById('gemini-model-choice')?.value || 'auto');

    fetch('<?php echo base_url("admin/api_ai_kunci.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(d => {
        btn.disabled = false;
        icon.textContent = '⚡';
        text.textContent = 'Generate Kunci dengan Google Gemini';

        if (d.success && d.kunci) {
            // 1. Terapkan ke string kunci
            if (d.kunci_string) {
                document.getElementById('paste-kunci-str').value = d.kunci_string;
            }

            // 2. Terapkan ke dropdown butir soal
            for (const q in d.kunci) {
                const sel = document.getElementById('k_' + q);
                if (sel) {
                    sel.value = d.kunci[q];
                    sel.style.backgroundColor = '#ecfdf5';
                    sel.style.borderColor = '#10b981';
                }
            }

            statusText.innerHTML = '<span style="color: #059669; font-weight: 700;">✓ Sukses! ' + (d.message || '') + ' (' + d.total_soal + ' butir kunci berhasil dipecahkan).</span><br><span style="color: #475569; font-size: 11.5px;">Silakan tinjau jawaban di bawah, lalu klik <strong>"Simpan Kunci & Hitung Nilai"</strong> untuk mengaktifkannya.</span>';
        } else {
            statusText.innerHTML = '<span style="color: #dc2626; font-weight: 600;">Gagal: ' + (d.message || 'Terjadi kesalahan pada Google Gemini API.') + '</span>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.textContent = '⚡';
        text.textContent = 'Generate Kunci dengan Google Gemini';
        statusText.innerHTML = '<span style="color: #dc2626;">Gagal menghubungi server Gemini: ' + err.message + '</span>';
    });
}

function saveGeminiKey() {
    const key = document.getElementById('gemini-api-key').value.trim();
    if (!key) {
        alert('Harap masukkan API Key terlebih dahulu.');
        return;
    }
    const formData = new FormData();
    formData.append('gemini_key', key);
    formData.append('save_key', '1');
    formData.append('paket', '<?php echo e($defaultPaket); ?>');

    fetch('<?php echo base_url("admin/api_ai_kunci.php"); ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            alert(d.message || 'Google Gemini API Key berhasil disimpan ke database sekolah.');
        })
        .catch(() => {
            alert('Gagal menyimpan API Key.');
        });
}
</script>

<?php admin_footer(); ?>