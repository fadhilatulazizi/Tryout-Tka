<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

Auth::requireAdmin();
$db = get_db();
$school = get_school_profile();

$allJadwal = get_all_jadwal();
$activeJadwal = get_active_jadwal();

// Menentukan Hari / Jadwal yang dipilih
$p = isset($_GET['p']) ? preg_replace('/[^0-9a-zA-Z_-]/', '', (string)$_GET['p']) : '';
if ($p === '') {
    $p = !empty($activeJadwal['hari']) ? (string)$activeJadwal['hari'] : '1';
}

$curJadwal = get_jadwal_by_hari($p);
$table = $p === '1' ? 'pg_tb' : 'pg_tb_' . $p;
ensure_jawaban_table($table);

if ($curJadwal) {
    $defaultPaket = !empty($curJadwal['folder_soal']) ? $curJadwal['folder_soal'] : ($p === '1' ? ($school['soal_hari1'] ?? 'conf31') : 'conf32');
    $mapelTitle = !empty($curJadwal['mapel']) ? $curJadwal['mapel'] : ($p === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : 'Mata Pelajaran');
} else {
    $defaultPaket = $p === '1' ? ($school['soal_hari1'] ?? 'conf31') : ($school['soal_hari2'] ?? 'conf32');
    $mapelTitle = $p === '1' ? ($school['mapel_hari1'] ?? 'Matematika') : ($school['mapel_hari2'] ?? 'Bahasa Indonesia');
}
$totalSoalAktif = SyncEngine::getPackageQuestionCount($defaultPaket);

// Hitung total peserta yang mengumpulkan
$qTotal = mysqli_query($db, "SELECT COUNT(*) as c FROM {$table}");
$totalPeserta = (int) (mysqli_fetch_assoc($qTotal)['c'] ?? 0);

// Hitung sebaran jawaban A, B, C, D, E per butir soal aktif
$stats = [];
for ($q = 1; $q <= $totalSoalAktif; $q++) {
    $col = 'j' . $q;
    $res = mysqli_query($db, "SELECT UPPER(TRIM({$col})) as ans, COUNT(*) as cnt FROM {$table} WHERE {$col} != '' GROUP BY ans");
    $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
    $totalAnswered = 0;
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $ans = $row['ans'];
            if (isset($counts[$ans])) {
                $counts[$ans] = (int) $row['cnt'];
                $totalAnswered += (int) $row['cnt'];
            }
        }
    }

    // Cari modus / jawaban dominan
    arsort($counts);
    $dominant = key($counts);
    $maxCount = current($counts);
    $dominantPct = $totalPeserta > 0 ? round(($maxCount / $totalPeserta) * 100, 1) : 0;

    $stats[$q] = [
        'counts' => [
            'A' => $counts['A'] ?? 0,
            'B' => $counts['B'] ?? 0,
            'C' => $counts['C'] ?? 0,
            'D' => $counts['D'] ?? 0,
            'E' => $counts['E'] ?? 0,
        ],
        'total_answered' => $totalAnswered,
        'unanswered' => max(0, $totalPeserta - $totalAnswered),
        'dominant' => $dominant,
        'dominant_pct' => $dominantPct
    ];
}

// Ekspor Excel
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $filename = "Analisis_Soal_Hari_" . $p . "_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}");
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body>";
    echo "<h3>" . htmlspecialchars($mapelTitle, ENT_QUOTES, 'UTF-8') . "</h3>";
    echo "<table border='1'><tr><th>Nomor Soal</th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>Kosong</th><th>Total Peserta</th><th>Pilihan Terbanyak</th><th>% Dominan</th></tr>";
    for ($q = 1; $q <= $totalSoalAktif; $q++) {
        $st = $stats[$q];
        echo "<tr>";
        echo "<td>Soal " . $q . "</td>";
        echo "<td>" . $st['counts']['A'] . "</td>";
        echo "<td>" . $st['counts']['B'] . "</td>";
        echo "<td>" . $st['counts']['C'] . "</td>";
        echo "<td>" . $st['counts']['D'] . "</td>";
        echo "<td>" . $st['counts']['E'] . "</td>";
        echo "<td>" . $st['unanswered'] . "</td>";
        echo "<td>" . $totalPeserta . "</td>";
        echo "<td>" . $st['dominant'] . " (" . $st['counts'][$st['dominant']] . ")</td>";
        echo "<td>" . $st['dominant_pct'] . "%</td>";
        echo "</tr>";
    }
    echo "</table></body></html>";
    exit;
}

require_once __DIR__ . '/layout.php';
admin_header('Analisis Butir Soal', 'analisis');
?>

<div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <?php if (!empty($allJadwal)): ?>
        <?php foreach ($allJadwal as $j): ?>
            <?php $jHari = (string)$j['hari']; ?>
            <a href="<?php echo base_url('admin/analisis.php?p=' . urlencode($jHari)); ?>" class="btn <?php echo (string)$p === $jHari ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                <span><?php echo e($j['nama_hari'] ?: ('Hari ke-' . $jHari)); ?>: <strong><?php echo e($j['mapel']); ?></strong></span>
                <?php if (!empty($j['aktif'])): ?>
                    <span style="background: #10b981; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 9999px;">AKTIF</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <a href="<?php echo base_url('admin/analisis.php?p=1'); ?>" class="btn <?php echo $p === '1' ? 'btn-primary' : 'btn-secondary'; ?>">
            Hari ke-1: Matematika
        </a>
        <a href="<?php echo base_url('admin/analisis.php?p=2'); ?>" class="btn <?php echo $p === '2' ? 'btn-primary' : 'btn-secondary'; ?>">
            Hari ke-2: Bahasa Indonesia
        </a>
    <?php endif; ?>
</div>

<div class="panel-card">
    <div class="panel-header">
        <div>
            <h2><?php echo e($mapelTitle); ?></h2>
            <p>Distribusi pilihan jawaban siswa dari total <strong><?php echo $totalPeserta; ?> lembar jawaban</strong> masuk.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo base_url('admin/analisis.php?p=' . $p . '&export=1'); ?>" class="btn btn-success">📥 Ekspor ke Excel (.xls)</a>
            <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Cetak</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Soal</th>
                    <th style="text-align: center;">Pilihan A</th>
                    <th style="text-align: center;">Pilihan B</th>
                    <th style="text-align: center;">Pilihan C</th>
                    <th style="text-align: center;">Pilihan D</th>
                    <th style="text-align: center;">Pilihan E</th>
                    <th style="text-align: center;">Kosong</th>
                    <th style="text-align: center;">Dominan</th>
                    <th style="text-align: center;">Distribusi Grafis</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($q = 1; $q <= $totalSoalAktif; $q++): $st = $stats[$q] ?? ['counts' => ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0], 'unanswered' => 0, 'dominant' => '-', 'dominant_pct' => 0]; ?>
                    <tr>
                        <td><strong>Soal <?php echo $q; ?></strong></td>
                        <td style="text-align: center;"><?php echo $st['counts']['A']; ?></td>
                        <td style="text-align: center;"><?php echo $st['counts']['B']; ?></td>
                        <td style="text-align: center;"><?php echo $st['counts']['C']; ?></td>
                        <td style="text-align: center;"><?php echo $st['counts']['D']; ?></td>
                        <td style="text-align: center;"><?php echo $st['counts']['E']; ?></td>
                        <td style="text-align: center; color: #94a3b8;"><?php echo $st['unanswered']; ?></td>
                        <td style="text-align: center;">
                            <span class="badge-mono" style="color: #0284c7;"><?php echo $st['dominant']; ?> (<?php echo $st['dominant_pct']; ?>%)</span>
                        </td>
                        <td style="min-width: 140px;">
                            <div style="display: flex; height: 12px; border-radius: 4px; overflow: hidden; background: #e2e8f0;">
                                <?php 
                                    $colors = ['A' => '#38bdf8', 'B' => '#34d399', 'C' => '#fbbf24', 'D' => '#f87171', 'E' => '#a78bfa'];
                                    foreach (['A', 'B', 'C', 'D', 'E'] as $opt):
                                        $cnt = $st['counts'][$opt];
                                        $pct = $totalPeserta > 0 ? round(($cnt / $totalPeserta) * 100, 1) : 0;
                                        if ($pct > 0):
                                ?>
                                    <div style="width: <?php echo $pct; ?>%; background: <?php echo $colors[$opt]; ?>;" title="<?php echo $opt; ?>: <?php echo $cnt; ?> (<?php echo $pct; ?>%)"></div>
                                <?php endif; endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<?php admin_footer(); ?>
