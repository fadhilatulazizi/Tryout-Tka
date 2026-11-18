<?php
require_once __DIR__ . '/layout.php';

$db = get_db();
$msg = '';
$msgType = '';

// Aksi Tambah Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $kelas = trim($_POST['kelas'] ?? '');
    $abs = trim($_POST['abs'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $sesi = trim($_POST['sesi'] ?? '1');
    $ruang = trim($_POST['ruang'] ?? '1');

    if ($kelas && $nama && $user && $pass) {
        $order = range(1, 50);
        shuffle($order);
        $orderVals = implode(', ', $order);
        $cols = "kelas, abs, nama, user, pass, sesi, ruang, status";
        for ($i = 1; $i <= 50; $i++) $cols .= ", a{$i}";

        $stmt = mysqli_prepare($db, "INSERT INTO account ({$cols}) VALUES (?, ?, ?, ?, ?, ?, ?, '0', {$orderVals})");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssssss', $kelas, $abs, $nama, $user, $pass, $sesi, $ruang);
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Peserta {$nama} berhasil ditambahkan.";
                $msgType = 'success';
            } else {
                $msg = "Gagal menambah peserta: Username {$user} sudah digunakan.";
                $msgType = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Aksi Edit Siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $kelas = trim($_POST['kelas'] ?? '');
    $abs = trim($_POST['abs'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $sesi = trim($_POST['sesi'] ?? '1');
    $ruang = trim($_POST['ruang'] ?? '1');

    if ($id > 0 && $kelas && $nama && $user && $pass) {
        $stmt = mysqli_prepare($db, "UPDATE account SET kelas = ?, abs = ?, nama = ?, user = ?, pass = ?, sesi = ?, ruang = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssssssi', $kelas, $abs, $nama, $user, $pass, $sesi, $ruang, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = "Data peserta {$nama} berhasil diperbarui.";
            $msgType = 'success';
        }
    }
}

// Aksi Hapus Satuan
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    if ($delId > 0) {
        mysqli_query($db, "DELETE FROM account WHERE id = {$delId}");
        $qTables = mysqli_query($db, "SHOW TABLES LIKE 'pg_tb%'");
        if ($qTables) {
            while ($rowT = mysqli_fetch_row($qTables)) {
                $tName = $rowT[0];
                mysqli_query($db, "DELETE FROM `{$tName}` WHERE id = {$delId}");
            }
        }
        $msg = "Peserta berhasil dihapus.";
        $msgType = 'success';
    }
}

// Aksi Hapus Semua Siswa
if (isset($_GET['truncate']) && $_GET['truncate'] === 'all') {
    mysqli_query($db, "DELETE FROM account");
    $qTables = mysqli_query($db, "SHOW TABLES LIKE 'pg_tb%'");
    if ($qTables) {
        while ($rowT = mysqli_fetch_row($qTables)) {
            $tName = $rowT[0];
            mysqli_query($db, "DELETE FROM `{$tName}`");
        }
    }
    $msg = "Seluruh data peserta telah dibersihkan.";
    $msgType = 'success';
}

// Filter
$filterKelas = trim($_GET['kelas'] ?? '');
$filterCari = trim($_GET['cari'] ?? '');

$sql = "SELECT * FROM account WHERE 1=1";
$params = [];
$types = '';

if ($filterKelas !== '') {
    $sql .= " AND kelas = ?";
    $params[] = $filterKelas;
    $types .= 's';
}
if ($filterCari !== '') {
    $sql .= " AND (nama LIKE ? OR user LIKE ?)";
    $like = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$sql .= " ORDER BY kelas ASC, abs ASC, nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($db, $sql);
}

// Dropdown Kelas
$qKelas = mysqli_query($db, "SELECT DISTINCT kelas FROM account WHERE kelas != '' ORDER BY kelas ASC");

admin_header('Manajemen Peserta', 'siswa');
?>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?php echo $msgType; ?>">
        <?php echo $msgType === 'success' ? '✓' : '⚠️'; ?> <?php echo e($msg); ?>
    </div>
<?php endif; ?>

<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2>Data Peserta Ujian</h2>
            <p>Kelola daftar peserta ujian, kelas, sesi, dan ruang.</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-primary" onclick="openAddModal()">+ Tambah Peserta</button>
            <a href="<?php echo base_url('admin/impor_siswa.php'); ?>" class="btn btn-secondary">📥 Impor Excel</a>
            <a href="<?php echo base_url('admin/kartu.php'); ?>" class="btn btn-secondary">🖨️ Cetak Kartu</a>
            <a href="<?php echo base_url('admin/siswa.php?truncate=all'); ?>" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN: Semua akun peserta dan lembar jawaban akan TERHAPUS. Lanjutkan?')">Hapus Semua</a>
        </div>
    </div>

    <!-- Filter Form -->
    <form method="get" action="" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center;">
        <select name="kelas" class="form-control" style="width: auto;" onchange="this.form.submit()">
            <option value="">-- Semua Kelas --</option>
            <?php if ($qKelas): while ($k = mysqli_fetch_assoc($qKelas)): ?>
                <option value="<?php echo e($k['kelas']); ?>" <?php echo $filterKelas === $k['kelas'] ? 'selected' : ''; ?>>
                    Kelas <?php echo e($k['kelas']); ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <input type="text" name="cari" class="form-control" placeholder="Cari nama atau username..." value="<?php echo e($filterCari); ?>" style="max-width: 260px;">
        <button type="submit" class="btn btn-secondary">Cari</button>
        <?php if ($filterKelas !== '' || $filterCari !== ''): ?>
            <a href="<?php echo base_url('admin/siswa.php'); ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kelas</th>
                    <th>Abs</th>
                    <th>Nama Peserta</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Sesi</th>
                    <th>Ruang</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; while ($s = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><strong><?php echo e($s['kelas']); ?></strong></td>
                            <td><?php echo e($s['abs']); ?></td>
                            <td><?php echo e($s['nama']); ?></td>
                            <td><span class="badge-mono"><?php echo e($s['user']); ?></span></td>
                            <td><span class="badge-mono" style="color: #92400e;"><?php echo e($s['pass']); ?></span></td>
                            <td>Sesi <?php echo e($s['sesi']); ?></td>
                            <td>Ruang <?php echo e($s['ruang']); ?></td>
                            <td>
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
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?php echo json_encode($s); ?>)'>Edit</button>
                                <a href="<?php echo base_url('admin/siswa.php?delete=' . $s['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus peserta ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align: center; color: #64748b;">Tidak ada data peserta ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah / Edit Siswa -->
<div id="modal-siswa" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: #ffffff; border-radius: 12px; width: 90%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <h2 id="modal-siswa-title" style="font-size: 17px; font-weight: 700; margin-bottom: 16px;">Tambah Peserta</h2>
        <form method="post" action="">
            <input type="hidden" name="action" id="modal-action" value="add">
            <input type="hidden" name="id" id="modal-id" value="">

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-kelas">Kelas</label>
                    <input type="text" name="kelas" id="m-kelas" class="form-control" required placeholder="Contoh: 9A" maxlength="15">
                </div>
                <div class="form-group">
                    <label for="m-abs">No. Absen</label>
                    <input type="text" name="abs" id="m-abs" class="form-control" placeholder="01" maxlength="5">
                </div>
            </div>

            <div class="form-group">
                <label for="m-nama">Nama Lengkap</label>
                <input type="text" name="nama" id="m-nama" class="form-control" required placeholder="Nama Siswa">
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label for="m-user">Username</label>
                    <input type="text" name="user" id="m-user" class="form-control" required placeholder="siswa01">
                </div>
                <div class="form-group">
                    <label for="m-pass">Password</label>
                    <input type="text" name="pass" id="m-pass" class="form-control" required placeholder="123456">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div class="form-group">
                    <label for="m-sesi">Sesi</label>
                    <input type="text" name="sesi" id="m-sesi" class="form-control" value="1">
                </div>
                <div class="form-group">
                    <label for="m-ruang">Ruang</label>
                    <input type="text" name="ruang" id="m-ruang" class="form-control" value="1">
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModalSiswa()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modal-action').value = 'add';
    document.getElementById('modal-id').value = '';
    document.getElementById('modal-siswa-title').textContent = 'Tambah Peserta';
    document.getElementById('m-kelas').value = '';
    document.getElementById('m-abs').value = '';
    document.getElementById('m-nama').value = '';
    document.getElementById('m-user').value = '';
    document.getElementById('m-pass').value = '123456';
    document.getElementById('m-sesi').value = '1';
    document.getElementById('m-ruang').value = '1';
    document.getElementById('modal-siswa').style.display = 'flex';
}

function openEditModal(s) {
    document.getElementById('modal-action').value = 'edit';
    document.getElementById('modal-id').value = s.id;
    document.getElementById('modal-siswa-title').textContent = 'Ubah Peserta';
    document.getElementById('m-kelas').value = s.kelas;
    document.getElementById('m-abs').value = s.abs;
    document.getElementById('m-nama').value = s.nama;
    document.getElementById('m-user').value = s.user;
    document.getElementById('m-pass').value = s.pass;
    document.getElementById('m-sesi').value = s.sesi;
    document.getElementById('m-ruang').value = s.ruang;
    document.getElementById('modal-siswa').style.display = 'flex';
}

function closeModalSiswa() {
    document.getElementById('modal-siswa').style.display = 'none';
}
</script>

<?php admin_footer(); ?>
