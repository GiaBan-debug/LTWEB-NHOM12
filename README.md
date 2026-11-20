# Nhóm 12 Store — PHP/MySQL demo

> Mặc định dùng Firebase **Google sign-in** của project `bta-store`. Trong `AUTH_MODE=legacy`, nhập **0916111111** (admin) hoặc **0916222222** (user), không cần mật khẩu/OTP. Xem [cấu hình xác thực](#firebase-authentication).

Website bán điện thoại, laptop, tablet và phụ kiện, dành cho đồ án/portfolio. Có dữ liệu MySQL thật, mua hàng COD và quản trị; chưa tích hợp thanh toán hoặc vận chuyển thực tế.

## Chạy nhanh trên máy Windows hiện tại

Máy đang có PHP tại `C:\xampp\php\php.exe`. MariaDB portable nằm trong `.local-test/`, không cài Windows service và chỉ lắng nghe `127.0.0.1`.

Máy clone mới cần chạy `powershell -ExecutionPolicy Bypass -File scripts/setup.ps1` một lần để cài thư viện xác minh JWT. Website dùng mạng khi đăng nhập Google. Lần đầu Google tạo tài khoản user; xem cách cấp admin bên dưới.

Mở **CMD hoặc terminal VS Code**, tại thư mục dự án chạy:

```cmd
run-demo.bat
```

Mở **http://localhost:8000/**. Giữ terminal mở; nhấn Ctrl+C để dừng. Script đọc kết nối database từ `.env`, bật driver PHP và cập nhật các bảng/cột còn thiếu. Với cấu hình portable cổng 3307, script tự mở MariaDB nếu cần; với XAMPP/MySQL riêng, bật database trước. Dữ liệu hiện có được giữ nguyên, không tự nhập lại seed. Database mới được tạo schema trống.

## Các script cần dùng

| File | Công dụng |
| --- | --- |
| `scripts/setup.ps1` | Cài thư viện PHP; thêm `-Portable` nếu cần tải MariaDB riêng |
| `scripts/start.ps1` | Chạy website theo `.env`; `run-demo.bat` gọi file này |
| `scripts/database.php` | Cập nhật schema; `--initialize` tạo database mới nếu cần |
| `scripts/firebase-admin.ps1` | Cấp/thu quyền admin theo email |
| `scripts/firebase-user.php` | CLI liên kết tài khoản Firebase và cập nhật quyền |
| `scripts/package-host.ps1` | Đóng gói ứng dụng để upload hosting |

Không cần chạy các script setup/test riêng. Database mới có thể nhập `database/seed.sql` một lần để có sản phẩm mẫu; hoặc chạy `scripts/database.php --initialize --seed` trên database trống. Script bỏ qua seed nếu database đã có bảng. `.local-test/` hiện chứa dữ liệu MariaDB và công cụ tải về trên máy này; không xóa thư mục đó khi chưa sao lưu database.

Nếu cổng 8000 đang được server cũ sử dụng:

```cmd
run-demo.bat -Port 8012
```

Nếu clone trên máy khác và chưa có MariaDB portable, chạy một lần:

```cmd
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\setup.ps1 -Portable
```

Script tải MariaDB 11.4.5 portable (~90 MB) từ archive chính thức vào `.local-test/`. Thư mục này bị bỏ qua bởi Git, không upload lên hosting. Cần PHP 8.1 trở lên và Node.js chỉ khi chạy các test Playwright; PHP và MariaDB là đủ để chạy website.

Nếu PHP nằm chỗ khác:

```cmd
run-demo.bat -PhpPath "D:\xampp\php\php.exe"
```

## Firebase Authentication

Đặt `AUTH_MODE=firebase` trong `.env`, bật Google trong Firebase Authentication và thêm domain website vào Authorized domains. Cấu hình Web App nằm trong `.env.example`; không đưa service-account/private key lên frontend hoặc Git. PHP cần PDO MySQL, mbstring, OpenSSL, cURL; upload ảnh cần fileinfo. Thư mục `var/` cần quyền ghi để cache chứng thư công khai.

Chọn cơ chế bằng `AUTH_MODE`:
1. **`legacy` (chỉ kiểm thử):** nhập SĐT 0916111111 (admin) hoặc 0916222222 (user). Backend tạo hai user thử riêng ở lần đầu, giữ cùng ID/giỏ/đơn cho các lần sau. Không xác minh quyền sở hữu SĐT, không dùng chế độ này khi triển khai thật.
2. **`firebase` (mặc định):** đăng nhập Google qua Firebase Web SDK, gửi **ID token có chữ ký** tới `backend/api/firebase-session.php`. Route tương thích `firebase-auth.php` cũng xác minh đầy đủ; không chấp nhận UID do client tự gửi. Email/Password Firebase hiện chưa bật.

UID trong dữ liệu seed để `NULL`; tài khoản email/mật khẩu seed cũ không dùng để đăng nhập Google. Backend xác minh ID token rồi liên kết theo `users.firebase_uid`, giữ hồ sơ/quyền/giỏ/đơn trong MySQL. Không tự liên kết tài khoản cũ chỉ vì trùng email. Phiên PHP mặc định tối đa 3.600 giây, không vượt hạn ID token; đăng xuất xóa cả phiên Firebase và PHP. Form SĐT hiện chỉ chuẩn bị bước OTP, chưa gửi SMS.

Để cấp admin, đăng nhập Google vào website trước, rồi chạy PowerShell tại gốc dự án:

```powershell
.\scripts\firebase-admin.ps1 -Email "emailcuaban@gmail.com"
```

Script đọc database từ `.env`. Thêm `-Role user` để thu quyền admin. Tải lại website sau khi đổi quyền. Các tham số `-DatabaseName`, `-DatabasePort`, `-PhpPath` chỉ cần khi muốn ghi đè cấu hình.

Để liên kết một tài khoản cũ, CLI `scripts/firebase-user.php --user-id=ID --link` nhận Firebase ID token qua stdin và yêu cầu email đã xác minh khớp tài khoản cũ. Không ghi token vào file hoặc tham số command line. Sao lưu trước khi thay đổi liên kết tài khoản.

Tài liệu chi tiết:
- Sơ đồ ERD & Đặc tả CSDL: [`docs/DATABASE_ERD.md`](docs/DATABASE_ERD.md)
- File migration TV6 (Firebase UID, Profile, Vận chuyển, Specs): [`database/migration-tv6.sql`](database/migration-tv6.sql)

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

Khởi động MariaDB portable bằng `run-demo.bat`, sau đó mở terminal **PowerShell khác** để tạo DB test và chạy PHP server test:

```powershell
$env:DB_PORT='3307'
$env:DB_NAME='ltweb_nhom12_test'
$env:AUTH_MODE='legacy'
& C:/xampp/php/php.exe -d extension_dir=C:/xampp/php/ext -d extension=pdo_mysql -d extension=mbstring scripts/database.php --initialize --seed
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
