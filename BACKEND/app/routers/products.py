from typing import Optional
from math import ceil
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from sqlalchemy import or_
from app.database import get_db
from app.models.product import Product
from app.models.category import Category
from app.models.user import User
from app.schemas.product import ProductCreate, ProductUpdate, ProductResponse, ProductListResponse
from app.dependencies import get_current_admin
from app.utils.slug import slugify

router = APIRouter(prefix="/api/products", tags=["Sản phẩm"])

def to_product_response(product: Product) -> dict:
    return {
        "id": product.id,
        "category_id": product.category_id,
        "category_name": product.category.name if product.category else None,
        "name": product.name,
        "slug": product.slug,
        "description": product.description,
        "price": float(product.price),
        "original_price": float(product.original_price) if product.original_price is not None else None,
        "stock": product.stock,
        "image_url": product.image_url,
        "is_active": product.is_active,
        "created_at": product.created_at,
        "updated_at": product.updated_at
    }

@router.get("", response_model=ProductListResponse, summary="Lấy danh sách sản phẩm (có tìm kiếm, lọc, phân trang)")
def list_products(
    q: Optional[str] = Query(None, description="Từ khóa tìm kiếm theo tên hoặc mô tả"),
    category_id: Optional[int] = Query(None, description="Lọc theo ID danh mục"),
    category_slug: Optional[str] = Query(None, description="Lọc theo Slug danh mục"),
    min_price: Optional[float] = Query(None, ge=0, description="Giá thấp nhất"),
    max_price: Optional[float] = Query(None, ge=0, description="Giá cao nhất"),
    sort_by: Optional[str] = Query("newest", description="Sắp xếp: newest, price_asc, price_desc, name_asc"),
    page: int = Query(1, ge=1, description="Trang hiện tại"),
    limit: int = Query(12, ge=1, le=100, description="Số sản phẩm mỗi trang"),
    only_active: bool = Query(True, description="Chỉ lấy sản phẩm đang hoạt động"),
    db: Session = Depends(get_db)
):
    query = db.query(Product)

    if only_active:
        query = query.filter(Product.is_active == True)

    if q and q.strip():
        search_pattern = f"%{q.strip()}%"
        query = query.filter(
            or_(
                Product.name.ilike(search_pattern),
                Product.description.ilike(search_pattern)
            )
        )

    if category_id:
        query = query.filter(Product.category_id == category_id)

    if category_slug:
        cat = db.query(Category).filter(Category.slug == category_slug).first()
        if cat:
            query = query.filter(Product.category_id == cat.id)

    if min_price is not None:
        query = query.filter(Product.price >= min_price)

    if max_price is not None:
        query = query.filter(Product.price <= max_price)

    # Xử lý sắp xếp
    if sort_by == "price_asc":
        query = query.order_by(Product.price.asc())
    elif sort_by == "price_desc":
        query = query.order_by(Product.price.desc())
    elif sort_by == "name_asc":
        query = query.order_by(Product.name.asc())
    else:  # newest
        query = query.order_by(Product.created_at.desc())

    total = query.count()
    total_pages = ceil(total / limit) if total > 0 else 1
    offset = (page - 1) * limit

    products = query.offset(offset).limit(limit).all()
    items = [to_product_response(p) for p in products]

    return {
        "total": total,
        "page": page,
        "limit": limit,
        "total_pages": total_pages,
        "items": items
    }

@router.get("/{id_or_slug}", response_model=ProductResponse, summary="Chi tiết sản phẩm theo ID hoặc Slug")
def get_product(id_or_slug: str, db: Session = Depends(get_db)):
    if id_or_slug.isdigit():
        product = db.query(Product).filter(Product.id == int(id_or_slug)).first()
    else:
        product = db.query(Product).filter(Product.slug == id_or_slug).first()

    if not product:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Không tìm thấy sản phẩm"
        )

    return to_product_response(product)

@router.post("", response_model=ProductResponse, status_code=status.HTTP_201_CREATED, summary="Thêm sản phẩm mới (Admin)")
def create_product(
    prod_in: ProductCreate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    target_slug = prod_in.slug.strip() if prod_in.slug and prod_in.slug.strip() else slugify(prod_in.name)

    # Đảm bảo slug không trùng
    existing = db.query(Product).filter(Product.slug == target_slug).first()
    if existing:
        import time
        target_slug = f"{target_slug}-{int(time.time())}"

    # Kiểm tra category_id nếu có
    if prod_in.category_id:
        cat = db.query(Category).filter(Category.id == prod_in.category_id).first()
        if not cat:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Danh mục liên kết không tồn tại")

    new_product = Product(
        name=prod_in.name,
        slug=target_slug,
        category_id=prod_in.category_id,
        description=prod_in.description,
        price=prod_in.price,
        original_price=prod_in.original_price,
        stock=prod_in.stock,
        image_url=prod_in.image_url,
        is_active=prod_in.is_active
    )

    db.add(new_product)
    db.commit()
    db.refresh(new_product)

    return to_product_response(new_product)

@router.put("/{product_id}", response_model=ProductResponse, summary="Cập nhật thông tin sản phẩm (Admin)")
def update_product(
    product_id: int,
    prod_in: ProductUpdate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    product = db.query(Product).filter(Product.id == product_id).first()
    if not product:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy sản phẩm")

    if prod_in.category_id is not None:
        if prod_in.category_id > 0:
            cat = db.query(Category).filter(Category.id == prod_in.category_id).first()
            if not cat:
                raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Danh mục không tồn tại")
            product.category_id = prod_in.category_id
        else:
            product.category_id = None

    if prod_in.name is not None:
        product.name = prod_in.name
    if prod_in.slug is not None:
        product.slug = slugify(prod_in.slug)
    if prod_in.description is not None:
        product.description = prod_in.description
    if prod_in.price is not None:
        product.price = prod_in.price
    if prod_in.original_price is not None:
        product.original_price = prod_in.original_price
    if prod_in.stock is not None:
        product.stock = prod_in.stock
    if prod_in.image_url is not None:
        product.image_url = prod_in.image_url
    if prod_in.is_active is not None:
        product.is_active = prod_in.is_active

    db.commit()
    db.refresh(product)

    return to_product_response(product)

@router.delete("/{product_id}", summary="Xóa sản phẩm (Admin)")
def delete_product(
    product_id: int,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    product = db.query(Product).filter(Product.id == product_id).first()
    if not product:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy sản phẩm")

    db.delete(product)
    db.commit()

    return {"message": "Đã xóa sản phẩm thành công"}
