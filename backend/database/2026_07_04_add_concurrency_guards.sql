-- Preflight: resolve any rows returned by these checks before adding constraints.
SELECT order_code, COUNT(*) AS duplicate_count
FROM orders
WHERE order_code IS NOT NULL AND order_code <> ''
GROUP BY order_code
HAVING COUNT(*) > 1;

SELECT order_id, COUNT(*) AS active_payment_count
FROM payments
WHERE verification_status IN ('pending', 'verified')
GROUP BY order_id
HAVING COUNT(*) > 1;

ALTER TABLE orders
    ADD UNIQUE KEY uq_orders_order_code (order_code);

ALTER TABLE payments
    ADD COLUMN active_lock_order_id INT NULL AFTER order_id;

UPDATE payments
SET active_lock_order_id = order_id
WHERE verification_status IN ('pending', 'verified');

ALTER TABLE payments
    ADD UNIQUE KEY uq_payments_active_lock_order_id (active_lock_order_id);
