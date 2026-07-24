CREATE TABLE IF NOT EXISTS shop_service_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shop_service_type (shop_id, service_type),
    CONSTRAINT fk_shop_service_types_shop
        FOREIGN KEY (shop_id) REFERENCES print_shops(shop_id)
        ON DELETE CASCADE
);
