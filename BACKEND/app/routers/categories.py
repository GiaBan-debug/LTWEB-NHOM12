from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func
from app.database import get_db
from app.models.category import Category
from app.models.product import Product
from app.models.user import User
from app.schemas.category import CategoryCreate, CategoryUpdate, CategoryResponse
from app.dependencies import get_current_admin
from app.utils.slug import slugify

router = APIRouter(prefix="/api/categories", tags=["Danh mục sản phẩm"])

@router.get("", response_model=List[CategoryResponse], summary="Lấy danh sách tất cả danh mục")
def list_categories(db: Session = Depends(get_db)):
    categories = db.query(Category).all()
    results = []
    for cat in categories:
        prod_count = db.query(func.count(Product.id)).filter(Product.category_id == cat.id).scalar() or 0
        cat_dict = {
            "id": cat.id,
            "name": cat.name,
            "slug": cat.slug,
            "description": cat.description,
            "image_url": cat.image_url,
            "created_at": cat.created_at,
            "product_count": prod_count
        }
        results.append(cat_dict)
    return results

@router.get("/{id_or_slug}", response_model=CategoryResponse, summary="Lấy chi tiết danh mục theo ID hoặc Slug")
def get_category(id_or_slug: str, db: Session = Depends(get_db)):
    if id_or_slug.isdigit():
        category = db.query(Category).filter(Category.id == int(id_or_slug)).first()
    else:
        category = db.query(Category).filter(Category.slug == id_or_slug).first()

    if not category:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Không tìm thấy danh mục"
        )

    prod_count = db.query(func.count(Product.id)).filter(Product.category_id == category.id).scalar() or 0
    return {
        "id": category.id,
        "name": category.name,
        "slug": category.slug,
        "description": category.description,
        "image_url": category.image_url,
        "created_at": category.created_at,
        "product_count": prod_count
    }

@router.post("", response_model=CategoryResponse, status_code=status.HTTP_201_CREATED, summary="Tạo danh mục mới (Admin)")
def create_category(
    cat_in: CategoryCreate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    target_slug = cat_in.slug.strip() if cat_in.slug and cat_in.slug.strip() else slugify(cat_in.name)

    # Đảm bảo slug duy nhất
    existing = db.query(Category).filter(Category.slug == target_slug).first()
    if existing:
        target_slug = f"{target_slug}-{int(func.unix_timestamp(func.now())) if 'mysql' in str(db.bind.url) else 1}"

    new_cat = Category(
        name=cat_in.name,
        slug=target_slug,
        description=cat_in.description,
        image_url=cat_in.image_url
    )
    db.add(new_cat)
    db.commit()
    db.refresh(new_cat)

    return {
        "id": new_cat.id,
        "name": new_cat.name,
        "slug": new_cat.slug,
        "description": new_cat.description,
        "image_url": new_cat.image_url,
        "created_at": new_cat.created_at,
        "product_count": 0
    }

@router.put("/{category_id}", response_model=CategoryResponse, summary="Cập nhật danh mục (Admin)")
def update_category(
    category_id: int,
    cat_in: CategoryUpdate,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    category = db.query(Category).filter(Category.id == category_id).first()
    if not category:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy danh mục")

    if cat_in.name is not None:
        category.name = cat_in.name
    if cat_in.slug is not None:
        category.slug = slugify(cat_in.slug)
    if cat_in.description is not None:
        category.description = cat_in.description
    if cat_in.image_url is not None:
        category.image_url = cat_in.image_url

    db.commit()
    db.refresh(category)

    prod_count = db.query(func.count(Product.id)).filter(Product.category_id == category.id).scalar() or 0
    return {
        "id": category.id,
        "name": category.name,
        "slug": category.slug,
        "description": category.description,
        "image_url": category.image_url,
        "created_at": category.created_at,
        "product_count": prod_count
    }

@router.delete("/{category_id}", summary="Xóa danh mục (Admin)")
def delete_category(
    category_id: int,
    db: Session = Depends(get_db),
    admin: User = Depends(get_current_admin)
):
    category = db.query(Category).filter(Category.id == category_id).first()
    if not category:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Không tìm thấy danh mục")

    db.delete(category)
    db.commit()
    return {"message": "Đã xóa danh mục thành công"}
