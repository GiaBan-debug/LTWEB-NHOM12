from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.database import get_db
from app.models.cart import CartItem
from app.models.product import Product
from app.models.user import User
from app.schemas.cart import CartItemCreate, CartItemUpdate, CartResponse
from app.dependencies import get_current_user

router = APIRouter(prefix="/api/cart", tags=["Giỏ hàng"])

def get_user_cart_data(user_id: int, db: Session) -> dict:
    cart_items = db.query(CartItem).filter(CartItem.user_id == user_id).all()
    items = []
    total_price = 0.0
    total_items = 0

    for item in cart_items:
        prod = item.product
        if not prod:
            continue
        unit_price = float(prod.price)
        item_total = unit_price * item.quantity
        total_price += item_total
        total_items += item.quantity

        items.append({
            "id": item.id,
            "product_id": prod.id,
            "product_name": prod.name,
            "product_slug": prod.slug,
            "product_price": unit_price,
            "product_image": prod.image_url,
            "stock": prod.stock,
            "quantity": item.quantity,
            "total_item_price": item_total
        })

    return {
        "total_items": total_items,
        "total_price": total_price,
        "items": items
    }

@router.get("", response_model=CartResponse, summary="Xem giỏ hàng của người dùng hiện tại")
def view_cart(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    return get_user_cart_data(current_user.id, db)

@router.post("", response_model=CartResponse, status_code=status.HTTP_201_CREATED, summary="Thêm sản phẩm vào giỏ hàng")
def add_to_cart(
    cart_in: CartItemCreate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    product = db.query(Product).filter(Product.id == cart_in.product_id).first()
    if not product:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Sản phẩm không tồn tại")

    if not product.is_active:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Sản phẩm hiện đang tạm ngừng kinh doanh")

    if product.stock < cart_in.quantity:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Số lượng sản phẩm trong kho không đủ (Hiện còn: {product.stock})"
        )

    # Kiểm tra sản phẩm đã có trong giỏ của user chưa
    cart_item = db.query(CartItem).filter(
        CartItem.user_id == current_user.id,
        CartItem.product_id == cart_in.product_id
    ).first()

    if cart_item:
        new_quantity = cart_item.quantity + cart_in.quantity
        if product.stock < new_quantity:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Tổng số lượng trong giỏ ({new_quantity}) vượt quá tồn kho ({product.stock})"
            )
        cart_item.quantity = new_quantity
    else:
        cart_item = CartItem(
            user_id=current_user.id,
            product_id=cart_in.product_id,
            quantity=cart_in.quantity
        )
        db.add(cart_item)

    db.commit()
    return get_user_cart_data(current_user.id, db)

@router.put("/{cart_item_id}", response_model=CartResponse, summary="Cập nhật số lượng sản phẩm trong giỏ")
def update_cart_item(
    cart_item_id: int,
    cart_in: CartItemUpdate,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    cart_item = db.query(CartItem).filter(
        CartItem.id == cart_item_id,
        CartItem.user_id == current_user.id
    ).first()

    if not cart_item:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Mục giỏ hàng không tồn tại")

    product = cart_item.product
    if product and product.stock < cart_in.quantity:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Số lượng yêu cầu ({cart_in.quantity}) vượt quá tồn kho ({product.stock})"
        )

    cart_item.quantity = cart_in.quantity
    db.commit()

    return get_user_cart_data(current_user.id, db)

@router.delete("/{cart_item_id}", response_model=CartResponse, summary="Xóa một sản phẩm khỏi giỏ hàng")
def remove_cart_item(
    cart_item_id: int,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    cart_item = db.query(CartItem).filter(
        CartItem.id == cart_item_id,
        CartItem.user_id == current_user.id
    ).first()

    if not cart_item:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Mục giỏ hàng không tồn tại")

    db.delete(cart_item)
    db.commit()

    return get_user_cart_data(current_user.id, db)

@router.delete("", summary="Xóa toàn bộ giỏ hàng")
def clear_cart(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    db.query(CartItem).filter(CartItem.user_id == current_user.id).delete()
    db.commit()

    return {"message": "Đã xóa toàn bộ giỏ hàng thành công"}
