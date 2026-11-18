-- Migration from the completed 16/11 demo. Back up first; apply once.
-- Does not change users.id, orders, cart contents, passwords or existing roles.
ALTER TABLE users
  MODIFY password VARCHAR(255) NULL,
  ADD COLUMN firebase_uid VARCHAR(128) COLLATE utf8mb4_bin NULL UNIQUE,
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN phone VARCHAR(15) NOT NULL DEFAULT '',
  ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(15,2) NOT NULL DEFAULT 0;
