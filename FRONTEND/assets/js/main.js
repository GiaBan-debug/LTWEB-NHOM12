/**
 * main.js — Cập nhật header theo trạng thái đăng nhập
 * Thiên Ân — feature/auth
 *
 * Nhúng file này vào cuối <body> của index.html và các trang cần hiển thị
 * trạng thái đăng nhập trên header.
 *
 * YÊU CẦU BẮT BUỘC: auth.js phải được nhúng TRƯỚC main.js
 *   <script src="assets/js/auth.js"></script>   ← TRƯỚC
 *   <script src="assets/js/main.js"></script>    ← SAU
 *
 * Cách dùng trong HTML (header của index.html):
 *
 *   <!-- Khu vực đăng nhập/đăng xuất trên nav -->
 *
 *   <!-- Chưa đăng nhập: hiện 2 link này -->
 *   <div data-auth="guest">
 *     <a href="pages/users/login.html">Đăng nhập</a> |
 *     <a href="pages/users/register.html">Đăng ký</a>
 *   </div>
 *
 *   <!-- Đã đăng nhập: hiện tên + nút đăng xuất -->
 *   <div data-auth="user" style="display:none;">
 *     Xin chào, <span data-auth="username"></span>
 *     <button id="btn-logout">Đăng xuất</button>
 *   </div>
 *
 *   <!-- Chỉ hiện với admin -->
 *   <a href="pages/admin/index.html" data-auth="admin" style="display:none;">Quản trị</a>
 */

document.addEventListener('DOMContentLoaded', () => {
    // syncSession + updateAuthUI chạy trong auth.js (nhúng auth.js trước main.js)
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
        btnLogout.addEventListener('click', async () => {
            btnLogout.disabled = true;
            btnLogout.textContent = 'Đang đăng xuất...';
            await logout();
        });
    }
});
