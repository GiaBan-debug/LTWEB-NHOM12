from typing import Optional
from math import ceil
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from sqlalchemy import func
from app.database import get_db
from app.models.user import User
from app.models.product import Product
from app.models.category import Category
from app.models.order import Order
from app.schemas.admin import DashboardStats, UserRoleUpdate
from app.schemas.user import UserResponse
from app.schemas.order import OrderListResponse, OrderResponse, OrderStatusUpdate
from app.routers.orders import to_order_response
from app.dependencies import get_current_admin

router = APIRouter(prefix="/api/admin", tags=["Quản trị viên (Admin)"])

@router.get("/stats", response_model=DashboardStats, summary="Thống kê tổng quan hệ thống (Dashboard)")
def get_dashboard_stats(
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    total_users = db.query(func.count(User.id)).scalar() or 0
    total_products = db.query(func.count(Product.id)).scalar() or 0
    total_categories = db.query(func.count(Category.id)).scalar() or 0
    total_orders = db.query(func.count(Order.id)).scalar() or 0

    # Doanh thu từ các đơn hàng completed (hoặc confirmed/shipping nếu muốn)
    revenue = db.query(func.sum(Order.total_amount)).filter(Order.status == "completed").scalar() or 0.0

    pending_orders = db.query(func.count(Order.id)).filter(Order.status == "pending").scalar() or 0
    low_stock = db.query(func.count(Product.id)).filter(Product.stock <= 5).scalar() or 0

    recent_orders_db = db.query(Order).order_by(Order.created_at.desc()).limit(5).all()
    recent_orders = [to_order_response(o) for o in recent_orders_db]

    return {
        "total_users": total_users,
        "total_products": total_products,
        "total_categories": total_categories,
        "total_orders": total_orders,
        "total_revenue": float(revenue),
        "pending_orders": pending_orders,
        "low_stock_products": low_stock,
        "recent_orders": recent_orders
    }

@router.get("/users", summary="Danh sách người dùng hệ thống")
def list_users(
    q: Optional[str] = Query(None, description="Tìm theo username hoặc email"),
    role: Optional[str] = Query(None, description="Lọc theo role"),
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    query = db.query(User)
    if q and q.strip():
        pattern = f"%{q.strip()}%"
        query = query.filter((User.username.ilike(pattern)) | (User.email.ilike(pattern)))
    if role:
        query = query.filter(User.role == role)

    total = query.count()
    total_pages = ceil(total / limit) if total > 0 else 1
    offset = (page - 1) * limit

    users = query.order_by(User.created_at.desc()).offset(offset).limit(limit).all()

    return {
        "total": total,
        "page": page,
        "limit": limit,
        "total_pages": total_pages,
        "items": [UserResponse.model_validate(u) for u in users]
    }

@router.put("/users/{user_id}/role", response_model=UserResponse, summary="Cập nhật quyền và trạng thái người dùng")
def update_user_role(
    user_id: int,
    data: UserRoleUpdate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    target_user = db.query(User).filter(User.id == user_id).first()
    if not target_user:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy người dùng")

    # Tránh trường hợp admin tự hạ quyền của chính mình
    if target_user.id == admin.id and data.role != "admin":
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Bạn không thể tự hạ quyền Admin của chính mình")

    if data.role in ["admin", "customer"]:
        target_user.role = data.role

    if data.is_active is not None:
        target_user.is_active = data.is_active

    db.commit()
    db.refresh(target_user)

    return target_user

@router.get("/orders", response_model=OrderListResponse, summary="Quản lý toàn bộ danh sách đơn hàng")
def list_all_orders(
    status_filter: Optional[str] = Query(None, alias="status", description="Lọc theo trạng thái đơn"),
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=100),
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    query = db.query(Order)
    if status_filter:
        query = query.filter(Order.status == status_filter)

    total = query.count()
    total_pages = ceil(total / limit) if total > 0 else 1
    offset = (page - 1) * limit

    orders = query.order_by(Order.created_at.desc()).offset(offset).limit(limit).all()
    items = [to_order_response(o) for o in orders]

    return {
        "total": total,
        "page": page,
        "limit": limit,
        "total_pages": total_pages,
        "items": items
    }

@router.put("/orders/{order_id}/status", response_model=OrderResponse, summary="Cập nhật trạng thái đơn hàng và thanh toán")
def update_order_status(
    order_id: int,
    status_data: OrderStatusUpdate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    order = db.query(Order).filter(Order.id == order_id).first()
    if not order:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy đơn hàng")

    valid_statuses = ["pending", "confirmed", "processing", "shipping", "completed", "cancelled"]
    if status_data.status not in valid_statuses:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Trạng thái không hợp lệ. Phải là một trong: {', '.join(valid_statuses)}"
        )

    # Nếu chuyển sang cancelled và đơn trước đó chưa cancelled thì hoàn kho
    if status_data.status == "cancelled" and order.status != "cancelled":
        for item in order.items:
            if item.product_id:
                prod = db.query(Product).filter(Product.id == item.product_id).first()
                if prod:
                    prod.stock += item.quantity

    order.status = status_data.status
    if status_data.payment_status:
        order.payment_status = status_data.payment_status

    db.commit()
    db.refresh(order)

    return to_order_response(order)
