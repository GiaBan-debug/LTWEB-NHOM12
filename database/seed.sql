-- =============================================================================
-- Seed Data: BTA STORE (Nhóm 12) - Dữ liệu demo đầy đủ
-- Chọn database trước khi import; chỉ dùng cho database mới.
-- =============================================================================

-- ----------------------------
-- 1. Danh mục sản phẩm (4 danh mục chính)
-- ----------------------------
INSERT INTO categories (id, name, slug, description) VALUES
(1, 'Điện thoại',    'dien-thoai',    'Điện thoại di động chính hãng Apple, Samsung, Xiaomi, OPPO'),
(2, 'Laptop',        'laptop',        'Máy tính xách tay cao cấp, đồ họa và gaming mỏng nhẹ'),
(3, 'Phụ kiện',      'phu-kien',      'Tai nghe, củ cáp sạc nhanh, chuột, bàn phím và phụ kiện công nghệ'),
(4, 'Máy tính bảng', 'may-tinh-bang', 'Tablet phục vụ học tập, đồ họa và giải trí: iPad, Galaxy Tab, Xiaomi Pad')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- ----------------------------
-- 2. Tài khoản demo (Hỗ trợ cả Firebase UID và PHP password hash)
-- Mật khẩu mặc định: 123456
-- Hash: $2y$12$yXbNYDlLOK4Dw.GdJVT3ae5Er3UiacD9UYgw6X74XJ9/V7eYFU1iC
-- ----------------------------
INSERT INTO users (id, name, email, firebase_uid, password, role, phone, address) VALUES
(1, 'Admin',        'admin@ltweb.com', NULL, '$2y$12$yXbNYDlLOK4Dw.GdJVT3ae5Er3UiacD9UYgw6X74XJ9/V7eYFU1iC', 'admin', '0901234567', 'Ban Quản Trị BTA Store, Quận 1, TP.HCM'),
(2, 'Nguyễn Văn A', 'user1@ltweb.com', NULL, '$2y$12$yXbNYDlLOK4Dw.GdJVT3ae5Er3UiacD9UYgw6X74XJ9/V7eYFU1iC', 'user',  '0912345678', '123 Lê Lợi, P. Bến Nghé, Quận 1, TP.HCM'),
(3, 'Trần Thị B',   'user2@ltweb.com', NULL, '$2y$12$yXbNYDlLOK4Dw.GdJVT3ae5Er3UiacD9UYgw6X74XJ9/V7eYFU1iC', 'user',  '0987654321', '456 Nguyễn Huệ, P. Bến Nghé, Quận 1, TP.HCM')
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), phone = VALUES(phone), address = VALUES(address);

-- ----------------------------
-- 3. Sản phẩm mẫu (24 sản phẩm: có hàng, hết hàng và ngừng bán)
-- ----------------------------
INSERT INTO products (id, category_id, name, slug, description, specs, warranty_months, price, stock, thumbnail, status) VALUES
-- --- Nhóm 1: Điện thoại ---
(1,  1, 'iPhone 15 Pro Max 256GB',  'iphone-15-pro-max-256gb',  'Apple A17 Pro, camera 48MP tiềm vọng 5x, khung titan tự nhiên bền nhẹ.',
 'Màn hình 6.7" Super Retina XDR OLED 120Hz; Chip A17 Pro 3nm; RAM 8GB; Pin 4422 mAh; Sạc 25W', 12, 34990000, 50, 'products/iphone-15-pro-max.jpg', 'active'),

(2,  1, 'Samsung Galaxy S24 Ultra', 'samsung-galaxy-s24-ultra', 'Snapdragon 8 Gen 3 for Galaxy, bút S Pen tích hợp, camera 200MP Zoom AI.',
 'Màn hình 6.8" Dynamic AMOLED 2X 120Hz; Chip Snapdragon 8 Gen 3; RAM 12GB; Pin 5000 mAh; Sạc 45W', 12, 29990000, 30, 'products/samsung-s24-ultra.jpg', 'active'),

(3,  1, 'Xiaomi 14 Ultra',          'xiaomi-14-ultra',          'Camera cảm biến 1 inch hợp tác Leica, màn hình 2K WQHD+ cực sáng.',
 'Màn hình 6.73" LTPO AMOLED 120Hz; Chip Snapdragon 8 Gen 3; RAM 16GB; Pin 5000 mAh; Sạc 90W', 18, 22990000, 20, 'products/xiaomi-14-ultra.jpg', 'active'),

(4,  1, 'iPhone 14 128GB',          'iphone-14-128gb',          'Thiết kế chuẩn mực, camera kép nâng cấp chụp đêm, thời lượng pin cả ngày.',
 'Màn hình 6.1" Super Retina XDR; Chip A15 Bionic; RAM 6GB; Pin 3279 mAh', 12, 16990000, 0, 'products/iphone-14.jpg', 'active'), -- Hết hàng

(5,  1, 'Samsung Galaxy Z Flip5',   'samsung-galaxy-z-flip5',   'Điện thoại gập thời trang, màn hình ngoài Flex Window 3.4 inch độc đáo.',
 'Màn hình chính 6.7" Dynamic AMOLED 2X + phụ 3.4"; Snapdragon 8 Gen 2; RAM 8GB; Pin 3700 mAh', 12, 17490000, 15, 'products/samsung-z-flip5.jpg', 'active'),

(6,  1, 'OPPO Find N3 Flip',        'oppo-find-n3-flip',        'Thiết kế gập vỏ sò cao cấp, 3 camera Hasselblad chuyên nghiệp.',
 'Màn hình chính 6.8" AMOLED 120Hz; Dimensity 9200; RAM 12GB; Pin 4300 mAh; Sạc 44W', 12, 14990000, 10, 'products/oppo-find-n3.jpg', 'inactive'), -- Ngừng bán

-- --- Nhóm 2: Laptop ---
(7,  2, 'MacBook Air M3 13"',       'macbook-air-m3-13',        'Chip Apple M3 hiệu năng vượt trội, thời lượng pin tới 18 giờ, thiết kế siêu mỏng.',
 'Chip Apple M3 8-core CPU / 10-core GPU; RAM 8GB; SSD 256GB; Màn 13.6" Liquid Retina; Trọng lượng 1.24kg', 12, 28990000, 15, 'products/macbook-air-m3.jpg', 'active'),

(8,  2, 'Dell XPS 15 9530',         'dell-xps-15-9530',         'Laptop đồ họa chuyên nghiệp, Intel Core i7 thế hệ 13, màn hình 3.5K OLED rực rỡ.',
 'Intel Core i7-13700H; RTX 4060 8GB; RAM 16GB DDR5; SSD 512GB; Màn 15.6" OLED 3.5K Touch', 24, 39990000, 0, 'products/dell-xps-15.jpg', 'active'), -- Hết hàng

(9,  2, 'MacBook Pro 14" M3 Pro',   'macbook-pro-14-m3-pro',    'Cỗ máy cho lập trình viên và sáng tạo nội dung chuyên nghiệp, màn Liquid Retina XDR 120Hz.',
 'Chip Apple M3 Pro 11-core CPU / 14-core GPU; RAM 18GB Unified; SSD 512GB; Pin 70Wh', 12, 49990000, 8, 'products/macbook-pro-14.jpg', 'active'),

(10, 2, 'ASUS ROG Zephyrus G16',    'asus-rog-zephyrus-g16',    'Laptop gaming mỏng nhẹ cao cấp, màn OLED 2.5K 240Hz, Intel Core Ultra 9.',
 'Intel Core Ultra 9 185H; RTX 4070 8GB; RAM 32GB LPDDR5X; SSD 1TB NVMe; Màn 16" ROG Nebula OLED 240Hz', 24, 44990000, 12, 'products/asus-rog-zephyrus.jpg', 'active'),

(11, 2, 'Lenovo ThinkPad X1 Carbon Gen 11', 'thinkpad-x1-carbon-gen-11', 'Biểu tượng doanh nhân: bền bỉ chuẩn quân đội, bàn phím gõ êm ái, bảo mật vân tay.',
 'Intel Core i7-1365U vPro; RAM 16GB; SSD 512GB; Màn 14" 2.8K OLED; Trọng lượng 1.12kg', 36, 36500000, 6, 'products/thinkpad-x1.jpg', 'active'),

(12, 2, 'HP Envy 16 OLED',          'hp-envy-16-oled',          'Laptop sáng tạo nội dung màn hình rộng, card đồ họa rời Intel Arc chuyên dụng.',
 'Intel Core i7-13700H; Intel Arc A370M; RAM 16GB; SSD 1TB; Màn 16" OLED 120Hz Touch', 12, 32900000, 5, 'products/hp-envy-16.jpg', 'inactive'), -- Ngừng bán

-- --- Nhóm 3: Phụ kiện ---
(13, 3, 'AirPods Pro Gen 2',        'airpods-pro-gen-2',        'Chống ồn chủ động ANC gấp 2 lần, âm thanh thích ứng và cổng sạc USB-C mới.',
 'Chip Apple H2; Chống ồn chủ động; Xuyên âm; Chống nước IP54; Pin kèm hộp 30 giờ', 12, 6290000, 100, 'products/airpods-pro-2.jpg', 'active'),

(14, 3, 'Cáp USB-C 100W 2m',        'cap-usb-c-100w-2m',        'Dây dù bện chống rối siêu bền, hỗ trợ sạc nhanh Power Delivery 100W và truyền dữ liệu.',
 'Công suất sạc tối đa 100W (20V/5A); Tốc độ truyền 480Mbps; Vỏ bện nylon mật độ cao; Dài 2m', 12, 199000, 200, 'products/cap-usb-c.jpg', 'active'),

(15, 3, 'Củ sạc nhanh Anker GaN 65W', 'anker-gan-65w',          'Công nghệ GaNPrime nhỏ gọn, 3 cổng ra (2 Type-C + 1 Type-A) sạc cùng lúc 3 thiết bị.',
 'Công suất 65W; 2 cổng USB-C + 1 cổng USB-A; Tương thích iPhone, iPad, MacBook, Laptop Windows', 18, 890000, 80, 'products/anker-gan-65w.jpg', 'active'),

(16, 3, 'Chuột Logitech MX Master 3S', 'logitech-mx-master-3s', 'Chuột công thái học hàng đầu cho dân văn phòng và coder, cuộn MagSpeed siêu tốc cực êm.',
 'Cảm biến 8000 DPI Darkfield; Click êm giảm 90% tiếng ồn; Pin sạc Type-C dùng tới 70 ngày; Kết nối 3 máy', 24, 2190000, 45, 'products/logitech-mx-master-3s.jpg', 'active'),

(17, 3, 'Bàn phím cơ Keychron K2 Pro', 'keychron-k2-pro',       'Bàn phím cơ không dây layout 75%, hỗ trợ QMK/VIA tùy biến phím, switch êm ái.',
 'Layout 84 phím (75%); Kết nối Bluetooth 5.1 & Dây Type-C; Pin 4000mAh; Tương thích Mac/Windows', 12, 2450000, 35, 'products/keychron-k2-pro.jpg', 'active'),

(18, 3, 'Pin dự phòng Baseus Blade 100W', 'baseus-blade-100w',  'Pin sạc mỏng nhẹ như quyển sổ tay, sạc nhanh cho laptop và điện thoại.',
 'Dung lượng 20000mAh; Công suất 100W Type-C; Màn hình LED kỹ thuật số hiển thị công suất thực', 12, 1350000, 0, 'products/baseus-blade-100w.jpg', 'active'), -- Hết hàng

-- --- Nhóm 4: Máy tính bảng ---
(19, 4, 'iPad Pro M4 11"',          'ipad-pro-m4-11',           'Thiết kế mỏng kỷ lục 5.3mm, chip M4 sức mạnh AI, màn hình Ultra Retina XDR OLED kép.',
 'Chip Apple M4; Màn hình 11" Tandem OLED 120Hz; RAM 8GB; Camera 12MP; Hỗ trợ Apple Pencil Pro', 12, 23990000, 25, 'products/ipad-pro-m4.jpg', 'active'),

(20, 4, 'iPad Air 5 64GB',          'ipad-air-5-64gb',          'Hiệu năng chuẩn Pro với chip Apple M1, cổng USB-C tốc độ cao và camera trước Center Stage.',
 'Chip Apple M1 8 nhân; Màn hình 10.9" Liquid Retina; RAM 8GB; Bộ nhớ 64GB; Touch ID tích hợp nút nguồn', 12, 13990000, 0, 'products/ipad-air-5.jpg', 'active'), -- Hết hàng

(21, 4, 'iPad 10.9" Gen 10',        'ipad-gen-10',              'Màn hình tràn cạnh rực rỡ, cổng sạc Type-C hiện đại, màu sắc trẻ trung đa phong cách.',
 'Chip Apple A14 Bionic; Màn hình 10.9" Liquid Retina; Bộ nhớ 64GB; Wi-Fi 6 siêu tốc', 12, 8990000, 40, 'products/ipad-gen-10.jpg', 'active'),

(22, 4, 'Samsung Galaxy Tab S9 Ultra', 'galaxy-tab-s9-ultra',   'Màn hình Dynamic AMOLED 2X 14.6 inch khổng lồ, chuẩn kháng nước bụi IP68 kèm bút S Pen.',
 'Màn hình 14.6" 120Hz; Snapdragon 8 Gen 2 for Galaxy; RAM 12GB; Pin 11200 mAh; Sạc 45W', 12, 24990000, 14, 'products/galaxy-tab-s9.jpg', 'active'),

(23, 4, 'Xiaomi Pad 6',             'xiaomi-pad-6',             'Màn hình 2.8K 144Hz sắc nét mượt mà, vi xử lý Snapdragon 870 ổn định, 4 loa vòm Dolby Atmos.',
 'Màn hình 11" 2.8K 144Hz; Snapdragon 870; RAM 8GB; Pin 8840 mAh; Sạc 33W', 18, 7490000, 28, 'products/xiaomi-pad-6.jpg', 'active'),

(24, 4, 'Lenovo Tab P12',           'lenovo-tab-p12',           'Máy tính bảng học tập giải trí màn hình lớn 12.7 inch 3K, hệ thống âm thanh JBL.',
 'Màn hình 12.7" 3K LTPS; MediaTek Dimensity 7050; RAM 8GB; Pin 10200 mAh', 12, 6890000, 10, 'products/lenovo-tab-p12.jpg', 'inactive') -- Ngừng bán
ON DUPLICATE KEY UPDATE
  name = VALUES(name), price = VALUES(price), stock = VALUES(stock),
  thumbnail = VALUES(thumbnail), status = VALUES(status), specs = VALUES(specs),
  warranty_months = VALUES(warranty_months);

-- ----------------------------
-- 4. Đơn hàng mẫu đa dạng trạng thái (Demo trình diễn khách hàng & Admin)
-- ----------------------------
INSERT INTO orders (id, user_id, total_price, shipping_fee, receiver_name, phone, address, note, carrier, tracking_code, estimated_delivery, checkout_key, status, created_at) VALUES
-- Đơn 1: Chờ xác nhận (pending)
(1, 2, 34990000, 0, 'Nguyễn Văn A', '0912345678', '123 Lê Lợi, P. Bến Nghé, Quận 1, TP.HCM', 'Giao trong giờ hành chính, gọi trước khi đến.', NULL, NULL, NULL, 'seed-order-pending-01', 'pending', DATE_SUB(NOW(), INTERVAL 2 HOUR)),

-- Đơn 2: Đang chuẩn bị hàng (processing)
(2, 2, 6489000, 0, 'Nguyễn Văn A', '0912345678', '123 Lê Lợi, P. Bến Nghé, Quận 1, TP.HCM', 'Đóng gói cẩn thận giúp tôi.', NULL, NULL, NULL, 'seed-order-processing-02', 'processing', DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Đơn 3: Đang giao hàng (shipped - có thông tin đơn vị vận chuyển và mã vận đơn)
(3, 2, 28990000, 0, 'Nguyễn Văn A', '0912345678', '123 Lê Lợi, P. Bến Nghé, Quận 1, TP.HCM', NULL, 'Giao Hàng Nhanh', 'GHN892184912', DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), 'seed-order-shipped-03', 'shipped', DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- Đơn 4: Hoàn thành (completed - có doanh thu cho dashboard)
(4, 3, 29990000, 0, 'Trần Thị B', '0987654321', '456 Nguyễn Huệ, P. Bến Nghé, Quận 1, TP.HCM', 'Giao cho lễ tân tòa nhà', 'Viettel Post', 'VTP99382103', CURRENT_DATE(), 'seed-order-completed-04', 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- Đơn 5: Đã hủy (cancelled)
(5, 3, 6290000, 0, 'Trần Thị B', '0987654321', '456 Nguyễn Huệ, P. Bến Nghé, Quận 1, TP.HCM', 'Khách đổi ý muốn đổi sang mẫu khác', NULL, NULL, NULL, 'seed-order-cancelled-05', 'cancelled', DATE_SUB(NOW(), INTERVAL 7 DAY))
ON DUPLICATE KEY UPDATE status = VALUES(status), carrier = VALUES(carrier), tracking_code = VALUES(tracking_code);

-- ----------------------------
-- 5. Chi tiết các đơn hàng mẫu
-- ----------------------------
INSERT INTO order_items (id, order_id, product_id, quantity, unit_price, product_name, image) VALUES
-- Chi tiết Đơn 1 (iPhone 15 Pro Max)
(1, 1, 1, 1, 34990000, 'iPhone 15 Pro Max 256GB', 'products/iphone-15-pro-max.jpg'),

-- Chi tiết Đơn 2 (AirPods Pro 2 + Cáp sạc USB-C)
(2, 2, 13, 1, 6290000, 'AirPods Pro Gen 2', 'products/airpods-pro-2.jpg'),
(3, 2, 14, 1, 199000, 'Cáp USB-C 100W 2m', 'products/cap-usb-c.jpg'),

-- Chi tiết Đơn 3 (MacBook Air M3)
(4, 3, 7, 1, 28990000, 'MacBook Air M3 13"', 'products/macbook-air-m3.jpg'),

-- Chi tiết Đơn 4 (Samsung Galaxy S24 Ultra)
(5, 4, 2, 1, 29990000, 'Samsung Galaxy S24 Ultra', 'products/samsung-s24-ultra.jpg'),

-- Chi tiết Đơn 5 (AirPods Pro 2 - đã hủy)
(6, 5, 13, 1, 6290000, 'AirPods Pro Gen 2', 'products/airpods-pro-2.jpg')
ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price), quantity = VALUES(quantity);
