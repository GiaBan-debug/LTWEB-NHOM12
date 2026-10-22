-- ========================================================
-- DỮ LIỆU MẪU BAN ĐẦU CHO HỆ THỐNG (LTWEB - NHÓM 12)
-- ========================================================

USE `ltweb_nhom12`;

-- Mật khẩu mặc định: 'admin123' cho admin, 'customer123' cho customer
-- Đã được hash chuẩn bcrypt
INSERT INTO `users` (`username`, `email`, `hashed_password`, `full_name`, `phone`, `address`, `role`, `is_active`)
VALUES 
('admin', 'admin@shopdemo.com', '$2b$12$O/3.e9rxzBblzyFbZQuhzOWVQS0TsVQhVp/rA2TVc1w2j1EpElZfe', 'Quản Trị Viên Hệ Thống', '0123456789', '123 Đường Công Nghệ, Quận 1, TP. HCM', 'admin', 1),
('customer', 'customer@gmail.com', '$2b$12$qGf82P56/eK9N.BvJkWaU.z68R3Psh6aCg9tHhCgRjE1D1R7y7x0a', 'Nguyễn Văn Khách Hàng', '0987654321', '456 Đường Mua Sắm, Quận 7, TP. HCM', 'customer', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Danh mục mẫu
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_url`)
VALUES
(1, 'PC Gaming', 'pc-gaming', 'Máy tính chơi game cấu hình mạnh mẽ, tối ưu FPS cao', 'assets/images/pc1.jpeg'),
(2, 'PC Văn phòng', 'pc-van-phong', 'Máy tính để bàn phục vụ công việc học tập và văn phòng mượt mà', 'assets/images/pc2.jpg'),
(3, 'Laptop Gaming', 'laptop-gaming', 'Laptop chơi game hiệu năng cao, tản nhiệt tốt, di động', 'assets/images/pc3.png'),
(4, 'Linh kiện máy tính', 'linh-kien-may-tinh', 'CPU, VGA, RAM, Ổ cứng SSD, Mainboard chính hãng', 'assets/images/pc4.png')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Sản phẩm mẫu (Khớp với giao diện ThuanThien)
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `price`, `original_price`, `stock`, `image_url`, `is_active`)
VALUES
(1, 'PC TTG GAMING i5 12400F - 16GB DDR4 - RTX 5060 8GB (All NEW - Bảo hành 36 tháng)', 'pc-ttg-gaming-i5-12400f-rtx-5060', 'Cấu hình chuẩn thi đấu: CPU Core i5-12400F, RAM 16GB RGB 3200MHz, VGA RTX 5060 8GB, SSD 512GB NVMe, Nguồn 650W 80 Plus.', 25280000.00, 27990000.00, 20, 'assets/images/pc1.jpeg', 1),
(1, 'PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5060 Ti 8GB', 'pc-ttg-gaming-core-ultra-5-250k-plus-rtx-5060-ti', 'Thế hệ CPU Core Ultra 5 250K mạnh mẽ kết hợp cùng RTX 5060 Ti chiến mượt mọi tựa game AAA ở độ phân giải 2K.', 37980000.00, 39990000.00, 15, 'assets/images/pc2.jpg', 1),
(1, 'PC TTG AMD GAMING Ryzen 7 5700X - RTX 3050 8GB (All NEW - Bảo hành 36 tháng)', 'pc-ttg-amd-gaming-ryzen-7-5700x-rtx-3050', 'Chiến binh đồ họa & Gaming với 8 nhân 16 luồng Ryzen 7 5700X, RAM 16GB, VGA GeForce RTX 3050.', 18280000.00, 19990000.00, 25, 'assets/images/pc3.png', 1),
(1, 'PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5070 12GB OC', 'pc-ttg-gaming-core-ultra-5-250k-plus-rtx-5070', 'Đỉnh cao hiệu năng Gaming 4K và đồ họa 3D render cực nhanh với card đồ họa RTX 5070 12GB VRAM.', 48980000.00, 53990000.00, 10, 'assets/images/pc4.png', 1),
(1, 'PC TTG AMD Ryzen 5 5500 - RTX 5050 8GB OC (Cấu hình gốc)', 'pc-ttg-amd-ryzen-5-5500-rtx-5050', 'Cấu hình quốc dân mức giá mềm, xử lý đồ họa mượt mà, chơi tốt GTA V, Valorant, CS2, LOL.', 19680000.00, 24990000.00, 30, 'assets/images/pc5.png', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
