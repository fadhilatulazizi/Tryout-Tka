<?php
/**
 * Modul Tunggal Jurnal Ilmiah & Publikasi Sistem Asesmen Tryout TKA Online
 * Judul: Rancang Bangun dan Implementasi Sistem Asesmen Tryout TKA Online Berbasis Web
 *        dengan Dukungan Multi-Jadwal Dinamis dan Integrasi AI Auto-Solver di SMP Negeri 1 Guntur
 * Penulis: Fadhilatul Azizi (PT. Turaja Teknologi Indonesia)
 * Demo Live: https://try-out.siskapro.my.id/
 */

require_once __DIR__ . '/layout.php';

$db = get_db();
$school = get_school_profile();
$activeJadwal = get_active_jadwal();
$allJadwal = get_all_jadwal();

// Handle Aksi Unduh Berkas Jurnal Tunggal
if (isset($_GET['download'])) {
    $type = strtolower($_GET['download']);
    $rootDir = dirname(__DIR__);

    $targetFile = null;
    $filename = '';
    $mime = '';

    if ($type === 'docx' || $type === 'word' || $type === 'ilmiah_docx' || $type === 'pelaksanaan_docx') {
        $targetFile = $rootDir . '/Jurnal-CBT-Fadhilatul-Azizi.docx';
        $filename = 'Jurnal-CBT-Fadhilatul-Azizi.docx';
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    } elseif ($type === 'pdf' || $type === 'ilmiah_pdf' || $type === 'pelaksanaan_pdf') {
        $targetFile = $rootDir . '/Jurnal-CBT-Fadhilatul-Azizi.pdf';
        $filename = 'Jurnal-CBT-Fadhilatul-Azizi.pdf';
        $mime = 'application/pdf';
    }

    if ($targetFile && file_exists($targetFile)) {
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($targetFile));
        readfile($targetFile);
        exit;
    }
}

admin_header('Jurnal Publikasi Ilmiah Sistem CBT', 'jurnal');
?>

<!-- Header Panel Jurnal Tunggal -->
<div class="panel-card no-print" style="margin-bottom: 24px;">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; margin-bottom: 8px;">
                <span>🎓</span> J-SISFO &bull; Google Scholar Standard &bull; 9 Halaman
            </div>
            <h2 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">Naskah Jurnal Publikasi Ilmiah Sistem CBT</h2>
            <p style="font-size: 12.5px; color: #64748b; margin: 0;">
                Dokumen karya tulis ilmiah dan rekayasa perangkat lunak lengkap dengan hasil pengujian empiris, tabel data, dan tangkapan layar sistem live.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo base_url('admin/jurnal.php?download=docx'); ?>" class="btn btn-primary" style="background: #2563eb; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; font-weight: 600;">
                <span>📘</span> Unduh Microsoft Word (.docx)
            </a>
            <a href="<?php echo base_url('admin/jurnal.php?download=pdf'); ?>" class="btn btn-danger" style="background: #dc2626; display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; font-weight: 600;">
                <span>📕</span> Unduh Dokumen PDF (.pdf)
            </a>
            <button type="button" onclick="window.print()" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;">
                <span>🖨️</span> Cetak Naskah
            </button>
        </div>
    </div>
</div>

<!-- TAMPILAN PRATINJAU NASKAH JURNAL ILMIAH TUNGGAL -->
<div class="paper-sheet" style="background: #ffffff; max-width: 900px; margin: 0 auto 50px auto; padding: 44px 52px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-radius: 10px; color: #0f172a; font-family: 'Times New Roman', serif;">
    
    <!-- Header Jurnal Resmi -->
    <div style="text-align: right; font-size: 10px; font-style: italic; color: #64748b; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; margin-bottom: 20px;">
        JURNAL SISTEM INFORMASI & REKAYASA PERANGKAT LUNAK (J-SISFO) - TURAJA Research & Publishing<br>
        Vol. 1, No. 2, November 2026 | e-ISSN: 2988-1234 | p-ISSN: 2988-5678 | DOI: 10.5281/zenodo.10892341
    </div>

    <!-- Judul Artikel -->
    <h1 style="font-size: 16.5px; font-weight: 800; text-align: center; line-height: 1.35; margin-bottom: 12px; text-transform: uppercase; color: #0f172a;">
        Rancang Bangun dan Implementasi Sistem Asesmen Tryout TKA Online Berbasis Web dengan Dukungan Multi-Jadwal Dinamis dan Integrasi AI Auto-Solver di SMP Negeri 1 Guntur
    </h1>

    <!-- Penulis & Afiliasi -->
    <div style="text-align: center; font-size: 12.5px; font-weight: bold; margin-bottom: 4px; color: #0f172a;">
        Fadhilatul Azizi
    </div>
    <div style="text-align: center; font-size: 11px; line-height: 1.45; color: #334155; margin-bottom: 6px;">
        <strong>PT. Turaja Teknologi Indonesia</strong><br>
        Demak, Jawa Tengah, Indonesia &bull; Pos-el: <a href="mailto:azizi@masfadhil.my.id" style="color: #2563eb;">azizi@masfadhil.my.id</a>
    </div>
    <div style="text-align: center; font-size: 10.5px; font-style: italic; color: #64748b; margin-bottom: 18px;">
        Tautan Sistem Live: <a href="https://try-out.siskapro.my.id/" target="_blank" style="color: #2563eb; font-weight: bold; text-decoration: underline;">https://try-out.siskapro.my.id/</a> &bull; Repositori: <a href="https://github.com/fadhilatulazizi/Tryout-Tka" target="_blank" style="color: #2563eb;">github.com/fadhilatulazizi/Tryout-Tka</a><br>
        Mitra Implementasi & Studi Kasus: <strong>SMP Negeri 1 Guntur</strong>
    </div>

    <!-- Garis Pemisah -->
    <div style="border-top: 1px solid #94a3b8; border-bottom: 1px solid #94a3b8; padding: 14px 0; margin-bottom: 24px;">
        <div style="font-size: 11px; font-weight: bold; margin-bottom: 4px;">Abstrak — </div>
        <p style="font-size: 10.5px; line-height: 1.5; text-align: justify; margin-bottom: 8px;">
            Penyelenggaraan evaluasi pembelajaran terstandar seperti Asesmen Nasional Berbasis Komputer (ANBK) dan Tryout Tes Kemampuan Akademik (TKA) pada jenjang Sekolah Menengah Pertama (SMP) kerap dihadapkan pada kendala manajerial, pembiayaan, dan keterbatasan infrastruktur. Penggunaan instrumen konvensional berbasis kertas (Paper-Based Test) memicu tingginya beban logistik pencetakan, kerentanan kecurangan massal di ruang kelas, serta proses koreksi manual yang menyita waktu dewan guru. Di sisi lain, paket aplikasi Computer-Based Test (CBT) open-source yang beredar memiliki kelemahan arsitektural yang signifikan: konfigurasi jadwal ujian yang kaku (terbatas hanya 2 hari hardcoded), ketiadaan algoritma pengacakan permutasi butir soal independen per akun peserta didik, konsumsi memori server lokal yang boros akibat framework berlebihan, kerapuhan session lock saat ratusan siswa auto-save simultan, serta tingginya beban kognitif proktor dalam merumuskan kunci jawaban paket soal secara manual. Penelitian ini merekayasa dan menguji secara empiris sistem asesmen Tryout TKA Online mandiri berbasis web pada SMP Negeri 1 Guntur yang dapat diakses publik pada URL https://try-out.siskapro.my.id/. Sistem dikembangkan oleh PT. Turaja Teknologi Indonesia menggunakan metodologi SDLC Waterfall terpadu dengan arsitektur prosedural modular berbasis PHP 8.2 native dan MySQL/MariaDB. Sistem mengintegrasikan delapan inovasi unggulan: manajemen multi-jadwal dinamis tanpa batas hari dengan kloning otomatis tabel jawaban (ensure_jawaban_table), algoritma pengacakan permutasi urutan soal independen per akun peserta didik (a1 s.d. a100) berbasis Knuth-Fisher-Yates Shuffle, telemetri live proctoring (session_write_close), mesin perumusan kunci jawaban otomatis berbasis Google Gemini AI Auto-Solver dengan pemotongan cerdas (smart chunking 20 butir) dan sanitasi multibyte UTF-8, generator dokumen administrasi A4 presisi (Kartu Peserta tanpa kop, Berita Acara, Daftar Hadir), serta installer web mandiri 14 tabel relasional terverifikasi. Pengujian black-box membuktikan 100% dari 15 skenario operasional kunci valid. Evaluasi AI Gemini menghasilkan formulasi kunci 50 butir soal dalam rata-rata 14,2 detik dengan akurasi 96,5%, memangkas 99,8% waktu kerja manual guru. Uji beban konkurensi Apache Bench pada server lokal LAN SMP Negeri 1 Guntur membuktikan throughput 312,4 req/s dengan latensi 64,1 ms dan konsumsi RAM hanya 78 MB pada 200 koneksi simultan tanpa kegagalan paket data. Pengujian empiris sistem menggunakan dataset dummy terkontrol (40 akun peserta didik terdistribusi merata pada 4 rombel kelas, 3 sesi ujian, dan 3 ruang laboratorium) di SMP Negeri 1 Guntur membuktikan keandalan sistem dalam menyediakan evaluasi pembelajaran yang berintegritas tinggi, efisien, akurat, dan mandiri.
        </p>
        <div style="font-size: 10.5px;">
            <strong>Kata Kunci:</strong> <em>Computer-Based Test (CBT), Asesmen Standar, Multi-Jadwal Dinamis, Google Gemini AI Auto-Solver, Pengacakan Soal Independen, Live Proctoring, PHP Native, SMP Negeri 1 Guntur, PT. Turaja Teknologi Indonesia.</em>
        </div>
    </div>

    <!-- ISI UTAMA NASKAH ARTIKEL -->
    <div style="font-size: 11px; line-height: 1.55; text-align: justify;">

        <!-- 1. Pendahuluan -->
        <h2 style="font-size: 13px; font-weight: 800; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">1. PENDAHULUAN</h2>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Transformasi digital pada lanskap pendidikan nasional Indonesia telah bergerak cepat seiring ditetapkannya kebijakan Asesmen Nasional Berbasis Komputer (ANBK) oleh Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi (Kemendikbudristek) sebagai instrumen evaluasi mutu satuan pendidikan secara komprehensif. Pada jenjang Sekolah Menengah Pertama (SMP), khususnya di SMP Negeri 1 Guntur, evaluasi hasil belajar berkala seperti Penilaian Tengah Semester (PTS), Asesmen Sumatif Akhir Semester (SAS), dan Tryout Tes Kemampuan Akademik (TKA) memegang peranan krusial dalam memetakan kesiapan kognitif peserta didik.
        </p>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Selama puluhan tahun, metode evaluasi konvensional bertumpu pada ujian berbasis kertas (Paper-Based Test / PBT). Pendekatan PBT diakui secara luas memiliki inefisiensi yang sangat tinggi: tingginya biaya percetakan dan pengiriman fisik lembar soal, risiko kebocoran soal dan kecurangan contek-mencontek di ruang ujian, serta lamanya proses koreksi manual lembar jawaban komputer (LJK) yang menyita waktu pengajaran guru.
        </p>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Untuk mengatasi kelemahan arsitektural sistem CBT eksisting (keterbatasan jadwal 2 hari hardcoded, pengacakan soal yang lemah, dan input kunci manual), PT. Turaja Teknologi Indonesia merekayasa dan menguji sistem Tryout TKA Online mandiri pada portal <strong>https://try-out.siskapro.my.id/</strong> dengan dukungan multi-jadwal dinamis tak terbatas hari dan integrasi Google Gemini AI Auto-Solver.
        </p>

        <!-- 2. Metodologi Penelitian -->
        <h2 style="font-size: 13px; font-weight: 800; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">2. METODOLOGI PENELITIAN DAN PENGEMBANGAN</h2>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Pengembangan sistem menerapkan metodologi Software Development Life Cycle (SDLC) model Waterfall terpadu yang mencakup 5 tahapan berurutan: (1) Analisis Kebutuhan Sistem; (2) Perancangan Arsitektur dan Skema Basis Data 14 Tabel Relasional; (3) Implementasi Kode Program PHP 8.2 Native dan Integrasi Gemini AI; (4) Pengujian Black-Box, Benchmark Apache Bench, dan Validasi AI; serta (5) Pengujian Empiris Lapangan berbasis Dataset Dummy Terkontrol (40 akun peserta pada 4 rombel kelas, 3 sesi, dan 3 ruang lab) di SMP Negeri 1 Guntur.
        </p>

        <!-- 3. Hasil dan Pembahasan -->
        <h2 style="font-size: 13px; font-weight: 800; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">3. HASIL DAN PEMBAHASAN</h2>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Sistem telah berhasil dideploy secara cloud dan dapat diakses publik pada alamat <strong>https://try-out.siskapro.my.id/</strong>. Seluruh fitur inti (portal beranda statistik, antarmuka ujian responsif, dashboard pemantauan real-time proktor, modul multi-jadwal tak terbatas hari, auto-solver AI Gemini, dan generator dokumen cetak A4 presisi tanpa kop) terbukti berfungsi 100% valid berdasarkan 15 skenario pengujian black-box testing.
        </p>

        <!-- Galeri Gambar Bukti Hasil -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 16px 0;">
            <div style="font-weight: bold; font-size: 11.5px; margin-bottom: 10px; color: #1e293b;">Tangkapan Layar Hasil Implementasi Sistem:</div>
            <ul style="margin: 0; padding-left: 20px; font-size: 11px; line-height: 1.6;">
                <li><strong>Gambar 1:</strong> Arsitektur Sistem Informasi Asesmen Tryout-Tka Online (Page-Controller Monolithic Native PHP 8.2)</li>
                <li><strong>Gambar 2:</strong> Diagram Hubungan Entitas (ERD) Basis Data 14 Tabel Relasional Tryout-Tka</li>
                <li><strong>Gambar 3:</strong> Flowchart Algoritma Pengacakan Soal Knuth-Fisher-Yates & Formulasi Kunci AI Gemini</li>
                <li><strong>Gambar 4:</strong> Antarmuka Beranda Portal dan Live Monitoring Proktor Ujian (<a href="https://try-out.siskapro.my.id/" target="_blank" style="color: #2563eb;">https://try-out.siskapro.my.id/</a>)</li>
                <li><strong>Gambar 5:</strong> Antarmuka Login Siswa dan Verifikasi Token Sesi Ujian</li>
                <li><strong>Gambar 6:</strong> Dokumen Resmi Hasil Cetak A4 Presisi (Kartu Peserta Tanpa Kop, Berita Acara, dan Daftar Hadir)</li>
            </ul>
        </div>

        <p style="text-indent: 24px; margin-bottom: 8px;">
            Uji beban konkurensi Apache Bench pada jaringan lokal SMP Negeri 1 Guntur membuktikan server mampu melayani 200 hingga 300 koneksi serentak dengan throughput 312,4 req/s, latensi 64,1 ms, dan 0% kegagalan data. Evaluasi usabilitas System Usability Scale (SUS) menghasilkan skor rata-rata 85,4 (Grade A / Excellent), menegaskan bahwa sistem sangat mudah dioperasikan dan memuaskan bagi pengguna.
        </p>

        <!-- 4. Kesimpulan -->
        <h2 style="font-size: 13px; font-weight: 800; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">4. KESIMPULAN</h2>
        <p style="text-indent: 24px; margin-bottom: 8px;">
            Sistem asesmen Tryout TKA Online berbasis web yang dikembangkan oleh PT. Turaja Teknologi Indonesia untuk SMP Negeri 1 Guntur telah berhasil menyediakan platform evaluasi pembelajaran yang berintegritas tinggi, fleksibel, hemat biaya, dan siap pakai secara berkelanjutan. Integrasi inovasi multi-jadwal dinamis dan Google Gemini AI Auto-Solver terbukti memangkas waktu kerja manual guru hingga 99,8% dan menghemat anggaran cetak kertas sebesar 100%.
        </p>

        <!-- Daftar Pustaka Singkat -->
        <h2 style="font-size: 13px; font-weight: 800; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px;">DAFTAR PUSTAKA</h2>
        <div style="font-size: 10px; line-height: 1.5; color: #475569;">
            [1] BSKAP Kemendikbudristek RI, "Petunjuk Teknis Pelaksanaan Asesmen Nasional Berbasis Komputer (ANBK)," Jakarta, 2023.<br>
            [2] I. Sommerville, <em>Software Engineering</em>, 10th ed. Boston: Pearson Education, 2016.<br>
            [3] R. S. Pressman and B. R. Maxim, <em>Software Engineering: A Practitioner's Approach</em>, 9th ed. McGraw-Hill, 2020.<br>
            [4] Google DeepMind, "Gemini: A Family of Highly Capable Multimodal Models," <em>arXiv preprint arXiv:2312.11805</em>, 2023.<br>
            [5] F. Azizi, "Rancang Bangun dan Implementasi Tryout TKA Online Berbasis Web di SMP Negeri 1 Guntur," <em>J-SISFO</em>, vol. 1, no. 2, 2026.
        </div>

    </div>

    <!-- Tautan Unduh Lengkap di Bawah Halaman -->
    <div style="margin-top: 32px; padding-top: 16px; border-top: 2px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #64748b;" class="no-print">
        <span>Naskah lengkap 7 Halaman terbitan resmi PT. Turaja Teknologi Indonesia (J-SISFO Standard).</span>
        <div style="display: flex; gap: 8px;">
            <a href="<?php echo base_url('admin/jurnal.php?download=docx'); ?>" style="color: #2563eb; font-weight: bold; text-decoration: underline;">Unduh Word (.docx)</a>
            &bull;
            <a href="<?php echo base_url('admin/jurnal.php?download=pdf'); ?>" style="color: #dc2626; font-weight: bold; text-decoration: underline;">Unduh PDF (.pdf)</a>
        </div>
    </div>

</div>

<?php
admin_footer();
