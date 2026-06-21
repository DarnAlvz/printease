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
    if (!function_exists('shell_exec')) {
        return '';
    }

    $binary = resolveTesseractBinary();
    $binary_command = $binary === 'tesseract' ? 'tesseract' : escapeshellarg($binary);

    $null_device = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $version_command = $binary_command . ' --version 2>' . $null_device;
    $version_output = @shell_exec($version_command);

    if (!is_string($version_output) || trim($version_output) === '') {
        return '';
    }

    $ocr_command = $binary_command . ' ' . escapeshellarg($image_path) . ' stdout --psm 6 2>' . $null_device;
    $ocr_output = @shell_exec($ocr_command);

    return is_string($ocr_output) ? $ocr_output : '';
}

function resolveTesseractBinary()
{
    $binary = trim((string) getenv('TESSERACT_PATH'));

    if ($binary === '') {
        return 'tesseract';
    }

    $binary = trim($binary, "\"'");

    if (is_dir($binary)) {
        $candidate = rtrim($binary, "\\/") . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'tesseract.exe' : 'tesseract');
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $binary;
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
