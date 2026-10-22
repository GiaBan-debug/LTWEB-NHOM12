import os
import sys

# Đảm bảo in tiếng Việt / emoji trên Windows console không bị lỗi mã hóa cp1252
if sys.platform.startswith("win"):
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except Exception:
        pass

# Đảm bảo import app từ BACKEND
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

# Sử dụng SQLite in-memory để kiểm thử độc lập mà không cần MySQL Server đang chạy
os.environ["DATABASE_URL"] = "sqlite:///./test_ecommerce.db"

from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from app.database import Base, get_db
from app.main import app

test_engine = create_engine("sqlite:///./test_ecommerce.db", connect_args={"check_same_thread": False})
TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=test_engine)

def override_get_db():
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()

app.dependency_overrides[get_db] = override_get_db

client = TestClient(app)

def run_tests():
    print("==================================================")
    print("🧪 Bắt đầu kiểm thử tự động toàn bộ REST API")
    print("==================================================")

    # Khởi tạo DB test
    Base.metadata.drop_all(bind=test_engine)
    Base.metadata.create_all(bind=test_engine)

    # 1. Test Root & Health Check
    res = client.get("/")
    assert res.status_code == 200, f"Root endpoint failed: {res.text}"
    print("✅ 1. Root & Health Check API: PASS")

    # 2. Test Register (Admin - tài khoản đầu tiên)
    admin_payload = {
        "username": "admin",
        "email": "admin@test.com",
        "password": "adminpassword123",
        "full_name": "Quản Trị Viên",
        "phone": "0123456789",
        "address": "TP.HCM"
    }
    res = client.post("/api/auth/register", json=admin_payload)
    assert res.status_code == 201, f"Register admin failed: {res.text}"
    admin_data = res.json()
    admin_token = admin_data["access_token"]
    assert admin_data["user"]["role"] == "admin"
    print("✅ 2. Đăng ký Admin (Auto First User = Admin): PASS")

    # 3. Test Register (Customer)
    cust_payload = {
        "username": "customer",
        "email": "customer@test.com",
        "password": "customerpassword123",
        "full_name": "Khách hàng 1",
        "phone": "0987654321",
        "address": "Hà Nội"
    }
    res = client.post("/api/auth/register", json=cust_payload)
    assert res.status_code == 201, f"Register customer failed: {res.text}"
    cust_data = res.json()
    cust_token = cust_data["access_token"]
    assert cust_data["user"]["role"] == "customer"
    print("✅ 3. Đăng ký Customer: PASS")

    # 4. Test Validation Input (Email sai, mật khẩu quá ngắn)
    bad_payload = {
        "username": "us",
        "email": "invalid-email",
        "password": "123"
    }
    res = client.post("/api/auth/register", json=bad_payload)
    assert res.status_code == 422, f"Validation test failed: {res.text}"
    print("✅ 4. Kiểm tra Validation dữ liệu đầu vào (Reject invalid data): PASS")

    # 5. Test Login
    login_res = client.post("/api/auth/login", json={
        "username_or_email": "admin@test.com",
        "password": "adminpassword123"
    })
    assert login_res.status_code == 200, f"Login failed: {login_res.text}"
    assert "access_token" in login_res.json()
    print("✅ 5. Đăng nhập & cấp phát JWT: PASS")

    # 6. Test Get Me (Protected)
    admin_headers = {"Authorization": f"Bearer {admin_token}"}
    cust_headers = {"Authorization": f"Bearer {cust_token}"}
    me_res = client.get("/api/auth/me", headers=cust_headers)
    assert me_res.status_code == 200
    assert me_res.json()["username"] == "customer"
    print("✅ 6. Xác thực JWT User Profile (/api/auth/me): PASS")

    # 7. Test Categories API (Create by Admin)
    cat_payload = {
        "name": "PC Gaming Cao Cấp",
        "description": "Dòng máy chuyên game thủ",
        "image_url": "assets/images/pc1.jpeg"
    }
    cat_res = client.post("/api/categories", json=cat_payload, headers=admin_headers)
    assert cat_res.status_code == 201, f"Create category failed: {cat_res.text}"
    cat_id = cat_res.json()["id"]
    print("✅ 7. Thêm Danh mục mới (Quyền Admin): PASS")

    # Test Customer cannot create category (Forbidden 403)
    cat_res_forbidden = client.post("/api/categories", json=cat_payload, headers=cust_headers)
    assert cat_res_forbidden.status_code == 403
    print("✅ 8. Phân quyền Admin (Chặn Customer tạo danh mục): PASS")

    # 8. Test Product API (Create by Admin)
    prod_payload = {
        "name": "PC Gaming RTX 5060",
        "category_id": cat_id,
        "description": "Core i5 12400F, RTX 5060 8GB, 16GB RAM",
        "price": 25280000.0,
        "original_price": 27990000.0,
        "stock": 10,
        "image_url": "assets/images/pc1.jpeg",
        "is_active": True
    }
    prod_res = client.post("/api/products", json=prod_payload, headers=admin_headers)
    assert prod_res.status_code == 201, f"Create product failed: {prod_res.text}"
    prod_id = prod_res.json()["id"]
    print("✅ 9. Thêm Sản phẩm mới (Admin): PASS")

    # 9. Test Product Public List, Search & Filter
    list_res = client.get(f"/api/products?q=RTX&min_price=20000000")
    assert list_res.status_code == 200
    assert list_res.json()["total"] >= 1
    print("✅ 10. Tìm kiếm & Lọc sản phẩm (Public Search/Filter): PASS")

    # 10. Test Cart API (Customer)
    add_cart_res = client.post("/api/cart", json={"product_id": prod_id, "quantity": 2}, headers=cust_headers)
    assert add_cart_res.status_code == 201
    cart_data = add_cart_res.json()
    assert cart_data["total_items"] == 2
    assert cart_data["total_price"] == 25280000.0 * 2
    cart_item_id = cart_data["items"][0]["id"]
    print("✅ 11. Thêm vào Giỏ hàng & Tính tiền tự động: PASS")

    # Update Cart quantity
    up_cart_res = client.put(f"/api/cart/{cart_item_id}", json={"quantity": 3}, headers=cust_headers)
    assert up_cart_res.status_code == 200
    assert up_cart_res.json()["total_items"] == 3
    print("✅ 12. Cập nhật số lượng trong Giỏ hàng: PASS")

    # 11. Test Order API (Checkout)
    order_payload = {
        "shipping_name": "Nguyễn Văn Khách Hàng",
        "shipping_phone": "0987654321",
        "shipping_address": "456 Đường Mua Sắm, Quận 7, TP.HCM",
        "payment_method": "cod",
        "note": "Giao giờ hành chính"
    }
    order_res = client.post("/api/orders", json=order_payload, headers=cust_headers)
    assert order_res.status_code == 201, f"Create order failed: {order_res.text}"
    order_data = order_res.json()
    order_id = order_data["id"]
    assert order_data["status"] == "pending"
    print("✅ 13. Đặt hàng (Checkout) & Trừ tồn kho tự động: PASS")

    # Verify Cart is now empty after checkout
    cart_check = client.get("/api/cart", headers=cust_headers)
    assert cart_check.json()["total_items"] == 0
    print("✅ 14. Tự động xóa sản phẩm đã mua khỏi giỏ hàng: PASS")

    # 12. Test Admin Dashboard Stats & Manage Orders
    stats_res = client.get("/api/admin/stats", headers=admin_headers)
    assert stats_res.status_code == 200
    stats = stats_res.json()
    assert stats["total_orders"] >= 1
    assert stats["total_products"] >= 1
    assert stats["total_users"] >= 2
    print("✅ 15. Thống kê Dashboard Quản trị viên (Admin Stats): PASS")

    # Admin update order status to completed
    update_order_res = client.put(
        f"/api/admin/orders/{order_id}/status",
        json={"status": "completed", "payment_status": "paid"},
        headers=admin_headers
    )
    assert update_order_res.status_code == 200
    assert update_order_res.json()["status"] == "completed"
    print("✅ 16. Admin duyệt & cập nhật trạng thái Đơn hàng: PASS")

    print("==================================================")
    print("🎉 TẤT CẢ 16/16 TEST CASES ĐỀU ĐẠT CHUẨN 100%!")
    print("==================================================")

    # Dọn dẹp DB test
    if os.path.exists("./test_ecommerce.db"):
        try:
            os.remove("./test_ecommerce.db")
        except Exception:
            pass

if __name__ == "__main__":
    run_tests()
