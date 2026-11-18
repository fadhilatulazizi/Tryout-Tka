<?php
/**
 * API Generate Kunci Jawaban Murni Menggunakan Google Gemini API
 * CBT Demak Pro
 */

// Naikkan limit eksekusi agar tidak terputus di tengah proses analisis
set_time_limit(300);
ini_set('max_execution_time', '300');

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Helper.php';
require_once dirname(__DIR__) . '/app/SyncEngine.php';

// Verifikasi sesi admin
if (empty($_SESSION['admin_logged_in'])) {
    json_response(['success' => false, 'message' => 'Sesi admin tidak valid atau telah berakhir.'], 401);
}

session_write_close();

$db = get_db();
$school = get_school_profile();

$paket = trim($_POST['paket'] ?? ($school['soal_hari1'] ?? 'conf32'));
if (!preg_match('/^conf[a-zA-Z0-9_-]+$/', $paket)) {
    json_response(['success' => false, 'message' => 'Format nama paket soal tidak valid.'], 400);
}

$totalSoal = SyncEngine::getPackageQuestionCount($paket);
$apiKey = trim($_POST['gemini_key'] ?? ($school['gemini_key'] ?? ''));
$requestedModel = trim($_POST['model'] ?? 'auto');

// 1. Simpan API Key jika diminta
if (!empty($_POST['save_key'])) {
    if (empty($apiKey)) {
        json_response(['success' => false, 'message' => 'API Key tidak boleh kosong.'], 400);
    }
    // Pastikan kolom gemini_key ada di tabel mapel
    $chkCol = mysqli_query($db, "SHOW COLUMNS FROM mapel LIKE 'gemini_key'");
    if ($chkCol && mysqli_num_rows($chkCol) === 0) {
        mysqli_query($db, "ALTER TABLE mapel ADD COLUMN gemini_key VARCHAR(255) NULL AFTER kode");
    }
    $stmtKey = mysqli_prepare($db, "UPDATE mapel SET gemini_key = ? WHERE id = 1");
    if ($stmtKey) {
        mysqli_stmt_bind_param($stmtKey, 's', $apiKey);
        mysqli_stmt_execute($stmtKey);
        mysqli_stmt_close($stmtKey);
    }
    json_response(['success' => true, 'message' => 'Google Gemini API Key berhasil disimpan ke database.']);
}

// 2. Validasi Ketersediaan API Key
if (empty($apiKey)) {
    json_response([
        'success' => false,
        'need_key' => true,
        'message' => 'Google Gemini API Key belum diisi. Silakan masukkan API Key Gemini Anda dari Google AI Studio (aistudio.google.com).'
    ], 400);
}

// Auto-persist API Key ke database jika belum tersimpan
if (!empty($apiKey) && ($school['gemini_key'] ?? '') !== $apiKey) {
    $stmtKey = mysqli_prepare($db, "UPDATE mapel SET gemini_key = ? WHERE id = 1");
    if ($stmtKey) {
        mysqli_stmt_bind_param($stmtKey, 's', $apiKey);
        mysqli_stmt_execute($stmtKey);
        mysqli_stmt_close($stmtKey);
    }
}

// Helper untuk membersihkan dan menstabilkan karakter UTF-8 teks soal
function cleanUtf8Text(string $text): string
{
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $converted = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $converted));
}

// 3. Baca dan Ekstrak Teks Butir Soal dari Berkas Paket
$dir = dirname(__DIR__) . "/soal/{$paket}";
if (!is_dir($dir)) {
    json_response(['success' => false, 'message' => "Folder paket soal {$paket} tidak ditemukan di server."], 404);
}

$extractedQuestions = [];
for ($q = 1; $q <= $totalSoal; $q++) {
    $f = "{$dir}/{$q}.php";
    if (!file_exists($f)) continue;

    $raw = file_get_contents($f);
    // Abaikan soal yang berlabel 'Lewati soal ini'
    if (stripos($raw, 'Lewati soal ini') !== false) {
        continue;
    }

    // Bersihkan skrip & tag PHP
    $cleanHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw);
    $cleanHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanHtml);
    $cleanHtml = preg_replace('/<\?php.*?\?>/is', '', $cleanHtml);

    // 1. Tipe Benar / Salah (True/False Table)
    if (strpos($cleanHtml, '1B') !== false || preg_match('/name=["\']jawaban1["\']/i', $cleanHtml)) {
        $parts = preg_split('/<form/i', $cleanHtml);
        $wacana = cleanUtf8Text(strip_tags($parts[0]));
        if (mb_strlen($wacana, 'UTF-8') > 600) {
            $wacana = mb_substr($wacana, 0, 600, 'UTF-8') . '...';
        }

        $statements = [];
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $parts[1] ?? '', $rows)) {
            $pIdx = 1;
            foreach ($rows[1] as $row) {
                if (strpos($row, 'jawaban') !== false && preg_match('/<td[^>]*>(.*?)<\/td>/is', $row, $td)) {
                    $txt = cleanUtf8Text(strip_tags($td[1]));
                    if ($txt !== '' && stripos($txt, 'Pernyataan') === false) {
                        $statements[] = "Pernyataan {$pIdx}: {$txt}";
                        $pIdx++;
                    }
                }
            }
        }
        $itemStr = implode(" | ", $statements);
        $extractedQuestions[$q] = "[SOAL {$q}] [TIPE: BENAR_SALAH]\nKonteks: {$wacana}\n{$itemStr}\nFormat Kunci: 1B/1S,2B/2S,3B/3S (contoh: 1B,2S,3B)";
    }
    // 2. Tipe Checkbox (Pilihan Ganda Kompleks Multijawaban)
    elseif (strpos($cleanHtml, 'checkbox') !== false && strpos($cleanHtml, 'jawaban[]') !== false) {
        $parts = preg_split('/<form/i', $cleanHtml);
        $wacana = cleanUtf8Text(strip_tags($parts[0]));
        if (mb_strlen($wacana, 'UTF-8') > 600) {
            $wacana = mb_substr($wacana, 0, 600, 'UTF-8') . '...';
        }

        $statements = [];
        if (preg_match_all('/<label[^>]*class=["\']container["\'][^>]*>(.*?)<\/label>/is', $parts[1] ?? '', $labels)) {
            foreach ($labels[1] as $idx => $lbl) {
                $num = $idx + 1;
                $txt = cleanUtf8Text(strip_tags($lbl));
                $statements[] = "Opsi {$num}: {$txt}";
            }
        }
        $itemStr = implode(" | ", $statements);
        $extractedQuestions[$q] = "[SOAL {$q}] [TIPE: CHECKBOX_MULTIJAWABAN]\nKonteks: {$wacana}\n{$itemStr}\nFormat Kunci: Nomor opsi yang benar dipisah koma tanpa spasi (contoh: 1,3 atau 2,4)";
    }
    // 3. Tipe Isian Singkat / Uraian Angka
    elseif (preg_match('/<input[^>]+type=["\']text["\'][^>]+name=["\']jawaban["\']/i', $cleanHtml) || stripos($cleanHtml, '<textarea') !== false) {
        $parts = preg_split('/<form/i', $cleanHtml);
        $wacana = cleanUtf8Text(strip_tags($parts[0]));
        if (mb_strlen($wacana, 'UTF-8') > 600) {
            $wacana = mb_substr($wacana, 0, 600, 'UTF-8') . '...';
        }
        $extractedQuestions[$q] = "[SOAL {$q}] [TIPE: ISIAN_SINGKAT]\nPertanyaan/Soal: {$wacana}\nFormat Kunci: Tuliskan jawaban singkat/angka yang paling tepat tanpa kata pengantar.";
    }
    // 4. Tipe PG Biasa (Single Choice)
    else {
        $choiceStr = '';
        if (preg_match('/<ol[^>]*>(.*?)<\/ol>/is', $cleanHtml, $ol)) {
            if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $ol[1], $lis)) {
                $letters = ['A', 'B', 'C', 'D', 'E'];
                foreach ($lis[1] as $idx => $li) {
                    $choiceStr .= " " . ($letters[$idx] ?? '') . "." . cleanUtf8Text(strip_tags($li));
                }
            }
        }

        $cleanText = cleanUtf8Text(strip_tags($cleanHtml));
        if (mb_strlen($cleanText, 'UTF-8') > 600) {
            $cleanText = mb_substr($cleanText, 0, 600, 'UTF-8') . '...';
        }
        $extractedQuestions[$q] = "[SOAL {$q}] [TIPE: PG_BIASA]\nPertanyaan: {$cleanText}" . ($choiceStr ? " | Opsi: {$choiceStr}" : "") . "\nFormat Kunci: Satu huruf kapital (A, B, C, D, atau E)";
    }
}

if (empty($extractedQuestions)) {
    json_response(['success' => false, 'message' => 'Tidak ada butir soal yang dapat dibaca dari berkas paket ini.'], 400);
}

/**
 * Memeriksa apakah error respons Google Gemini bersifat sementara (transient) sehingga aman di-fallback ke model lain
 */
function isTransientGeminiError(int $httpCode, string $errorMsg): bool
{
    if (in_array($httpCode, [429, 500, 502, 503, 504], true)) {
        return true;
    }

    $lower = strtolower($errorMsg);
    $keywords = [
        'high demand',
        'spikes in demand',
        'try again later',
        'resource_exhausted',
        'quota exceeded',
        'rate limit',
        'overloaded',
        'temporarily unavailable',
        'capacity',
        'service unavailable',
        'deadline exceeded'
    ];
    foreach ($keywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Eksekusi Panggilan ke Google Gemini API dengan Multi-Model Fallback & Smart Exponential Backoff
 */
function callGeminiGenerateApi(string $promptText, string $apiKey, ?string $preferredModel = null): array
{
    // Daftar model resmi Google Gemini sesuai alokasi kuota akun pengguna:
    // Prioritas utama adalah gemini-3.5-flash-lite dan gemini-3.1-flash-lite yang memiliki kuota besar (500 RPD, 15 RPM)
    $defaultModels = [
        'gemini-3.5-flash-lite',
        'gemini-3.8-flash',
        'gemini-3.1-flash-lite',
        'gemini-2.5-flash-lite',
        'gemini-2.5-flash',
        'gemini-3.6-flash'
    ];

    if ($preferredModel && in_array($preferredModel, $defaultModels, true)) {
        $candidateModels = array_unique(array_merge([$preferredModel], $defaultModels));
    } else {
        $candidateModels = $defaultModels;
    }

    $maxAttempts = 3; // Maksimal 3 siklus percobaan
    $lastError = '';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        foreach ($candidateModels as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

            $postData = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $promptText]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json'
                ]
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if (empty($postData) || $postData === 'false') {
                $lastError = "Gagal menyusun payload JSON request: " . json_last_error_msg();
                continue;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $apiKey
                ],
                CURLOPT_TIMEOUT => 90,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = "Koneksi ke {$model} gagal: {$curlError}";
                continue; // Coba model berikutnya jika terjadi error socket/timeout
            }

            $geminiData = json_decode($responseBody, true);
            $errMsg = $geminiData['error']['message'] ?? '';

            // Jika error disebabkan API Key salah / tidak ada izin (HTTP 400 / 403), hentikan loop
            if (($httpCode === 400 || $httpCode === 403) && (stripos($errMsg, 'API key') !== false || stripos($errMsg, 'PERMISSION_DENIED') !== false)) {
                return [
                    'success' => false,
                    'message' => "Google Gemini API Key tidak valid: {$errMsg}"
                ];
            }

            // Jika error sementara (429, 503, high demand, overloaded, dll.), fallback ke model berikutnya
            if (isTransientGeminiError($httpCode, $errMsg) || $httpCode === 404) {
                $lastError = "Model {$model} sedang mengalami lonjakan antrean (" . ($errMsg ?: "HTTP {$httpCode}") . "). Beralih ke model cadangan...";
                continue;
            }

            if ($httpCode !== 200) {
                $lastError = "Model {$model} merespon status {$httpCode}: " . ($errMsg ?: substr($responseBody, 0, 150));
                continue;
            }

            // Berhasil mendapat respons HTTP 200
            $generatedText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $generatedText = trim($generatedText);
            $generatedText = preg_replace('/^```(?:json)?\s*/i', '', $generatedText);
            $generatedText = preg_replace('/\s*```$/', '', $generatedText);

            $parsed = json_decode($generatedText, true);
            if (is_array($parsed) && !empty($parsed)) {
                return [
                    'success' => true,
                    'model' => $model,
                    'data' => $parsed
                ];
            }

            $lastError = "Respons model {$model} tidak dapat di-parse sebagai format kunci JSON.";
        }

        // Jika seluruh model dalam daftar sedang padat pada percobaan ini, jeda sejenak (backoff)
        if ($attempt < $maxAttempts) {
            sleep(2 * $attempt);
        }
    }

    return [
        'success' => false,
        'message' => "Seluruh model Google Gemini saat ini sedang sibuk (high demand). Silakan tunggu beberapa detik dan coba klik kembali. (Detail: {$lastError})"
    ];
}

// 4. Pembagian Batch (Chunking) untuk Mencegah Timeout pada Paket Soal Besar
// Maksimal 20 butir soal per batch agar respon Gemini kembali cepat (<15 detik)
$chunks = array_chunk($extractedQuestions, 20, true);
$allParsedKeys = [];
$modelsUsed = [];
$activePreferredModel = ($requestedModel !== 'auto') ? $requestedModel : null;

foreach ($chunks as $chunk) {
    $promptText = "Anda adalah pakar pembuat kunci jawaban asesmen/ujian CBT resmi tingkat SMP/MTs (standar ANBK Pusmendik Kemendikbud).\n" .
        "Tugas Anda: Menganalisis butir-butir soal di bawah dan menentukan kunci jawaban yang paling tepat untuk setiap nomor soal.\n\n" .
        "ATURAN FORMAT KUNCI JAWABAN (SANGAT KETAT):\n" .
        "1. Untuk [TIPE: PG_BIASA]: Berikan HANYA SATU huruf kapital opsi yang benar: \"A\", \"B\", \"C\", \"D\", atau \"E\".\n" .
        "2. Untuk [TIPE: BENAR_SALAH]: Tentukan status kebenaran tiap pernyataan (B untuk Benar, S untuk Salah) berformat nomor urut diikuti huruf status dipisah koma tanpa spasi. Contoh: \"1B,2S,3B\" atau \"1S,2B,3B\" atau \"1B,2B,3S\".\n" .
        "3. Untuk [TIPE: CHECKBOX_MULTIJAWABAN]: Tentukan opsi pernyataan yang benar/sesuai, urutkan ascending dan pisahkan dengan koma tanpa spasi. Contoh: \"1,3\" atau \"2,4\" atau \"1,2,3\".\n" .
        "4. Untuk [TIPE: ISIAN_SINGKAT]: Berikan jawaban singkat berupa angka atau kata kunci tanpa kalimat pengantar.\n\n" .
        "PENTING: Berikan jawaban HANYA berupa JSON murni tanpa pembungkus markdown (tanpa ```json dan ```), berformat objek pasangan nomor dan string kunci jawaban:\n" .
        '{"1": "C", "2": "A", "17": "1B,2S,3B", "24": "1,3"}' . "\n\n" .
        "DAFTAR SOAL:\n\n" . implode("\n\n", $chunk);

    $apiRes = callGeminiGenerateApi($promptText, $apiKey, $activePreferredModel);
    if (!$apiRes['success']) {
        json_response(['success' => false, 'message' => $apiRes['message']], 500);
    }

    $activePreferredModel = $apiRes['model'];
    $modelsUsed[$apiRes['model']] = true;
    foreach ($apiRes['data'] as $k => $v) {
        $allParsedKeys[$k] = $v;
    }
}

if (empty($allParsedKeys)) {
    json_response(['success' => false, 'message' => 'Google Gemini tidak menghasilkan data kunci jawaban yang valid.'], 500);
}

// 5. Validasi & Format Kunci Jawaban
$kunciHasil = [];
for ($i = 1; $i <= $totalSoal; $i++) {
    $rawVal = trim((string) ($allParsedKeys[$i] ?? ($allParsedKeys[(string)$i] ?? '')));
    if ($rawVal === '') continue;

    $val = strtoupper(preg_replace('/\s+/', '', $rawVal));

    // 1. Single letter: A, B, C, D, E
    if (in_array($val, ['A', 'B', 'C', 'D', 'E'], true)) {
        $kunciHasil[$i] = $val;
    }
    // 2. Benar/Salah (contoh: 1B,2S,3B)
    elseif (preg_match('/^([1-5][BS],)+[1-5][BS]$/i', $val)) {
        $kunciHasil[$i] = $val;
    }
    // 3. Checkbox multijawaban (contoh: 1,3 atau 2,4)
    elseif (preg_match('/^(\d+,)+\d+$/', $val)) {
        $parts = explode(',', $val);
        sort($parts, SORT_NUMERIC);
        $kunciHasil[$i] = implode(',', $parts);
    }
    // 4. Checkbox single digit (contoh: 2)
    elseif (preg_match('/^[1-5]$/', $val)) {
        $kunciHasil[$i] = $val;
    }
    // 5. Isian singkat / teks / angka (maksimal 30 karakter)
    elseif (strlen($val) <= 30 && preg_match('/^[A-Z0-9,\.\-\s]+$/i', $rawVal)) {
        $kunciHasil[$i] = strtoupper(trim($rawVal));
    }
}

// Rangkai string kunci ringkas
$kunciStr = '';
for ($i = 1; $i <= $totalSoal; $i++) {
    if (isset($kunciHasil[$i])) {
        $kunciStr .= ($kunciStr !== '' ? ',' : '') . $kunciHasil[$i];
    }
}

$modelLabel = implode(', ', array_keys($modelsUsed));

json_response([
    'success' => true,
    'paket' => $paket,
    'total_soal' => count($kunciHasil),
    'total_soal_aktif' => $totalSoal,
    'kunci' => $kunciHasil,
    'kunci_string' => $kunciStr,
    'source' => "Google Gemini ({$modelLabel})",
    'message' => "Kunci jawaban berhasil di-generate secara cepat dan akurat menggunakan Google Gemini ({$modelLabel})."
]);
