CREATE TABLE IF NOT EXISTS shop_service_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    option_size VARCHAR(50) DEFAULT NULL,
    option_label VARCHAR(150) NOT NULL,
    unit VARCHAR(50) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ssp_shop_type (shop_id, service_type),
    KEY idx_ssp_shop_available (shop_id, is_available),
    CONSTRAINT fk_ssp_shop FOREIGN KEY (shop_id) REFERENCES print_shops(shop_id) ON DELETE CASCADE
);
