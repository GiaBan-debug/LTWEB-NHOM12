# Nhóm 12 Store — PHP/MySQL demo

> **Cập nhật TV5 (18/11/2026):** mặc định dùng Firebase **Google sign-in** của project `bta-store`. Xem [hướng dẫn TV5](docs/TV5_FIREBASE.md) để chạy, cấp admin, migration và test. Các tài khoản mật khẩu seed trong bảng bên dưới chỉ áp dụng khi đặt `AUTH_MODE=legacy`; không dùng để đăng nhập Google.

Website bán điện thoại, laptop, tablet và phụ kiện, dành cho đồ án/portfolio. Có dữ liệu MySQL thật, mua hàng COD và quản trị; chưa tích hợp thanh toán hoặc vận chuyển thực tế.

## Chạy nhanh trên máy Windows hiện tại

Máy đang có PHP tại `C:\xampp\php\php.exe`. MariaDB portable nằm trong `.local-test/`, không cài Windows service và chỉ lắng nghe `127.0.0.1`.

Máy clone mới cần chạy `powershell -ExecutionPolicy Bypass -File scripts/install-dependencies.ps1` một lần để cài thư viện xác minh JWT. Website dùng mạng khi đăng nhập Google. Lần đầu Google tạo tài khoản user; cấp admin bằng script hướng dẫn trong docs/TV5_FIREBASE.md.

Mở **CMD hoặc terminal VS Code**, tại thư mục dự án chạy:

```cmd
run-demo.cmd
```

Mở **http://localhost:8000/**. Giữ terminal mở; nhấn Ctrl+C để dừng. Script bật driver PHP cần thiết, khởi động database cổng 3307, tạo database riêng `ltweb_nhom12_demo` nếu chưa có và giữ lại dữ liệu giữa các lần chạy. Không xóa/reset database đang có.

Nếu cổng 8000 đang được server cũ sử dụng:

```cmd
run-demo.cmd -Port 8012
```

Nếu clone trên máy khác và chưa có MariaDB portable, chạy một lần:

```cmd
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\setup-local.ps1
```

Script tải MariaDB 11.4.5 portable (~90 MB) từ archive chính thức vào `.local-test/`. Thư mục này bị bỏ qua bởi Git, không upload lên hosting. Cần PHP 8.1 trở lên và Node.js chỉ khi chạy các test Playwright; PHP và MariaDB là đủ để chạy website.

Nếu PHP nằm chỗ khác:

```cmd
run-demo.cmd -PhpPath "D:\xampp\php\php.exe"
```

## Tài khoản demo

| Vai trò | Email | Mật khẩu |
| --- | --- | --- |
| Admin | admin@ltweb.com | 123456 |
| Khách hàng | user1@ltweb.com | 123456 |
| Khách hàng | user2@ltweb.com | 123456 |

Áp dụng cho database mới tạo từ seed hiện tại. Database đã import seed cũ không tự đổi mật khẩu; hãy đăng ký tài khoản khách mới hoặc cập nhật hash trong DB của bạn. Không dùng mật khẩu demo khi triển khai kinh doanh thật.

## Dùng MySQL/XAMPP sẵn có

1. Bật MySQL; tạo và chọn database `ltweb_nhom12` trong phpMyAdmin.
2. Import `database/schema.sql` rồi `database/seed.sql` vào database mới. Hai file không chứa CREATE DATABASE/USE để dùng được trên shared hosting.
3. Copy `.env.example` thành `.env`, sửa hostname, port, database, user/password.
4. Chạy `powershell -ExecutionPolicy Bypass -File scripts/start.ps1` hoặc dùng Apache với đường dẫn `/LTWEB-NHOM12/`.

Không cần phpMyAdmin để chạy bản portable. phpMyAdmin là công cụ quản lý riêng; website chỉ cần PHP kết nối database. Với database cũ theo schema ban đầu, sao lưu rồi chạy `database/migrate-demo.sql` **một lần** thay vì import lại seed. Migration không đổi mật khẩu/tồn kho hay tự gán thumbnail cho dữ liệu cũ.

## Các luồng đã triển khai

- Trang chủ và danh mục lấy cùng dữ liệu sản phẩm với danh sách/chi tiết.
- Tìm kiếm, lọc danh mục/giá, sắp xếp và phân trang phía server.
- Đăng ký, đăng nhập, đăng xuất, session và đổi tên hồ sơ.
- Thêm/xóa sản phẩm, sửa số lượng, kiểm tra tồn kho trong giỏ.
- Checkout COD: phí ship mặc định 30.000 ₫, miễn phí từ 2.000.000 ₫; server tính và lưu phí tại thời điểm mua, dùng transaction và chặn đơn trùng.
- Xác nhận, lịch sử, chi tiết và trạng thái đơn theo tài khoản.
- Admin: dashboard, thêm/sửa/ngừng bán sản phẩm, chọn ảnh có sẵn, thêm danh mục, xem đơn và cập nhật trạng thái.
- Trạng thái: chờ xác nhận → chuẩn bị → đang giao → hoàn thành; có thể hủy ở hai bước đầu. Hủy hoàn kho một lần; sản phẩm ngừng bán vẫn giữ lịch sử đơn.
- Bảo vệ API bằng session/role, CSRF cho thao tác ghi, prepared statements và escape dữ liệu giao diện.

Ảnh mới cho demo có thể đặt vào `uploads/products/` và bổ sung lựa chọn trong `admin.js`; chưa có chức năng upload từ trình duyệt. Banner/bài viết/quản lý user/settings nâng cao chưa nằm trong luồng demo và chưa có giao diện hoàn chỉnh.

## Đưa lên hosting PHP/MySQL miễn phí

Tạo ZIP chỉ chứa ứng dụng và SQL mẫu bằng `powershell -ExecutionPolicy Bypass -File scripts/package-host.ps1`. File nằm ở `dist/nhom12-store-demo.zip`; gói không chứa `.env`, `.git`, database local hoặc thư viện kiểm thử.

1. Chọn host có PHP ≥ 8.1, PDO MySQL, mbstring, session và MySQL/MariaDB InnoDB. Ví dụ: tài khoản InfinityFree đã tạo.
2. Upload `index.php`, `.htaccess`, `frontend/`, `backend/`, `uploads/` vào document root (`htdocs/` trên InfinityFree). Không upload `.git`, `.local-test`, test, docs hoặc thông tin tài khoản của máy cá nhân.
3. Tạo database từ Control Panel của host; mở phpMyAdmin và import schema rồi seed. Database trên host có thể có prefix do nhà cung cấp cấp.
4. Tạo `.env` trong root trên host từ mẫu và điền **hostname MySQL của host**, tên DB, user/password; không giữ `127.0.0.1`/`root` của local.
5. Giữ đúng chữ thường `backend` khi upload. Nếu ZIP cũ còn `BACKEND`, đổi tên thành `backend` để URL khớp trên Linux.
6. Mở domain, thử health `/backend/api/health.php`, rồi đăng nhập, mua hàng và xử lý đơn. Kiểm tra `.env` không truy cập được qua HTTP; file cấu hình backend được chặn bằng `.htaccess`.

Các URL frontend tự tính theo gốc dự án, hỗ trợ domain root và thư mục con. Cần Composer khi chuẩn bị dependency; host có thể dùng thư mục vendor đã build sẵn nên không cần Composer/SSH khi chạy. Chưa triển khai lên host trong phiên này.

## Kiểm thử

Các test ghi dữ liệu: chỉ chạy trên database kiểm thử riêng, không chạy vào database đang demo cho người khác.

```cmd
npm install --prefix .local-test --no-audit --no-fund playwright@1.65.1
node .local-test/node_modules/playwright/cli.js install chromium
```

Khởi động MariaDB portable bằng `run-demo.cmd`, sau đó mở terminal **PowerShell khác** để tạo DB test và chạy PHP server test:

```powershell
$env:DB_PORT='3307'
$env:DB_NAME='ltweb_nhom12_test'
$env:AUTH_MODE='legacy'
& C:/xampp/php/php.exe -d extension_dir=C:/xampp/php/ext -d extension=pdo_mysql -d extension=mbstring scripts/setup-demo.php
./scripts/start.ps1 -Port 8013
```

Ở terminal khác chạy:

```cmd
node tests/demo-api.mjs
node tests/demo-browser.mjs
```

Test mặc định dùng `http://localhost:8013`. Có thể đặt `TEST_URL` để đổi server và `CHROME_PATH` để dùng Chromium có sẵn. Không cần Node/Playwright trên hosting.

## Cấu trúc

```text
backend/api/          API JSON và xác thực quyền
backend/includes/     Helper, session, giỏ và chi tiết đơn
backend/config/       Kết nối MySQL
frontend/             Trang chủ, giao diện khách hàng/Admin
database/             Schema, seed, migration từ bản cũ
uploads/products/     Ảnh sản phẩm demo
scripts/              Khởi động và chuẩn bị môi trường local
tests/                API/browser integration tests
docs/                 Báo cáo và đặc tả
```
