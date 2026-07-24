<?php

function priceRecordsTableExists(mysqli $conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $result = mysqli_query($conn, "SHOW TABLES LIKE 'shop_price_records'");
    $exists = $result && mysqli_num_rows($result) > 0;

    return $exists;
}

function priceRecordAdvancedFieldsFromInput(array $input): array
{
    $decimal_fields = ['width', 'height'];
    $integer_fields = ['min_quantity', 'max_quantity'];
    $advanced = [
        'variant' => trim((string) ($input['variant'] ?? '')),
        'width' => null,
        'height' => null,
        'dimension_unit' => trim((string) ($input['dimension_unit'] ?? '')),
        'min_quantity' => null,
        'max_quantity' => null,
    ];

    foreach ($decimal_fields as $field) {
        if (isset($input[$field]) && trim((string) $input[$field]) !== '') {
            $advanced[$field] = max(0, (float) $input[$field]);
        }
    }

    foreach ($integer_fields as $field) {
        if (isset($input[$field]) && trim((string) $input[$field]) !== '') {
            $advanced[$field] = max(0, (int) $input[$field]);
        }
    }

    if ($advanced['variant'] === '') {
        $advanced['variant'] = null;
    }

    if ($advanced['dimension_unit'] === '') {
        $advanced['dimension_unit'] = null;
    }

    if ($advanced['min_quantity'] !== null && $advanced['max_quantity'] !== null && $advanced['max_quantity'] < $advanced['min_quantity']) {
        $advanced['max_quantity'] = null;
    }

    return $advanced;
}

function existingPriceRecordAdvancedFields(mysqli $conn, int $shop_id, string $legacy_source, int $legacy_id): array
{
    $empty = [
        'variant' => null,
        'width' => null,
        'height' => null,
        'dimension_unit' => null,
        'min_quantity' => null,
        'max_quantity' => null,
    ];

    $sql = "SELECT variant, width, height, dimension_unit, min_quantity, max_quantity
            FROM shop_price_records
            WHERE shop_id = ? AND legacy_source = ? AND legacy_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $empty;
    }

    mysqli_stmt_bind_param($stmt, "isi", $shop_id, $legacy_source, $legacy_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    return $row ? array_merge($empty, $row) : $empty;
}

function syncDocumentPriceRecord(mysqli $conn, int $shop_id, int $service_id, array $advanced = []): void
{
    if (!priceRecordsTableExists($conn)) {
        return;
    }

    $fetch_sql = "SELECT service_id, paper_size, paper_type, print_type, price_per_page, is_available
                  FROM shop_services
                  WHERE service_id = ? AND shop_id = ?
                  LIMIT 1";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    if (!$fetch_stmt) {
        return;
    }

    mysqli_stmt_bind_param($fetch_stmt, "ii", $service_id, $shop_id);
    mysqli_stmt_execute($fetch_stmt);
    $service = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));

    if (!$service) {
        return;
    }

    $service_type = 'Document Printing';
    $size_name = trim((string) ($service['paper_size'] ?? ''));
    $paper_type = trim((string) ($service['paper_type'] ?? ''));
    $print_type = trim((string) ($service['print_type'] ?? ''));
    $legacy_source = 'shop_services';
    if (empty($advanced)) {
        $advanced = existingPriceRecordAdvancedFields($conn, $shop_id, $legacy_source, $service_id);
        $advanced['variant'] = null;
    }

    $variant = $advanced['variant'] ?? trim($paper_type . ($paper_type !== '' && $print_type !== '' ? ' / ' : '') . $print_type);
    $pricing_basis = 'per page';
    $width = $advanced['width'] ?? null;
    $height = $advanced['height'] ?? null;
    $dimension_unit = $advanced['dimension_unit'] ?? null;
    $min_quantity = $advanced['min_quantity'] ?? null;
    $max_quantity = $advanced['max_quantity'] ?? null;
    $price = (float) ($service['price_per_page'] ?? 0);
    $is_available = !empty($service['is_available']) ? 1 : 0;

    if ($size_name === '' || $pricing_basis === '' || $price <= 0) {
        return;
    }

    $upsert_sql = "INSERT INTO shop_price_records
                   (shop_id, service_type, size_name, variant, width, height, dimension_unit, pricing_basis, min_quantity, max_quantity, price, is_available, legacy_source, legacy_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE
                       service_type = VALUES(service_type),
                       size_name = VALUES(size_name),
                       variant = VALUES(variant),
                       width = VALUES(width),
                       height = VALUES(height),
                       dimension_unit = VALUES(dimension_unit),
                       pricing_basis = VALUES(pricing_basis),
                       min_quantity = VALUES(min_quantity),
                       max_quantity = VALUES(max_quantity),
                       price = VALUES(price),
                       is_available = VALUES(is_available)";
    $upsert_stmt = mysqli_prepare($conn, $upsert_sql);
    if (!$upsert_stmt) {
        return;
    }

    mysqli_stmt_bind_param($upsert_stmt, "isssddssiidisi", $shop_id, $service_type, $size_name, $variant, $width, $height, $dimension_unit, $pricing_basis, $min_quantity, $max_quantity, $price, $is_available, $legacy_source, $service_id);
    mysqli_stmt_execute($upsert_stmt);
}

function syncServicePricingRecord(mysqli $conn, int $shop_id, int $pricing_id, array $advanced = []): void
{
    if (!priceRecordsTableExists($conn)) {
        return;
    }

    $fetch_sql = "SELECT id, service_type, option_label, unit, price, is_available
                  FROM shop_service_pricing
                  WHERE id = ? AND shop_id = ?
                  LIMIT 1";
    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    if (!$fetch_stmt) {
        return;
    }

    mysqli_stmt_bind_param($fetch_stmt, "ii", $pricing_id, $shop_id);
    mysqli_stmt_execute($fetch_stmt);
    $entry = mysqli_fetch_assoc(mysqli_stmt_get_result($fetch_stmt));

    if (!$entry || ($entry['service_type'] ?? '') === 'Document Printing') {
        return;
    }

    $service_type = trim((string) ($entry['service_type'] ?? ''));
    $size_name = trim((string) ($entry['option_label'] ?? ''));
    $legacy_source = 'shop_service_pricing';
    if (empty($advanced)) {
        $advanced = existingPriceRecordAdvancedFields($conn, $shop_id, $legacy_source, $pricing_id);
    }

    $variant = $advanced['variant'] ?? null;
    $pricing_basis = trim((string) ($entry['unit'] ?? ''));
    $width = $advanced['width'] ?? null;
    $height = $advanced['height'] ?? null;
    $dimension_unit = $advanced['dimension_unit'] ?? null;
    $min_quantity = $advanced['min_quantity'] ?? null;
    $max_quantity = $advanced['max_quantity'] ?? null;
    $price = (float) ($entry['price'] ?? 0);
    $is_available = !empty($entry['is_available']) ? 1 : 0;

    if ($service_type === '' || $size_name === '' || $price <= 0) {
        return;
    }

    if ($pricing_basis === '') {
        $pricing_basis = 'flat rate';
    }

    $upsert_sql = "INSERT INTO shop_price_records
                   (shop_id, service_type, size_name, variant, width, height, dimension_unit, pricing_basis, min_quantity, max_quantity, price, is_available, legacy_source, legacy_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE
                       service_type = VALUES(service_type),
                       size_name = VALUES(size_name),
                       variant = VALUES(variant),
                       width = VALUES(width),
                       height = VALUES(height),
                       dimension_unit = VALUES(dimension_unit),
                       pricing_basis = VALUES(pricing_basis),
                       min_quantity = VALUES(min_quantity),
                       max_quantity = VALUES(max_quantity),
                       price = VALUES(price),
                       is_available = VALUES(is_available)";
    $upsert_stmt = mysqli_prepare($conn, $upsert_sql);
    if (!$upsert_stmt) {
        return;
    }

    mysqli_stmt_bind_param($upsert_stmt, "isssddssiidisi", $shop_id, $service_type, $size_name, $variant, $width, $height, $dimension_unit, $pricing_basis, $min_quantity, $max_quantity, $price, $is_available, $legacy_source, $pricing_id);
    mysqli_stmt_execute($upsert_stmt);
}

function deletePriceRecordForLegacy(mysqli $conn, int $shop_id, string $legacy_source, int $legacy_id): void
{
    if (!priceRecordsTableExists($conn)) {
        return;
    }

    $delete_sql = "DELETE FROM shop_price_records WHERE shop_id = ? AND legacy_source = ? AND legacy_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    if (!$delete_stmt) {
        return;
    }

    mysqli_stmt_bind_param($delete_stmt, "isi", $shop_id, $legacy_source, $legacy_id);
    mysqli_stmt_execute($delete_stmt);
}
