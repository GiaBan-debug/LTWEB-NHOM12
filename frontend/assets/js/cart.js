/* Trang giỏ hàng: hiển thị, cập nhật số lượng, xoá, đặt hàng. */
(() => {
  "use strict";

  // ---- Cấu hình (sửa nếu cấu trúc thư mục khác) ----
  const API_BASE = "../../backend/api/";
  const UPLOAD_BASE = "../../uploads/"; // nơi lưu ảnh sản phẩm
  const LOGIN_URL = "login.html";
  const ORDERS_URL = "orders.html";
  const MAX_QTY = 99;
  const PHONE_RE = /^(0|\+84)\d{9}$/;

  const PLACEHOLDER_IMG =
    "data:image/svg+xml;utf8," +
    encodeURIComponent(
      '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" fill="#eef1f7"/>' +
        '<path d="M28 64l14-16 10 11 8-9 8 14z" fill="#c5cde0"/><circle cx="36" cy="36" r="6" fill="#c5cde0"/></svg>',
    );

  const $ = (sel, root = document) => root.querySelector(sel);
  const els = {
    alert: $("#alert"),
    loading: $("#cart-loading"),
    empty: $("#cart-empty"),
    content: $("#cart-content"),
    list: $("#cart-list"),
    sumItems: $("#sum-items"),
    sumTotal: $("#sum-total"),
    navCount: $("#nav-count"),
    form: $("#checkout-form"),
    btnOrder: $("#btn-order"),
  };

  let cart = null;
  let busy = false;

  // ---- Tiện ích ----
  const formatVND = (n) => new Intl.NumberFormat("vi-VN").format(n) + " ₫";

  const escapeHtml = (s) =>
    String(s ?? "").replace(
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

  const imageUrl = (path) => {
    if (!path) return PLACEHOLDER_IMG;
    if (/^(https?:)?\/\//.test(path) || path.startsWith("data:")) return path;
    return UPLOAD_BASE + String(path).replace(/^\/+/, "");
  };

  let alertTimer;
  function showAlert(message, { type = "error", list = [] } = {}) {
    clearTimeout(alertTimer);
    els.alert.className = "alert" + (type === "ok" ? " alert--ok" : "");
    els.alert.innerHTML =
      escapeHtml(message) +
      (list.length
        ? "<ul>" +
          list.map((t) => `<li>${escapeHtml(t)}</li>`).join("") +
          "</ul>"
        : "");
    els.alert.hidden = false;
    els.alert.scrollIntoView({ block: "nearest", behavior: "smooth" });
    if (type === "ok")
      alertTimer = setTimeout(() => {
        els.alert.hidden = true;
      }, 3500);
  }
  const hideAlert = () => {
    els.alert.hidden = true;
  };

  async function api(method, query = "", body) {
    const res = await fetch(API_BASE + "cart.php" + query, {
      method,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    return handleResponse(res);
  }

  async function handleResponse(res) {
    let data = null;
    try {
      data = await res.json();
    } catch {
      /* body không phải JSON */
    }

    if (res.status === 401) {
      const back = encodeURIComponent(
        location.pathname.split("/").pop() || "cart.html",
      );
      location.href = `${LOGIN_URL}?redirect=${back}`;
      throw new Error("Chưa đăng nhập");
    }
    if (!res.ok || !data || data.success === false) {
      const err = new Error(
        (data && data.message) || "Có lỗi xảy ra, vui lòng thử lại.",
      );
      err.payload = data;
      throw err;
    }
    return data;
  }

  // ---- Hiển thị ----
  function itemHtml(it) {
    const warn = !it.available;
    const stockText =
      it.stock === 0
        ? "Đã hết hàng"
        : warn
          ? `Chỉ còn ${it.stock} sản phẩm`
          : `Còn ${it.stock} sản phẩm`;
    return `
      <li class="cart-item${warn ? " cart-item--warn" : ""}" data-id="${it.product_id}" data-stock="${it.stock}">
        <img class="cart-item__img" src="${escapeHtml(imageUrl(it.image))}" alt="${escapeHtml(it.name)}" loading="lazy">
        <div>
          <h3 class="cart-item__name">${escapeHtml(it.name)}</h3>
          <p class="cart-item__price">${formatVND(it.price)}</p>
          <div class="qty" role="group" aria-label="Số lượng ${escapeHtml(it.name)}">
            <button type="button" data-action="dec" aria-label="Giảm số lượng" ${it.quantity <= 1 ? "disabled" : ""}>−</button>
            <input type="number" inputmode="numeric" min="1" max="${Math.min(it.stock, MAX_QTY)}" value="${it.quantity}" aria-label="Số lượng">
            <button type="button" data-action="inc" aria-label="Tăng số lượng" ${it.quantity >= Math.min(it.stock, MAX_QTY) ? "disabled" : ""}>+</button>
          </div>
          <div class="cart-item__stock${warn ? " cart-item__stock--warn" : ""}">${stockText}</div>
        </div>
        <div class="cart-item__side">
          <span class="cart-item__subtotal">${formatVND(it.subtotal)}</span>
          <button type="button" class="link-btn" data-action="remove">Xoá</button>
        </div>
      </li>`;
  }

  function render(data) {
    cart = data;
    els.loading.hidden = true;

    const count = data.total_items;
    els.navCount.textContent = count;
    els.navCount.hidden = count === 0;

    if (data.is_empty) {
      els.content.hidden = true;
      els.empty.hidden = false;
      return;
    }
    els.empty.hidden = true;
    els.content.hidden = false;
    els.list.innerHTML = data.items.map(itemHtml).join("");
    els.sumItems.textContent = count;
    els.sumTotal.textContent = formatVND(data.total);
    els.btnOrder.disabled = !data.can_checkout;
    els.btnOrder.title = data.can_checkout
      ? ""
      : "Một số sản phẩm vượt tồn kho — hãy giảm số lượng hoặc xoá.";
  }

  async function load() {
    try {
      render((await api("GET")).data);
    } catch (e) {
      els.loading.hidden = true;
      showAlert(e.message);
    }
  }

  // ---- Thao tác ----
  async function mutate(fn) {
    if (busy) return;
    busy = true;
    hideAlert();
    try {
      const res = await fn();
      render(res.data);
    } catch (e) {
      showAlert(e.message);
      await load(); // đồng bộ lại với server (trả ô số lượng về giá trị đúng)
    } finally {
      busy = false;
    }
  }

  function setQuantity(productId, raw, stock) {
    const qty = Number(raw);
    if (!Number.isInteger(qty) || qty < 1) {
      showAlert("Số lượng phải là số nguyên từ 1 trở lên.");
      return load();
    }
    if (qty > Math.min(stock, MAX_QTY)) {
      showAlert(
        stock > 0
          ? `Chỉ còn ${stock} sản phẩm trong kho.`
          : "Sản phẩm đã hết hàng.",
      );
      return load();
    }
    return mutate(() =>
      api("PUT", "", { product_id: productId, quantity: qty }),
    );
  }

  els.list.addEventListener("click", (ev) => {
    const btn = ev.target.closest("[data-action]");
    if (!btn) return;
    const li = btn.closest(".cart-item");
    const id = Number(li.dataset.id);
    const stock = Number(li.dataset.stock);
    const input = $("input", li);
    const current = Number(input.value);

    if (btn.dataset.action === "inc") setQuantity(id, current + 1, stock);
    if (btn.dataset.action === "dec") setQuantity(id, current - 1, stock);
    if (btn.dataset.action === "remove") {
      const name = $(".cart-item__name", li).textContent;
      if (confirm(`Xoá "${name}" khỏi giỏ hàng?`)) {
        mutate(() => api("DELETE", `?product_id=${id}`));
      }
    }
  });

  els.list.addEventListener("change", (ev) => {
    if (!ev.target.matches(".qty input")) return;
    const li = ev.target.closest(".cart-item");
    setQuantity(
      Number(li.dataset.id),
      ev.target.value,
      Number(li.dataset.stock),
    );
  });

  // ---- Đặt hàng ----
  function clearFieldErrors() {
    els.form
      .querySelectorAll(".field--invalid")
      .forEach((f) => f.classList.remove("field--invalid"));
    els.form.querySelectorAll("[data-error-for]").forEach((s) => {
      s.textContent = "";
    });
  }
  function setFieldError(name, message) {
    const small = els.form.querySelector(`[data-error-for="${name}"]`);
    if (small) {
      small.textContent = message;
      small.closest(".field").classList.add("field--invalid");
    }
  }

  function validateForm(values) {
    const errors = {};
    if (values.receiver_name.length < 2)
      errors.receiver_name = "Vui lòng nhập họ tên.";
    if (!PHONE_RE.test(values.phone.replace(/[\s.\-]/g, "")))
      errors.phone = "Số điện thoại không hợp lệ (VD: 0912345678).";
    if (values.address.length < 5)
      errors.address = "Vui lòng nhập địa chỉ nhận hàng.";
    return errors;
  }

  els.form.addEventListener("submit", async (ev) => {
    ev.preventDefault();
    if (busy || !cart || !cart.can_checkout) return;
    hideAlert();
    clearFieldErrors();

    const fd = new FormData(els.form);
    const values = {
      receiver_name: String(fd.get("receiver_name")).trim(),
      phone: String(fd.get("phone")).trim(),
      address: String(fd.get("address")).trim(),
      note: String(fd.get("note")).trim(),
    };
    const errors = validateForm(values);
    if (Object.keys(errors).length) {
      Object.entries(errors).forEach(([k, v]) => setFieldError(k, v));
      els.form.querySelector(".field--invalid input")?.focus();
      return;
    }

    busy = true;
    els.btnOrder.disabled = true;
    els.btnOrder.textContent = "Đang đặt hàng…";
    try {
      const res = await fetch(API_BASE + "orders.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(values),
      }).then(handleResponse);
      location.href = `${ORDERS_URL}?id=${res.data.order_id}&placed=1`;
    } catch (e) {
      const p = e.payload || {};
      if (p.errors)
        Object.entries(p.errors).forEach(([k, v]) => setFieldError(k, v));
      const list = Array.isArray(p.items)
        ? p.items.map((i) => `${i.name}: cần ${i.requested}, còn ${i.stock}`)
        : [];
      showAlert(e.message, { list });
      busy = false;
      els.btnOrder.textContent = "Đặt hàng";
      await load();
    }
  });

  load();
})();
