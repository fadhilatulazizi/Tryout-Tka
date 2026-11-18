<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/ExamEngine.php';

// Cek autentikasi siswa
if (empty($_SESSION['student_logged_in']) || empty($_SESSION['id'])) {
    json_response(['success' => false, 'message' => 'Sesi login telah berakhir.'], 401);
}

// Ambil info sesi siswa lalu lepas lock session PHP agar request paralel tidak terhambat
$studentId = (int) $_SESSION['id'];
$sesiSiswa = (string) ($_SESSION['sesi'] ?? '1');
$ruangSiswa = (string) ($_SESSION['ruang'] ?? '1');
$csrfSession = (string) ($_SESSION['csrf_token'] ?? '');
session_write_close();

$db = get_db();

/**
 * Ambil siaran aktif dengan sistem cache berkas (0 MySQL load pada polling)
 */
function get_active_broadcast_optimized($db, $sesi, $ruang) {
    // 1. Cek dari file cache
    $cached = get_cached_broadcast();
    if ($cached !== null) {
        if (!empty($cached['aktif'])) {
            $tSesi = $cached['target_sesi'] ?? 'all';
            $tRuang = $cached['target_ruang'] ?? 'all';
            if (($tSesi === 'all' || $tSesi === $sesi) && ($tRuang === 'all' || $tRuang === $ruang)) {
                return [
                    'id' => (int) $cached['id'],
                    'message' => $cached['pesan'],
                    'time' => $cached['time'] ?? substr($cached['created_at'] ?? '', 11, 5)
                ];
            }
        }
        return null;
    }

    // 2. Fallback query database jika belum dicache
    $q = mysqli_query($db, "SELECT id, pesan, target_sesi, target_ruang, created_at FROM broadcast_pesan 
                            WHERE aktif = 1 
                            ORDER BY id DESC LIMIT 1");
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $cacheData = [
            'id' => (int) $r['id'],
            'pesan' => $r['pesan'],
            'target_sesi' => $r['target_sesi'],
            'target_ruang' => $r['target_ruang'],
            'time' => substr($r['created_at'], 11, 5),
            'aktif' => 1
        ];
        set_cached_broadcast($cacheData);

        if (($r['target_sesi'] === 'all' || $r['target_sesi'] === $sesi) && 
            ($r['target_ruang'] === 'all' || $r['target_ruang'] === $ruang)) {
            return [
                'id' => (int) $r['id'],
                'message' => $r['pesan'],
                'time' => substr($r['created_at'], 11, 5)
            ];
        }
    } else {
        set_cached_broadcast(['aktif' => 0]);
    }
    return null;
}

// 1. Endpoint Heartbeat / Cek Broadcast (GET atau POST)
if (isset($_GET['action']) && ($_GET['action'] === 'heartbeat' || $_GET['action'] === 'broadcast')) {
    $lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;
    $bc = get_active_broadcast_optimized($db, $sesiSiswa, $ruangSiswa);
    
    // Jika siswa sudah memiliki broadcast ini, kirim null untuk hemat bandwidth
    if ($bc && $bc['id'] <= $lastId) {
        $bc = null;
    }
    json_response(['success' => true, 'broadcast' => $bc]);
}

// Validasi request POST untuk penyimpanan jawaban & pelaporan pelanggaran
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode request tidak diizinkan.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// 2. Aksi Pelaporan Pelanggaran Anti-Curang
if (isset($data['action']) && $data['action'] === 'report_violation') {
    mysqli_query($db, "UPDATE account SET pelanggaran = pelanggaran + 1 WHERE id = {$studentId}");
    $qV = mysqli_query($db, "SELECT pelanggaran FROM account WHERE id = {$studentId}");
    $vCount = (int) (mysqli_fetch_assoc($qV)['pelanggaran'] ?? 1);
    
    $bc = get_active_broadcast_optimized($db, $sesiSiswa, $ruangSiswa);
    json_response([
        'success' => true, 
        'violation_reported' => true, 
        'total_violations' => $vCount,
        'broadcast' => $bc
    ]);
}

// 3. Aksi Simpan Jawaban
$csrf = $data['csrf_token'] ?? '';
if (!empty($csrf) && !empty($csrfSession) && !hash_equals($csrfSession, $csrf)) {
    json_response(['success' => false, 'message' => 'Token keamanan (CSRF) tidak valid.'], 403);
}

$no = isset($data['no']) ? (int) $data['no'] : 0;
$answer = isset($data['answer']) ? strtoupper(trim($data['answer'])) : '';
$isDoubt = isset($data['is_doubt']) ? ((int) $data['is_doubt'] === 1) : null;

if ($no < 1 || $no > 100) {
    json_response(['success' => false, 'message' => 'Nomor soal tidak valid.'], 400);
}

$ok = ExamEngine::saveAnswer($studentId, $no, $answer, $isDoubt);
if ($ok) {
    $bc = get_active_broadcast_optimized($db, $sesiSiswa, $ruangSiswa);
    json_response([
        'success' => true,
        'no' => $no,
        'answer' => $answer,
        'is_doubt' => $isDoubt,
        'time' => date('H:i:s'),
        'broadcast' => $bc
    ]);
} else {
    json_response(['success' => false, 'message' => 'Gagal menyimpan jawaban.'], 500);
}
