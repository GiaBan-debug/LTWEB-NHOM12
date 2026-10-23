USE ltweb_nhom12;

-- Du lieu mau danh muc
INSERT INTO categories (name, slug, description) VALUES
('Điện thoại',    'dien-thoai',    'Điện thoại di động các loại'),
('Laptop',        'laptop',        'Máy tính xách tay'),
('Phụ kiện',      'phu-kien',      'Phụ kiện điện tử'),
('Máy tính bảng', 'may-tinh-bang', 'Tablet các hãng');

-- Du lieu mau nguoi dung (password: 123456 -> bcrypt)
INSERT INTO users (name, email, password, role) VALUES
('Admin',     'admin@ltweb.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Nguyen Van A', 'user1@ltweb.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('Tran Thi B',   'user2@ltweb.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user');

-- Du lieu mau san pham
INSERT INTO products (category_id, name, slug, description, price, stock, status) VALUES
(1, 'iPhone 15 Pro Max 256GB',   'iphone-15-pro-max-256gb',   'iPhone mới nhất của Apple, chip A17 Pro', 34990000, 50, 'active'),
(1, 'Samsung Galaxy S24 Ultra',  'samsung-galaxy-s24-ultra',  'Flagship Android với bút S Pen',          29990000, 30, 'active'),
(1, 'Xiaomi 14 Ultra',           'xiaomi-14-ultra',           'Camera Leica, Snapdragon 8 Gen 3',        22990000, 20, 'active'),
(2, 'MacBook Air M3 13"',        'macbook-air-m3-13',         'Laptop Apple chip M3, 8GB RAM, 256GB SSD',28990000, 15, 'active'),
(2, 'Dell XPS 15 9530',          'dell-xps-15-9530',          'Intel i7, RTX 4060, màn 3.5K OLED',      39990000, 10, 'active'),
(3, 'AirPods Pro 2nd Gen',       'airpods-pro-2nd-gen',       'Chống ồn chủ động, âm thanh lossless',    6290000, 100, 'active'),
(3, 'Cáp USB-C 100W 2m',         'cap-usb-c-100w-2m',         'Sạc nhanh, truyền dữ liệu 10Gbps',         199000, 200, 'active'),
(4, 'iPad Pro M4 11"',           'ipad-pro-m4-11',            'Màn OLED, chip M4, 256GB',               23990000, 25, 'active');