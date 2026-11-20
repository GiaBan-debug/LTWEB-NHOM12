/* shop.js — BTA Store Design · Nhóm 12
   Shared utilities: URL helper, API transport, UI components.
   Nguồn thiết kế: BTA Store Figma (Be Vietnam Pro, Apple-style).
*/
(() => {
  "use strict";

  /* ---- URL helpers ---- */
  const root = new URL("../../../", document.currentScript.src);
  const url = (path) => new URL(path, root).href;

  /* ---- HTML escape ---- */
  const escape = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );

  /* ---- CSRF ---- */
  let csrfPromise;
  async function csrf() {
    csrfPromise ??= fetch(url("backend/api/csrf.php"), {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(async (r) => {
        const p = await r.json();
        if (!r.ok || !p.success)
          throw new Error(p.message || "Không lấy được phiên đăng nhập.");
        return p.data.csrf_token;
      })
      .catch((e) => {
        csrfPromise = null;
        throw e;
      });
    return csrfPromise;
  }

  /* ---- API transport ---- */
  async function api(
    file,
    { method = "GET", body, restoreSession = true } = {},
  ) {
    const headers = { Accept: "application/json" };
    if (method !== "GET") headers["X-CSRF-Token"] = await csrf();
    if (body !== undefined) headers["Content-Type"] = "application/json";
    const response = await fetch(url("backend/api/" + file), {
      method,
      headers,
      credentials: "same-origin",
      cache: "no-store",
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error("Máy chủ không trả JSON hợp lệ. Kiểm tra cấu hình PHP.");
    }
    if (!response.ok || !result.success) {
      if (
        response.status === 401 &&
        restoreSession &&
        window.Auth &&
        !/^(me|firebase-session|login|logout|register|csrf|auth-config)\.php/.test(
          file,
        )
      ) {
        const user = await Auth.currentUser();
        if (user) return api(file, { method, body, restoreSession: false });
      }
      if (response.status === 403) csrfPromise = null;
      const error = new Error(result.message || "Yêu cầu thất bại.");
      error.status = response.status;
      error.payload = result;
      throw error;
    }
    return result;
  }

  /* ---- Formatters ---- */
  const money = (value) => Number(value).toLocaleString("vi-VN") + " ₫";

  /* Cùng quy tắc với backend/includes/functions.php normalizePhone(). */
  function normalizePhone(raw) {
    let phone = String(raw ?? "")
      .trim()
      .replace(/[\s.\-()]/g, "");
    if (/^84\d{9}$/.test(phone)) phone = "+" + phone;
    const withTrunk = phone.match(/^(?:\+84|84)0(\d{9})$/);
    if (withTrunk) phone = "0" + withTrunk[1];
    return /^(0|\+84)\d{9}$/.test(phone) ? phone : "";
  }

  const payable = (order) =>
    Number(order.total_amount ?? order.total_price ?? 0) +
    Number(order.shipping_fee || 0);

  function image(path) {
    if (
      typeof path !== "string" ||
      !/^products\/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp|avif)$/i.test(path)
    )
      return url("frontend/assets/images/logo1.avif");
    return url("uploads/" + path);
  }

  const page = (name, query = "") =>
    url("frontend/pages/users/" + name + ".html" + query);

  /* ---- Status labels ---- */
  const labels = {
    pending: "Chờ xác nhận",
    processing: "Đang chuẩn bị",
    shipped: "Đang giao",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
  };

  /* ---- SVG icons (inline, BTA style) ---- */
  const icons = {
    bag: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>`,
    search: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>`,
    user: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>`,
    truck: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>`,
    arrow: `<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>`,
    check: `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>`,
    chevron: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>`,
    phone: `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>`,
    laptop: `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="11" rx="1"/><path d="M2 19h20"/></svg>`,
    accessory: `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M6 13H4v6h3v-6H6Zm12 0h2v6h-3v-6h1Z"/></svg>`,
    tablet: `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M11 18h2"/></svg>`,
    reload: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>`,
  };

  /* Icon cho từng danh mục theo slug */
  const categoryIcons = {
    "dien-thoai": icons.phone,
    laptop: icons.laptop,
    "phu-kien": icons.accessory,
    "may-tinh-bang": icons.tablet,
  };

  /* ---- Error helper ---- */
  function error(el, message) {
    el.innerHTML = `<div class="notice notice-error" role="alert">${escape(message)} <button type="button" data-reload>${icons.reload} Thử lại</button></div>`;
  }

  /* ---- Login redirect ---- */
  function login() {
    const current = location.pathname.split("/").pop();
    const folder = location.pathname.includes("/users/")
      ? "users/"
      : location.pathname.includes("/admin/")
        ? "admin/"
        : "";
    const redirect = folder + current + location.search;
    location.href = url(
      "frontend/pages/login.html?redirect=" + encodeURIComponent(redirect),
    );
  }

  /* ---- Cart count badge ---- */
  async function updateCartCount() {
    try {
      const result = await api("cart.php");
      document.querySelectorAll("[data-cart-count]").forEach((el) => {
        el.textContent = result.data.total_items;
        el.closest(".cart-badge-wrap")?.classList.toggle(
          "hidden",
          result.data.total_items === 0,
        );
      });
    } catch {
      /* guest stays zero */
    }
  }

  /* ---- Category card ---- */
  function categoryCard(c, i) {
    const slugMap = {
      "Điện thoại": "dien-thoai",
      Laptop: "laptop",
      "Phụ kiện": "phu-kien",
      "Máy tính bảng": "may-tinh-bang",
    };
    const slug = slugMap[c.name] ?? c.slug ?? "";
    const icon = categoryIcons[slug] ?? icons.phone;
    const count = Number(c.product_count);
    return `
<a class="category-tile" href="${page("products", "?category_id=" + c.id)}">
  <span class="category-icon">${icon}</span>
  <div>
    <h3>${escape(c.name)}</h3>
    <p>${count} sản phẩm · Khám phá →</p>
  </div>
</a>`;
  }

  /* ---- Product card ---- */
  function productCard(p) {
    const inStock = p.stock > 0;
    return `
<article class="product-tile">
  <a class="product-photo" href="${page("product-detail", "?id=" + p.id)}">
    <img src="${image(p.thumbnail)}" alt="${escape(p.name)}" loading="lazy">
  </a>
  <div class="product-copy">
    <span class="eyebrow">${escape(p.category_name)}</span>
    <h3><a href="${page("product-detail", "?id=" + p.id)}">${escape(p.name)}</a></h3>
    <div class="product-bottom">
      <div>
        <strong class="price">${money(p.price)}</strong>
        <small class="subtle">${inStock ? "Còn " + Number(p.stock) + " sản phẩm" : "Tạm hết hàng"}</small>
      </div>
      <button class="add-btn" data-add="${p.id}" ${inStock ? "" : "disabled"} aria-label="Thêm ${escape(p.name)} vào giỏ">
        ${icons.bag}
      </button>
    </div>
  </div>
</article>`;
  }

  /* ---- Pagination ---- */
  function pagination(data) {
    if (data.total_pages <= 1) return "";
    return `
<nav class="pager" aria-label="Phân trang">
  <button data-page="${data.page - 1}" ${data.page <= 1 ? "disabled" : ""}>← Trước</button>
  <span>Trang ${data.page} / ${data.total_pages}</span>
  <button data-page="${data.page + 1}" ${data.page >= data.total_pages ? "disabled" : ""}>Sau →</button>
</nav>`;
  }

  /* ---- Toast ---- */
  function toast(message, bad = false) {
    let el = document.querySelector("#shop-toast");
    if (!el) {
      el = document.createElement("div");
      el.id = "shop-toast";
      el.setAttribute("role", "status");
      document.body.append(el);
    }
    el.className = "toast" + (bad ? " error" : "");
    el.innerHTML = bad
      ? escape(message)
      : `<span class="check-dot">${icons.check}</span> ${escape(message)}`;
    el.hidden = false;
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
      el.hidden = true;
    }, 3800);
  }

  /* ---- Order detail ---- */
  function orderDetail(o) {
    return `
<article class="panel">
  <div class="order-head">
    <h2>Đơn hàng #${Number(o.id)}</h2>
    <span class="status ${escape(o.status)}">${escape(labels[o.status])}</span>
  </div>
  <p class="subtle" style="margin-top:8px">Ngày đặt: ${escape(o.created_at)}</p>
  <div class="order-meta">
    <div><b>Thông tin giao hàng</b>
      <p>${escape(o.receiver_name)}</p>
      <p>${escape(o.phone)}</p>
      <p>${escape(o.address)}</p>
    </div>
    <div><b>Thanh toán khi nhận hàng (COD)</b>
      <p>Tạm tính: ${money(o.total_amount)}</p>
      <p>Phí giao hàng: ${money(o.shipping_fee || 0)}</p>
      <p>Ghi chú: ${escape(o.note || "Không có")}</p>
    </div>
  </div>
  ${o.items
    .map(
      (i) => `
  <div class="order-item">
    <img src="${image(i.image)}" alt="${escape(i.name)}">
    <div><b>${escape(i.name)}</b><br><small class="subtle">${Number(i.quantity)} × ${money(i.price)}</small></div>
    <strong>${money(i.subtotal)}</strong>
  </div>`,
    )
    .join("")}
  <div class="order-total">
    <b>Tổng thanh toán</b>
    <strong class="price">${money(payable(o))}</strong>
  </div>
</article>`;
  }

  /* ============================================================
     Header & Footer mount — BTA Store style
     ============================================================ */
  function mountHeader() {
    const header = document.querySelector("[data-site-header]");
    if (!header) return;
    header.innerHTML = `
<div class="shell header-row">
  <a class="wordmark" href="${url("frontend/index.html")}">
    <span class="brand-mark">B</span>
    <span>BTA <span class="store-label">STORE</span></span>
  </a>
  <nav class="primary-nav" aria-label="Điều hướng chính">
    <a href="${page("products")}">Sản phẩm</a>
    <a href="${page("categories")}">Danh mục</a>
    <a href="${page("order-history")}">Đơn hàng</a>
  </nav>
  <div class="header-tools">
    <a class="cart-link" href="${page("cart")}">
      ${icons.bag}
      <span class="cart-badge" data-cart-count>0</span>
    </a>
    <div id="auth-area" class="auth-area"></div>
  </div>
</div>`;
  }

  function mountFooter() {
    const footer = document.querySelector("[data-site-footer]");
    if (!footer) return;
    footer.innerHTML = `
<div class="shell footer-grid">
  <div>
    <a class="wordmark" href="${url("frontend/index.html")}">
      <span class="brand-mark">B</span>
      <span>BTA <span class="store-label">STORE</span></span>
    </a>
    <p style="margin-top:14px">Công nghệ cho cuộc sống mỗi ngày.</p>
    <small>Thanh toán khi nhận hàng (COD) · Giao hàng toàn quốc</small>
  </div>
  <div>
    <h3>Khám phá</h3>
    <a href="${page("products")}">Tất cả sản phẩm</a>
    <a href="${page("categories")}">Danh mục sản phẩm</a>
  </div>
  <div>
    <h3>Tài khoản</h3>
    <a href="${page("profile")}">Thông tin cá nhân</a>
    <a href="${page("order-history")}">Lịch sử đơn hàng</a>
  </div>
</div>
<div class="shell footer-bottom">
  <span>© ${new Date().getFullYear()} BTA Store · Thanh toán khi nhận hàng toàn quốc.</span>
</div>`;
  }

  /* ============================================================
     DOMContentLoaded — global event delegation + mount
     ============================================================ */
  document.addEventListener("DOMContentLoaded", () => {
    mountHeader();
    mountFooter();
    updateCartCount();

    /* Reload button in error notices */
    document.addEventListener("click", async (event) => {
      if (event.target.closest("[data-reload]")) {
        location.reload();
        return;
      }

      /* Add-to-cart button */
      const addBtn = event.target.closest("[data-add]");
      if (!addBtn || addBtn.disabled) return;
      addBtn.disabled = true;
      try {
        const quantity = Number(
          document.querySelector("#buy-quantity")?.value || 1,
        );
        await api("cart.php", {
          method: "POST",
          body: { product_id: Number(addBtn.dataset.add), quantity },
        });
        toast("Đã thêm vào giỏ hàng.");
        await updateCartCount();
      } catch (e) {
        if (e.status === 401) login();
        else toast(e.message, true);
      } finally {
        addBtn.disabled = false;
      }
    });
  });

  /* ---- Export ---- */
  window.Shop = {
    url,
    csrf,
    api,
    escape,
    money,
    image,
    page,
    normalizePhone,
    payable,
    labels,
    error,
    login,
    categoryCard,
    productCard,
    pagination,
    toast,
    updateCartCount,
    orderDetail,
  };
})();
