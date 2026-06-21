CREATE TABLE IF NOT EXISTS customer_favorite_shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    shop_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_shop (customer_id, shop_id),
    KEY idx_customer_favorites_customer (customer_id),
    KEY idx_customer_favorites_shop (shop_id),
    CONSTRAINT fk_customer_favorites_customer
        FOREIGN KEY (customer_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_customer_favorites_shop
        FOREIGN KEY (shop_id) REFERENCES print_shops(shop_id)
        ON DELETE CASCADE
);
