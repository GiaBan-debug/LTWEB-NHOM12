USE ltweb_nhom12;

-- ----------------------------
-- Danh mục
-- ----------------------------
INSERT INTO categories (name, slug, description) VALUES
('Điện thoại',    'dien-thoai',    'Điện thoại di động các loại'),
('Laptop',        'laptop',        'Máy tính xách tay'),
('Phụ kiện',      'phu-kien',      'Phụ kiện điện tử'),
('Máy tính bảng', 'may-tinh-bang', 'Tablet các hãng');

-- ----------------------------
-- Tài khoản demo
-- Mật khẩu đều là: 123456
-- Hash tạo bằng: password_hash('123456', PASSWORD_BCRYPT)
-- ----------------------------
INSERT INTO users (name, email, password, role) VALUES
('Admin',        'admin@ltweb.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Nguyễn Văn A', 'user1@ltweb.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('Trần Thị B',   'user2@ltweb.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- ----------------------------
-- Sản phẩm mẫu
-- ----------------------------
INSERT INTO products (category_id, name, slug, description, price, stock, status) VALUES
(1, 'iPhone 15 Pro Max 256GB',  'iphone-15-pro-max-256gb',  'Apple A17 Pro, camera 48MP, titanium',   34990000, 50, 'active'),
(1, 'Samsung Galaxy S24 Ultra', 'samsung-galaxy-s24-ultra', 'Snapdragon 8 Gen 3, bút S Pen tích hợp', 29990000, 30, 'active'),
(1, 'Xiaomi 14 Ultra',          'xiaomi-14-ultra',          'Camera Leica, Snapdragon 8 Gen 3',        22990000, 20, 'active'),
(2, 'MacBook Air M3 13"',       'macbook-air-m3-13',        'Chip M3, 8GB RAM, 256GB SSD',             28990000, 15, 'active'),
(2, 'Dell XPS 15 9530',         'dell-xps-15-9530',         'Intel Core i7, RTX 4060, màn 3.5K OLED', 39990000, 10, 'active'),
(3, 'AirPods Pro Gen 2',        'airpods-pro-gen-2',        'Chống ồn ANC, âm thanh Lossless',          6290000,100, 'active'),
(3, 'Cáp USB-C 100W 2m',        'cap-usb-c-100w-2m',        'Sạc nhanh 100W, truyền dữ liệu 10Gbps',     199000,200, 'active'),
(4, 'iPad Pro M4 11"',          'ipad-pro-m4-11',           'Màn OLED nano-texture, chip M4',          23990000, 25, 'active');