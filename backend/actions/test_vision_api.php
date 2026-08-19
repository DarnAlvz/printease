<?php
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/gcash_ocr.php";

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<title>OCR.space Test</title>
<style>
body { font-family: monospace; max-width: 900px; margin: 20px auto; padding: 0 20px; background: #1a1a2e; color: #e0e0e0; }
h1 { color: #00d4ff; }
h2 { color: #ff6b6b; margin-top: 20px; }
.ok { color: #4caf50; }
.fail { color: #ff6b6b; }
.log { background: #0d1117; padding: 12px; border-radius: 8px; border: 1px solid #30363d; margin: 10px 0; white-space: pre-wrap; word-break: break-all; font-size: 13px; line-height: 1.6; }
.result { background: #1a2332; padding: 12px; border-radius: 8px; border: 1px solid #1e3a5f; margin: 10px 0; }
.label { color: #8b949e; }
.value { color: #58a6ff; font-weight: bold; }
hr { border: 1px solid #30363d; margin: 20px 0; }
</style>
</head>
<body>
<h1>OCR.space API Diagnostic</h1>

<?php
$api_key = trim((string) getenv('OCR_SPACE_API_KEY'));
echo '<p><span class="label">OCR.space API Key:</span> <span class="' . ($api_key !== '' ? 'ok' : 'fail') . '">' . ($api_key !== '' ? 'LOADED (ends ...' . substr($api_key, -4) . ')' : 'EMPTY - NOT LOADED') . '</span></p>';
echo '<p><span class="label">curl:</span> <span class="' . (function_exists('curl_init') ? 'ok' : 'fail') . '">' . (function_exists('curl_init') ? 'Available' : 'NOT AVAILABLE') . '</span></p>';

$proof_dir = ocrUploadsPath('payment_proofs');
echo '<p><span class="label">Proof directory:</span> ' . $proof_dir . '</p>';
echo '<p><span class="label">Directory exists:</span> <span class="' . (is_dir($proof_dir) ? 'ok' : 'fail') . '">' . (is_dir($proof_dir) ? 'YES' : 'NO') . '</span></p>';

$image_path = trim((string) ($_GET['image'] ?? ''));

if ($image_path === '' && is_dir($proof_dir)) {
    $files = glob($proof_dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
    if (!empty($files)) {
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        $image_path = $files[0];
        $relative = str_replace(realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR, '', $image_path);
        $relative = str_replace('\\', '/', $relative);
        echo '<p><span class="label">Testing with:</span> <span class="value">' . $relative . '</span> (' . round(filesize($image_path) / 1024) . ' KB)</p>';
    }
}

if (empty($image_path)) {
    echo '<div class="fail">No payment proof images found in ' . $proof_dir . '</div>';
    exit;
}

if (!is_file($image_path)) {
    $full_path = realpath(__DIR__ . '/../../' . $image_path);
    if ($full_path && is_file($full_path)) {
        $image_path = $full_path;
    } else {
        echo '<div class="fail">Image not found: ' . htmlspecialchars($image_path) . '</div>';
        exit;
    }
}

echo '<hr>';

echo '<h2>Direct OCR.space API Test</h2>';

$image_data = @file_get_contents($image_path);
if ($image_data === false) {
    echo '<div class="fail">Could not read image file: ' . htmlspecialchars($image_path) . '</div>';
    exit;
}

$mime = @mime_content_type($image_path);
if (!$mime) { $mime = 'image/jpeg'; }
$b64 = base64_encode($image_data);
$data_uri = 'data:' . $mime . ';base64,' . $b64;

$url = 'https://api.ocr.space/parse/image';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'apikey'            => $api_key,
        'base64Image'       => $data_uri,
        'language'          => 'eng',
        'isOverlayRequired' => 'false',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo '<h2>HTTP Response</h2>';
echo '<div class="result">';
echo '<p><span class="label">HTTP Status:</span> <span class="' . ($http_code === 200 ? 'ok' : 'fail') . '">' . $http_code . '</span></p>';
if ($curl_error) {
    echo '<p><span class="label">curl Error:</span> <span class="fail">' . htmlspecialchars($curl_error) . '</span></p>';
}
echo '</div>';

if ($response === false) {
    echo '<div class="fail">curl_exec returned false. Error: ' . htmlspecialchars($curl_error) . '</div>';
    exit;
}

if ($http_code !== 200) {
    echo '<h2>ERROR RESPONSE</h2>';
    echo '<div class="log">' . htmlspecialchars($response) . '</div>';
    exit;
}

$data = json_decode($response, true);

echo '<h2>Raw API Response (first 2000 chars)</h2>';
echo '<div class="log">' . htmlspecialchars(substr($response, 0, 2000)) . '</div>';

if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing'] === true) {
    $err_msg = $data['ErrorMessage'][0] ?? $data['ErrorDetails'][0] ?? 'unknown';
    echo '<h2 class="fail">OCR.space ERROR</h2>';
    echo '<div class="result">';
    echo '<p><span class="label">Message:</span> <span class="fail">' . htmlspecialchars($err_msg) . '</span></p>';
    echo '</div>';
    exit;
}

$exit_code = $data['OCRExitCode'] ?? 0;
if ((int) $exit_code !== 1) {
    echo '<h2 class="fail">Non-success exit code: ' . $exit_code . '</h2>';
    exit;
}

$parsed_results = $data['ParsedResults'] ?? [];
$text = '';
if (!empty($parsed_results)) {
    $text = $parsed_results[0]['ParsedText'] ?? '';
}

echo '<h2>Detected Text</h2>';
if (is_string($text) && trim($text) !== '') {
    echo '<div class="log ok">' . htmlspecialchars($text) . '</div>';

    $ref = detectGcashReferenceFromText($text);
    $date = detectGcashPaymentDateFromText($text);
    $status = gcashOcrStatus($ref, $date);

    echo '<h2>Pattern Matching Results</h2>';
    echo '<div class="result">';
    echo '<p><span class="label">Reference Number:</span> <span class="' . ($ref ? 'ok' : 'fail') . '">' . htmlspecialchars($ref ?: 'NOT FOUND') . '</span></p>';
    echo '<p><span class="label">Payment Date:</span> <span class="' . ($date ? 'ok' : 'fail') . '">' . htmlspecialchars($date ?: 'NOT FOUND') . '</span></p>';
    echo '<p><span class="label">OCR Status:</span> <span class="value">' . htmlspecialchars(ucwords(str_replace('_', ' ', $status))) . '</span></p>';
    echo '</div>';
} else {
    echo '<div class="fail">No text detected by OCR.space</div>';
}

echo '<h2>runReceiptOcr() Function Test</h2>';
ocrLog('--- Testing via runReceiptOcr ---');
$result = runReceiptOcr($image_path);

echo '<div class="result">';
echo '<p><span class="label">Result:</span> <span class="' . ($result ? 'ok' : 'fail') . '">' . ($result ? 'SUCCESS (' . strlen($result) . ' bytes)' : 'EMPTY') . '</span></p>';
if ($result) {
    $ref2 = detectGcashReferenceFromText($result);
    $date2 = detectGcashPaymentDateFromText($result);
    echo '<p><span class="label">Reference:</span> <span class="' . ($ref2 ? 'ok' : 'fail') . '">' . htmlspecialchars($ref2 ?: 'NOT FOUND') . '</span></p>';
    echo '<p><span class="label">Date:</span> <span class="' . ($date2 ? 'ok' : 'fail') . '">' . htmlspecialchars($date2 ?: 'NOT FOUND') . '</span></p>';
}
echo '</div>';

echo '<h2>Debug Log</h2>';
echo '<div class="log">';
if (!empty($ocr_debug_log)) {
    foreach ($ocr_debug_log as $entry) {
        echo htmlspecialchars($entry) . "\n";
    }
} else {
    echo "(no entries)\n";
}
echo '</div>';
?>
</body>
</html>
