<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';

// Jika siswa sudah login, arahkan ke konfirmasi ujian
if (!empty($_SESSION['student_logged_in']) && !empty($_SESSION['id'])) {
    header('Location: ' . base_url('ujian/konfirmasi.php'));
    exit;
}

// Terpadu: arahkan langsung ke portal SSO
header('Location: ' . base_url('login.php'));
exit;
