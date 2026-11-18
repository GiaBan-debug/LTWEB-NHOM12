-- =============================================================================
-- Migration TV6: Firebase UID, Profile Fields, Shipping Info, Specs & Warranty
-- Dự án: Nhóm 12 Store (BTA STORE) - Cửa hàng điện tử PHP & MySQL
-- Ngày: 17/11/2026
-- Mô tả:
--   1. Thêm users.firebase_uid UNIQUE, đổi users.password sang NULLABLE (hỗ trợ Firebase Auth).
--   2. Bổ sung trường hồ sơ: phone, address trong users.
--   3. Bổ sung trường vận chuyển và phí ship trong orders: shipping_fee, carrier, tracking_code, estimated_delivery.
--   4. Bổ sung thông số kỹ thuật (specs) và bảo hành (warranty_months) trong products.
--   5. Cập nhật tài khoản demo có sẵn với mock firebase_uid.
-- =============================================================================

-- 1. Bảng users: Thêm firebase_uid, cho phép password NULL, thêm phone, address
ALTER TABLE users
  ADD COLUMN firebase_uid VARCHAR(128) NULL UNIQUE AFTER email,
  MODIFY COLUMN password VARCHAR(255) NULL,
  ADD COLUMN phone VARCHAR(20) NULL AFTER role,
  ADD COLUMN address TEXT NULL AFTER phone;

-- 2. Bảng orders: Bổ sung phí giao hàng, đơn vị vận chuyển, mã vận đơn, ngày giao dự kiến
-- (Phần "thu tiền" đã bỏ theo quyết định nhóm; chỉ giữ thông tin vận chuyển)
ALTER TABLE orders
  ADD COLUMN shipping_fee DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total_price,
  ADD COLUMN carrier VARCHAR(100) NULL AFTER note,
  ADD COLUMN tracking_code VARCHAR(100) NULL AFTER carrier,
  ADD COLUMN estimated_delivery DATE NULL AFTER tracking_code;

-- 3. Bảng products: Bổ sung thời hạn bảo hành và thông số kỹ thuật
ALTER TABLE products
  ADD COLUMN warranty_months INT UNSIGNED NOT NULL DEFAULT 12 AFTER stock,
  ADD COLUMN specs TEXT NULL AFTER warranty_months;

-- 4. Cập nhật mock firebase_uid cho các tài khoản demo có sẵn
UPDATE users SET phone = '0901234567', address = 'Quận 1, TP. Hồ Chí Minh' WHERE email = 'admin@ltweb.com';
UPDATE users SET phone = '0912345678', address = '123 Lê Lợi, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh' WHERE email = 'user1@ltweb.com';
UPDATE users SET phone = '0987654321', address = '456 Nguyễn Huệ, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh' WHERE email = 'user2@ltweb.com';
