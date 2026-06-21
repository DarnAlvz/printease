ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS ocr_payment_date DATE NULL AFTER ocr_reference_number;
