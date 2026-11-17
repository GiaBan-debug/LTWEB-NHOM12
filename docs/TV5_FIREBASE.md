# TV5 — Firebase và backend BTA Store

Trạng thái triển khai: 18/11/2026. Firebase project đã được người dùng cung cấp là **bta-store**; Google là phương thức ưu tiên. Không sử dụng project super-calendar cũ.

## Chạy ngay trên máy hiện tại

Thư viện Composer và MariaDB portable đã có trong workspace này. Mở CMD tại gốc dự án:

```cmd
run-demo.cmd
```

Nếu cổng 8000 đang dùng: `run-demo.cmd -Port 8012`. Mở `http://localhost:8000/` (hoặc cổng đã chọn), vào Đăng nhập → **Đăng nhập bằng Google**. Chọn tài khoản trực tiếp trong popup Google. Website tự tạo hồ sơ MySQL với quyền user khi UID mới đăng nhập lần đầu.

Google đã được người dùng xác nhận bật và thêm localhost vào Authorized domains. Nếu chạy trên domain khác, thêm đúng domain vào Firebase Authentication → Settings → Authorized domains. Không nhập protocol hoặc port trong danh sách domain. Máy cần có mạng để dùng SDK và Google OAuth.

Trên máy clone mới, chạy lần lượt:

```cmd
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\setup-local.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\install-dependencies.ps1
run-demo.cmd
```

Script start-demo tự bổ sung các cột còn thiếu vào database demo, không reset giỏ/đơn/role/mật khẩu cũ. Với DB khác hoặc hosting, sao lưu trước rồi chạy migration theo phần bên dưới.

## Cấp quyền admin sau lần đăng nhập Google đầu tiên

Mặc định mọi tài khoản Firebase mới chỉ có quyền user. Giữ terminal run-demo mở, mở terminal khác tại root rồi chạy:

```cmd
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\firebase-admin.ps1 -Email "email-google-cua-ban@gmail.com"
```

Thay email bằng chính tài khoản vừa đăng nhập. Script local chỉ làm việc với database demo ở 127.0.0.1:3307; truyền DatabaseName/DatabasePort nếu cần đổi. Để hạ quyền, dùng `-Role user`. Role được đọc lại từ MySQL ở request tiếp theo; tải lại trang để thấy nút Quản trị.

Không có API công khai cho khách tự nâng quyền. Không tự cấp admin dựa trên email trùng seed, custom role trên frontend hoặc claim do client tự gửi.

Trên hosting không có SSH, người quản trị database có thể chọn đúng tài khoản đã đăng nhập Firebase trong bảng users bằng phpMyAdmin rồi đổi role thành admin. Không tạo endpoint nâng quyền cho client để thay thế thao tác quản trị này.

## Luồng xác thực

1. `auth-config.php` trả cấu hình Firebase Web công khai và các provider UI.
2. Frontend dùng Firebase Web SDK 12.19.0 đăng nhập bằng Google popup. Không khởi tạo Analytics/Firestore.
3. ID token gửi qua POST `firebase-session.php`, kèm cookie và X-CSRF-Token.
4. PHP dùng firebase/php-jwt 7.2.1 xác minh RS256 với chứng thư công khai từ Google. Kiểm tra chữ ký, alg/kid, exp, iat, auth_time, issuer, audience, sub và email.
5. MySQL liên kết bằng `firebase_uid` duy nhất, phân biệt hoa/thường; `users.id` và khóa ngoại giỏ/đơn giữ nguyên.
6. PHP tạo session HttpOnly/SameSite=Lax; không lưu ID token hoặc mật khẩu Firebase vào session/MySQL.
7. Session hết hạn không vượt thời hạn ID token đã xác minh, mặc định tối đa 3600 giây. Frontend có thể đổi ID token mới thành PHP session để khôi phục request 401 một lần.
8. Quyền/hồ sơ được đọc lại từ MySQL, không tin cache trình duyệt. Đăng xuất chờ các yêu cầu restore hiện tại rồi xóa cả PHP session và Firebase persistence.

Đây là PHP session của ứng dụng sau khi xác minh token, không phải Firebase Admin session cookie. Bản này chưa tích hợp kiểm tra thu hồi token/disable tài khoản Firebase theo từng request; phiên có giới hạn thời gian. Xóa/hạ quyền user trong MySQL có tác dụng ngay ở request tiếp theo. Không coi đây là chứng nhận bảo mật production.

Nguồn: [Google sign-in](https://firebase.google.com/docs/auth/web/google-signin), [Verify ID tokens](https://firebase.google.com/docs/auth/admin/verify-id-tokens), [PHP-JWT](https://github.com/googleapis/php-jwt).

## Google và Email/Password

`AUTH_MODE=firebase` là mặc định. `FIREBASE_EMAIL_PASSWORD_ENABLED=false` ẩn form mật khẩu, ưu tiên Google như người dùng chọn. Không cần bật Email/Password để dùng nút Google.

Nếu sau này muốn dùng thêm email/mật khẩu:

1. Bật Email/Password trong Firebase Console.
2. Đặt `FIREBASE_EMAIL_PASSWORD_ENABLED=true` trong .env rồi khởi động lại PHP.
3. Website có đăng ký email, gửi email xác minh, gửi lại thư và quên mật khẩu.

Mặc định chỉ cho checkout khi Firebase email_verified=true; Google thường cung cấp email đã xác minh. Trang `frontend/pages/verify-email.html` dùng để gửi lại và cập nhật trạng thái. Khi dùng Google, mật khẩu được quản lý tại Google; không dùng form khôi phục mật khẩu Firebase cho tài khoản Google-only.

`AUTH_MODE=legacy` là chế độ riêng để hồi quy bản PHP cũ. Firebase mode trả HTTP 410 ở login.php/register.php cũ, không tự fallback sang mật khẩu MySQL khi Firebase lỗi. Legacy mode không cho đăng nhập bằng mật khẩu vào user đã được bind UID.

## Tài khoản cũ và dữ liệu

- Token UID mới nhưng trùng email tài khoản cũ trả 409, không tự gộp hoặc kế thừa quyền admin.
- Tài khoản Google mới với email khác seed sẽ có user id riêng; không tự nhận giỏ/đơn của email cũ.
- Để giữ dữ liệu cũ, quản trị có thể dùng CLI `scripts/firebase-user.php --user-id=ID --link`, cung cấp ID token qua stdin. Script xác minh token và yêu cầu email Firebase verified khớp email tài khoản cũ; chỉ xóa hash cũ sau khi bind thành công.
- Không truyền ID token/private key qua command argument, không commit token vào file. Đây là thao tác quản trị, không phải chức năng người dùng tự gộp tài khoản.
- Để có admin demo bằng Gmail, ưu tiên đăng nhập Google rồi dùng script firebase-admin, không cố dùng email giả admin@ltweb.com để nhận thư xác minh.

## API hồ sơ, giao hàng và hủy đơn

- `GET me.php`: id, name, email, role, phone, address, email_verified, auth_provider.
- `PUT me.php`: cập nhật name, phone, address; không nhận sửa role/email/UID. Điện thoại nhận khoảng trắng/dấu chấm/gạch và +84, lưu dạng 0xxxxxxxxx.
- Giỏ trả subtotal, shipping_fee và total; server là nơi tính tiền.
- Mặc định ship 30.000 ₫ cho đơn dưới 2.000.000 ₫; miễn ship từ 2.000.000 ₫. Có thể cấu hình SHIPPING_BASE_FEE/SHIPPING_FREE_FROM.
- Checkout lưu snapshot shipping_fee vào đơn; total_price/total_amount gồm phí ship. Đổi chính sách hoặc sửa giá sau này không đổi đơn cũ.
- `PUT orders.php` với `{id, action:"cancel"}`: khách chỉ hủy đơn của mình ở pending; cancelled gọi lại không hoàn kho lần hai. Đơn processing trở đi phải liên hệ cửa hàng.
- Các lỗi field nằm trong `data.errors`; email chưa verified trả `data.code=EMAIL_NOT_VERIFIED`.

Frontend đã được nối tối thiểu để hiển thị phí ship, sửa hồ sơ, tự điền checkout và hủy pending. TV3 có thể tiếp tục hoàn thiện trải nghiệm; TV4 tiếp tục upload/quản trị/lịch sử trạng thái ngoài phạm vi TV5.

## Migration và hosting

- DB mới: schema.sql đã có UID, profile và phí ship.
- DB demo hiện có: start-demo gọi migration có kiểm tra cột/index trước khi ALTER.
- DB khác: cấu hình .env, backup rồi chạy CLI `scripts/migrate-tv5.php`, hoặc áp dụng `database/migrate-tv5.sql` đúng một lần trên schema demo 16/11. Không chạy SQL migration mù lên schema đã có cột.
- PHP cần PDO MySQL, mbstring, OpenSSL và cURL. Cài Composer dependency bằng composer.lock; host không có Composer thì upload vendor từ máy build.
- `scripts/package-host.ps1` đã đóng thêm vendor và chỉ tạo var/.htaccess, không đưa key cache/token/runtime vào ZIP.
- CA bundle từ curl.se được đặt trong backend/config/cacert.pem cho PHP Windows; không tắt SSL verification. Có thể cấu hình TLS_CA_BUNDLE nếu môi trường cần trust store riêng.
- var phải ghi được để cache chứng thư công khai. Router/.htaccess chặn var, vendor, config và file Composer qua HTTP.
- Frontend/backend chạy cùng origin. CORS credentials chỉ bật cho danh sách exact origin trong APP_ALLOWED_ORIGINS; mặc định để trống.
- Firebase Web config/API key là cấu hình client công khai; service-account private key không được đưa lên frontend/Git. Bản xác minh ID token này không cần service-account private key.

## Kiểm thử

Không chạy test ghi dữ liệu vào database demo đang dùng trình bày. Đợt phát triển dùng DB riêng `ltweb_nhom12_tv5_20261118`.

- `tests/tv5-unit.php`: RSA/token claims, chống alg none/HS256, UID collision, role SQL, session hết hạn/khác project, điện thoại và phí ship. Với TV5_TEST_DATABASE=true còn kiểm tra mapping/session trên MySQL; mặc định chỉ chạy phần không cần DB.
- `tests/tv5-api.mjs`: profile, phí ship/snapshot, điện thoại, hủy và hoàn kho một lần, auth cũ bị tắt, token giả bị từ chối và bảo vệ file riêng tư.
- `tests/demo-api.mjs`/`demo-browser.mjs`: hồi quy luồng trước đó trong AUTH_MODE=legacy, đã điều chỉnh kỳ vọng phí ship mới. Không gọi kết quả legacy này là xác nhận Google login hoàn chỉnh.
- `tests/firebase-browser.mjs`: SDK Firebase thật, Google-only UI và popup chuyển tới accounts.google.com; đóng lại mà không đăng nhập/chấp thuận bằng tài khoản người dùng.
- `tests/firebase-live-smoke.php`: lấy public keys bằng HTTPS và từ chối token giả với keys thật; probe Email/Password tài khoản không tồn tại không tạo user/gửi thư. PASSWORD_LOGIN_DISABLED là bình thường khi project chỉ bật Google.

Kết quả hiện tại: 36 assertions TV5 unit/MySQL, 49 assertions TV5 API, 73 assertions API hồi quy legacy và 29 assertions browser hồi quy legacy đều qua. 7 kiểm tra giao diện Firebase qua với SDK thật và popup chuyển tới **accounts.google.com**, không nhập/chọn tài khoản. Chứng thư Google trả HTTP 200, token giả bị từ chối 401. Các kết quả này không thay thế bước đăng nhập Google thật do người dùng thực hiện.

Lần đăng nhập Google thật/chọn tài khoản là bước người dùng thực hiện trực tiếp. Chưa có kết quả kiểm thử đầy đủ với tài khoản Google cá nhân hoặc hosting miễn phí trong phiên này; không ghi là đã thành công khi chưa thực hiện.
