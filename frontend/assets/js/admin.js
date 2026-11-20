/**
 * frontend/assets/js/admin.js  — TV4
 * Các tính năng mới so với bản cũ:
 *  - Upload ảnh sản phẩm từ trình duyệt (kèm preview, kiểm tra trước khi gửi)
 *  - Trường Thông số kỹ thuật (specs) và Bảo hành (warranty_months)
 *  - Sửa và xóa danh mục (có kiểm tra sản phẩm liên quan)
 *  - Lọc đơn hàng theo trạng thái và ngày đặt
 *  - Lịch sử đổi trạng thái đơn hàng
 *  - Nhập lý do hủy đơn
 *  - Nút in phiếu đơn hàng
 *  - Dashboard giải thích cách tính doanh thu
 *  - Bỏ: trường vận chuyển / mã vận đơn / ngày giao dự kiến
 */
document.addEventListener("DOMContentLoaded", async () => {
  "use strict";
  const S = window.Shop,
    e = S.escape,
    root = document.querySelector("#admin-content");

  const view = document.body.dataset.view,
    params = new URLSearchParams(location.search),
    id = Number(params.get("id"));

  const admin = (resource, options = {}, query = "") =>
    S.api("admin.php?resource=" + resource + query, options);

  const link = (file) => S.url("frontend/pages/admin/" + file);

  const notice = (err) => {
    if (err.status === 401) S.login();
    else S.error(root, err.message);
  };

  /* ---- Helper: input field ---- */
  const input = (label, name, value = "", type = "text", attrs = "") =>
    `<label class="field">${label}<input name="${name}" type="${type}" value="${e(String(value ?? ""))}" ${attrs}></label>`;

  /* ---- Upload ảnh lên upload.php ---- */
  async function uploadImage(file) {
    const fd = new FormData();
    fd.append("image", file);
    const csrfToken = await S.csrf();
    const res = await fetch(S.url("backend/api/upload.php"), {
      method: "POST",
      headers: { "X-CSRF-Token": csrfToken },
      credentials: "same-origin",
      body: fd,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.success) {
      throw new Error(json.message || "Upload thất bại.");
    }
    return json.data.path;
  }

  try {
    const { data: user } = await S.api("me.php");
    if (user.role !== "admin") {
      root.innerHTML =
        '<div class="notice notice-error">Bạn không có quyền quản trị.</div>';
      return;
    }

    /* ---- Nav ---- */
    const tabs = [
      ["index.html", "Tổng quan", "dashboard"],
      ["products.html", "Sản phẩm", "admin-product"],
      ["categories.html", "Danh mục", "admin-categories"],
      ["orders.html", "Đơn hàng", "admin-order"],
    ];
    document.querySelector("#admin-nav").innerHTML = tabs
      .map(
        ([file, label, match]) =>
          `<a class="${view.startsWith(match) ? "active" : ""}" href="${link(file)}">${label}</a>`,
      )
      .join("");

    /* ============================================================
       DASHBOARD
       ============================================================ */
    if (view === "dashboard") {
      const { data: d } = await admin("dashboard");
      root.innerHTML = `
<div class="stat-grid">
  ${[
    ["Sản phẩm đang bán", d.products],
    ["Tổng đơn hàng", d.orders],
    ["Đơn chờ xác nhận", d.pending],
    ["Doanh thu đơn hoàn thành", S.money(d.revenue)],
  ]
    .map(
      ([label, value]) =>
        `<div class="panel stat"><small>${label}</small><strong>${value}</strong></div>`,
    )
    .join("")}
</div>
<div class="panel" style="margin-top:24px">
  <h2>Quản lý cửa hàng</h2>
  <p class="lead">Kiểm tra đơn mới, cập nhật trạng thái và quản lý sản phẩm từ các mục phía trên.</p>
  <p class="subtle" style="margin-top:8px">
    <strong>Cách tính doanh thu:</strong> ${e(d.revenue_note)}
  </p>
  <a class="button" href="${link("orders.html")}">Xem đơn hàng →</a>
</div>`;
    }

    /* ============================================================
       DANH SÁCH SẢN PHẨM / ĐƠN HÀNG
       ============================================================ */
    if (view === "admin-products" || view === "admin-orders") {
      const isProducts = view === "admin-products";
      const search = params.get("search") || "";
      const statusP = params.get("status") || "";
      const dateFrom = params.get("date_from") || "";
      const dateTo = params.get("date_to") || "";
      const page = Number(params.get("page")) || 1;

      let query = `&page=${page}&search=${encodeURIComponent(search)}`;
      if (!isProducts) {
        query += `&status=${encodeURIComponent(statusP)}`;
        query += `&date_from=${encodeURIComponent(dateFrom)}`;
        query += `&date_to=${encodeURIComponent(dateTo)}`;
      }

      const { data: d } = await admin(
        isProducts ? "products" : "orders",
        {},
        query,
      );

      /* ---- Hàng trong bảng ---- */
      const rows = d.items
        .map((p) =>
          isProducts
            ? `<tr>
               <td><img src="${S.image(p.thumbnail)}" alt=""></td>
               <td>${e(p.name)}<br><small>#${p.id} · ${p.status === "active" ? "Đang bán" : "Ngừng bán"}</small></td>
               <td>${S.money(p.price)}</td>
               <td>${Number(p.stock)}</td>
               <td>
                 <div class="table-actions">
                   <a class="button secondary small" href="${link("product-form.html?id=" + p.id)}">Sửa</a>
                   <button class="button danger small" data-stop="${p.id}" ${p.status !== "active" ? "disabled" : ""}>Ngừng bán</button>
                 </div>
               </td>
             </tr>`
            : `<tr>
               <td>#${p.id}</td>
               <td>${e(p.receiver_name)}</td>
               <td>${S.money(Number(p.total_price) + Number(p.shipping_fee || 0))}</td>
               <td><span class="status ${e(p.status)}">${e(S.labels[p.status])}</span></td>
               <td>${e(p.created_at)}</td>
               <td><a class="button secondary small" href="${link("order-detail.html?id=" + p.id)}">Chi tiết</a></td>
             </tr>`,
        )
        .join("");

      /* ---- Bộ lọc đơn hàng ---- */
      const orderFilter = !isProducts
        ? `
<div class="order-filters" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
  <label class="field" style="margin:0">Trạng thái
    <select name="status" id="f-status">
      <option value="">Tất cả</option>
      ${["pending", "processing", "shipped", "completed", "cancelled"]
        .map(
          (s) =>
            `<option value="${s}" ${statusP === s ? "selected" : ""}>${e(S.labels[s])}</option>`,
        )
        .join("")}
    </select>
  </label>
  <label class="field" style="margin:0">Từ ngày
    <input type="date" name="date_from" id="f-date-from" value="${e(dateFrom)}">
  </label>
  <label class="field" style="margin:0">Đến ngày
    <input type="date" name="date_to" id="f-date-to" value="${e(dateTo)}">
  </label>
  <button class="button secondary" id="btn-filter">Lọc</button>
  <a class="button secondary" href="${link("orders.html")}">Xóa lọc</a>
</div>`
        : "";

      root.innerHTML = `
<div class="toolbar">
  <form id="admin-search" style="display:flex;gap:8px">
    <input class="search-input" name="search" value="${e(search)}"
           placeholder="${isProducts ? "Tìm tên sản phẩm" : "Tìm người nhận / mã đơn"}"
           maxlength="150" aria-label="Tìm kiếm">
    <button class="button secondary">Tìm</button>
  </form>
  ${isProducts ? `<a class="button" href="${link("product-form.html")}">+ Thêm sản phẩm</a>` : ""}
</div>
${orderFilter}
<div class="panel table-wrap">
  <p class="subtle">${d.total} kết quả</p>
  <table>
    <thead><tr>
      ${(isProducts
        ? ["Ảnh", "Sản phẩm", "Giá", "Tồn kho", "Thao tác"]
        : [
            "Mã đơn",
            "Người nhận",
            "Tổng tiền",
            "Trạng thái",
            "Ngày đặt",
            "Thao tác",
          ]
      )
        .map((t) => "<th>" + t + "</th>")
        .join("")}
    </tr></thead>
    <tbody>${rows || `<tr><td colspan="6">Chưa có dữ liệu phù hợp.</td></tr>`}</tbody>
  </table>
</div>
${S.pagination(d)}`;

      /* Lọc đơn hàng */
      document.querySelector("#btn-filter")?.addEventListener("click", () => {
        const q = new URLSearchParams({
          search,
          status: document.querySelector("#f-status").value,
          date_from: document.querySelector("#f-date-from").value,
          date_to: document.querySelector("#f-date-to").value,
          page: 1,
        });
        location.search = "?" + q;
      });

      /* Pagination */
      root.addEventListener("click", async (ev) => {
        const pg = ev.target.closest("[data-page]");
        if (pg && !pg.disabled) {
          const q = new URLSearchParams({
            page: pg.dataset.page,
            search,
            status: statusP,
            date_from: dateFrom,
            date_to: dateTo,
          });
          location.search = "?" + q;
        }
        const stop = ev.target.closest("[data-stop]");
        if (
          stop &&
          confirm("Ngừng bán sản phẩm này? Lịch sử đơn vẫn được giữ.")
        ) {
          stop.disabled = true;
          try {
            await admin(
              "products",
              { method: "DELETE" },
              "&id=" + stop.dataset.stop,
            );
            location.reload();
          } catch (err) {
            stop.disabled = false;
            S.toast(err.message, true);
          }
        }
      });
    }

    /* ============================================================
       FORM SẢN PHẨM (thêm / sửa)
       ============================================================ */
    if (view === "admin-product-form") {
      const [{ data: cats }, { data: p }] = await Promise.all([
        admin("categories"),
        id
          ? admin("products", {}, "&id=" + id)
          : Promise.resolve({
              data: {
                status: "active",
                price: 0,
                stock: 0,
                warranty_months: 12,
              },
            }),
      ]);

      /* Danh sách ảnh có sẵn */
      const presetImages = [
        "iphone-15-pro-max.jpg",
        "samsung-s24-ultra.jpg",
        "xiaomi-14-ultra.jpg",
        "iphone-14.jpg",
        "samsung-z-flip5.jpg",
        "oppo-find-n3.jpg",
        "macbook-air-m3.jpg",
        "dell-xps-15.jpg",
        "macbook-pro-14.jpg",
        "asus-rog-zephyrus.jpg",
        "thinkpad-x1.jpg",
        "hp-envy-16.jpg",
        "airpods-pro-2.jpg",
        "cap-usb-c.jpg",
        "anker-gan-65w.jpg",
        "logitech-mx-master-3s.jpg",
        "keychron-k2-pro.jpg",
        "baseus-blade-100w.jpg",
        "ipad-pro-m4.jpg",
        "ipad-air-5.jpg",
        "ipad-gen-10.jpg",
        "galaxy-tab-s9.jpg",
        "xiaomi-pad-6.jpg",
        "lenovo-tab-p12.jpg",
      ];

      root.innerHTML = `
<form id="product-form" class="panel">
  <div class="form-grid">
    ${input("Tên sản phẩm", "name", p.name || "", "text", 'required maxlength="200"')}

    <label class="field">Danh mục
      <select name="category_id" required>
        ${cats
          .map(
            (c) =>
              `<option value="${c.id}" ${Number(p.category_id) === Number(c.id) ? "selected" : ""}>${e(c.name)}</option>`,
          )
          .join("")}
      </select>
    </label>

    ${input("Giá bán (₫)", "price", p.price, "number", 'required min="0" max="1000000000" step="1"')}
    ${input("Tồn kho", "stock", p.stock, "number", 'required min="0" max="1000000" step="1"')}
    ${input("Bảo hành (tháng)", "warranty_months", p.warranty_months ?? 12, "number", 'min="0" max="240" step="1"')}

    <label class="field">Trạng thái
      <select name="status">
        <option value="active">Đang bán</option>
        <option value="inactive" ${p.status === "inactive" ? "selected" : ""}>Ngừng bán</option>
      </select>
    </label>

    <!-- ---- Ảnh sản phẩm ---- -->
    <label class="field span-2">Ảnh sản phẩm
      <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-top:6px">
        <div>
          <img id="thumb-preview"
               src="${S.image(p.thumbnail || "")}"
               alt="Ảnh xem trước"
               style="width:120px;height:90px;object-fit:cover;border-radius:6px;border:1px solid var(--border,#ddd)">
        </div>
        <div style="flex:1;min-width:200px">
          <!-- Upload từ trình duyệt -->
          <label style="display:block;font-size:.85rem;font-weight:600;margin-bottom:4px">Upload ảnh mới (JPEG/PNG/WebP/AVIF, tối đa 5 MB)</label>
          <input type="file" id="upload-input" accept="image/jpeg,image/png,image/webp,image/avif" style="margin-bottom:8px">
          <span id="upload-status" style="font-size:.8rem;color:var(--muted,#888)"></span>

          <!-- Hoặc chọn ảnh có sẵn -->
          <label style="display:block;font-size:.85rem;font-weight:600;margin-top:10px;margin-bottom:4px">Hoặc chọn ảnh có sẵn trong uploads/</label>
          <select id="preset-select">
            <option value="">— Dùng ảnh đã upload —</option>
            ${presetImages
              .map(
                (f) =>
                  `<option value="products/${f}" ${p.thumbnail === "products/" + f ? "selected" : ""}>${f}</option>`,
              )
              .join("")}
          </select>
        </div>
      </div>
      <!-- Trường hidden gửi lên API -->
      <input type="hidden" name="thumbnail" id="thumbnail-value" value="${e(p.thumbnail || "")}">
    </label>

    <label class="field span-2">Mô tả
      <textarea name="description" maxlength="10000">${e(p.description || "")}</textarea>
    </label>

    <label class="field span-2">Thông số kỹ thuật (specs)
      <textarea name="specs" maxlength="10000" placeholder="CPU: ...\nRAM: ...\nMàn hình: ...">${e(p.specs || "")}</textarea>
    </label>
  </div>

  <p class="subtle">Giá và tồn kho phải là số nguyên không âm. Upload ảnh sẽ lưu ngay vào uploads/products/.</p>
  <button class="button" id="save-btn">Lưu sản phẩm</button>
  <a class="button secondary" href="${link("products.html")}">Quay lại</a>
  <p id="save-status" role="status" style="margin-top:8px;color:var(--danger,#c00)"></p>
</form>`;

      /* ---- Thumbnail: preview chung ---- */
      function setThumb(path) {
        document.querySelector("#thumbnail-value").value = path;
        const img = document.querySelector("#thumb-preview");
        img.src = path ? S.image(path) : "";
      }

      /* Chọn từ preset */
      document
        .querySelector("#preset-select")
        .addEventListener("change", (ev) => {
          setThumb(ev.target.value);
          document.querySelector("#upload-status").textContent = "";
        });

      /* Upload từ trình duyệt */
      document
        .querySelector("#upload-input")
        .addEventListener("change", async (ev) => {
          const file = ev.target.files[0];
          if (!file) return;
          const status = document.querySelector("#upload-status");
          status.textContent = "Đang upload…";
          try {
            const path = await uploadImage(file);
            setThumb(path);
            document.querySelector("#preset-select").value = "";
            status.textContent = "✓ Đã upload: " + path;
            status.style.color = "var(--success,green)";
          } catch (err) {
            status.textContent = "⚠ " + err.message;
            status.style.color = "var(--danger,#c00)";
            ev.target.value = "";
          }
        });

      /* Submit */
      document
        .querySelector("#product-form")
        .addEventListener("submit", async (ev) => {
          ev.preventDefault();
          const form = ev.currentTarget,
            button = form.querySelector("#save-btn"),
            status = form.querySelector("#save-status");
          button.disabled = true;
          status.textContent = "";
          try {
            const fd = Object.fromEntries(new FormData(form));
            await admin(
              "products",
              { method: id ? "PUT" : "POST", body: fd },
              id ? "&id=" + id : "",
            );
            location.href = link("products.html");
          } catch (err) {
            status.textContent = err.message;
            button.disabled = false;
          }
        });
    }

    /* ============================================================
       CHI TIẾT ĐƠN HÀNG (admin)
       ============================================================ */
    if (view === "admin-order-detail") {
      if (!id) throw new Error("Thiếu mã đơn hàng.");
      const { data: o } = await admin("orders", {}, "&id=" + id);

      const allowed = {
        pending: ["processing", "cancelled"],
        processing: ["shipped", "cancelled"],
        shipped: ["completed"],
        completed: [],
        cancelled: [],
      };

      /* ---- Lịch sử trạng thái ---- */
      const logsHtml = (o.status_logs || []).length
        ? `<div class="panel" style="margin-top:16px">
             <h3>Lịch sử trạng thái</h3>
             <table>
               <thead><tr><th>Từ</th><th>Sang</th><th>Người xử lý</th><th>Thời gian</th><th>Lý do hủy</th></tr></thead>
               <tbody>
                 ${o.status_logs
                   .map(
                     (lg) => `
                   <tr>
                     <td><span class="status ${e(lg.from_status)}">${e(S.labels[lg.from_status] || lg.from_status)}</span></td>
                     <td><span class="status ${e(lg.to_status)}">${e(S.labels[lg.to_status] || lg.to_status)}</span></td>
                     <td>${e(lg.handler_name || "—")}</td>
                     <td>${e(lg.created_at)}</td>
                     <td>${e(lg.cancel_reason || "")}</td>
                   </tr>`,
                   )
                   .join("")}
               </tbody>
             </table>
           </div>`
        : "";

      root.innerHTML =
        S.orderDetail(o) +
        `
<div class="panel" style="margin-top:20px">
  <h2>Cập nhật trạng thái</h2>
  <div class="table-actions" id="action-area">
    ${
      allowed[o.status].length
        ? allowed[o.status]
            .map(
              (s) =>
                `<button class="button ${s === "cancelled" ? "danger" : ""}" data-status="${s}">${e(S.labels[s])}</button>`,
            )
            .join("")
        : '<p class="subtle">Đơn đã kết thúc, không thể đổi trạng thái.</p>'
    }
  </div>
  ${
    allowed[o.status].includes("cancelled")
      ? `<label class="field" style="margin-top:12px;max-width:400px">Lý do hủy (tùy chọn)
         <input type="text" id="cancel-reason" maxlength="500" placeholder="Nhập lý do nếu cần">
       </label>`
      : ""
  }
  <p class="subtle" style="margin-top:8px">Hủy đơn sẽ hoàn tồn kho đúng một lần. Đơn đang giao chỉ có thể chuyển sang hoàn thành.</p>
</div>
${logsHtml}
<div style="margin-top:16px">
  <button class="button secondary" id="btn-print">🖨 In phiếu đơn hàng</button>
</div>`;

      /* ---- Đổi trạng thái ---- */
      root.addEventListener("click", async (ev) => {
        const button = ev.target.closest("[data-status]");
        if (!button) return;
        const nextStatus = button.dataset.status;
        const cancelReason =
          document.querySelector("#cancel-reason")?.value || "";
        if (nextStatus === "cancelled" && !confirm("Hủy đơn và hoàn tồn kho?"))
          return;
        root.querySelectorAll("button").forEach((b) => (b.disabled = true));
        try {
          await admin(
            "orders",
            {
              method: "PUT",
              body: { status: nextStatus, cancel_reason: cancelReason },
            },
            "&id=" + id,
          );
          location.reload();
        } catch (err) {
          S.toast(err.message, true);
          root.querySelectorAll("button").forEach((b) => (b.disabled = false));
        }
      });

      /* ---- In phiếu đơn hàng ---- */
      document.querySelector("#btn-print").addEventListener("click", () => {
        const win = window.open("", "_blank", "width=700,height=900");
        const totalPayable =
          Number(o.total_amount || 0) + Number(o.shipping_fee || 0);
        win.document.write(`<!DOCTYPE html>
<html lang="vi"><head><meta charset="utf-8">
<title>Phiếu đơn hàng #${Number(o.id)}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; padding: 24px; color: #000; }
  h1   { font-size: 18px; margin-bottom: 4px; }
  .meta { color: #555; font-size: 12px; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th, td { border: 1px solid #bbb; padding: 6px 10px; text-align: left; }
  th { background: #f4f4f4; }
  .total-row td { font-weight: bold; }
  .footer { margin-top: 24px; font-size: 11px; color: #777; }
  @media print { button { display: none; } }
</style>
</head><body>
<h1>PHIẾU ĐƠN HÀNG — BTA Store</h1>
<p class="meta">Mã đơn: #${Number(o.id)} · Ngày đặt: ${e(o.created_at)} · Trạng thái: ${e(S.labels[o.status] || o.status)}</p>
<p><strong>Người nhận:</strong> ${e(o.receiver_name)}</p>
<p><strong>Điện thoại:</strong> ${e(o.phone)}</p>
<p><strong>Địa chỉ:</strong>   ${e(o.address)}</p>
${o.note ? `<p><strong>Ghi chú:</strong> ${e(o.note)}</p>` : ""}
<table>
  <thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
  <tbody>
    ${(o.items || [])
      .map(
        (i) => `<tr>
      <td>${e(i.name)}</td>
      <td style="text-align:center">${Number(i.quantity)}</td>
      <td style="text-align:right">${Number(i.price).toLocaleString("vi-VN")} ₫</td>
      <td style="text-align:right">${Number(i.subtotal).toLocaleString("vi-VN")} ₫</td>
    </tr>`,
      )
      .join("")}
  </tbody>
  <tfoot>
    <tr><td colspan="3">Phí giao hàng</td><td style="text-align:right">${Number(o.shipping_fee || 0).toLocaleString("vi-VN")} ₫</td></tr>
    <tr class="total-row"><td colspan="3">TỔNG THANH TOÁN (COD)</td><td style="text-align:right">${totalPayable.toLocaleString("vi-VN")} ₫</td></tr>
  </tfoot>
</table>
<div class="footer">In lúc ${new Date().toLocaleString("vi-VN")} · Thanh toán khi nhận hàng (COD)</div>
<br>
<button onclick="window.print()">🖨 In ngay</button>
</body></html>`);
        win.document.close();
        win.focus();
      });
    }

    /* ============================================================
       DANH MỤC — thêm / sửa / xóa
       ============================================================ */
    if (view === "admin-categories") {
      let editingId = null; // null = thêm mới, số = đang sửa

      async function renderCategories() {
        const { data: cats } = await admin("categories");
        const tableBody = cats
          .map(
            (c) => `
          <tr>
            <td>#${c.id}</td>
            <td>${e(c.name)}</td>
            <td>${e(c.description || "")}</td>
            <td>${Number(c.product_count)} sản phẩm</td>
            <td>
              <div class="table-actions">
                <button class="button secondary small" data-edit="${c.id}"
                        data-name="${e(c.name)}" data-desc="${e(c.description || "")}">Sửa</button>
                <button class="button danger small" data-delete="${c.id}"
                        ${Number(c.product_count) > 0 ? 'disabled title="Còn sản phẩm, không thể xóa"' : ""}>Xóa</button>
              </div>
            </td>
          </tr>`,
          )
          .join("");

        document.querySelector("#cat-table-body").innerHTML =
          tableBody || '<tr><td colspan="5">Chưa có danh mục nào.</td></tr>';
      }

      root.innerHTML = `
<div class="catalog-layout">
  <form class="panel" id="category-form">
    <h2 id="form-title">Thêm danh mục</h2>
    ${input("Tên danh mục", "name", "", "text", 'required maxlength="100"')}
    ${input("Mô tả", "description", "", "text", 'maxlength="1000"')}
    <div style="display:flex;gap:8px">
      <button class="button" id="submit-btn">Thêm danh mục</button>
      <button type="button" class="button secondary" id="cancel-edit" style="display:none">Hủy sửa</button>
    </div>
    <p id="save-status" role="status" style="margin-top:8px;color:var(--danger,#c00)"></p>
  </form>
  <div class="panel table-wrap">
    <table>
      <thead><tr><th>Mã</th><th>Tên</th><th>Mô tả</th><th>Sản phẩm</th><th>Thao tác</th></tr></thead>
      <tbody id="cat-table-body"><tr><td colspan="5">Đang tải…</td></tr></tbody>
    </table>
  </div>
</div>`;

      await renderCategories();

      const form = document.querySelector("#category-form");
      const nameInput = form.querySelector('[name="name"]');
      const descInput = form.querySelector('[name="description"]');
      const submitBtn = form.querySelector("#submit-btn");
      const cancelBtn = form.querySelector("#cancel-edit");
      const formTitle = form.querySelector("#form-title");
      const saveStatus = form.querySelector("#save-status");

      /* Nhấn Sửa / Xóa trong bảng */
      root.addEventListener("click", async (ev) => {
        /* ---- Nút Sửa ---- */
        const editBtn = ev.target.closest("[data-edit]");
        if (editBtn) {
          editingId = Number(editBtn.dataset.edit);
          nameInput.value = editBtn.dataset.name;
          descInput.value = editBtn.dataset.desc;
          formTitle.textContent = "Sửa danh mục #" + editingId;
          submitBtn.textContent = "Lưu thay đổi";
          cancelBtn.style.display = "";
          nameInput.focus();
          return;
        }

        /* ---- Nút Xóa ---- */
        const deleteBtn = ev.target.closest("[data-delete]");
        if (deleteBtn && !deleteBtn.disabled) {
          if (!confirm("Xóa danh mục này? Thao tác không thể hoàn tác."))
            return;
          deleteBtn.disabled = true;
          try {
            await admin(
              "categories",
              { method: "DELETE" },
              "&id=" + deleteBtn.dataset.delete,
            );
            S.toast("Đã xóa danh mục.");
            await renderCategories();
          } catch (err) {
            S.toast(err.message, true);
            deleteBtn.disabled = false;
          }
        }
      });

      /* Hủy sửa */
      cancelBtn.addEventListener("click", () => {
        editingId = null;
        form.reset();
        formTitle.textContent = "Thêm danh mục";
        submitBtn.textContent = "Thêm danh mục";
        cancelBtn.style.display = "none";
        saveStatus.textContent = "";
      });

      /* Submit (thêm hoặc sửa) */
      form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        submitBtn.disabled = true;
        saveStatus.textContent = "";
        const body = { name: nameInput.value, description: descInput.value };
        try {
          if (editingId) {
            await admin(
              "categories",
              { method: "PUT", body },
              "&id=" + editingId,
            );
          } else {
            await admin("categories", { method: "POST", body });
          }
          cancelBtn.click(); // reset form
          await renderCategories();
        } catch (err) {
          saveStatus.textContent = err.message;
        } finally {
          submitBtn.disabled = false;
        }
      });
    }
  } catch (err) {
    notice(err);
  }
});
