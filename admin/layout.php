<?php
/**
 * Layout Shell Administrator CBT Demak
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';

function admin_header(string $title, string $activeMenu = 'dashboard'): void
{
    Auth::requireAdmin();
    $school = get_school_profile();
    $adminName = $_SESSION['nama_admin'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?> | Panel Admin CBT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css?v=' . time()); ?>">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo-icon">D</div>
                <div class="sidebar-title">
                    <h2>CBT DEMAK</h2>
                    <p>ADMIN PANEL v4.0</p>
                </div>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-group-label">Utama</div>
                <a href="<?php echo base_url('admin/index.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo base_url('admin/pantau.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'pantau' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">👁️</span>
                    <span>Pantau Ujian Live</span>
                </a>

                <div class="menu-group-label">Kesiswaan & Jadwal</div>
                <a href="<?php echo base_url('admin/siswa.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'siswa' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">👥</span>
                    <span>Data Peserta</span>
                </a>
                <a href="<?php echo base_url('admin/impor_siswa.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'impor' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📥</span>
                    <span>Impor Excel</span>
                </a>
                <a href="<?php echo base_url('admin/kartu.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'kartu' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🖨️</span>
                    <span>Cetak Kartu Ujian</span>
                </a>
                <a href="<?php echo base_url('admin/berita_acara.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'berita_acara' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📝</span>
                    <span>Berita Acara & Hadir</span>
                </a>
                <a href="<?php echo base_url('admin/jurnal.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'jurnal' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📑</span>
                    <span>Jurnal & Publikasi</span>
                </a>
                <a href="<?php echo base_url('admin/sesi_token.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'sesi' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">⚙️</span>
                    <span>Server, Sesi & Mapel</span>
                </a>

                <div class="menu-group-label">Hasil & Soal</div>
                <a href="<?php echo base_url('admin/hasil.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'hasil' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📋</span>
                    <span>Hasil Ujian</span>
                </a>
                <a href="<?php echo base_url('admin/analisis.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'analisis' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">📈</span>
                    <span>Analisis Butir Soal</span>
                </a>
                <a href="<?php echo base_url('admin/preview_soal.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'preview_soal' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🔍</span>
                    <span>Pratinjau Soal</span>
                </a>
                <a href="<?php echo base_url('admin/sinkron_soal.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'sinkron' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🔄</span>
                    <span>Sinkron Soal Luar</span>
                </a>

                <div class="menu-group-label">Pengaturan</div>
                <a href="<?php echo base_url('admin/sekolah.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'sekolah' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">🏫</span>
                    <span>Profil Sekolah</span>
                </a>
                <a href="<?php echo base_url('admin/backup.php'); ?>" class="sidebar-item <?php echo $activeMenu === 'backup' ? 'active' : ''; ?>">
                    <span class="sidebar-icon">💾</span>
                    <span>Backup Database</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="<?php echo base_url('admin/logout.php'); ?>" class="btn-logout" onclick="return confirm('Keluar dari sesi administrator?')">
                    <span>↪ Keluar</span>
                </a>
                <span style="font-size: 11px; color: #64748b;">2026</span>
            </div>
        </aside>

        <!-- Main Column -->
        <div class="admin-main">
            <header class="admin-topbar">
                <div class="topbar-left" style="display: flex; align-items: center; gap: 14px;">
                    <button type="button" id="sidebar-toggle" style="display: none; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; width: 36px; height: 36px; font-size: 18px; cursor: pointer; color: #334155;" title="Buka / Tutup Menu">☰</button>
                    <div>
                        <h1><?php echo e($title); ?></h1>
                        <p>Server CBT Terhubung &bull; Aktif</p>
                    </div>
                </div>
                <div class="topbar-right">
                    <span class="badge-school">🏫 <?php echo e($school['sekolah']); ?></span>
                    <span style="font-size: 13px; font-weight: 600; color: #334155;">👤 <?php echo e($adminName); ?></span>
                </div>
            </header>

            <main class="admin-content">
<?php
}

function admin_footer(): void
{
?>
            </main>
        </div>
    </div>

    <script>
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        if (sidebarToggle && sidebar) {
            function checkWidth() {
                if (window.innerWidth <= 900) {
                    sidebarToggle.style.display = 'inline-flex';
                    sidebarToggle.style.alignItems = 'center';
                    sidebarToggle.style.justifyContent = 'center';
                } else {
                    sidebarToggle.style.display = 'none';
                    sidebar.classList.remove('open');
                }
            }
            window.addEventListener('resize', checkWidth);
            checkWidth();

            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('open');
            });

            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 900 && sidebar.classList.contains('open')) {
                    if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php
}
