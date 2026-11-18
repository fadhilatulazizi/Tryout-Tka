<?php
/**
 * Helper Utility Functions
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

function e(?string $str): string
{
    return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function tgl_indo(string $tanggal, bool $withTime = false): string
{
    if (empty($tanggal) || $tanggal === '0000-00-00') return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $parts = explode(' ', $tanggal);
    $dateParts = explode('-', $parts[0]);
    if (count($dateParts) !== 3) return $tanggal;

    $d = (int)$dateParts[2];
    $m = (int)$dateParts[1];
    $y = $dateParts[0];

    $result = $d . ' ' . ($bulan[$m] ?? $m) . ' ' . $y;
    if ($withTime && isset($parts[1])) {
        $result .= ' pukul ' . substr($parts[1], 0, 5) . ' WIB';
    }

    return $result;
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_school_profile(): array
{
    $db = get_db();
    $res = mysqli_query($db, "SELECT * FROM mapel WHERE id='1' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row;
    }
    return [
        'id' => 1,
        'sekolah' => 'SMP NEGERI 1 DEMAK',
        'npsn' => '20319280',
        'ks' => 'Drs. H. Sugiyanto, M.Pd.',
        'hari' => '1',
        'kode' => 'N02'
    ];
}

/**
 * Memastikan tabel lembar jawaban (pg_tb, pg_tb_2, pg_tb_3, dst.) ada di database
 */
function ensure_jawaban_table(string $table): void
{
    if (!preg_match('/^pg_tb(_[0-9]+)?$/', $table)) return;
    $db = get_db();
    mysqli_query($db, "CREATE TABLE IF NOT EXISTS {$table} LIKE pg_tb");
}

/**
 * Mengambil semua daftar jadwal dan mata pelajaran
 */
function get_all_jadwal(): array
{
    $db = get_db();
    $list = [];
    $res = mysqli_query($db, "SELECT * FROM jadwal_mapel ORDER BY hari ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $list[] = $r;
        }
    }
    // Fallback jika belum ada baris di jadwal_mapel
    if (empty($list)) {
        $school = get_school_profile();
        $list = [
            [
                'id' => 1,
                'hari' => 1,
                'nama_hari' => 'Hari ke-1',
                'mapel' => $school['mapel_hari1'] ?? 'Matematika',
                'folder_soal' => $school['soal_hari1'] ?? 'conf31',
                'durasi_menit' => (int)($school['durasi_hari1'] ?? 120),
                'aktif' => ($school['hari'] ?? '1') == '1' ? 1 : 0
            ],
            [
                'id' => 2,
                'hari' => 2,
                'nama_hari' => 'Hari ke-2',
                'mapel' => $school['mapel_hari2'] ?? 'Bahasa Indonesia',
                'folder_soal' => $school['soal_hari2'] ?? 'conf32',
                'durasi_menit' => (int)($school['durasi_hari2'] ?? 120),
                'aktif' => ($school['hari'] ?? '1') == '2' ? 1 : 0
            ]
        ];
    }
    return $list;
}

/**
 * Mengambil jadwal dan mata pelajaran yang sedang AKTIF hari ini
 */
function get_active_jadwal(): array
{
    $all = get_all_jadwal();
    foreach ($all as $j) {
        if (!empty($j['aktif'])) {
            return $j;
        }
    }
    return $all[0] ?? [
        'id' => 1,
        'hari' => 1,
        'nama_hari' => 'Hari ke-1',
        'mapel' => 'Matematika',
        'folder_soal' => 'conf31',
        'durasi_menit' => 120,
        'aktif' => 1
    ];
}

/**
 * Mengambil jadwal berdasarkan nomor hari
 */
function get_jadwal_by_hari($hari): ?array
{
    $hari = (int) $hari;
    $all = get_all_jadwal();
    foreach ($all as $j) {
        if ((int)$j['hari'] === $hari) {
            return $j;
        }
    }
    return null;
}

function get_broadcast_cache_path(): string
{
    $dir = dirname(__DIR__) . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir . '/broadcast_active.json';
}

function get_cached_broadcast(): ?array
{
    $file = get_broadcast_cache_path();
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content !== false) {
            $data = json_decode($content, true);
            if (is_array($data)) return $data;
        }
    }
    return null;
}

function set_cached_broadcast(?array $bc): void
{
    $file = get_broadcast_cache_path();
    if ($bc === null) {
        if (file_exists($file)) @unlink($file);
    } else {
        @file_put_contents($file, json_encode($bc, JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Statistik Publik Real-Time untuk Transparansi di Halaman Depan
 */
function get_public_exam_statistics(): array
{
    $db = get_db();
    $school = get_school_profile();
    $activeJadwal = get_active_jadwal();
    $hari = (string) ($activeJadwal['hari'] ?? ($school['hari'] ?? '1'));
    $jawabanTable = $hari == '1' ? 'pg_tb' : 'pg_tb_' . $hari;
    ensure_jawaban_table($jawabanTable);
    $defaultPaket = $activeJadwal['folder_soal'] ?? 'conf31';
    $mapelName = $activeJadwal['mapel'] ?? 'Mata Pelajaran';

    // 1. Total & Status Siswa
    $qStats = mysqli_query($db, "SELECT status, COUNT(*) as c FROM account GROUP BY status");
    $statusCounts = ['0' => 0, '1' => 0, '2' => 0, '3' => 0];
    $totalPeserta = 0;
    if ($qStats) {
        while ($r = mysqli_fetch_assoc($qStats)) {
            $st = (string) $r['status'];
            $cnt = (int) $r['c'];
            $statusCounts[$st] = $cnt;
            $totalPeserta += $cnt;
        }
    }

    $belum = $statusCounts['0'] ?? 0;
    $sedang = $statusCounts['1'] ?? 0;
    $selesai = $statusCounts['2'] ?? 0;
    $reset = $statusCounts['3'] ?? 0;
    $sudahMulai = $sedang + $selesai;
    $tingkatPartisipasi = $totalPeserta > 0 ? round(($sudahMulai / $totalPeserta) * 100, 1) : 0;
    $tingkatSelesai = $totalPeserta > 0 ? round(($selesai / $totalPeserta) * 100, 1) : 0;

    // 2. Integritas / Anti-Curang
    $qVio = mysqli_query($db, "SELECT 
        COUNT(CASE WHEN pelanggaran = 0 THEN 1 END) as bersih,
        COUNT(CASE WHEN pelanggaran > 0 THEN 1 END) as melanggar,
        SUM(pelanggaran) as total_pelanggaran
        FROM account");
    $rVio = $qVio ? mysqli_fetch_assoc($qVio) : [];
    $siswaBersih = (int) ($rVio['bersih'] ?? 0);
    $totalPelanggaran = (int) ($rVio['total_pelanggaran'] ?? 0);
    $indeksIntegritas = $totalPeserta > 0 ? round(($siswaBersih / $totalPeserta) * 100, 1) : 100;

    // 3. Progres per Kelas
    $qKelas = mysqli_query($db, "SELECT kelas,
        COUNT(*) as total,
        COUNT(CASE WHEN status = '0' THEN 1 END) as belum,
        COUNT(CASE WHEN status = '1' THEN 1 END) as sedang,
        COUNT(CASE WHEN status = '2' THEN 1 END) as selesai
        FROM account GROUP BY kelas ORDER BY kelas ASC");
    $perKelas = [];
    if ($qKelas) {
        while ($rk = mysqli_fetch_assoc($qKelas)) {
            $tot = (int) $rk['total'];
            $sel = (int) $rk['selesai'];
            $sed = (int) $rk['sedang'];
            $pct = $tot > 0 ? round((($sel + $sed) / $tot) * 100, 1) : 0;
            $perKelas[] = [
                'kelas' => $rk['kelas'],
                'total' => $tot,
                'belum' => (int) $rk['belum'],
                'sedang' => $sed,
                'selesai' => $sel,
                'progres' => $pct
            ];
        }
    }

    // 4. Kunci & Capaian Nilai (Transparansi Nilai)
    $qKunci = mysqli_query($db, "SELECT kunci FROM kunci_jawaban WHERE paket = '{$defaultPaket}' LIMIT 1");
    $kunci = [];
    if ($qKunci && $rowK = mysqli_fetch_assoc($qKunci)) {
        $kunci = json_decode($rowK['kunci'], true) ?: [];
    }
    $hasKunci = !empty(array_filter($kunci));

    require_once __DIR__ . '/SyncEngine.php';
    $totalSoal = SyncEngine::getPackageQuestionCount($defaultPaket);

    $nilaiArr = [];
    $totalNilai = 0;
    $nilaiTertinggi = 0;
    $nilaiTerendah = 100;
    $tuntasCount = 0;

    if ($hasKunci && $selesai > 0) {
        $qJwb = mysqli_query($db, "SELECT a.id, a.nama, a.kelas, j.* FROM account a JOIN {$jawabanTable} j ON a.id = j.id WHERE a.status = '2'");
        if ($qJwb) {
            while ($s = mysqli_fetch_assoc($qJwb)) {
                $benar = 0;
                for ($i = 1; $i <= $totalSoal; $i++) {
                    $ans = strtoupper(trim((string) ($s['j' . $i] ?? '')));
                    $k = $kunci[$i] ?? '';
                    if ($ans !== '' && $ans === $k) $benar++;
                }
                $skor = $totalSoal > 0 ? round(($benar / $totalSoal) * 100, 1) : 0;
                $nilaiArr[] = $skor;
                $totalNilai += $skor;
                if ($skor > $nilaiTertinggi) $nilaiTertinggi = $skor;
                if ($skor < $nilaiTerendah) $nilaiTerendah = $skor;
                if ($skor >= 75) $tuntasCount++;
            }
        }
    }

    $countSelesaiNilai = count($nilaiArr);
    $rataRata = $countSelesaiNilai > 0 ? round($totalNilai / $countSelesaiNilai, 1) : 0;
    if ($countSelesaiNilai === 0) $nilaiTerendah = 0;
    $persenKetuntasan = $countSelesaiNilai > 0 ? round(($tuntasCount / $countSelesaiNilai) * 100, 1) : 0;

    // 5. Status Sesi Ujian
    $qSesi = mysqli_query($db, "SELECT * FROM atur ORDER BY ruang ASC, sesi ASC");
    $sesiList = [];
    if ($qSesi) {
        while ($rs = mysqli_fetch_assoc($qSesi)) {
            $sesiList[] = [
                'id' => (int) $rs['id'],
                'sesi' => $rs['sesi'],
                'ruang' => $rs['ruang'],
                'durasi' => (int) ($rs['durasi_menit'] ?? 120),
                'aktif' => (string) $rs['ujian_aktif'] === '1',
                'ket' => $rs['ket'] ?: "Sesi {$rs['sesi']} Ruang {$rs['ruang']}"
            ];
        }
    }

    return [
        'timestamp' => date('H:i:s'),
        'tanggal' => tgl_indo(date('Y-m-d')),
        'hari' => $hari,
        'mapel' => $mapelName,
        'paket' => $defaultPaket,
        'total_soal' => $totalSoal,
        'total_peserta' => $totalPeserta,
        'belum_login' => $belum,
        'sedang_ujian' => $sedang,
        'selesai' => $selesai,
        'reset' => $reset,
        'sudah_mulai' => $sudahMulai,
        'tingkat_partisipasi' => $tingkatPartisipasi,
        'tingkat_selesai' => $tingkatSelesai,
        'indeks_integritas' => $indeksIntegritas,
        'siswa_bersih' => $siswaBersih,
        'total_pelanggaran' => $totalPelanggaran,
        'has_kunci' => $hasKunci,
        'count_nilai' => $countSelesaiNilai,
        'rata_rata' => $rataRata,
        'nilai_tertinggi' => $nilaiTertinggi,
        'nilai_terendah' => $nilaiTerendah,
        'persen_tuntas' => $persenKetuntasan,
        'per_kelas' => $perKelas,
        'sesi_list' => $sesiList
    ];
}
