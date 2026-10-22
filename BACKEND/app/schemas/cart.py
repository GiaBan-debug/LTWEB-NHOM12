from typing import List, Optional
from pydantic import BaseModel, Field

class CartItemCreate(BaseModel):
    product_id: int = Field(..., gt=0, description="ID của sản phẩm cần thêm vào giỏ")
    quantity: int = Field(1, gt=0, description="Số lượng muốn thêm (mặc định 1)")

class CartItemUpdate(BaseModel):
    quantity: int = Field(..., gt=0, description="Số lượng mới cho sản phẩm")

class CartItemResponse(BaseModel):
    id: int
    product_id: int
    product_name: str
    product_slug: str
    product_price: float
    product_image: Optional[str] = None
    stock: int
    quantity: int
    total_item_price: float

    class Config:
        from_attributes = True

class CartResponse(BaseModel):
    total_items: int
    total_price: float
    items: List[CartItemResponse]
