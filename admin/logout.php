<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';

Auth::logoutAdmin();
header('Location: ' . base_url('admin/login.php'));
exit;
