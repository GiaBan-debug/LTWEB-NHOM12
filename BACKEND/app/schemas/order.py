from datetime import datetime
from typing import List, Optional
from pydantic import BaseModel, Field

class OrderItemCreate(BaseModel):
    product_id: int = Field(..., gt=0)
    quantity: int = Field(..., gt=0)

class OrderCreate(BaseModel):
    shipping_name: str = Field(..., min_length=2, max_length=255, description="Họ tên người nhận hàng")
    shipping_phone: str = Field(..., min_length=9, max_length=20, description="Số điện thoại nhận hàng")
    shipping_address: str = Field(..., min_length=5, description="Địa chỉ giao hàng chi tiết")
    payment_method: str = Field("cod", description="Hình thức thanh toán: cod, banking, momo")
    note: Optional[str] = Field(None, description="Ghi chú đơn hàng")
    # Nếu items rỗng, hệ thống sẽ tự lấy tất cả sản phẩm trong Cart của user
    items: Optional[List[OrderItemCreate]] = Field(None, description="Danh sách sản phẩm mua (nếu không truyền sẽ lấy từ giỏ hàng)")

class OrderItemResponse(BaseModel):
    id: int
    product_id: Optional[int] = None
    product_name: str
    price: float
    quantity: int
    total_price: float

    class Config:
        from_attributes = True

class OrderStatusUpdate(BaseModel):
    status: str = Field(..., description="Trạng thái: pending, confirmed, processing, shipping, completed, cancelled")
    payment_status: Optional[str] = Field(None, description="Trạng thái thanh toán: unpaid, paid")

class OrderResponse(BaseModel):
    id: int
    user_id: Optional[int] = None
    total_amount: float
    status: str
    shipping_name: str
    shipping_phone: str
    shipping_address: str
    payment_method: str
    payment_status: str
    note: Optional[str] = None
    created_at: datetime
    updated_at: datetime
    items: List[OrderItemResponse] = []

    class Config:
        from_attributes = True

class OrderListResponse(BaseModel):
    total: int
    page: int
    limit: int
    total_pages: int
    items: List[OrderResponse]
