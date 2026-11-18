<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$msg = '';
$msgType = '';

// 1. Aksi Simpan / Tambah Jadwal & Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_jadwal') {
    $id = (int) ($_POST['id'] ?? 0);
    $hari = (int) ($_POST['hari'] ?? 1);
    if ($hari < 1) $hari = 1;
    $namaHari = trim($_POST['nama_hari'] ?? "Hari ke-{$hari}");
    if ($namaHari === '') $namaHari = "Hari ke-{$hari}";
    $mapel = trim($_POST['mapel'] ?? 'Mata Pelajaran');
    $folderSoal = trim($_POST['folder_soal'] ?? 'conf31');
    $durasi = (int) ($_POST['durasi_menit'] ?? 120);
    if ($durasi < 10) $durasi = 120;
    $isAktif = isset($_POST['aktif']) ? 1 : 0;

    // Pastikan tabel lembar jawaban pg_tb_{hari} tersedia
    $tableJwb = $hari === 1 ? 'pg_tb' : 'pg_tb_' . $hari;
    ensure_jawaban_table($tableJwb);

    if ($id > 0) {
        $stmt = mysqli_prepare($db, "UPDATE jadwal_mapel SET hari=?, nama_hari=?, mapel=?, folder_soal=?, durasi_menit=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isssii', $hari, $namaHari, $mapel, $folderSoal, $durasi, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Jadwal {$namaHari} ({$mapel}) berhasil diperbarui.";
            $msgType = 'success';
        }
    } else {
        // Cek apakah nomor hari sudah ada
        $chkHari = mysqli_query($db, "SELECT id FROM jadwal_mapel WHERE hari = {$hari} LIMIT 1");
        if ($chkHari && mysqli_num_rows($chkHari) > 0) {
            $stmt = mysqli_prepare($db, "UPDATE jadwal_mapel SET nama_hari=?, mapel=?, folder_soal=?, durasi_menit=? WHERE hari=?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssii', $namaHari, $mapel, $folderSoal, $durasi, $hari);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = "Jadwal Hari ke-{$hari} ({$mapel}) berhasil diperbarui.";
                $msgType = 'success';
            }
        } else {
            $stmt = mysqli_prepare($db, "INSERT INTO jadwal_mapel (hari, nama_hari, mapel, folder_soal, durasi_menit, aktif) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isssii', $hari, $namaHari, $mapel, $folderSoal, $durasi, $isAktif);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = "Jadwal baru {$namaHari} ({$mapel}) berhasil ditambahkan.";
                $msgType = 'success';
            }
        }
    }

    // Jika diset sebagai jadwal aktif hari ini
    if ($isAktif) {
        mysqli_query($db, "UPDATE jadwal_mapel SET aktif = 0");
        mysqli_query($db, "UPDATE jadwal_mapel SET aktif = 1 WHERE hari = {$hari}");
        mysqli_query($db, "UPDATE mapel SET hari = '{$hari}' WHERE id = 1");
        mysqli_query($db, "UPDATE atur SET folder_soal = '{$folderSoal}', durasi_menit = {$durasi}");
    }
}

// 2. Aksi Aktifkan Jadwal Ujian Tertentu (1-Click Activate)
if (isset($_GET['set_aktif_jadwal'])) {
    $h = (int) $_GET['set_aktif_jadwal'];
    $targetJadwal = get_jadwal_by_hari($h);
    if ($targetJadwal) {
        mysqli_query($db, "UPDATE jadwal_mapel SET aktif = 0");
        mysqli_query($db, "UPDATE jadwal_mapel SET aktif = 1 WHERE hari = {$h}");
        mysqli_query($db, "UPDATE mapel SET hari = '{$h}' WHERE id = 1");

        $f = $targetJadwal['folder_soal'];
        $d = (int) ($targetJadwal['durasi_menit'] ?? 120);
        mysqli_query($db, "UPDATE atur SET folder_soal = '{$f}', durasi_menit = {$d}");

        $tbl = $h === 1 ? 'pg_tb' : 'pg_tb_' . $h;
        ensure_jawaban_table($tbl);

        $msg = "Jadwal " . e($targetJadwal['nama_hari']) . " (" . e($targetJadwal['mapel']) . ") berhasil diaktifkan sebagai ujian hari ini.";
        $msgType = 'success';
    }
}

// 3. Aksi Hapus Jadwal
if (isset($_GET['del_jadwal'])) {
    $delId = (int) $_GET['del_jadwal'];
    $chk = mysqli_query($db, "SELECT * FROM jadwal_mapel WHERE id = {$delId} LIMIT 1");
    if ($chk && $r = mysqli_fetch_assoc($chk)) {
        if (!empty($r['aktif'])) {
            $msg = "Tidak dapat menghapus jadwal yang sedang aktif berlangsung hari ini. Silakan aktifkan jadwal lain terlebih dahulu.";
            $msgType = 'danger';
        } else {
            mysqli_query($db, "DELETE FROM jadwal_mapel WHERE id = {$delId}");
            $msg = "Jadwal " . e($r['nama_hari']) . " berhasil dihapus.";
            $msgType = 'success';
        }
    }
}

// 4. Aksi Simpan / Tambah Sesi & Ruang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sesi') {
    $id = (int) ($_POST['id'] ?? 0);
    $sesi = trim($_POST['sesi'] ?? '1');
    $ruang = trim($_POST['ruang'] ?? '1');
    $token = strtoupper(trim($_POST['token'] ?? 'DEMAK1'));
    $folder = trim($_POST['folder_soal'] ?? 'conf31');
    $durasi = (int) ($_POST['durasi_menit'] ?? 120);
    $aktif = isset($_POST['ujian_aktif']) ? '1' : '0';
    $ket = trim($_POST['ket'] ?? '');

    if ($id > 0) {
        $stmt = mysqli_prepare($db, "UPDATE atur SET sesi=?, ruang=?, token=?, folder_soal=?, durasi_menit=?, ujian_aktif=?, ket=? WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssissi', $sesi, $ruang, $token, $folder, $durasi, $aktif, $ket, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Pengaturan Sesi {$sesi} Ruang {$ruang} berhasil diperbarui.";
            $msgType = 'success';
        }
    } else {
        $stmt = mysqli_prepare($db, "INSERT INTO atur (sesi, ruang, token, folder_soal, durasi_menit, ujian_aktif, ket) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssiss', $sesi, $ruang, $token, $folder, $durasi, $aktif, $ket);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Sesi {$sesi} Ruang {$ruang} berhasil ditambahkan.";
            $msgType = 'success';
        }
    }
}

// 5. Aksi Toggle Status Ujian (Buka / Tutup Cepat Sesi)
if (isset($_GET['toggle_id'])) {
    $tId = (int) $_GET['toggle_id'];
    $val = ($_GET['val'] ?? '0') === '1' ? '1' : '0';
    mysqli_query($db, "UPDATE atur SET ujian_aktif = '{$val}' WHERE id = {$tId}");
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']) && $_GET['ajax'] === '1')) {
        json_response(['success' => true, 'id' => $tId, 'ujian_aktif' => $val]);
    }
    header('Location: ' . base_url('admin/sesi_token.php'));
    exit;
}

// 6. Aksi Acak Semua Token Sekaligus
if (isset($_GET['action']) && $_GET['action'] === 'random_all_tokens') {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $qAll = mysqli_query($db, "SELECT id FROM atur");
    $tokens = [];
    if ($qAll) {
        while ($r = mysqli_fetch_assoc($qAll)) {
            $newToken = '';
            for ($k = 0; $k < 6; $k++) {
                $newToken .= $chars[random_int(0, strlen($chars) - 1)];
            }
            mysqli_query($db, "UPDATE atur SET token = '{$newToken}' WHERE id = {$r['id']}");
            $tokens[$r['id']] = $newToken;
        }
    }
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']) && $_GET['ajax'] === '1')) {
        json_response(['success' => true, 'tokens' => $tokens, 'message' => 'Semua token sesi & ruang berhasil diacak dan diperbarui.']);
    }
    $msg = "Semua token sesi & ruang berhasil diacak dan diperbarui.";
    $msgType = 'success';
}

// 7. Aksi Hapus Sesi
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    mysqli_query($db, "DELETE FROM atur WHERE id = {$delId}");
    $msg = "Sesi berhasil dihapus.";
    $msgType = 'success';
}

// Data Pendukung
$allJadwal = get_all_jadwal();
$activeJadwal = get_active_jadwal();
$qSesi = mysqli_query($db, "SELECT * FROM atur ORDER BY ruang ASC, sesi ASC");

// Ambil paket soal lokal yang ada di folder soal/
$soalDir = dirname(__DIR__) . '/soal';
$localFolders = [];
if (is_dir($soalDir)) {
    foreach (scandir($soalDir) as $f) {
        if (preg_match('/^conf[0-9]{2}$/', $f) && is_dir($soalDir . '/' . $f)) {
            $localFolders[] = $f;
        }
    }
}
sort($localFolders);

admin_header('Jadwal Mata Pelajaran & Sesi Ujian', 'sesi');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>" style="margin-bottom: 20px;">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<!-- CARD 1: PENGATURAN JADWAL & MATA PELAJARAN (FLEKSIBEL / BISA DITAMBAH BEBAS) -->
<div class="panel-card" style="margin-bottom: 28px;">
    <div class="panel-header">
        <div>
            <h2>📚 Jadwal Ujian & Mata Pelajaran</h2>
            <p>Kelola daftar mata pelajaran, alokasi waktu durasi, dan paket soal yang diujikan. Anda dapat menambah jadwal mata pelajaran sebanyak yang dibutuhkan.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <span class="badge badge-green" style="font-size: 13px; padding: 6px 12px;">
                ● Aktif Hari Ini: <strong><?php echo e($activeJadwal['nama_hari']); ?> - <?php echo e($activeJadwal['mapel']); ?> (<?php echo e($activeJadwal['folder_soal']); ?>)</strong>
            </span>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddJadwal()">
                + Tambah Jadwal & Mata Pelajaran
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 90px;">Hari ke-</th>
                    <th>Nama Jadwal / Hari</th>
                    <th>Mata Pelajaran</th>
                    <th>Folder Paket Soal</th>
                    <th>Durasi Ujian</th>
                    <th>Status Hari Ini</th>
                    <th style="text-align: right; width: 220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($allJadwal)): ?>
                    <?php foreach ($allJadwal as $j): ?>
                        <tr style="<?php echo !empty($j['aktif']) ? 'background: #f0fdf4;' : ''; ?>">
                            <td>
                                <span class="badge <?php echo !empty($j['aktif']) ? 'badge-green' : 'badge-blue'; ?>" style="font-weight: 800; font-size: 13px;">
                                    Hari <?php echo (int) $j['hari']; ?>
                                </span>
                            </td>
                            <td><strong><?php echo e($j['nama_hari']); ?></strong></td>
                            <td>
                                <span style="font-weight: 700; color: #1e293b; font-size: 14px;"><?php echo e($j['mapel']); ?></span>
                            </td>
                            <td>
                                <code style="background: #e2e8f0; color: #0f172a; padding: 3px 8px; border-radius: 6px; font-weight: 700;"><?php echo e($j['folder_soal']); ?></code>
                            </td>
                            <td><?php echo (int) ($j['durasi_menit'] ?? 120); ?> Menit</td>
                            <td>
                                <?php if (!empty($j['aktif'])): ?>
                                    <span class="badge badge-green" style="font-weight: 800;">
                                        ✓ AKTIF HARI INI
                                    </span>
                                <?php else: ?>
                                    <a href="<?php echo base_url('admin/sesi_token.php?set_aktif_jadwal=' . $j['hari']); ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       style="font-size: 11.5px; padding: 4px 10px;"
                                       onclick="return confirm('Aktifkan <?php echo e($j['nama_hari']); ?> (<?php echo e($j['mapel']); ?>) sebagai ujian aktif hari ini?')">
                                        Jadikan Aktif Hari Ini
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditJadwal(<?php echo json_encode($j); ?>)'>
                                        Edit
                                    </button>
                                    <?php if (empty($j['aktif'])): ?>
                                        <a href="<?php echo base_url('admin/sesi_token.php?del_jadwal=' . $j['id']); ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Hapus jadwal <?php echo e($j['nama_hari']); ?> (<?php echo e($j['mapel']); ?>)?')">
                                            Hapus
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="Jadwal aktif tidak dapat dihapus">
                                            Terkunci
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 24px;">Belum ada jadwal mata pelajaran. Silakan klik tombol Tambah.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CARD 2: DAFTAR SESI, RUANG & RILIS TOKEN -->
<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2>🔑 Manajemen Sesi, Ruang & Rilis Token Ujian</h2>
            <p>Kelola pembagian ruang lab, sesi waktu pengerjaan, rilis token ujian masuk, dan status aktif ujian siswa.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="ajaxRandomTokens()">🎲 Acak Semua Token</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openAddSesi()">+ Tambah Sesi / Ruang</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Sesi</th>
                    <th>Ruang</th>
                    <th>Token Ujian</th>
                    <th>Folder Paket Soal</th>
                    <th>Alokasi Durasi</th>
                    <th>Status Ujian</th>
                    <th>Keterangan</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($qSesi && mysqli_num_rows($qSesi) > 0): ?>
                    <?php while ($s = mysqli_fetch_assoc($qSesi)): ?>
                        <tr>
                            <td><strong>Sesi <?php echo e($s['sesi']); ?></strong></td>
                            <td>Ruang <?php echo e($s['ruang']); ?></td>
                            <td>
                                <span class="badge-mono" id="token-val-<?php echo $s['id']; ?>" style="font-size: 15px; color: #0284c7; letter-spacing: 1px; font-weight: 800;">
                                    <?php echo e($s['token']); ?>
                                </span>
                            </td>
                            <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 700;"><?php echo e($s['folder_soal']); ?></code></td>
                            <td><?php echo (int) ($s['durasi_menit'] ?? 120); ?> Menit</td>
                            <td>
                                <button type="button" 
                                        id="btn-toggle-sesi-<?php echo $s['id']; ?>"
                                        onclick="ajaxToggleSesi(<?php echo $s['id']; ?>)"
                                        class="badge <?php echo $s['ujian_aktif'] === '1' ? 'badge-green' : 'badge-gray'; ?>" 
                                        style="border: none; cursor: pointer;"
                                        title="Klik untuk Buka/Tutup Sesi Ini">
                                    <?php echo $s['ujian_aktif'] === '1' ? '● Aktif / Dibuka (Klik Tutup)' : '○ Ditutup (Klik Buka)'; ?>
                                </button>
                            </td>
                            <td><?php echo e($s['ket'] ?? '-'); ?></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick='openEditSesi(<?php echo json_encode($s); ?>)'>Edit</button>
                                    <a href="<?php echo base_url('admin/sesi_token.php?delete=' . $s['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus sesi & ruang ini?')">Hapus</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">Belum ada sesi atau ruang ujian yang dikonfigurasi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal 1: Form Tambah / Edit Jadwal & Mata Pelajaran -->
<div id="modal-jadwal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; border-radius: 16px; width: 90%; max-width: 500px; padding: 26px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h2 id="modal-jadwal-title" style="font-size: 18px; font-weight: 800; margin-bottom: 16px; color: #0f172a;">Jadwal & Mata Pelajaran</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="save_jadwal">
            <input type="hidden" name="id" id="m-jadwal-id" value="">

            <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-jadwal-hari">Hari ke- <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="hari" id="m-jadwal-hari" class="form-control" required min="1" max="30" value="1">
                    <small style="color: #64748b;">Urutan hari ujian.</small>
                </div>
                <div class="form-group">
                    <label for="m-jadwal-nama-hari">Label / Nama Jadwal</label>
                    <input type="text" name="nama_hari" id="m-jadwal-nama-hari" class="form-control" placeholder="Contoh: Hari ke-3 / Rabu, 19 Nov" required>
                </div>
            </div>

            <div class="form-group">
                <label for="m-jadwal-mapel">Nama Mata Pelajaran <span style="color: #ef4444;">*</span></label>
                <input type="text" name="mapel" id="m-jadwal-mapel" class="form-control" placeholder="Contoh: Ilmu Pengetahuan Alam (IPA)" required>
                <small style="color: #64748b;">Nama resmi mata pelajaran yang tertera di lembar ujian & rekap nilai.</small>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-jadwal-folder">Folder Paket Soal <span style="color: #ef4444;">*</span></label>
                    <select name="folder_soal" id="m-jadwal-folder" class="form-control" required>
                        <?php foreach ($localFolders as $lf): ?>
                            <option value="<?php echo $lf; ?>"><?php echo $lf; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b;">Paket soal dari folder soal/.</small>
                </div>
                <div class="form-group">
                    <label for="m-jadwal-durasi">Alokasi Waktu (Menit) <span style="color: #ef4444;">*</span></label>
                    <input type="number" name="durasi_menit" id="m-jadwal-durasi" class="form-control" value="120" min="10" max="300" required>
                    <small style="color: #64748b;">Durasi pengerjaan siswa.</small>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px; background: #f8fafc; padding: 12px 14px; border-radius: 8px; border: 1.5px solid #bae6fd;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; margin: 0;">
                    <input type="checkbox" name="aktif" id="m-jadwal-aktif" value="1" style="width: 18px; height: 18px;">
                    <span><strong>Jadikan Jadwal Aktif Hari Ini</strong> (Siswa langsung mengerjakan mata pelajaran ini saat ujian dimulai)</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModalJadwal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Form Tambah / Edit Sesi & Ruang -->
<div id="modal-sesi" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; border-radius: 16px; width: 90%; max-width: 480px; padding: 26px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
        <h2 id="modal-sesi-title" style="font-size: 18px; font-weight: 800; margin-bottom: 16px; color: #0f172a;">Pengaturan Sesi & Ruang</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="save_sesi">
            <input type="hidden" name="id" id="m-sesi-id" value="">

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-sesi-num">Nomor Sesi</label>
                    <input type="text" name="sesi" id="m-sesi-num" class="form-control" required value="1" placeholder="Contoh: 1">
                </div>
                <div class="form-group">
                    <label for="m-sesi-ruang">Nomor Ruang Lab</label>
                    <input type="text" name="ruang" id="m-sesi-ruang" class="form-control" required value="1" placeholder="Contoh: 1">
                </div>
            </div>

            <div class="form-group">
                <label for="m-sesi-token">Token Ujian Masuk</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="token" id="m-sesi-token" class="form-control" required style="font-family: monospace; font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="generateToken()">🎲 Acak</button>
                </div>
                <span style="font-size: 11px; color: #64748b;">Token 6 karakter yang dimasukkan siswa pada konfirmasi ujian.</span>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-sesi-folder">Folder Paket Soal</label>
                    <select name="folder_soal" id="m-sesi-folder" class="form-control">
                        <?php foreach ($localFolders as $lf): ?>
                            <option value="<?php echo $lf; ?>"><?php echo $lf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="m-sesi-durasi">Durasi (Menit)</label>
                    <input type="number" name="durasi_menit" id="m-sesi-durasi" class="form-control" value="120" min="10" max="300">
                </div>
            </div>

            <div class="form-group">
                <label for="m-sesi-ket">Keterangan Sesi / Ruang</label>
                <input type="text" name="ket" id="m-sesi-ket" class="form-control" placeholder="Contoh: Sesi Pagi Lab 1">
            </div>

            <div class="form-group" style="margin-bottom: 24px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                    <input type="checkbox" name="ujian_aktif" id="m-sesi-aktif" value="1" checked style="width: 18px; height: 18px;">
                    <span><strong>Aktifkan Ruang & Sesi Ini</strong> (Siswa diizinkan masuk ujian)</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModalSesi()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Sesi</button>
            </div>
        </form>
    </div>
</div>

<script>
// --- JADWAL & MAPEL ---
function openAddJadwal() {
    document.getElementById('m-jadwal-id').value = '';
    document.getElementById('modal-jadwal-title').textContent = 'Tambah Jadwal & Mata Pelajaran';
    // Otomatis tentukan hari berikutnya
    const nextHari = <?php echo count($allJadwal) + 1; ?>;
    document.getElementById('m-jadwal-hari').value = nextHari;
    document.getElementById('m-jadwal-nama-hari').value = 'Hari ke-' + nextHari;
    document.getElementById('m-jadwal-mapel').value = '';
    document.getElementById('m-jadwal-durasi').value = '120';
    document.getElementById('m-jadwal-aktif').checked = false;
    document.getElementById('modal-jadwal').style.display = 'flex';
}

function openEditJadwal(j) {
    document.getElementById('m-jadwal-id').value = j.id;
    document.getElementById('modal-jadwal-title').textContent = 'Ubah Jadwal: ' + j.nama_hari;
    document.getElementById('m-jadwal-hari').value = j.hari;
    document.getElementById('m-jadwal-nama-hari').value = j.nama_hari;
    document.getElementById('m-jadwal-mapel').value = j.mapel;
    document.getElementById('m-jadwal-folder').value = j.folder_soal;
    document.getElementById('m-jadwal-durasi').value = j.durasi_menit || 120;
    document.getElementById('m-jadwal-aktif').checked = (j.aktif == 1);
    document.getElementById('modal-jadwal').style.display = 'flex';
}

function closeModalJadwal() {
    document.getElementById('modal-jadwal').style.display = 'none';
}

// --- SESI & RUANG ---
function generateToken() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let res = '';
    for (let i = 0; i < 6; i++) {
        res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('m-sesi-token').value = res;
}

function openAddSesi() {
    document.getElementById('m-sesi-id').value = '';
    document.getElementById('modal-sesi-title').textContent = 'Tambah Sesi & Ruang';
    document.getElementById('m-sesi-num').value = '1';
    document.getElementById('m-sesi-ruang').value = '1';
    generateToken();
    document.getElementById('m-sesi-durasi').value = '120';
    document.getElementById('m-sesi-ket').value = '';
    document.getElementById('m-sesi-aktif').checked = true;
    document.getElementById('modal-sesi').style.display = 'flex';
}

function openEditSesi(s) {
    document.getElementById('m-sesi-id').value = s.id;
    document.getElementById('modal-sesi-title').textContent = 'Ubah Sesi & Ruang';
    document.getElementById('m-sesi-num').value = s.sesi;
    document.getElementById('m-sesi-ruang').value = s.ruang;
    document.getElementById('m-sesi-token').value = s.token;
    document.getElementById('m-sesi-folder').value = s.folder_soal;
    document.getElementById('m-sesi-durasi').value = s.durasi_menit || 120;
    document.getElementById('m-sesi-ket').value = s.ket || '';
    document.getElementById('m-sesi-aktif').checked = (s.ujian_aktif === '1');
    document.getElementById('modal-sesi').style.display = 'flex';
}

function closeModalSesi() {
    document.getElementById('modal-sesi').style.display = 'none';
}

function ajaxRandomTokens() {
    if (!confirm('Acak dan perbarui semua token ujian sekarang?')) return;
    fetch('sesi_token.php?action=random_all_tokens&ajax=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success && d.tokens) {
            for (const id in d.tokens) {
                const el = document.getElementById('token-val-' + id);
                if (el) {
                    el.textContent = d.tokens[id];
                    el.style.color = '#10b981';
                    setTimeout(() => { el.style.color = '#0284c7'; }, 1000);
                }
            }
            alert('✓ Semua token ujian berhasil diacak seketika via AJAX!');
        }
    })
    .catch(() => alert('Gagal mengacak token'));
}

function ajaxToggleSesi(id) {
    const btn = document.getElementById('btn-toggle-sesi-' + id);
    const isAktif = btn.classList.contains('badge-green');
    const nextVal = isAktif ? '0' : '1';
    btn.disabled = true;

    fetch('sesi_token.php?toggle_id=' + id + '&val=' + nextVal + '&ajax=1', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        if (d.success) {
            if (d.ujian_aktif === '1') {
                btn.className = 'badge badge-green';
                btn.textContent = '● Aktif / Dibuka (Klik Tutup)';
            } else {
                btn.className = 'badge badge-gray';
                btn.textContent = '○ Ditutup (Klik Buka)';
            }
        }
    })
    .catch(() => {
        btn.disabled = false;
        alert('Gagal mengubah status sesi');
    });
}
</script>

<?php admin_footer(); ?>
