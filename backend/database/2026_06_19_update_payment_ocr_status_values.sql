ALTER TABLE payments
    MODIFY payment_reference_match ENUM('unchecked','matched','not_matched','not_detected','detected','partial') NULL DEFAULT 'unchecked';
