-- Chỉ chạy MỘT LẦN trên database dùng schema cũ (trước bản demo).
-- Sao lưu trước; không chạy nếu đã import schema.sql mới.
ALTER TABLE orders
  ADD COLUMN receiver_name VARCHAR(100) NOT NULL DEFAULT '',
  ADD COLUMN phone VARCHAR(15) NOT NULL DEFAULT '',
  ADD COLUMN checkout_key VARCHAR(64) NULL,
  ADD UNIQUE KEY uq_checkout (user_id, checkout_key);
ALTER TABLE order_items
  ADD COLUMN product_name VARCHAR(200) NOT NULL DEFAULT '',
  ADD COLUMN image VARCHAR(255) NULL;
UPDATE order_items oi JOIN products p ON p.id = oi.product_id
SET oi.product_name = p.name, oi.image = p.thumbnail
WHERE oi.product_name = '';
