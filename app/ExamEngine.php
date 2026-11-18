<?php
/**
 * Engine Ujian CBT ANBK / Pusmendik
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/SyncEngine.php';

class ExamEngine
{
    /**
     * Dapatkan konfigurasi sesi & ruang ujian siswa
     */
    public static function getSessionConfig(string $sesi, string $ruang): array
    {
        $db = get_db();
        $stmt = mysqli_prepare($db, "SELECT * FROM atur WHERE sesi = ? AND ruang = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $sesi, $ruang);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            if ($row) return $row;
        }

        // Fallback jika belum diatur spesifik
        $res = mysqli_query($db, "SELECT * FROM atur ORDER BY id ASC LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            return $row;
        }

        return [
            'id' => 1,
            'sesi' => $sesi,
            'ruang' => $ruang,
            'token' => 'DEMAK1',
            'ujian_aktif' => '1',
            'folder_soal' => 'conf31',
            'hari' => '1',
            'durasi_menit' => 120,
            'ket' => 'Sesi Standar'
        ];
    }

    /**
     * Dapatkan State Ujian Lengkap Siswa
     */
    public static function getStudentState(int $studentId): array
    {
        $db = get_db();

        // 1. Ambil data akun siswa
        $stmt = mysqli_prepare($db, "SELECT * FROM account WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $studentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$student) {
            throw new RuntimeException('Data siswa tidak ditemukan.');
        }

        // 2. Ambil profil sekolah & konfigurasi jadwal aktif
        $school = get_school_profile();
        $config = self::getSessionConfig($student['sesi'], $student['ruang']);
        $activeJadwal = get_active_jadwal();

        $hari = (string) ($config['hari'] ?? ($school['hari'] ?? ($activeJadwal['hari'] ?? '1')));
        $curJadwal = get_jadwal_by_hari($hari) ?? $activeJadwal;

        $folderSoal = $config['folder_soal'] ?? ($curJadwal['folder_soal'] ?? 'conf31');
        $mapelName = $curJadwal['mapel'] ?? 'Mata Pelajaran';
        $jawabanTable = $hari == '1' ? 'pg_tb' : 'pg_tb_' . $hari;
        ensure_jawaban_table($jawabanTable);
        $totalQuestions = SyncEngine::getPackageQuestionCount($folderSoal);

        // 3. Pastikan baris lembar jawaban di tabel jawaban sudah ada
        $chkStmt = mysqli_prepare($db, "SELECT * FROM {$jawabanTable} WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($chkStmt, 'i', $studentId);
        mysqli_stmt_execute($chkStmt);
        $chkRes = mysqli_stmt_get_result($chkStmt);
        $answersRow = mysqli_fetch_assoc($chkRes);
        mysqli_stmt_close($chkStmt);

        if (!$answersRow) {
            $insStmt = mysqli_prepare($db, "INSERT INTO {$jawabanTable} (id, sekolah, nama, kelas, abs, mapel, waktu_mulai) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insStmt, 'isssss', $studentId, $school['sekolah'], $student['nama'], $student['kelas'], $student['abs'], $mapelName);
            mysqli_stmt_execute($insStmt);
            mysqli_stmt_close($insStmt);

            // Re-fetch
            $chkStmt = mysqli_prepare($db, "SELECT * FROM {$jawabanTable} WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($chkStmt, 'i', $studentId);
            mysqli_stmt_execute($chkStmt);
            $answersRow = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
            mysqli_stmt_close($chkStmt);
        }

        // Parse jawaban j1..j{totalQuestions}
        $answers = [];
        for ($i = 1; $i <= $totalQuestions; $i++) {
            $val = trim((string) ($answersRow['j' . $i] ?? ''));
            $answers[$i] = $val !== '' ? strtoupper($val) : '';
        }

        // Parse ragu-ragu
        $ragu = [];
        if (!empty($student['ragu'])) {
            $decoded = json_decode($student['ragu'], true);
            if (is_array($decoded)) {
                $ragu = array_map('intval', $decoded);
            }
        }

        // Susun urutan acak a1..a{totalQuestions}
        $questionOrder = [];
        for ($i = 1; $i <= $totalQuestions; $i++) {
            $val = isset($student['a' . $i]) ? (int) $student['a' . $i] : $i;
            $questionOrder[$i] = ($val >= 1 && $val <= $totalQuestions) ? $val : $i;
        }

        return [
            'student' => $student,
            'school' => $school,
            'config' => $config,
            'hari' => $hari,
            'folderSoal' => $folderSoal,
            'mapelName' => $mapelName,
            'jawabanTable' => $jawabanTable,
            'answers' => $answers,
            'ragu' => $ragu,
            'questionOrder' => $questionOrder,
            'totalQuestions' => $totalQuestions,
            'status' => $student['status']
        ];
    }

    /**
     * Simpan satu jawaban butir soal
     */
    public static function saveAnswer(int $studentId, int $noSoal, string $jawaban, ?bool $isRagu = null): bool
    {
        if ($noSoal < 1 || $noSoal > 100) return false;
        $jawaban = strtoupper(trim($jawaban));
        // Mendukung Pilihan Ganda Tunggal (A-E), Benar/Salah (1B,2S...), atau Checkbox (1,3...)
        if ($jawaban !== '' && !preg_match('/^[A-E0-9,BS\s]+$/i', $jawaban)) {
            return false;
        }

        $db = get_db();
        $state = self::getStudentState($studentId);
        $table = $state['jawabanTable'];

        // 1. Update lembar jawaban
        $col = 'j' . $noSoal;
        $stmt = mysqli_prepare($db, "UPDATE {$table} SET {$col} = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $jawaban, $studentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // 2. Update status ragu jika dispesifikasikan
        if ($isRagu !== null) {
            $raguList = $state['ragu'];
            if ($isRagu) {
                if (!in_array($noSoal, $raguList, true)) {
                    $raguList[] = $noSoal;
                }
            } else {
                $raguList = array_values(array_filter($raguList, fn($n) => $n !== $noSoal));
            }
            $raguJson = json_encode($raguList);
            $stmtRagu = mysqli_prepare($db, "UPDATE account SET ragu = ? WHERE id = ?");
            if ($stmtRagu) {
                mysqli_stmt_bind_param($stmtRagu, 'si', $raguJson, $studentId);
                mysqli_stmt_execute($stmtRagu);
                mysqli_stmt_close($stmtRagu);
            }
        }

        return true;
    }

    /**
     * Selesaikan Ujian
     */
    public static function finishExam(int $studentId): bool
    {
        $db = get_db();
        $state = self::getStudentState($studentId);
        $table = $state['jawabanTable'];

        // Tandai status selesai (2) di account
        $stmt1 = mysqli_prepare($db, "UPDATE account SET status = '2' WHERE id = ?");
        if ($stmt1) {
            mysqli_stmt_bind_param($stmt1, 'i', $studentId);
            mysqli_stmt_execute($stmt1);
            mysqli_stmt_close($stmt1);
        }

        // Catat waktu selesai di pg_tb / pg_tb_2
        mysqli_query($db, "UPDATE {$table} SET waktu_selesai = NOW() WHERE id = {$studentId}");

        return true;
    }
}
