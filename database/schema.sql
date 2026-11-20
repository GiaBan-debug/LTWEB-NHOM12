
-- Tạo/chọn database trong phpMyAdmin trước khi import. Tương thích shared hosting.

-- ----------------------------
-- Bảng người dùng
-- ----------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)     NOT NULL,
    email      VARCHAR(150)     NOT NULL UNIQUE,
    password   VARCHAR(255)     NULL,
    firebase_uid VARCHAR(128) COLLATE utf8mb4_bin NULL UNIQUE,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    phone      VARCHAR(15) NOT NULL DEFAULT '',
    address    VARCHAR(255) NOT NULL DEFAULT '',
    role       ENUM('user','admin') NOT NULL DEFAULT 'user',
    avatar     VARCHAR(255)     NULL,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng danh mục
-- ----------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    slug        VARCHAR(110)  NOT NULL UNIQUE,
    description TEXT          NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng sản phẩm
-- ----------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED     NOT NULL,
    name            VARCHAR(200)     NOT NULL,
    slug            VARCHAR(220)     NOT NULL UNIQUE,
    description     TEXT             NULL,
    specs           TEXT             NULL,
    warranty_months INT UNSIGNED     NOT NULL DEFAULT 12,
    price           DECIMAL(15,2)    NOT NULL DEFAULT 0,
    stock           INT UNSIGNED     NOT NULL DEFAULT 0,
    thumbnail       VARCHAR(255)     NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_status   (status),
    INDEX idx_slug     (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng ảnh sản phẩm
-- ----------------------------
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED  NOT NULL,
    image_path VARCHAR(255)  NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_img_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng giỏ hàng
-- ----------------------------
CREATE TABLE IF NOT EXISTS carts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL UNIQUE,   -- mỗi user 1 giỏ
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng item trong giỏ
-- ----------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    cart_id    INT UNSIGNED  NOT NULL,
    product_id INT UNSIGNED  NOT NULL,
    quantity   INT UNSIGNED  NOT NULL DEFAULT 1,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    CONSTRAINT fk_cartitem_cart
        FOREIGN KEY (cart_id) REFERENCES carts(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cartitem_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng đơn hàng
-- ----------------------------
CREATE TABLE IF NOT EXISTS orders (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    shipping_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
    carrier VARCHAR(100) NULL,
    tracking_code VARCHAR(100) NULL,
    estimated_delivery DATE NULL,
    receiver_name VARCHAR(100) NOT NULL DEFAULT '',
    phone       VARCHAR(15) NOT NULL DEFAULT '',
    checkout_key VARCHAR(64) NULL,
    status      ENUM('pending','processing','shipped','completed','cancelled')
                              NOT NULL DEFAULT 'pending',
    address     TEXT          NULL,
    note        TEXT          NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_order_user   (user_id),
    UNIQUE KEY uq_checkout (user_id, checkout_key),
    INDEX idx_order_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng chi tiết đơn hàng
-- ----------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED  NOT NULL,
    product_id INT UNSIGNED  NOT NULL,
    quantity   INT UNSIGNED  NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    product_name VARCHAR(200) NOT NULL DEFAULT '',
    image      VARCHAR(255) NULL,
    CONSTRAINT fk_orderitem_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_orderitem_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng lịch sử trạng thái đơn hàng (TV4)
-- ----------------------------
CREATE TABLE IF NOT EXISTS order_status_logs (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED  NOT NULL,
    from_status   VARCHAR(20)   NOT NULL,
    to_status     VARCHAR(20)   NOT NULL,
    handled_by    INT UNSIGNED  NULL,          -- NULL = khách tự hủy
    handler_name  VARCHAR(100)  NOT NULL DEFAULT '',
    cancel_reason TEXT          NULL,           -- lý do hủy (nếu có)
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_log_order (order_id),
    INDEX idx_log_time  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng bài viết (TV5 dùng)
-- ----------------------------
CREATE TABLE IF NOT EXISTS posts (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NOT NULL,
    title      VARCHAR(255)  NOT NULL,
    slug       VARCHAR(270)  NOT NULL UNIQUE,
    content    LONGTEXT      NULL,
    thumbnail  VARCHAR(255)  NULL,
    status     ENUM('draft','published') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Bảng banner (TV5 dùng)
-- ----------------------------
CREATE TABLE IF NOT EXISTS banners (
    id         INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(255)     NOT NULL,
    image_path VARCHAR(255)     NOT NULL,
    link       VARCHAR(255)     NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active  TINYINT(1)       NOT NULL DEFAULT 1,
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safe for existing databases; retains all accounts, products and orders.
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    feedback TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_user_product (user_id, product_id),
    INDEX idx_review_product (product_id, id),
    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
