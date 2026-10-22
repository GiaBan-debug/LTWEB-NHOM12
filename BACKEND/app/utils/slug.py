import re
import unicodedata

def slugify(text: str) -> str:
    """Chuyển đổi chuỗi tiếng Việt thành slug URL an toàn"""
    if not text:
        return ""
    # Chuẩn hóa ký tự Unicode và bỏ dấu tiếng Việt
    text = unicodedata.normalize('NFD', text)
    text = re.sub(r'[\u0300-\u036f]', '', text)
    text = text.replace('đ', 'd').replace('Đ', 'D')
    # Chuyển thành chữ thường
    text = text.lower()
    # Thay thế các ký tự không phải chữ và số thành gạch ngang
    text = re.sub(r'[^a-z0-9]+', '-', text)
    # Loại bỏ gạch ngang ở đầu và cuối
    text = text.strip('-')
    return text
