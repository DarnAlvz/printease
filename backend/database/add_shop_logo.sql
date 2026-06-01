ALTER TABLE print_shops
ADD COLUMN IF NOT EXISTS shop_logo VARCHAR(255) NULL AFTER business_permit_file;
