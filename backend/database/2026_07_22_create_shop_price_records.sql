CREATE TABLE IF NOT EXISTS shop_price_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    size_name VARCHAR(150) NOT NULL,
    variant VARCHAR(150) DEFAULT NULL,
    width DECIMAL(10,2) DEFAULT NULL,
    height DECIMAL(10,2) DEFAULT NULL,
    dimension_unit VARCHAR(20) DEFAULT NULL,
    pricing_basis VARCHAR(50) NOT NULL,
    min_quantity INT DEFAULT NULL,
    max_quantity INT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    legacy_source ENUM('shop_services', 'shop_service_pricing') NOT NULL,
    legacy_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_spr_legacy (shop_id, legacy_source, legacy_id),
    KEY idx_spr_shop_service (shop_id, service_type),
    KEY idx_spr_shop_available (shop_id, is_available),
    KEY idx_spr_pricing_basis (pricing_basis),
    CONSTRAINT fk_spr_shop FOREIGN KEY (shop_id) REFERENCES print_shops(shop_id) ON DELETE CASCADE
);

INSERT INTO shop_price_records (
    shop_id,
    service_type,
    size_name,
    variant,
    pricing_basis,
    price,
    is_available,
    legacy_source,
    legacy_id
)
SELECT
    ss.shop_id,
    'Document Printing',
    ss.paper_size,
    CONCAT_WS(' / ', NULLIF(ss.paper_type, ''), NULLIF(ss.print_type, '')),
    'per page',
    ss.price_per_page,
    COALESCE(ss.is_available, 1),
    'shop_services',
    ss.service_id
FROM shop_services ss
WHERE ss.paper_size IS NOT NULL
  AND ss.paper_size <> ''
  AND ss.paper_type IS NOT NULL
  AND ss.paper_type <> ''
  AND ss.print_type IS NOT NULL
  AND ss.print_type <> ''
ON DUPLICATE KEY UPDATE
    service_type = VALUES(service_type),
    size_name = VALUES(size_name),
    variant = VALUES(variant),
    pricing_basis = VALUES(pricing_basis),
    price = VALUES(price),
    is_available = VALUES(is_available);

INSERT INTO shop_price_records (
    shop_id,
    service_type,
    size_name,
    variant,
    pricing_basis,
    price,
    is_available,
    legacy_source,
    legacy_id
)
SELECT
    ssp.shop_id,
    ssp.service_type,
    ssp.option_label,
    NULL,
    COALESCE(NULLIF(ssp.unit, ''), 'flat rate'),
    ssp.price,
    COALESCE(ssp.is_available, 1),
    'shop_service_pricing',
    ssp.id
FROM shop_service_pricing ssp
WHERE ssp.service_type <> 'Document Printing'
  AND ssp.option_label IS NOT NULL
  AND ssp.option_label <> ''
ON DUPLICATE KEY UPDATE
    service_type = VALUES(service_type),
    size_name = VALUES(size_name),
    variant = VALUES(variant),
    pricing_basis = VALUES(pricing_basis),
    price = VALUES(price),
    is_available = VALUES(is_available);
