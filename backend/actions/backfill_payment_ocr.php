<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../includes/gcash_ocr.php";

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.";
    exit(1);
}

$base_dir = realpath(__DIR__ . "/../..");
$sql = "SELECT payment_id, proof_of_payment_file
        FROM payments
        WHERE proof_of_payment_file IS NOT NULL
          AND proof_of_payment_file != ''
          AND (
              payment_reference_match IS NULL
              OR payment_reference_match = ''
              OR payment_reference_match = 'not_detected'
              OR ocr_reference_number IS NULL
              OR ocr_reference_number = ''
              OR ocr_payment_date IS NULL
          )
        ORDER BY payment_id ASC";

$result = mysqli_query($conn, $sql);
$scanned = 0;
$updated = 0;

while ($payment = mysqli_fetch_assoc($result)) {
    $scanned++;
    $relative_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $payment['proof_of_payment_file']);
    $proof_path = $base_dir . DIRECTORY_SEPARATOR . $relative_path;
    $allowed_proof_path = resolveAllowedOcrImagePath($proof_path, [ocrUploadsPath('payment_proofs')]);

    if ($allowed_proof_path === null) {
        echo "Skipped payment #{$payment['payment_id']}: proof file not found or not allowed for OCR.\n";
        continue;
    }

    $ocr_text = runReceiptOcr($allowed_proof_path);
    $ocr_reference_number = detectGcashReferenceFromText($ocr_text);
    $ocr_payment_date = detectGcashPaymentDateFromText($ocr_text);
    $ocr_status = gcashOcrStatus($ocr_reference_number, $ocr_payment_date);

    if ($ocr_status === 'not_detected') {
        echo "Scanned payment #{$payment['payment_id']}: not detected.\n";
        continue;
    }

    $reference_number = $ocr_reference_number ?? '';
    $update_sql = "UPDATE payments
                   SET reference_number = ?,
                       ocr_reference_number = ?,
                       ocr_payment_date = ?,
                       payment_reference_match = ?
                   WHERE payment_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "ssssi", $reference_number, $ocr_reference_number, $ocr_payment_date, $ocr_status, $payment['payment_id']);
    mysqli_stmt_execute($stmt);
    $updated++;

    echo "Updated payment #{$payment['payment_id']}: {$ocr_status}";
    echo $ocr_reference_number ? " ref={$ocr_reference_number}" : "";
    echo $ocr_payment_date ? " date={$ocr_payment_date}" : "";
    echo "\n";
}

echo "Done. Scanned {$scanned}, updated {$updated}.\n";
?>
