<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

// Cek autentikasi admin/proktor
if (empty($_SESSION['admin_logged_in'])) {
    json_response(['success' => false, 'message' => 'Sesi proktor telah berakhir, silakan login kembali.'], 401);
}

// Lepas session lock PHP agar tidak menghambat request lain
session_write_close();

$db = get_db();
$school = get_school_profile();
$activeJadwal = get_active_jadwal();
$hari = (string)($activeJadwal['hari'] ?? $school['hari'] ?? '1');
$jawabanTable = $hari === '1' ? 'pg_tb' : 'pg_tb_' . $hari;
ensure_jawaban_table($jawabanTable);
$defaultPaket = !empty($activeJadwal['folder_soal']) ? $activeJadwal['folder_soal'] : ($hari === '1' ? ($school['soal_hari1'] ?? 'conf31') : 'conf32');
$totalSoalAktif = SyncEngine::getPackageQuestionCount($defaultPaket);

$action = $_REQUEST['action'] ?? 'live_data';

// 1. Aksi Reset Login Siswa
if ($action === 'reset_login') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'message' => 'ID peserta tidak valid.'], 400);
    }
    mysqli_query($db, "UPDATE account SET status = '0', sesi_aktif = NULL WHERE id = {$id}");
    json_response([
        'success' => true, 
        'id' => $id, 
        'status' => '0', 
        'message' => 'Sesi login peserta berhasil di-reset.'
    ]);
}

// 2. Aksi Paksa Selesai Ujian
if ($action === 'finish_exam') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'message' => 'ID peserta tidak valid.'], 400);
    }
    mysqli_query($db, "UPDATE account SET status = '2' WHERE id = {$id}");
    mysqli_query($db, "UPDATE {$jawabanTable} SET waktu_selesai = NOW() WHERE id = {$id} AND (waktu_selesai IS NULL OR waktu_selesai = '0000-00-00 00:00:00')");
    json_response([
        'success' => true, 
        'id' => $id, 
        'status' => '2', 
        'message' => 'Status peserta berhasil diubah menjadi selesai.'
    ]);
}

// 3. Aksi Kirim Siaran Broadcast
if ($action === 'send_broadcast') {
    $pesan = trim($_POST['pesan'] ?? '');
    $targetSesi = trim($_POST['target_sesi'] ?? 'all');
    $targetRuang = trim($_POST['target_ruang'] ?? 'all');

    if ($pesan === '') {
        json_response(['success' => false, 'message' => 'Isi pesan siaran tidak boleh kosong.'], 400);
    }

    $stmtB = mysqli_prepare($db, "INSERT INTO broadcast_pesan (pesan, target_sesi, target_ruang, aktif) VALUES (?, ?, ?, 1)");
    if ($stmtB) {
        mysqli_stmt_bind_param($stmtB, 'sss', $pesan, $targetSesi, $targetRuang);
        mysqli_stmt_execute($stmtB);
        $newId = mysqli_insert_id($db);
        mysqli_stmt_close($stmtB);

        // Update berkas cache langsung
        set_cached_broadcast([
            'id' => $newId,
            'pesan' => $pesan,
            'target_sesi' => $targetSesi,
            'target_ruang' => $targetRuang,
            'time' => date('H:i'),
            'aktif' => 1
        ]);

        json_response([
            'success' => true, 
            'message' => 'Pesan siaran berhasil dikirim ke layar peserta ujian.',
            'broadcast' => [
                'id' => $newId,
                'pesan' => $pesan,
                'target_sesi' => $targetSesi,
                'target_ruang' => $targetRuang,
                'time' => date('H:i') . ' WIB'
            ]
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Gagal menyimpan pesan siaran.'], 500);
    }
}

// 4. Aksi Nonaktifkan Siaran
if ($action === 'clear_broadcast') {
    mysqli_query($db, "UPDATE broadcast_pesan SET aktif = 0");
    set_cached_broadcast(['aktif' => 0]);
    json_response(['success' => true, 'message' => 'Semua siaran aktif telah dinonaktifkan.']);
}

// 5. Default: Ambil Data Live Monitoring
// Hitung Statistik
$qStats = mysqli_query($db, "SELECT status, COUNT(*) as c FROM account GROUP BY status");
$statCounts = ['0' => 0, '1' => 0, '2' => 0, '3' => 0];
if ($qStats) {
    while ($r = mysqli_fetch_assoc($qStats)) {
        $statCounts[$r['status']] = (int) $r['c'];
    }
}

// Total Pelanggaran
$qVio = mysqli_query($db, "SELECT SUM(pelanggaran) as tot FROM account");
$totalViolations = (int) (mysqli_fetch_assoc($qVio)['tot'] ?? 0);

// Ambil Siaran Aktif
$activeBc = null;
$cached = get_cached_broadcast();
if ($cached && !empty($cached['aktif'])) {
    $activeBc = [
        'id' => (int) $cached['id'],
        'pesan' => $cached['pesan'],
        'target_sesi' => $cached['target_sesi'] ?? 'all',
        'target_ruang' => $cached['target_ruang'] ?? 'all',
        'time' => ($cached['time'] ?? '--:--') . ' WIB'
    ];
} else {
    $qBc = mysqli_query($db, "SELECT * FROM broadcast_pesan WHERE aktif = 1 ORDER BY id DESC LIMIT 1");
    if ($qBc && $rB = mysqli_fetch_assoc($qBc)) {
        $activeBc = [
            'id' => (int) $rB['id'],
            'pesan' => $rB['pesan'],
            'target_sesi' => $rB['target_sesi'],
            'target_ruang' => $rB['target_ruang'],
            'time' => substr($rB['created_at'], 11, 5) . ' WIB'
        ];
        set_cached_broadcast(array_merge($activeBc, ['aktif' => 1]));
    }
}

// Ambil Daftar Siswa beserta lembar jawaban
$sql = "SELECT a.id, a.kelas, a.abs, a.nama, a.user, a.sesi, a.ruang, a.status, a.pelanggaran, a.ragu, j.*
        FROM account a
        LEFT JOIN {$jawabanTable} j ON a.id = j.id
        ORDER BY a.kelas ASC, a.abs ASC, a.nama ASC";
$res = mysqli_query($db, $sql);
$students = [];
if ($res) {
    while ($s = mysqli_fetch_assoc($res)) {
        // Hitung progres pengerjaan
        $answered = 0;
        for ($q = 1; $q <= $totalSoalAktif; $q++) {
            $col = 'j' . $q;
            if (isset($s[$col]) && trim($s[$col]) !== '') {
                $answered++;
            }
        }
        $pct = $totalSoalAktif > 0 ? round(($answered / $totalSoalAktif) * 100, 1) : 0;

        $raguCount = 0;
        if (!empty($s['ragu'])) {
            $dec = json_decode($s['ragu'], true);
            if (is_array($dec)) $raguCount = count($dec);
        }

        $students[] = [
            'id' => (int) $s['id'],
            'kelas' => $s['kelas'],
            'abs' => $s['abs'],
            'nama' => $s['nama'],
            'user' => $s['user'],
            'sesi' => $s['sesi'],
            'ruang' => $s['ruang'],
            'status' => (string) $s['status'],
            'pelanggaran' => (int) ($s['pelanggaran'] ?? 0),
            'waktu_mulai' => !empty($s['waktu_mulai']) ? substr($s['waktu_mulai'], 11, 5) . ' WIB' : '-',
            'waktu_selesai' => !empty($s['waktu_selesai']) ? substr($s['waktu_selesai'], 11, 5) . ' WIB' : '-',
            'answered' => $answered,
            'total_soal' => $totalSoalAktif,
            'progress_pct' => $pct,
            'ragu_count' => $raguCount
        ];
    }
}

json_response([
    'success' => true,
    'timestamp' => date('H:i:s'),
    'stats' => $statCounts,
    'total_violations' => $totalViolations,
    'total_soal' => $totalSoalAktif,
    'broadcast' => $activeBc,
    'students' => $students
]);
