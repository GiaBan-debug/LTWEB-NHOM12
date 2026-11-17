# Kịch Bản Trình Diễn (Demo Script) & Checklist Trước Buổi Báo Cáo

Dự án: **BTA STORE (Nhóm 12) — Website Thương Mại Điện Tử Cửa Hàng Thiết Bị Công Nghệ**  
Ngày cập nhật: **17/11/2026**  
Mục tiêu: Đạt điểm tối đa đồ án với luồng hoạt động mượt mà, dữ liệu chân thực và xử lý đầy đủ các ca biên nghiệp vụ.

---

## 1. Thông Tin Tài Khoản & Môi Trường Demo

### Môi trường chạy
- **Local:** PHP CLI 8.2+ kết nối MariaDB/MySQL port 3307 (`ltweb_nhom12_demo`).
- **Lệnh khởi động nhanh:** `run-demo.cmd` (truy cập `http://localhost:8000/`).

### Danh sách tài khoản demo
| Vai trò | Email đăng nhập | Mật khẩu | Firebase UID (Mock/Live) | Quyền hạn |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin@ltweb.com` | `123456` | `firebase_demo_admin` | Toàn quyền Dashboard, Sản phẩm, Đơn hàng |
| **Khách hàng 1** | `user1@ltweb.com` | `123456` | `firebase_demo_user1` | Đã có sẵn 3 đơn hàng ở các trạng thái |
| **Khách hàng 2** | `user2@ltweb.com` | `123456` | `firebase_demo_user2` | Đã có 2 đơn hàng (1 hoàn thành, 1 đã hủy) |

---

## 2. Checklist Chuẩn Bị Trước Giờ G (5 Phút Trước Báo Cáo)

- [ ] **1. Môi trường:** Khởi động server bằng lệnh `run-demo.cmd` và xác nhận terminal không báo lỗi.
- [ ] **2. Kiểm tra Health:** Mở trình duyệt vào `http://localhost:8000/backend/api/health.php` nhận được `{"success":true,"message":"Database connected."}`.
- [ ] **3. Trình duyệt:** Mở sẵn 2 cửa sổ/tab ẩn danh:
  - Tab 1: Khách hàng mua sắm (`http://localhost:8000/frontend/index.html`).
  - Tab 2: Admin quản trị (`http://localhost:8000/frontend/pages/admin/index.html`).
- [ ] **4. Kiểm tra tài nguyên:** Các ảnh trong `uploads/products/` hiển thị đầy đủ, không bị lỗi 404.
- [ ] **5. Chuẩn bị mạng:** Đảm bảo kết nối internet ổn định nếu có trình diễn Firebase live domain.

---

## 3. Kịch Bản Trình Diễn Từng Bước (Thời Lượng: 7 - 10 Phút)

### Bước 1: Giới thiệu giao diện & Catalogue sản phẩm (1.5 phút)
1. Mở trang chủ BTA Store (`index.html`).
2. Trình diễn Hero banner, các danh mục chính (Điện thoại, Laptop, Phụ kiện, Máy tính bảng).
3. Bấm vào **"Sản phẩm"** (`pages/users/products.html`):
   - Chỉ ra catalogue có đầy đủ **24 sản phẩm đa dạng** với hình ảnh, giá, cấu hình chi tiết.
   - Thử bộ lọc: Chọn danh mục "Laptop" → Chọn mức giá "Từ 30.000.000 ₫" → Bấm "Áp dụng" → Hiển thị kết quả chính xác theo server.
   - Trình bày điểm đặc biệt về nghiệp vụ:
     - Sản phẩm còn hàng: Hiển thị giá, tồn kho và nút thêm vào giỏ.
     - Sản phẩm hết hàng (như *iPhone 14 128GB*, *Dell XPS 15*): Nhãn **"Tạm hết hàng"**, nút thêm vào giỏ tự động bị vô hiệu hóa.
     - Sản phẩm ngừng bán (*OPPO Find N3 Flip*): Bị ẩn khỏi danh sách khách hàng nhưng bảo toàn trong cơ sở dữ liệu.

### Bước 2: Xem chi tiết sản phẩm & Cấu hình kỹ thuật (1 phút)
1. Nhấp vào sản phẩm *iPhone 15 Pro Max 256GB*.
2. Trình bày trang chi tiết:
   - Thông số kỹ thuật chi tiết (`specs`): Chip A17 Pro, màn hình Super Retina XDR OLED 120Hz, RAM 8GB.
   - Thời hạn bảo hành: 12 tháng chính hãng.
   - Tồn kho thực tế lấy từ database.

### Bước 3: Đăng nhập & Cầu nối xác thực Firebase Auth (1.5 phút)
1. Bấm nút **"Thêm vào giỏ"** hoặc biểu tượng giỏ hàng → Chuyển hướng đăng nhập an toàn kèm giữ lại `redirect` về trang đang xem.
2. Đăng nhập tài khoản `user1@ltweb.com` / `123456`.
3. Giải thích kiến trúc xác thực nhóm triển khai:
   - Hệ thống hỗ trợ đăng nhập Email/Password truyền thống lẫn cầu nối **Firebase Authentication** (`firebase_uid`).
   - Phía PHP xác minh danh tính và cấp PHP Session bảo mật qua cookie `HttpOnly`, `SameSite`, bảo vệ chống tấn công XSS/CSRF.
4. Mở trang **Thông tin cá nhân** (`pages/users/profile.html`):
   - Hiển thị tên, email, số điện thoại và địa chỉ nhận hàng đã lưu.

### Bước 4: Mua hàng, Giỏ hàng & Checkout COD (2 phút)
1. Thêm 1 sản phẩm vào giỏ hàng (ví dụ: *Cáp USB-C 100W 2m* giá 199.000 ₫ hoặc *AirPods Pro Gen 2*).
2. Vào giỏ hàng (`pages/cart.html`):
   - Thử tăng/giảm số lượng → Tổng tiền tự động tính lại chính xác theo dữ liệu server.
   - Nhập thông tin giao hàng:
     - Họ tên: `Nguyễn Văn A`
     - Số điện thoại có khoảng trắng: `0912 345 678` *(Điểm nhấn demo: Hệ thống đã khắc phục hoàn toàn lỗi khoảng trắng, tự động chuẩn hóa phía client & server)*.
     - Địa chỉ: `123 Lê Lợi, P. Bến Nghé, Quận 1, TP.HCM`.
3. Nhấn **"Đặt hàng"**:
   - Hệ thống sử dụng Transaction và Lock để kiểm tra tồn kho, trừ kho an toàn, lưu mã `checkout_key` chống đặt trùng.
   - Tạo đơn hàng COD thành công và chuyển thẳng sang trang chi tiết đơn.

### Bước 5: Lịch sử đơn hàng & Thông tin vận chuyển (1.5 phút)
1. Mở **"Đơn hàng của tôi"** (`pages/users/order-history.html`):
   - Xem đơn hàng vừa đặt ở trạng thái **"Chờ xác nhận"** (pending).
   - Xem các đơn hàng mẫu đã có sẵn:
     - Đơn số 3: Trạng thái **"Đang giao"** (shipped) có thông tin hãng vận chuyển (*Giao Hàng Nhanh*) và mã vận đơn (*GHN892184912*).
     - Đơn số 4: Trạng thái **"Hoàn thành"** (completed).
     - Đơn số 5: Trạng thái **"Đã hủy"** (cancelled).
   - Minh chứng tính toàn vẹn: Dù sản phẩm có thay đổi giá sau này, snapshot tên và đơn giá trong đơn hàng không bị ảnh hưởng.

### Bước 6: Quản trị Admin — Xử lý đơn & Vận chuyển (2 phút)
1. Chuyển sang Tab Admin (`pages/admin/index.html`), đăng nhập tài khoản `admin@ltweb.com` / `123456`.
2. **Dashboard tổng quan:**
   - Số sản phẩm đang bán.
   - Tổng đơn hàng & Số đơn chờ xác nhận.
   - Doanh thu thực tế tính từ các đơn hoàn thành.
3. **Mục Đơn hàng (`pages/admin/orders.html`):**
   - Mở chi tiết đơn hàng khách vừa đặt ở Bước 4.
   - Cập nhật trạng thái: `Chờ xác nhận` → `Đang chuẩn bị`.
   - Cập nhật trạng thái: `Đang chuẩn bị` → `Đang giao` kèm nhập Đơn vị vận chuyển (*Viettel Post*) và Mã vận đơn (*VTP123456789*).
   - Trình diễn hủy đơn: Thử hủy 1 đơn đang chờ → Tồn kho sản phẩm lập tức được hoàn trả tự động đúng 1 lần vào cơ sở dữ liệu.
4. **Mục Quản lý Sản phẩm (`pages/admin/products.html`):**
   - Trình diễn thao tác Thêm sản phẩm mới hoặc Sửa sản phẩm.
   - Trình diễn nút **"Ngừng bán"**: Bấm ngừng bán sản phẩm → Trạng thái chuyển sang `inactive`, sản phẩm không còn cho phép đặt mua nhưng lịch sử các đơn hàng cũ vẫn nguyên vẹn.

---

## 4. Bảng Câu Hỏi Thường Gặp Của Giảng Viên & Cách Trả Lời

| Câu hỏi của Hội đồng | Điểm mấu chốt để trả lời tự tin |
| :--- | :--- |
| **Cơ sở dữ liệu lưu trữ ở đâu khi dùng Firebase?** | "Dạ nhóm dùng Firebase Authentication để quản lý danh tính tài khoản. Toàn bộ cơ sở dữ liệu nghiệp vụ (sản phẩm, giỏ hàng, đơn hàng, phân quyền admin) vẫn lưu trữ an toàn trong MySQL/MariaDB thông qua khóa ngoại `users.id` liên kết với `users.firebase_uid`." |
| **Nếu khách hàng đặt hàng cùng lúc thì có bị âm kho không?** | "Dạ không, backend PHP sử dụng Database Transaction kết hợp khóa dòng `FOR UPDATE` (`lockUser`) và kiểm tra điều kiện `UPDATE stock = stock - ? WHERE stock >= ?` để ngăn chặn triệt để tình trạng overselling." |
| **Khi sản phẩm đổi giá thì đơn hàng cũ có bị đổi giá theo không?** | "Dạ không, bảng `order_items` lưu snapshot giá (`unit_price`) và tên (`product_name`) ngay tại thời điểm khách bấm đặt hàng, đảm bảo tính bất biến của chứng từ bán hàng." |
| **Website đã có biện pháp bảo mật nào?** | "Dạ hệ thống áp dụng: 1) CSRF Token cho tất cả request POST/PUT/DELETE; 2) PDO Prepared Statements chống SQL Injection 100%; 3) Escape dữ liệu đầu ra chống XSS; 4) Chặn truy cập thư mục nhạy cảm (`.env`, `config/`) bằng `.htaccess`." |
