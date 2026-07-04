<?php

function normalizeGcashReference($value)
{
    return preg_replace('/\D+/', '', (string) $value);
}

function gcashReferenceCaptureToNumber($value)
{
    $value = preg_replace('/\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\b.*$/i', '', (string) $value);
    $value = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b.*$/', '', $value);
    return normalizeGcashReference($value);
}

function detectGcashReferenceFromText($text)
{
    $patterns = [
        '/(?:Reference\s*(?:No\.?|Number)?|Ref\s*No\.?|Transaction\s*(?:No\.?|ID))\s*[:#\-]?\s*([0-9][0-9\s\-]{5,}(?=\s*(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|$)))/im',
        '/(?:Reference\s*(?:No\.?|Number)?|Ref\s*No\.?|Transaction\s*(?:No\.?|ID))\s*[:#\-]?\s*([0-9][0-9\s\-]{5,})/i',
        '/(?:Ref(?:erence)?|Transaction)\s*[:#\-]?\s*([0-9][0-9\s\-]{5,})/i',
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
    $patterns = [
        '/(?:Date|Paid\s*on|Sent\s*on|Transaction\s*date)\s*[:#\-]?\s*(' . $month_names . '\s+\d{1,2},?\s+\d{4})/i',
        '/(?:Date|Paid\s*on|Sent\s*on|Transaction\s*date)\s*[:#\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
        '/\b(' . $month_names . '\s+\d{1,2},?\s+\d{4})\b/i',
        '/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/',
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
        return '';
    }

    $binary = resolveTesseractBinary();
    if ($binary === null || !tesseractBinaryIsAvailable($binary)) {
        return '';
    }

    $ocr_output = runOcrProcess([$binary, $allowed_image_path, 'stdout', '--psm', '6'], 10);

    return is_string($ocr_output) ? $ocr_output : '';
}

function resolveTesseractBinary()
{
    $binary = trim((string) getenv('TESSERACT_PATH'));

    if ($binary === '') {
        return 'tesseract';
    }

    $binary = trim($binary, "\"'");
    if ($binary === '' || preg_match('/[\r\n\0]/', $binary)) {
        return null;
    }

    if (is_dir($binary)) {
        $binary = rtrim($binary, "\\/") . DIRECTORY_SEPARATOR . ocrTesseractExecutableName();
    }

    $resolved = realpath($binary);
    if ($resolved === false || !is_file($resolved)) {
        return null;
    }

    if (strtolower(basename($resolved)) !== strtolower(ocrTesseractExecutableName())) {
        return null;
    }

    if (PHP_OS_FAMILY !== 'Windows' && !is_executable($resolved)) {
        return null;
    }

    return $resolved;
}

function ocrTesseractExecutableName()
{
    return PHP_OS_FAMILY === 'Windows' ? 'tesseract.exe' : 'tesseract';
}

function tesseractBinaryIsAvailable($binary)
{
    static $available = [];

    $key = (string) $binary;
    if (array_key_exists($key, $available)) {
        return $available[$key];
    }

    $version_output = runOcrProcess([$key, '--version'], 3);
    $available[$key] = is_string($version_output) && trim($version_output) !== '';

    return $available[$key];
}

function runOcrProcess(array $command, $timeout_seconds = 10)
{
    if (!function_exists('proc_open')) {
        return '';
    }

    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptor_spec, $pipes);
    if (!is_resource($process)) {
        return '';
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $started_at = microtime(true);

    do {
        $output .= stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $started_at) >= (int) $timeout_seconds) {
            proc_terminate($process);
            break;
        }

        usleep(100000);
    } while (true);

    $output .= stream_get_contents($pipes[1]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return $output;
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
