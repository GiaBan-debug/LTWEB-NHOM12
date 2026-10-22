from typing import Optional
from math import ceil
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from app.database import get_db
from app.models.order import Order, OrderItem
from app.models.product import Product
from app.models.cart import CartItem
from app.models.user import User
from app.schemas.order import OrderCreate, OrderResponse, OrderListResponse, OrderItemResponse
from app.dependencies import get_current_user

router = APIRouter(prefix="/api/orders", tags=["Đơn hàng"])

def to_order_response(order: Order) -> dict:
    items = []
    for item in order.items:
        items.append({
            "id": item.id,
            "product_id": item.product_id,
            "product_name": item.product_name,
            "price": float(item.price),
            "quantity": item.quantity,
            "total_price": float(item.total_price)
        })

    return {
        "id": order.id,
        "user_id": order.user_id,
        "total_amount": float(order.total_amount),
        "status": order.status,
        "shipping_name": order.shipping_name,
        "shipping_phone": order.shipping_phone,
        "shipping_address": order.shipping_address,
        "payment_method": order.payment_method,
        "payment_status": order.payment_status,
        "note": order.note,
        "created_at": order.created_at,
        "updated_at": order.updated_at,
        "items": items
    }

@router.post("", response_model=OrderResponse, status_code=status.HTTP_201_CREATED, summary="Tạo đơn hàng mới (Checkout)")
def create_order(
    order_in: OrderCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    items_to_order = []

    if order_in.items and len(order_in.items) > 0:
        # Nếu gửi danh sách items cụ thể
        for req_item in order_in.items:
            prod = db.query(Product).filter(Product.id == req_item.product_id).first()
            if not prod:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"Sản phẩm ID {req_item.product_id} không tồn tại")
            if not prod.is_active:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Sản phẩm '{prod.name}' ngừng kinh doanh")
            if prod.stock < req_item.quantity:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Sản phẩm '{prod.name}' không đủ số lượng tồn kho")
            items_to_order.append((prod, req_item.quantity))
    else:
        # Lấy từ giỏ hàng hiện tại của user
        cart_items = db.query(CartItem).filter(CartItem.user_id == current_user.id).all()
        if not cart_items:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Giỏ hàng của bạn đang trống, không thể đặt hàng")

        for c_item in cart_items:
            prod = c_item.product
            if not prod or not prod.is_active:
                continue
            if prod.stock < c_item.quantity:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail=f"Sản phẩm '{prod.name}' chỉ còn {prod.stock} trong kho, không đủ số lượng đặt ({c_item.quantity})"
                )
            items_to_order.append((prod, c_item.quantity))

    if not items_to_order:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Không có sản phẩm hợp lệ để tạo đơn hàng")

    # Tính tổng tiền và trừ kho
    total_amount = 0.0
    order_items_entities = []

    for prod, qty in items_to_order:
        unit_price = float(prod.price)
        subtotal = unit_price * qty
        total_amount += subtotal

        # Trừ số lượng tồn kho
        prod.stock -= qty

        order_item = OrderItem(
            product_id=prod.id,
            product_name=prod.name,
            price=unit_price,
            quantity=qty,
            total_price=subtotal
        )
        order_items_entities.append(order_item)

    # Tạo Order
    new_order = Order(
        user_id=current_user.id,
        total_amount=total_amount,
        status="pending",
        shipping_name=order_in.shipping_name,
        shipping_phone=order_in.shipping_phone,
        shipping_address=order_in.shipping_address,
        payment_method=order_in.payment_method,
        payment_status="unpaid",
        note=order_in.note,
        items=order_items_entities
    )

    db.add(new_order)

    # Nếu đặt từ giỏ hàng hoặc có sản phẩm trong giỏ, dọn các sản phẩm đã mua khỏi giỏ
    purchased_prod_ids = [p.id for p, _ in items_to_order]
    db.query(CartItem).filter(
        CartItem.user_id == current_user.id,
        CartItem.product_id.in_(purchased_prod_ids)
    ).delete(synchronize_session=False)

    db.commit()
    db.refresh(new_order)

    return to_order_response(new_order)

@router.get("", response_model=OrderListResponse, summary="Danh sách lịch sử đơn hàng của tôi")
def get_my_orders(
    page: int = Query(1, ge=1),
    limit: int = Query(10, ge=1, le=50),
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    query = db.query(Order).filter(Order.user_id == current_user.id).order_by(Order.created_at.desc())
    total = query.count()
    total_pages = ceil(total / limit) if total > 0 else 1
    offset = (page - 1) * limit

    orders = query.offset(offset).limit(limit).all()
    items = [to_order_response(o) for o in orders]

    return {
        "total": total,
        "page": page,
        "limit": limit,
        "total_pages": total_pages,
        "items": items
    }

@router.get("/{order_id}", response_model=OrderResponse, summary="Xem chi tiết đơn hàng")
def get_order_detail(
    order_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    order = db.query(Order).filter(Order.id == order_id).first()
    if not order:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy đơn hàng")

    # Người mua hoặc Admin mới được xem
    if order.user_id != current_user.id and current_user.role != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Bạn không có quyền xem đơn hàng này")

    return to_order_response(order)

@router.put("/{order_id}/cancel", response_model=OrderResponse, summary="Hủy đơn hàng (Chỉ khi trạng thái đang là 'pending')")
def cancel_order(
    order_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    order = db.query(Order).filter(Order.id == order_id).first()
    if not order:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy đơn hàng")

    if order.user_id != current_user.id and current_user.role != "admin":
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Bạn không có quyền hủy đơn hàng này")

    if order.status != "pending":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Chỉ có thể hủy đơn hàng khi đang ở trạng thái 'pending'. Trạng thái hiện tại: {order.status}"
        )

    order.status = "cancelled"

    # Hoàn lại số lượng tồn kho cho các sản phẩm trong đơn
    for item in order.items:
        if item.product_id:
            prod = db.query(Product).filter(Product.id == item.product_id).first()
            if prod:
                prod.stock += item.quantity

    db.commit()
    db.refresh(order)

    return to_order_response(order)
