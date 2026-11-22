# Đăng nhập SĐT qua Cloudflare Quick Tunnel

Code đã nối Firebase Phone Authentication cho cả trang đăng nhập và đăng ký. Không dùng mật khẩu. Xác nhận OTP lần đầu tạo tài khoản user; các lần sau dùng lại cùng Firebase UID, giữ hồ sơ, giỏ và đơn. Google vẫn hoạt động. Không tự gộp Google và SĐT chỉ vì trùng thông tin liên hệ.

## 1. Chạy website

Mở `run-demo.bat`, giữ cửa sổ chạy. `.env` cần:

```dotenv
AUTH_MODE=firebase
FIREBASE_PHONE_ENABLED=true
```

Khởi động lại server nếu vừa sửa cấu hình. Trình khởi động tự migrate users.email thành nullable và thêm firebase_phone, giữ nguyên tài khoản và dữ liệu. Không import lại seed/schema lên database đang dùng. Triển khai thủ công thì chạy `php scripts/database.php` trước khi dùng code mới.

## 2. Mở tunnel

Nếu đã cài cloudflared, mở terminal khác:

```cmd
cloudflared tunnel --url http://localhost:8000
```

Copy link HTTPS dạng `https://abc-xyz.trycloudflare.com`. Giữ PHP, database và tunnel cùng chạy. Không thay FIREBASE_AUTH_DOMAIN bằng domain tunnel; giữ `bta-store.firebaseapp.com` như cấu hình hiện có. Frontend/backend dùng chung link tunnel, không cần APP_ALLOWED_ORIGINS hoặc import database lên Firebase.

## 3. Cấu hình Firebase Console của bta-store

1. Authentication → Sign-in method → bật Phone; giữ Google nếu đang sử dụng.
2. Authentication → Settings → Authorized domains → thêm chính xác `abc-xyz.trycloudflare.com`, không có `https://`, cổng hoặc đường dẫn. Khi Quick Tunnel đổi URL, thêm hostname mới.
3. Nếu thử SMS thật: kiểm tra Billing đã liên kết/bật Blaze và SMS region policy cho phép Vietnam. SMS thật có thể tính phí; hostname hợp lệ không thay thế điều kiện Billing/hạn mức.
4. Nếu chỉ demo: trong Phone → Phone numbers for testing, thêm một SĐT giả đúng định dạng E.164 và mã 6 chữ số tự chọn. Không dùng số thật của người khác. Nhập đúng SĐT đó trên website và dùng mã đã cấu hình; Firebase không gửi SMS cho số kiểm thử.

## 4. Thử đăng nhập

Mở website bằng **link HTTPS tunnel**, không dùng localhost cho luồng Phone. Nhập SĐT Việt Nam dạng `09...` hoặc `+84...`, nhấn đăng nhập/đăng ký, hoàn thành reCAPTCHA nếu hiện, nhập OTP rồi nhấn Xác nhận OTP. Có gửi lại sau 60 giây và đổi số. Mã sai/hết hạn, lỗi domain, quota, mạng được báo trên form. Sau khi đăng nhập, bổ sung tên và địa chỉ trong hồ sơ để dùng checkout.

Mã OTP do Firebase xác thực; PHP không nhận OTP trực tiếp và không có mã vượt xác thực trong code. Code không tắt reCAPTCHA khi thử nghiệm; số kiểm thử được cấu hình trên Firebase Console.

Tài khoản chỉ có SĐT không cần xác minh email để mua hàng vì SĐT đã được xác minh. Thay SĐT giao hàng trong hồ sơ không đổi SĐT đăng nhập hoặc cấp trạng thái xác minh.

Nếu muốn cấp admin cho tài khoản SĐT sau khi đăng nhập lần đầu, lấy users.id của đúng Firebase UID rồi chạy CLI quản trị:

```cmd
C:\xampp\php\php.exe scripts/firebase-user.php --user-id=ID --role=admin
```

CLI cần PHP bật pdo_mysql và mbstring như môi trường website. Không cấp admin bằng dữ liệu frontend hoặc bằng SĐT giao hàng.

## Kiểm tra đã thực hiện

`tests/tv5-unit.php` kiểm tra chữ ký/token SĐT, MySQL và session trên database kiểm thử. `tests/phone-auth-browser.mjs` kiểm tra code Auth và form bằng SDK/SMS mô phỏng (không gửi SMS thật). Lượt nhận OTP thực tế cần bạn hoàn tất Console và mở domain tunnel của mình.

Nguồn: [Firebase Phone Authentication](https://firebase.google.com/docs/auth/web/phone-auth), [Firebase SMS billing](https://firebase.google.com/docs/auth/faq-and-troubleshooting), [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).
