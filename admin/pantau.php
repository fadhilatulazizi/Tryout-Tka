<?php
require_once __DIR__ . '/layout.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

$db = get_db();
$school = get_school_profile();
$activeJadwal = get_active_jadwal();
$hari = (string)($activeJadwal['hari'] ?? $school['hari'] ?? '1');
$jawabanTable = $hari === '1' ? 'pg_tb' : 'pg_tb_' . $hari;
ensure_jawaban_table($jawabanTable);
$defaultPaket = !empty($activeJadwal['folder_soal']) ? $activeJadwal['folder_soal'] : ($hari === '1' ? ($school['soal_hari1'] ?? 'conf31') : 'conf32');
$mapelTitle = !empty($activeJadwal['mapel']) ? $activeJadwal['mapel'] : ($hari === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : 'Mata Pelajaran');
$totalSoalAktif = SyncEngine::getPackageQuestionCount($defaultPaket);

// Ambil daftar siswa awal beserta lembar jawaban (SSR fallback)
$sql = "SELECT a.id, a.kelas, a.abs, a.nama, a.user, a.sesi, a.ruang, a.status, a.pelanggaran, a.ragu, j.*
        FROM account a
        LEFT JOIN {$jawabanTable} j ON a.id = j.id
        ORDER BY a.kelas ASC, a.abs ASC, a.nama ASC";
$result = mysqli_query($db, $sql);

// Statistik Status
$qStats = mysqli_query($db, "SELECT status, COUNT(*) as c FROM account GROUP BY status");
$statCounts = ['0' => 0, '1' => 0, '2' => 0, '3' => 0];
if ($qStats) {
    while ($r = mysqli_fetch_assoc($qStats)) {
        $statCounts[$r['status']] = (int) $r['c'];
    }
}

$qVio = mysqli_query($db, "SELECT SUM(pelanggaran) as tot FROM account");
$totalViolations = (int) (mysqli_fetch_assoc($qVio)['tot'] ?? 0);

// Ambil Siaran Aktif Terakhir
$activeBroadcast = null;
$cached = get_cached_broadcast();
if ($cached && !empty($cached['aktif'])) {
    $activeBroadcast = [
        'id' => (int) $cached['id'],
        'pesan' => $cached['pesan'],
        'created_at' => date('Y-m-d ') . ($cached['time'] ?? '12:00') . ':00'
    ];
} else {
    $qLastBc = mysqli_query($db, "SELECT * FROM broadcast_pesan WHERE aktif = 1 ORDER BY id DESC LIMIT 1");
    if ($qLastBc && mysqli_num_rows($qLastBc) > 0) {
        $activeBroadcast = mysqli_fetch_assoc($qLastBc);
    }
}

admin_header('Pantau Ujian Live (Proctoring)', 'pantau');
?>

<!-- Header Toolbar & Live Indicators -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <p style="font-size: 13px; color: #64748b;">
            Server ID: <strong style="color: #0f172a;"><?php echo e($school['server_id'] ?? 'SRV-01'); ?></strong> &bull;
            Mata Pelajaran: <strong><?php echo e($mapelTitle); ?> (Hari ke-<?php echo e($hari); ?>)</strong>
        </p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <!-- Live Pulse Badge -->
        <div id="live-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 9999px; background: #ecfdf5; border: 1px solid #a7f3d0; font-size: 12px; font-weight: 700; color: #065f46;">
            <span id="live-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; animation: livePulse 1.2s infinite;"></span>
            <span id="live-timer-text">Live (10s)</span>
        </div>

        <button type="button" id="btn-pause-poll" class="btn btn-secondary btn-sm" onclick="toggleAutoPoll()" title="Jeda atau lanjutkan pembaruan otomatis">⏸️ Jeda</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="refreshLiveData(true)" title="Segarkan data seketika">🔄 Segarkan</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="toggleBroadcastForm()">📢 Kirim Siaran</button>
    </div>
</div>

<style>
@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.85); }
}
.toast-msg {
    min-width: 260px;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    animation: toastSlide 0.3s ease-out;
}
.toast-success { background: #065f46; color: #ffffff; }
.toast-info { background: #0369a1; color: #ffffff; }
.toast-error { background: #991b1b; color: #ffffff; }
@keyframes toastSlide {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<!-- Floating Toast Container -->
<div id="toast-container" style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px;"></div>

<!-- FORM BROADCAST POP-UP -->
<div id="broadcast-box" style="display: none; background: #f0f9ff; border: 2px solid #0284c7; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
    <h3 style="font-size: 15px; font-weight: 800; color: #0369a1; margin-bottom: 8px;">📢 Kirim Pengumuman Siaran (Broadcast) ke Layar Siswa</h3>
    <p style="font-size: 12px; color: #64748b; margin-bottom: 14px;">Pesan akan langsung muncul sebagai notifikasi pop-up dan berbunyi di layar monitor siswa yang sedang mengerjakan ujian via AJAX seketika tanpa refresh.</p>
    
    <form id="form-broadcast" onsubmit="handleSendBroadcast(event)">
        <div style="margin-bottom: 12px;">
            <textarea id="bc-pesan" name="pesan" rows="2" class="form-control" style="font-size: 13.5px;" placeholder="Tuliskan isi pengumuman, contoh: Waktu ujian tersisa 15 menit lagi, silakan periksa kembali jawaban Anda!" required></textarea>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 12px;">
                <select id="bc-sesi" name="target_sesi" class="form-control" style="width: auto; font-size: 12px;">
                    <option value="all">Semua Sesi</option>
                    <option value="1">Khusus Sesi 1</option>
                    <option value="2">Khusus Sesi 2</option>
                    <option value="3">Khusus Sesi 3</option>
                </select>
                <select id="bc-ruang" name="target_ruang" class="form-control" style="width: auto; font-size: 12px;">
                    <option value="all">Semua Ruang</option>
                    <option value="1">Khusus Ruang 1</option>
                    <option value="2">Khusus Ruang 2</option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleBroadcastForm()">Batal</button>
                <button type="submit" id="btn-submit-bc" class="btn btn-primary btn-sm">🚀 Kirimkan Sekarang</button>
            </div>
        </div>
    </form>
</div>

<!-- Active Broadcast Banner -->
<div id="active-bc-banner" style="<?php echo $activeBroadcast ? '' : 'display: none;'; ?> background: #fefce8; border: 1px solid #fde047; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; display: <?php echo $activeBroadcast ? 'flex' : 'none'; ?>; justify-content: space-between; align-items: center; gap: 12px;">
    <div style="font-size: 13px; color: #854d0e;">
        <strong>📢 Siaran Terakhir Aktif:</strong> <span id="bc-active-text">"<?php echo e($activeBroadcast['pesan'] ?? ''); ?>"</span> 
        <span id="bc-active-time" style="font-size: 11px; color: #a16207;">(Pukul <?php echo substr($activeBroadcast['created_at'] ?? '', 11, 5); ?> WIB)</span>
    </div>
    <button type="button" onclick="handleClearBroadcast()" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 10px;">Tutup Siaran</button>
</div>

<!-- Stat Bar (Auto updated via AJAX) -->
<div class="stat-grid" style="margin-bottom: 20px;">
    <div class="stat-card" style="padding: 14px 18px;">
        <div class="stat-icon amber" style="width: 38px; height: 38px; font-size: 18px;">⏳</div>
        <div class="stat-info">
            <h3 id="stat-sedang" style="font-size: 20px;"><?php echo $statCounts['1']; ?></h3>
            <p>Sedang Mengerjakan</p>
        </div>
    </div>
    <div class="stat-card" style="padding: 14px 18px;">
        <div class="stat-icon green" style="width: 38px; height: 38px; font-size: 18px;">✓</div>
        <div class="stat-info">
            <h3 id="stat-selesai" style="font-size: 20px;"><?php echo $statCounts['2']; ?></h3>
            <p>Telah Selesai</p>
        </div>
    </div>
    <div class="stat-card" style="padding: 14px 18px;">
        <div class="stat-icon purple" style="width: 38px; height: 38px; font-size: 18px;">⚪</div>
        <div class="stat-info">
            <h3 id="stat-belum" style="font-size: 20px;"><?php echo $statCounts['0']; ?></h3>
            <p>Belum Login</p>
        </div>
    </div>
    <div class="stat-card" style="padding: 14px 18px;">
        <div class="stat-icon blue" style="width: 38px; height: 38px; font-size: 18px;">🔄</div>
        <div class="stat-info">
            <h3 id="stat-reset" style="font-size: 20px;"><?php echo $statCounts['3']; ?></h3>
            <p>Minta Reset</p>
        </div>
    </div>
</div>

<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2>Monitoring Peserta Ujian Live</h2>
            <p>Pantau progres pengerjaan, deteksi keamanan tab lepas (anti-curang), dan kelola sesi peserta secara real-time via AJAX.</p>
        </div>
        <div>
            <input type="text" id="live-search" class="form-control" placeholder="Ketik kelas, nama, atau username..." style="width: 270px;" onkeyup="filterLiveTable()">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="table-pantau">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kelas</th>
                    <th>Nama Peserta</th>
                    <th>Username</th>
                    <th>Ruang/Sesi</th>
                    <th>Status Ujian</th>
                    <th style="min-width: 140px;">Progres Soal</th>
                    <th>Keamanan (Anti-Curang)</th>
                    <th>Waktu Mulai</th>
                    <th>Aksi Proktor</th>
                </tr>
            </thead>
            <tbody id="pantau-tbody">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; while ($s = mysqli_fetch_assoc($result)): ?>
                        <tr id="row-student-<?php echo $s['id']; ?>" data-id="<?php echo $s['id']; ?>" data-search="<?php echo strtolower(e($s['kelas'] . ' ' . $s['nama'] . ' ' . $s['user'])); ?>">
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo e($s['kelas']); ?></strong></td>
                            <td class="nama-col"><?php echo e($s['nama']); ?></td>
                            <td><span class="badge-mono"><?php echo e($s['user']); ?></span></td>
                            <td>R.<?php echo e($s['ruang']); ?> / S.<?php echo e($s['sesi']); ?></td>
                            <td class="cell-status">
                                <?php if ($s['status'] === '0'): ?>
                                    <span class="badge badge-gray">Belum Login</span>
                                <?php elseif ($s['status'] === '1'): ?>
                                    <span class="badge badge-amber">Sedang Ujian</span>
                                <?php elseif ($s['status'] === '2'): ?>
                                    <span class="badge badge-green">Selesai</span>
                                <?php elseif ($s['status'] === '3'): ?>
                                    <span class="badge badge-blue">Minta Reset</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-progres">
                                <?php
                                $answered = 0;
                                for ($q = 1; $q <= $totalSoalAktif; $q++) {
                                    $col = 'j' . $q;
                                    if (isset($s[$col]) && trim($s[$col]) !== '') {
                                        $answered++;
                                    }
                                }
                                $pct = $totalSoalAktif > 0 ? round(($answered / $totalSoalAktif) * 100, 1) : 0;
                                $raguArr = !empty($s['ragu']) ? json_decode($s['ragu'], true) : [];
                                $raguCount = is_array($raguArr) ? count($raguArr) : 0;
                                $barColor = $pct >= 100 ? '#10b981' : '#0284c7';
                                ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; margin-bottom: 3px;">
                                    <span style="font-weight: 700; color: #1e293b;"><?php echo $answered; ?>/<?php echo $totalSoalAktif; ?></span>
                                    <span style="color: #64748b;"><?php echo $pct; ?>%</span>
                                </div>
                                <div style="height: 6px; width: 100%; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo $pct; ?>%; background: <?php echo $barColor; ?>; border-radius: 9999px; transition: width 0.3s;"></div>
                                </div>
                                <?php if ($raguCount > 0): ?>
                                    <div style="font-size: 10.5px; color: #d97706; margin-top: 2px;">
                                        ⚠️ <?php echo $raguCount; ?> ragu-ragu
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-security">
                                <?php if ((int)($s['pelanggaran'] ?? 0) > 0): ?>
                                    <span class="badge badge-amber" style="background: #fee2e2; color: #991b1b; font-weight: 800;">
                                        ⚠️ <?php echo (int) $s['pelanggaran']; ?>x Tab Lepas
                                    </span>
                                <?php else: ?>
                                    <span style="color: #059669; font-size: 11.5px; font-weight: 600;">✓ Bersih</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-waktu" style="font-size: 11.5px; color: #64748b;">
                                <?php if (!empty($s['waktu_mulai'])): ?>
                                    <?php echo substr($s['waktu_mulai'], 11, 5); ?> WIB
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="cell-aksi">
                                <div style="display: flex; gap: 6px;">
                                    <?php if ($s['status'] === '1' || $s['status'] === '3'): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="actionReset(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nama']); ?>')">Reset Login</button>
                                        <button type="button" class="btn btn-success btn-sm" onclick="actionFinish(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nama']); ?>')">Selesai</button>
                                    <?php elseif ($s['status'] === '2'): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="actionReset(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nama']); ?>')">Buka Ulang</button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 12px;">Siap Login</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align: center; color: #64748b; padding: 24px;">Belum ada peserta terdaftar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const API_URL = '<?php echo base_url('admin/api_pantau.php'); ?>';
let pollInterval = 10; // Detik antar polling
let pollCountdown = 10;
let isPollPaused = false;
let pollTimer = null;
let isFetching = false;

// 1. Toast Notification Helper
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast-msg toast-' + type;
    toast.innerHTML = '<span>' + (type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ℹ')) + '</span> <span>' + message + '</span>';
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// 2. Form Broadcast Toggle
function toggleBroadcastForm() {
    const box = document.getElementById('broadcast-box');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
    if (box.style.display === 'block') {
        document.getElementById('bc-pesan').focus();
    }
}

// 3. Kirim Broadcast via AJAX
function handleSendBroadcast(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-bc');
    const pesan = document.getElementById('bc-pesan').value.trim();
    const sesi = document.getElementById('bc-sesi').value;
    const ruang = document.getElementById('bc-ruang').value;

    if (!pesan) return;

    btn.disabled = true;
    btn.textContent = 'Mengirim...';

    const formData = new FormData();
    formData.append('action', 'send_broadcast');
    formData.append('pesan', pesan);
    formData.append('target_sesi', sesi);
    formData.append('target_ruang', ruang);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.textContent = '🚀 Kirimkan Sekarang';
        if (d.success) {
            document.getElementById('bc-pesan').value = '';
            toggleBroadcastForm();
            showToast(d.message || 'Pesan siaran berhasil dikirim!', 'success');
            
            // Tampilkan banner siaran aktif
            if (d.broadcast) {
                const banner = document.getElementById('active-bc-banner');
                document.getElementById('bc-active-text').textContent = '"' + d.broadcast.pesan + '"';
                document.getElementById('bc-active-time').textContent = '(' + (d.broadcast.time || '') + ')';
                banner.style.display = 'flex';
            }
        } else {
            showToast(d.message || 'Gagal mengirim siaran', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '🚀 Kirimkan Sekarang';
        showToast('Koneksi terputus saat mengirim siaran', 'error');
    });
}

// 4. Tutup Broadcast via AJAX
function handleClearBroadcast() {
    if (!confirm('Nonaktifkan pesan siaran aktif sekarang?')) return;

    const formData = new FormData();
    formData.append('action', 'clear_broadcast');

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('active-bc-banner').style.display = 'none';
            showToast('Pesan siaran dinonaktifkan.', 'info');
        }
    })
    .catch(() => showToast('Gagal menonaktifkan siaran', 'error'));
}

// 5. Aksi Reset Login Peserta via AJAX
function actionReset(id, nama) {
    if (!confirm('Reset sesi login peserta ' + nama + '?\nPeserta akan dapat login kembali.')) return;

    const formData = new FormData();
    formData.append('action', 'reset_login');
    formData.append('id', id);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Sesi ' + nama + ' berhasil di-reset.', 'success');
            refreshLiveData(false);
        } else {
            showToast(d.message || 'Gagal mereset sesi peserta', 'error');
        }
    })
    .catch(() => showToast('Koneksi terputus ke server', 'error'));
}

// 6. Aksi Selesaikan Ujian Peserta via AJAX
function actionFinish(id, nama) {
    if (!confirm('Paksa selesaikan pengerjaan peserta ' + nama + '?\nStatus akan ditandai selesai.')) return;

    const formData = new FormData();
    formData.append('action', 'finish_exam');
    formData.append('id', id);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Status peserta ' + nama + ' diubah menjadi Selesai.', 'success');
            refreshLiveData(false);
        } else {
            showToast(d.message || 'Gagal menyelesaikan peserta', 'error');
        }
    })
    .catch(() => showToast('Koneksi terputus ke server', 'error'));
}

// 7. Core Live Data Fetching & DOM Patching (Optimal & Asinkron)
function refreshLiveData(isManual = false) {
    if (isFetching) return;
    isFetching = true;

    const liveDot = document.getElementById('live-dot');
    const liveText = document.getElementById('live-timer-text');
    if (liveText && isManual) liveText.textContent = 'Memperbarui...';

    fetch(API_URL + '?action=live_data')
    .then(r => r.json())
    .then(d => {
        isFetching = false;
        pollCountdown = pollInterval;

        if (!d.success) return;

        // A. Perbarui Kartu Statistik
        if (d.stats) {
            document.getElementById('stat-sedang').textContent = d.stats['1'] || 0;
            document.getElementById('stat-selesai').textContent = d.stats['2'] || 0;
            document.getElementById('stat-belum').textContent = d.stats['0'] || 0;
            document.getElementById('stat-reset').textContent = d.stats['3'] || 0;
        }

        // B. Perbarui Banner Siaran
        const banner = document.getElementById('active-bc-banner');
        if (d.broadcast) {
            document.getElementById('bc-active-text').textContent = '"' + d.broadcast.pesan + '"';
            document.getElementById('bc-active-time').textContent = '(' + (d.broadcast.time || '') + ')';
            banner.style.display = 'flex';
        } else {
            banner.style.display = 'none';
        }

        // C. Perbarui Baris Siswa (DOM Patching Tanpa Hancurkan Input/Scroll)
        if (Array.isArray(d.students)) {
            d.students.forEach(s => {
                const row = document.getElementById('row-student-' + s.id);
                if (row) {
                    // Update Status Badge
                    const cellStatus = row.querySelector('.cell-status');
                    if (cellStatus) {
                        let badgeHtml = '';
                        if (s.status === '0') badgeHtml = '<span class="badge badge-gray">Belum Login</span>';
                        else if (s.status === '1') badgeHtml = '<span class="badge badge-amber">Sedang Ujian</span>';
                        else if (s.status === '2') badgeHtml = '<span class="badge badge-green">Selesai</span>';
                        else if (s.status === '3') badgeHtml = '<span class="badge badge-blue">Minta Reset</span>';
                        cellStatus.innerHTML = badgeHtml;
                    }

                    // Update Progres Soal
                    const cellProg = row.querySelector('.cell-progres');
                    if (cellProg && s.total_soal !== undefined) {
                        const barColor = s.progress_pct >= 100 ? '#10b981' : '#0284c7';
                        let progHtml = '<div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; margin-bottom: 3px;">' +
                            '<span style="font-weight: 700; color: #1e293b;">' + s.answered + '/' + s.total_soal + '</span>' +
                            '<span style="color: #64748b;">' + s.progress_pct + '%</span>' +
                            '</div>' +
                            '<div style="height: 6px; width: 100%; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">' +
                            '<div style="height: 100%; width: ' + s.progress_pct + '%; background: ' + barColor + '; border-radius: 9999px; transition: width 0.3s;"></div>' +
                            '</div>';
                        if (s.ragu_count > 0) {
                            progHtml += '<div style="font-size: 10.5px; color: #d97706; margin-top: 2px;">⚠️ ' + s.ragu_count + ' ragu-ragu</div>';
                        }
                        cellProg.innerHTML = progHtml;
                    }

                    // Update Security Violation
                    const cellSec = row.querySelector('.cell-security');
                    if (cellSec) {
                        if (s.pelanggaran > 0) {
                            cellSec.innerHTML = '<span class="badge badge-amber" style="background: #fee2e2; color: #991b1b; font-weight: 800;">⚠️ ' + s.pelanggaran + 'x Tab Lepas</span>';
                        } else {
                            cellSec.innerHTML = '<span style="color: #059669; font-size: 11.5px; font-weight: 600;">✓ Bersih</span>';
                        }
                    }

                    // Update Waktu
                    const cellWaktu = row.querySelector('.cell-waktu');
                    if (cellWaktu) {
                        cellWaktu.textContent = s.waktu_mulai;
                    }

                    // Update Tombol Aksi
                    const cellAksi = row.querySelector('.cell-aksi');
                    if (cellAksi) {
                        let btnHtml = '';
                        const safeNama = s.nama.replace(/'/g, "\\'");
                        if (s.status === '1' || s.status === '3') {
                            btnHtml = '<div style="display: flex; gap: 6px;">' +
                                      '<button type="button" class="btn btn-secondary btn-sm" onclick="actionReset(' + s.id + ', \'' + safeNama + '\')">Reset Login</button>' +
                                      '<button type="button" class="btn btn-success btn-sm" onclick="actionFinish(' + s.id + ', \'' + safeNama + '\')">Selesai</button>' +
                                      '</div>';
                        } else if (s.status === '2') {
                            btnHtml = '<div style="display: flex; gap: 6px;">' +
                                      '<button type="button" class="btn btn-secondary btn-sm" onclick="actionReset(' + s.id + ', \'' + safeNama + '\')">Buka Ulang</button>' +
                                      '</div>';
                        } else {
                            btnHtml = '<span style="color: #94a3b8; font-size: 12px;">Siap Login</span>';
                        }
                        cellAksi.innerHTML = btnHtml;
                    }
                }
            });

            // Re-apply pencarian jika sedang memfilter
            filterLiveTable();
        }

        if (isManual) {
            showToast('Data live peserta telah diperbarui (' + (d.timestamp || '') + ')', 'info');
        }
    })
    .catch(() => {
        isFetching = false;
        pollCountdown = pollInterval;
    });
}

// 8. Filter Pencarian Peserta Live
function filterLiveTable() {
    const q = document.getElementById('live-search').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#pantau-tbody tr');
    rows.forEach(r => {
        const searchData = r.getAttribute('data-search') || r.textContent.toLowerCase();
        r.style.display = searchData.includes(q) ? '' : 'none';
    });
}

// 9. Jeda / Lanjutkan Auto Poll
function toggleAutoPoll() {
    isPollPaused = !isPollPaused;
    const btn = document.getElementById('btn-pause-poll');
    const badge = document.getElementById('live-badge');
    const dot = document.getElementById('live-dot');
    const txt = document.getElementById('live-timer-text');

    if (isPollPaused) {
        btn.textContent = '▶️ Lanjutkan';
        btn.className = 'btn btn-primary btn-sm';
        badge.style.background = '#fef3c7';
        badge.style.borderColor = '#fde68a';
        badge.style.color = '#92400e';
        dot.style.background = '#f59e0b';
        dot.style.animation = 'none';
        txt.textContent = 'Dijeda';
        showToast('Pembaruan otomatis dijeda.', 'info');
    } else {
        btn.textContent = '⏸️ Jeda';
        btn.className = 'btn btn-secondary btn-sm';
        badge.style.background = '#ecfdf5';
        badge.style.borderColor = '#a7f3d0';
        badge.style.color = '#065f46';
        dot.style.background = '#10b981';
        dot.style.animation = 'livePulse 1.2s infinite';
        pollCountdown = pollInterval;
        txt.textContent = 'Live (' + pollCountdown + 's)';
        refreshLiveData(false);
    }
}

// 10. Timer Polling Cerdas (Hemat Sumber Daya & CPU)
setInterval(() => {
    // Jika tab tidak aktif (sedang buka tab lain / minimize), hemat resource server
    if (document.hidden || isPollPaused) return;

    pollCountdown--;
    const txt = document.getElementById('live-timer-text');
    if (txt && !isFetching) {
        txt.textContent = 'Live (' + pollCountdown + 's)';
    }

    if (pollCountdown <= 0) {
        refreshLiveData(false);
    }
}, 1000);

// Auto-resume saat proctor kembali membuka tab
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !isPollPaused) {
        refreshLiveData(false);
    }
});
</script>

<?php admin_footer(); ?>
