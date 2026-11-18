# Tryout TKA — Sistem Asesmen CBT

Platform **Computer Based Test (CBT)** berbasis web untuk pelaksanaan asesmen dan tryout Tes Kemampuan Akademik (TKA). Aplikasi menyediakan portal peserta, panel administrator/proktor, pengaturan sesi ujian, pemantauan peserta, penilaian, serta pengelolaan paket soal.

<p align="center">
  <a href="https://try-out.siskapro.my.id/"><strong>🌐 Buka Aplikasi Live</strong></a>
</p>

## Informasi Proyek

| Informasi | Keterangan |
|---|---|
| Nama proyek | Tryout TKA — Sistem Asesmen CBT |
| Status | Live |
| Website produksi | https://try-out.siskapro.my.id/ |
| Repository | https://github.com/fadhilatulazizi/Tryout-Tka |
| Bahasa utama | PHP |
| Database | MySQL |
| Antarmuka | HTML, CSS, JavaScript, Tailwind CSS CDN |

## Fitur

### Portal dan autentikasi
- Halaman utama dengan informasi sekolah dan statistik asesmen.
- Login terpadu (SSO) untuk administrator/proktor dan peserta didik.
- Pengelolaan sesi autentikasi dan hak akses berdasarkan peran.

### Administrasi dan pengelolaan peserta
- Dashboard administrator.
- Pengelolaan data siswa dan impor data melalui spreadsheet.
- Pengelolaan profil/informasi sekolah.
- Pengaturan jadwal ujian, mata pelajaran, paket soal, durasi, sesi, dan ruang.
- Pembuatan, pengaturan, serta pengacakan token sesi/ruang.

### Pelaksanaan ujian
- Halaman konfirmasi dan lembar ujian peserta.
- Penyimpanan jawaban menggunakan endpoint AJAX.
- Penanda soal ragu-ragu dan penyelesaian ujian.
- Pemantauan status peserta dan aktivitas ujian.
- Pencatatan serta pemantauan pelanggaran, termasuk indikasi perpindahan tab atau jendela.

### Penilaian dan pelaporan
- Pengelolaan kunci jawaban per paket soal.
- Perhitungan jawaban benar, salah, kosong, dan nilai.
- Rekap hasil ujian per jadwal/mata pelajaran.
- Analisis hasil dan ekspor rekap ke format spreadsheet.
- Berita acara dan kartu peserta.

### Sinkronisasi dan informasi
- Sinkronisasi/pengelolaan paket soal.
- Statistik ujian pada halaman publik.
- Fitur pengumuman/broadcast untuk peserta.
- Cache untuk membantu penyajian data tertentu.

> Daftar fitur dirangkum dari struktur dan implementasi source code repository. Ketersediaan fitur pada server produksi dapat bergantung pada konfigurasi dan data yang digunakan.

## Teknologi

- **PHP** sebagai bahasa pemrograman sisi server.
- **MySQL** dengan ekstensi MySQLi untuk operasi database; PDO MySQL juga diperiksa/digunakan oleh konfigurasi koneksi.
- **HTML, CSS, dan JavaScript** untuk antarmuka dan interaksi aplikasi.
- **Tailwind CSS CDN** pada halaman portal.
- **Spreadsheet Reader** untuk membaca berkas spreadsheet (XLS, XLSX, ODS, dan CSV) pada proses impor.

## Struktur Repository

```text
Tryout-Tka/
├── admin/                 # Panel administrator dan proktor
├── app/                   # Autentikasi, database init, engine ujian,
│                          # helper, dan sinkronisasi
│   └── vendor/            # Library pembaca spreadsheet
├── assets/
│   ├── css/               # Stylesheet aplikasi
│   ├── images/            # Gambar dan aset antarmuka
│   └── js/                # JavaScript aplikasi
├── cache/                 # Cache aplikasi
├── soal/                  # Paket soal dan aset pendukung
├── ujian/                 # Halaman dan endpoint pelaksanaan ujian
├── index.php              # Portal utama
├── login.php              # Login terpadu
├── install.php            # Installer dan inisialisasi database
└── koneksiku.php          # Jembatan kompatibilitas koneksi
```

## Persyaratan Server

Mengacu pada pemeriksaan yang dilakukan installer:

- PHP 7.4 atau lebih baru (PHP 8.x direkomendasikan).
- MySQL/MariaDB yang dapat diakses aplikasi.
- Ekstensi PHP: MySQLi, PDO MySQL, mbstring, JSON, dan Session.
- Direktori `config/` dan `cache/` harus tersedia dan dapat ditulis oleh proses PHP.
- Ekstensi ZIP diperlukan oleh library pembaca spreadsheet untuk dukungan berkas tertentu.

## Instalasi dan Konfigurasi

1. Siapkan hosting/web server dengan PHP dan MySQL/MariaDB sesuai persyaratan.
2. Unggah source code ke direktori aplikasi pada hosting.
3. Siapkan direktori `config/` dan `cache/`, lalu atur permission tulis yang diperlukan.
4. Pastikan file konfigurasi aplikasi tersedia. **Catatan repository:** source code saat ini memanggil `config/app.php`, tetapi file tersebut tidak termasuk dalam tree repository. Installer `install.php` membuat `config/database.php`; file `config/app.php` tetap perlu disediakan sesuai konfigurasi aplikasi sebelum halaman yang memanggilnya dijalankan.
5. Buka `/install.php` melalui browser untuk mengisi koneksi database, identitas sekolah, dan akun administrator serta menjalankan inisialisasi skema.
6. Setelah instalasi berhasil, pastikan installer terkunci dan akses halaman admin serta portal peserta untuk pengujian.

**Penting untuk keamanan produksi:** gunakan password administrator yang kuat, jangan membagikan file konfigurasi atau kredensial database, batasi akses ke installer setelah setup, dan pastikan direktori konfigurasi/cache tidak dapat diakses publik secara langsung.

## Catatan Pengembangan

- Struktur konfigurasi dan kebutuhan deployment dapat berbeda antara lingkungan lokal dan hosting.
- Repository belum menyertakan file konfigurasi runtime `config/app.php` maupun kredensial database. Jangan commit password, API key, atau data peserta ke repository publik.
- Uji proses instalasi pada database kosong/staging sebelum melakukan perubahan pada server produksi.

## Lisensi

Lisensi proyek belum dinyatakan secara eksplisit dalam repository ini. Hak penggunaan dan distribusi source code mengikuti ketentuan pemilik proyek.
