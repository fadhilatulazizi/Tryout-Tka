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
| Antarmuka | HTML, CSS, JavaScript, Tailwind CSS |

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
- Pengaturan token sesi/ruang.

### Pelaksanaan ujian
- Halaman konfirmasi dan lembar ujian peserta.
- Penyimpanan jawaban menggunakan AJAX.
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

- **PHP** untuk backend.
- **MySQL/MariaDB** dengan ekstensi MySQLi dan PDO MySQL.
- **HTML, CSS, JavaScript** untuk antarmuka dan interaksi.
- **Tailwind CSS** untuk styling antarmuka.
- **Spreadsheet Reader** untuk membaca berkas spreadsheet pada proses impor.

## Struktur Repository

```text
Tryout-Tka/
├── admin/                 # Panel administrator dan proktor
├── app/                   # Autentikasi, helper, engine ujian,
│                          # inisialisasi database, sinkronisasi
│   └── vendor/            # Library pembaca spreadsheet
├── assets/
│   ├── css/               # Stylesheet
│   ├── images/            # Gambar dan aset antarmuka
│   └── js/                # JavaScript
├── cache/                 # Cache aplikasi
├── config/
│   ├── app.php            # Konfigurasi utama aplikasi
│   └── database.php       # Konfigurasi koneksi database
├── soal/                  # Paket soal dan aset pendukung
├── ujian/                 # Halaman dan endpoint pelaksanaan ujian
├── index.php              # Portal utama
├── login.php              # Login terpadu
├── install.php            # Installer dan inisialisasi database
└── koneksiku.php          # Kompatibilitas koneksi
```

## Persyaratan Server

- PHP 7.4 atau lebih baru (PHP 8.x direkomendasikan).
- MySQL/MariaDB.
- Ekstensi PHP MySQLi, PDO MySQL, mbstring, JSON, dan Session.
- Ekstensi ZIP untuk dukungan berkas spreadsheet tertentu.
- Direktori `config/` dan `cache/` dengan permission yang sesuai.

## Instalasi

1. Siapkan hosting/web server dengan PHP dan MySQL/MariaDB.
2. Unggah source code ke direktori aplikasi.
3. Buat database dan siapkan user database dengan hak akses yang diperlukan.
4. Sesuaikan `config/database.php` dengan kredensial database lingkungan instalasi. Utamakan environment variables untuk menyimpan kredensial.
5. Periksa `config/app.php` dan sesuaikan pengaturan aplikasi bila diperlukan.
6. Buka `/install.php` melalui browser untuk menjalankan proses inisialisasi database dan pengaturan awal.
7. Setelah instalasi selesai, pastikan installer terkunci dan uji login administrator serta peserta.

## Keamanan

- Jangan menyimpan password database, API key, atau kredensial produksi di repository.
- Gunakan environment variables atau konfigurasi rahasia di luar web root untuk kredensial.
- Jika kredensial database yang pernah di-commit merupakan kredensial aktif, segera ganti/rotasi password database tersebut.
- Batasi akses installer setelah instalasi selesai.
- Gunakan HTTPS dan password administrator yang kuat.
- Jangan commit data peserta atau hasil ujian yang bersifat pribadi.

## Status

Aplikasi telah tersedia secara online di **https://try-out.siskapro.my.id/**.

## Lisensi

Lisensi proyek belum dinyatakan secara eksplisit dalam repository ini. Hak penggunaan dan distribusi source code mengikuti ketentuan pemilik proyek.
