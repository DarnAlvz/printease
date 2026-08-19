<?php

global $ocr_debug_log;
$ocr_debug_log = [];

function ocrLog($message)
{
    global $ocr_debug_log;
    $entry = date('H:i:s') . ' ' . $message;
    $ocr_debug_log[] = $entry;
    error_log('[OCR] ' . $message);
}

function normalizeGcashReference($value)
{
    return preg_replace('/\D+/', '', (string) $value);
}

function gcashReferenceCaptureToNumber($value)
{
    $value = preg_replace('/\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\b.*$/i', '', (string) $value);
    $value = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b.*$/', '', $value);
    $value = preg_replace('/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\b.*$/', '', $value);
    return normalizeGcashReference($value);
}

function detectGcashReferenceFromText($text)
{
    $patterns = [
        '/(?:Reference\s*(?:No\.?|Number)?|Ref\.?\s*No\.?|Transaction\s*(?:No\.?|ID))\s*[:#\-]?\s*([0-9][0-9\s\-]{5,}(?=\s*(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|$)))/im',
        '/(?:Reference\s*(?:No\.?|Number)?|Ref\.?\s*No\.?|Transaction\s*(?:No\.?|ID))\s*[:#\-]?\s*([0-9][0-9\s\-]{5,})/i',
        '/(?:Ref(?:erence)?|Transaction)\s*[:#\-]?\s*([0-9][0-9\s\-]{5,})/i',
        '/\b(\d{12})\b/',
        '/\b(\d{10,16})\b/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, (string) $text, $matches)) {
            $reference = gcashReferenceCaptureToNumber($matches[1] ?? '');
            if (strlen($reference) >= 6 && strlen($reference) <= 100) {
                return $reference;
            }
        }
    }

    return null;
}

function detectGcashPaymentDateFromText($text)
{
    $text = (string) $text;
    $month_names = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';
    $date_labels = '(?:Date|Paid\s*on|Sent\s*on|Transaction\s*date)';
    $patterns = [
        '/' . $date_labels . '\s*[:#\-]?\s*(' . $month_names . '\s+\d{1,2},?\s+\d{4})/i',
        '/' . $date_labels . '\s*[:#\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
        '/' . $date_labels . '\s*[:#\-]?\s*(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})/i',
        '/\b(' . $month_names . '\s+\d{1,2},?\s+\d{4})\b/i',
        '/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/',
        '/\b(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})\b/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $timestamp = strtotime($matches[1] ?? '');
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }
    }

    return null;
}

function gcashOcrStatus($reference_number, $payment_date)
{
    $has_reference = trim((string) $reference_number) !== '';
    $has_date = trim((string) $payment_date) !== '';

    if ($has_reference && $has_date) {
        return 'detected';
    }

    if ($has_reference || $has_date) {
        return 'partial';
    }

    return 'not_detected';
}

function runReceiptOcr($image_path)
{
    $allowed_image_path = resolveAllowedOcrImagePath($image_path);
    if ($allowed_image_path === null) {
        ocrLog('Image path validation failed: ' . $image_path);
        return '';
    }

    $ocr_text = runOcrSpaceApi($allowed_image_path);
    if ($ocr_text !== '') {
        ocrLog('OCR.space success (' . strlen($ocr_text) . ' bytes): ' . substr($ocr_text, 0, 500));
        return $ocr_text;
    }

    ocrLog('OCR.space returned no text for: ' . basename((string) $image_path));
    return '';
}

function runOcrSpaceApi($image_path)
{
    $api_key = trim((string) getenv('OCR_SPACE_API_KEY'));
    if ($api_key === '') {
        ocrLog('OCR.space API key is empty');
        return '';
    }

    if (!function_exists('curl_init')) {
        ocrLog('curl extension not available for OCR.space');
        return '';
    }

    $image_data = @file_get_contents($image_path);
    if ($image_data === false) {
        ocrLog('OCR.space: failed to read image: ' . basename((string) $image_path));
        return '';
    }

    $mime = @mime_content_type($image_path);
    if (!$mime) {
        $mime = 'image/jpeg';
    }
    $b64 = base64_encode($image_data);
    $data_uri = 'data:' . $mime . ';base64,' . $b64;

    $url = 'https://api.ocr.space/parse/image';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'apikey'          => $api_key,
            'base64Image'     => $data_uri,
            'language'        => 'eng',
            'isOverlayRequired' => 'false',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        ocrLog('OCR.space curl failed: ' . $curl_error);
        return '';
    }

    if ($http_code !== 200) {
        ocrLog('OCR.space HTTP ' . $http_code . ': ' . substr((string) $response, 0, 500));
        return '';
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        ocrLog('OCR.space: failed to decode JSON response');
        return '';
    }

    if (isset($data['IsErroredOnProcessing']) && $data['IsErroredOnProcessing'] === true) {
        $err_msg = $data['ErrorMessage'][0] ?? $data['ErrorDetails'][0] ?? 'unknown';
        ocrLog('OCR.space error: ' . $err_msg);
        return '';
    }

    $exit_code = $data['OCRExitCode'] ?? 0;
    if ((int) $exit_code !== 1) {
        ocrLog('OCR.space exit code: ' . $exit_code . '. Response: ' . substr($response, 0, 500));
        return '';
    }

    $parsed_results = $data['ParsedResults'] ?? [];
    if (empty($parsed_results)) {
        ocrLog('OCR.space returned no parsed results. Response: ' . substr($response, 0, 500));
        return '';
    }

    $text = $parsed_results[0]['ParsedText'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        ocrLog('OCR.space parsed text is empty. Response: ' . substr($response, 0, 500));
        return '';
    }

    return $text;
}

function resolveAllowedOcrImagePath($path, $allowed_roots = null)
{
    $resolved_path = realpath((string) $path);
    if ($resolved_path === false || !is_file($resolved_path) || !is_readable($resolved_path)) {
        return null;
    }

    if (!ocrPathIsUnderAllowedRoots($resolved_path, $allowed_roots ?? ocrAllowedImageRoots())) {
        return null;
    }

    $mime_type = mime_content_type($resolved_path);
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mime_types, true)) {
        return null;
    }

    return $resolved_path;
}

function ocrAllowedImageRoots()
{
    return [
        ocrUploadsPath('payment_proofs'),
        ocrUploadsPath('ocr_tmp'),
    ];
}

function ocrUploadsPath($directory)
{
    return ocrProjectRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . trim((string) $directory, "\\/");
}

function ocrProjectRoot()
{
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    return $root !== false ? $root : dirname(__DIR__, 2);
}

function ocrPathIsUnderAllowedRoots($path, array $allowed_roots)
{
    $resolved_path = ocrNormalizePathForCompare($path);

    foreach ($allowed_roots as $root) {
        $resolved_root = realpath((string) $root);
        if ($resolved_root === false || !is_dir($resolved_root)) {
            continue;
        }

        $resolved_root = rtrim(ocrNormalizePathForCompare($resolved_root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($resolved_path, $resolved_root)) {
            return true;
        }
    }

    return false;
}

function ocrNormalizePathForCompare($path)
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $path);
    return PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized;
}

function isAllowedPaymentProofUpload(array $file, &$message = '')
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = 'Please upload a payment proof image.';
        return false;
    }

    $max_file_size = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_file_size) {
        $message = 'Payment proof file must be 5MB or smaller.';
        return false;
    }

    $proof_extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed_proof_extensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
    if (!in_array($proof_extension, $allowed_proof_extensions, true)) {
        $message = 'Please upload a valid image file.';
        return false;
    }

    $mime_type = mime_content_type($file['tmp_name'] ?? '');
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mime_types, true)) {
        $message = 'Please upload a valid image file.';
        return false;
    }

    $message = '';
    return true;
}

?>
