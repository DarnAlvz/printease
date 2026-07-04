CREATE TABLE IF NOT EXISTS shop_payment_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    payment_method ENUM('gcash') NOT NULL DEFAULT 'gcash',
    merchant_link VARCHAR(500) NULL,
    gcash_account_name VARCHAR(150) NOT NULL,
    gcash_number VARCHAR(30) NOT NULL,
    gcash_qr_code VARCHAR(255) NOT NULL,
    instructions TEXT NOT NULL,
    approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_shop_payment_settings_shop (shop_id),
    CONSTRAINT fk_shop_payment_settings_shop
        FOREIGN KEY (shop_id) REFERENCES print_shops(shop_id)
        ON DELETE CASCADE
);

INSERT INTO shop_payment_settings
    (shop_id, payment_method, gcash_account_name, gcash_number, gcash_qr_code, instructions, approval_status, is_active)
SELECT
    ps.shop_id,
    'gcash',
    ps.gcash_name,
    ps.gcash_number,
    ps.gcash_qr_file,
    'Pay the exact order total using this shop owner-provided GCash account, then upload your reference number and payment screenshot.',
    'approved',
    1
FROM print_shops ps
WHERE ps.gcash_name IS NOT NULL
  AND ps.gcash_name <> ''
  AND ps.gcash_number IS NOT NULL
  AND ps.gcash_number <> ''
  AND ps.gcash_qr_file IS NOT NULL
  AND ps.gcash_qr_file <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM shop_payment_settings sps
      WHERE sps.shop_id = ps.shop_id
  );
