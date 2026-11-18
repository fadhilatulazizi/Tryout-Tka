<?php
/**
 * Engine Sinkronisasi Paket Soal dari Server Eksternal
 * Sumber Resmi: https://smp1bonang.sch.id/demak/
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

class SyncEngine
{
    public const BASE_REMOTE_URL = 'https://smp1bonang.sch.id/demak/';
    public const ALLOWED_HOST = 'smp1bonang.sch.id';

    /**
     * Dapatkan daftar folder paket soal yang tersedia di server lokal
     */
    public static function getLocalPackages(): array
    {
        $soalDir = dirname(__DIR__) . '/soal';
        if (!is_dir($soalDir)) {
            return [];
        }

        $packages = [];
        $items = scandir($soalDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (preg_match('/^conf[a-zA-Z0-9_-]+$/', $item) && is_dir($soalDir . '/' . $item)) {
                $totalPhp = count(glob($soalDir . '/' . $item . '/*.php'));
                $realCount = self::getPackageQuestionCount($item);
                $packages[] = [
                    'folder' => $item,
                    'total_soal' => $realCount,
                    'total_files' => $totalPhp,
                    'path' => $soalDir . '/' . $item
                ];
            }
        }

        usort($packages, fn($a, $b) => strcmp($a['folder'], $b['folder']));
        return $packages;
    }

    /**
     * Hitung jumlah butir soal dalam paket (fleksibel: 30, 40, 50, s.d. 100 butir sesuai jumlah berkas)
     */
    public static function getPackageQuestionCount(string $folder): int
    {
        $dir = dirname(__DIR__) . '/soal/' . $folder;
        if (!is_dir($dir)) return 50;

        $count = 0;
        for ($i = 1; $i <= 100; $i++) {
            if (file_exists($dir . '/' . $i . '.php')) {
                $count = $i;
            } else {
                break;
            }
        }
        return $count > 0 ? $count : 50;
    }

    /**
     * Periksa metadata file ZIP di server luar (HEAD Request)
     */
    public static function checkRemoteMetadata(string $folder): array
    {
        if (!preg_match('/^conf[a-zA-Z0-9_-]+$/', $folder)) {
            throw new InvalidArgumentException('Nama folder paket soal tidak valid.');
        }

        $url = self::BASE_REMOTE_URL . $folder . '.zip';
        $ch = curl_init($url);
        if (!$ch) {
            throw new RuntimeException('cURL tidak dapat diinisialisasi.');
        }

        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'CBT-Demak-Sync/4.0',
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
                $len = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            }
        ]);

        $exec = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if (!$exec || $httpCode !== 200) {
            throw new RuntimeException("Server luar tidak merespons file {$folder}.zip (HTTP {$httpCode}).");
        }

        $size = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;
        $lastModified = $headers['last-modified'] ?? '-';
        $etag = trim($headers['etag'] ?? '', '"');

        return [
            'folder' => $folder,
            'url' => $url,
            'size' => $size,
            'size_formatted' => round($size / 1024, 2) . ' KB',
            'last_modified' => $lastModified,
            'etag' => $etag,
            'status' => 'available'
        ];
    }

    /**
     * Unduh dan ekstrak paket soal ZIP ke folder soal/{folder}/
     */
    public static function downloadAndExtract(string $folder): array
    {
        if (!preg_match('/^conf[a-zA-Z0-9_-]+$/', $folder)) {
            throw new InvalidArgumentException('Folder paket soal tidak valid.');
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif di server.');
        }

        $meta = self::checkRemoteMetadata($folder);
        $targetDir = dirname(__DIR__) . '/soal/' . $folder;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'soal_zip_');
        $fp = fopen($tempZip, 'w+');
        if (!$fp) {
            throw new RuntimeException('Gagal membuat file sementara untuk unduhan.');
        }

        $ch = curl_init($meta['url']);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'CBT-Demak-Sync/4.0'
        ]);

        $exec = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$exec || $httpCode !== 200) {
            @unlink($tempZip);
            throw new RuntimeException("Gagal mengunduh file ZIP dari server luar (HTTP {$httpCode}).");
        }

        $zip = new ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            throw new RuntimeException('File yang diunduh bukan format ZIP yang valid.');
        }

        $extractedCount = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr($name, -1) === '/') continue;

            // Strip subfolder if zip wrapped in folder
            $cleanName = basename($name);

            // Validasi nama file hanya 1.php - 50.php atau gambar
            $isPhpSoal = preg_match('/^(?:[1-9]|[1-4][0-9]|50)\.php$/', $cleanName);
            $isImage = preg_match('/\.(?:png|jpe?g|gif|webp)$/i', $cleanName);

            if ($isPhpSoal || $isImage) {
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($targetDir . '/' . $cleanName, $content);
                    $extractedCount++;
                }
            }
        }

        $zip->close();
        @unlink($tempZip);

        return [
            'folder' => $folder,
            'files_extracted' => $extractedCount,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}
