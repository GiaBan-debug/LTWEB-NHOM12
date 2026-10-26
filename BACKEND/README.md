# Backend PHP thuần

REST API dùng PHP 8.1+, PDO, MySQL và JWT. Cơ sở dữ liệu dùng chung schema có sẵn tại `DATABASE/schema.sql`.

## Yêu cầu

- PHP 8.1+ với extension `pdo_mysql`, `mbstring` và `iconv`.
- MySQL Server 5.7+ hoặc 8.0+.

## Cấu hình và chạy

1. Khởi động MySQL và import `DATABASE/schema.sql`; dữ liệu mẫu có thể nạp bằng `DATABASE/seed.sql`.
2. Nếu chưa có cấu hình riêng, sao chép mẫu: `Copy-Item BACKEND\.env.example BACKEND\.env`.
3. Cấu hình `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` theo MySQL. Thay `JWT_SECRET` bằng chuỗi bí mật ngẫu nhiên tối thiểu 32 ký tự; không dùng giá trị mẫu trong production. Đặt `CORS_ORIGINS` phù hợp với địa chỉ Frontend. Giữ file `.env` ở máy cá nhân, không commit.
4. Từ thư mục gốc chạy:

```powershell
php -S 127.0.0.1:8000 -t BACKEND\public BACKEND\public\router.php
```

Kiểm tra kết nối bằng `GET http://127.0.0.1:8000/api/health`.

## Tài khoản admin đầu tiên

Đăng ký qua API sẽ tạo tài khoản customer. Để cấp quyền quản trị cho tài khoản đáng tin cậy đầu tiên, chạy trong MySQL:

```sql
UPDATE users SET role = 'admin' WHERE username = 'ten-dang-nhap';
```

Không cấp quyền admin tự động cho người đăng ký công khai.

## API

Mọi request và response dùng JSON. API cần đăng nhập nhận header `Authorization: Bearer <access_token>`.

| Nhóm | Endpoint |
| --- | --- |
| Xác thực | `POST /api/auth/register`, `POST /api/auth/login`, `GET/PUT /api/auth/me`, `POST /api/auth/change-password` |
| Sản phẩm | `GET/POST /api/products`, `GET/PUT/DELETE /api/products/{id}`, tìm kiếm `q`, lọc danh mục/giá, sắp xếp và phân trang |
| Danh mục | `GET/POST /api/categories`, `GET/PUT/DELETE /api/categories/{id}` |
| Giỏ hàng | `GET/POST/DELETE /api/cart`, `PUT/DELETE /api/cart/{cart_item_id}` |
| Đơn hàng | `GET/POST /api/orders`, `GET /api/orders/{id}`, `PUT /api/orders/{id}/cancel` |
| Quản trị | `GET /api/admin/stats`, `GET /api/admin/users`, `PUT /api/admin/users/{id}/role`, `GET /api/admin/orders`, `PUT /api/admin/orders/{id}/status` |

Payload được kiểm tra trước khi xử lý; truy vấn dùng prepared statements. Thanh toán đơn từ giỏ hàng cập nhật tồn kho trong transaction. Các endpoint admin yêu cầu tài khoản có role `admin`.

## Đối chiếu 11 hạng mục

1. **FastAPI:** không áp dụng cho phiên bản này vì backend đã được chuyển sang PHP theo yêu cầu; điểm vào PHP nằm tại `BACKEND/public/index.php`.
2. **Kết nối database:** PDO MySQL tại `BACKEND/src/bootstrap.php`; `GET /api/health` kiểm tra kết nối.
3. **REST API:** định tuyến JSON cho tất cả endpoint trong `BACKEND/src/Api.php`.
4. **Đăng ký/đăng nhập:** `POST /api/auth/register` và `/api/auth/login`.
5. **Sản phẩm:** danh sách, tìm kiếm, lọc, phân trang và CRUD admin.
6. **Danh mục:** danh sách, chi tiết và CRUD admin.
7. **Giỏ hàng:** xem, thêm, đổi số lượng và xóa.
8. **Đơn hàng:** checkout, lịch sử, chi tiết, hủy và hoàn kho.
9. **Admin:** thống kê, quản lý user, role và trạng thái đơn.
10. **Authentication:** mật khẩu được hash; JWT Bearer token và quyền admin được xác thực.
11. **Validation:** JSON, email, độ dài, số lượng, giá, trạng thái và dữ liệu checkout được kiểm tra.
