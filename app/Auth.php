<?php
/**
 * Modul Autentikasi Admin & Siswa
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/Helper.php';

class Auth
{
    /**
     * Autentikasi Administrator
     */
    public static function attemptAdmin(string $username, string $password): bool
    {
        $db = get_db();
        $stmt = mysqli_prepare($db, "SELECT id, user, pass, nama FROM admin WHERE user = ? LIMIT 1");
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$admin) return false;

        // Mendukung password hash bcrypt maupun plain-text
        $valid = password_verify($password, $admin['pass']) || ($password === $admin['pass']);
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['user'];
            $_SESSION['nama_admin'] = $admin['nama'];
            $_SESSION['nama'] = $admin['nama'];
            $_SESSION['id'] = (int) $admin['id'];
            $_SESSION['admin_last_activity'] = time();
            return true;
        }

        return false;
    }

    public static function requireAdmin(): void
    {
        if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['nama_admin'])) {
            header('Location: ' . base_url('admin/login.php'));
            exit;
        }
    }

    public static function logoutAdmin(): void
    {
        unset(
            $_SESSION['admin_logged_in'],
            $_SESSION['admin_id'],
            $_SESSION['admin_username'],
            $_SESSION['nama_admin'],
            $_SESSION['admin_last_activity']
        );
    }

    /**
     * Autentikasi Siswa
     */
    public static function attemptStudent(string $username, string $password): array
    {
        $db = get_db();
        $stmt = mysqli_prepare($db, "SELECT * FROM account WHERE user = ? LIMIT 1");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem database.'];
        }

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$student) {
            return ['success' => false, 'message' => 'Username peserta tidak ditemukan.'];
        }

        if ($student['pass'] !== $password) {
            return ['success' => false, 'message' => 'Password yang Anda masukkan salah.'];
        }

        // Cek status siswa
        // 0: Belum login, 1: Sedang login, 2: Selesai ujian, 3: Butuh reset login proktor
        if ($student['status'] === '2') {
            return ['success' => false, 'message' => 'Anda sudah menyelesaikan ujian ini dan tidak dapat masuk kembali.'];
        }

        if ($student['status'] === '1') {
            return [
                'success' => false,
                'message' => 'Akun Anda sedang aktif di komputer lain. Hubungi proktor ruang untuk me-reset login jika terjadi kendala.'
            ];
        }

        // Simpan sesi siswa
        session_regenerate_id(true);
        $_SESSION['student_logged_in'] = true;
        $_SESSION['id'] = (int) $student['id'];
        $_SESSION['nama'] = $student['nama'];
        $_SESSION['user'] = $student['user'];
        $_SESSION['kelas'] = $student['kelas'];
        $_SESSION['abs'] = $student['abs'];
        $_SESSION['sesi'] = $student['sesi'];
        $_SESSION['ruang'] = $student['ruang'];

        return ['success' => true, 'student' => $student];
    }

    public static function requireStudent(): void
    {
        if (empty($_SESSION['student_logged_in']) || empty($_SESSION['id'])) {
            header('Location: ' . base_url('ujian/index.php'));
            exit;
        }
    }

    public static function logoutStudent(): void
    {
        unset(
            $_SESSION['student_logged_in'],
            $_SESSION['id'],
            $_SESSION['nama'],
            $_SESSION['user'],
            $_SESSION['kelas'],
            $_SESSION['abs'],
            $_SESSION['sesi'],
            $_SESSION['ruang'],
            $_SESSION['exam_token_valid'],
            $_SESSION['exam_start_time'],
            $_SESSION['exam_end_time']
        );
    }
}
