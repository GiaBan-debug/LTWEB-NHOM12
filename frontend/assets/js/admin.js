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
  const input = (label, name, value = "", type = "text", attrs = "") =>
    `<label class="field">${label}<input name="${name}" type="${type}" value="${e(value)}" ${attrs}></label>`;
  const notice = (err) => {
    if (err.status === 401) S.login();
    else S.error(root, err.message);
  };
  try {
    const { data: user } = await S.api("me.php");
    if (user.role !== "admin") {
      root.innerHTML =
        '<div class="notice notice-error">Bạn không có quyền quản trị.</div>';
      return;
    }
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
    if (view === "dashboard") {
      const { data: d } = await admin("dashboard");
      root.innerHTML = `<div class="stat-grid">${[
        ["Sản phẩm đang bán", d.products],
        ["Tổng đơn hàng", d.orders],
        ["Đơn chờ xác nhận", d.pending],
        ["Doanh thu đơn hoàn thành", S.money(d.revenue)],
      ]
        .map(
          ([label, value]) =>
            `<div class="panel stat"><small>${label}</small><strong>${value}</strong></div>`,
        )
        .join(
          "",
        )}</div><div class="panel" style="margin-top:24px"><h2>Quản lý cửa hàng</h2><p class="lead">Kiểm tra đơn mới, cập nhật trạng thái và quản lý sản phẩm từ các mục phía trên.</p><a class="button" href="${link("orders.html")}">Xem đơn hàng →</a></div>`;
    }
    if (view === "admin-products" || view === "admin-orders") {
      const isProducts = view === "admin-products";
      const search = params.get("search") || "";
      const { data: d } = await admin(
        isProducts ? "products" : "orders",
        {},
        "&page=" +
          (Number(params.get("page")) || 1) +
          "&search=" +
          encodeURIComponent(search),
      );
      const rows = d.items
        .map((p) =>
          isProducts
            ? `<tr><td><img src="${S.image(p.thumbnail)}" alt=""></td><td>${e(p.name)}<br><small>#${p.id} · ${p.status === "active" ? "Đang bán" : "Ngừng bán"}</small></td><td>${S.money(p.price)}</td><td>${Number(p.stock)}</td><td><div class="table-actions"><a class="button secondary small" href="${link("product-form.html?id=" + p.id)}">Sửa</a><button class="button danger small" data-stop="${p.id}" ${p.status !== "active" ? "disabled" : ""}>Ngừng bán</button></div></td></tr>`
            : `<tr><td>#${p.id}</td><td>${e(p.receiver_name)}</td><td>${S.money(Number(p.total_price) + Number(p.shipping_fee || 0))}</td><td><span class="status ${e(p.status)}">${e(S.labels[p.status])}</span></td><td>${e(p.created_at)}</td><td><a class="button secondary small" href="${link("order-detail.html?id=" + p.id)}">Chi tiết</a></td></tr>`,
        )
        .join("");
      root.innerHTML = `<div class="toolbar"><form id="admin-search" style="display:flex;gap:8px"><input class="search-input" name="search" value="${e(search)}" placeholder="${isProducts ? "Tìm tên sản phẩm" : "Tìm người nhận / mã đơn"}" maxlength="150" aria-label="Tìm kiếm"><button class="button secondary">Tìm</button></form>${isProducts ? `<a class="button" href="${link("product-form.html")}">+ Thêm sản phẩm</a>` : ""}</div><div class="panel table-wrap"><p class="subtle">${d.total} kết quả</p><table><thead><tr>${(isProducts ? ["Ảnh", "Sản phẩm", "Giá", "Tồn kho", "Thao tác"] : ["Mã đơn", "Người nhận", "Tổng tiền", "Trạng thái", "Ngày đặt", "Thao tác"]).map((t) => "<th>" + t + "</th>").join("")}</tr></thead><tbody>${rows || '<tr><td colspan="6">Chưa có dữ liệu phù hợp.</td></tr>'}</tbody></table></div>${S.pagination(d)}`;
      root.addEventListener("click", async (ev) => {
        const page = ev.target.closest("[data-page]");
        if (page && !page.disabled) {
          const q = new URLSearchParams({ page: page.dataset.page, search });
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
    if (view === "admin-product-form") {
      const [{ data: cats }, { data: p }] = await Promise.all([
        admin("categories"),
        id
          ? admin("products", {}, "&id=" + id)
          : Promise.resolve({ data: { status: "active", price: 0, stock: 0 } }),
      ]);
      root.innerHTML = `<form id="product-form" class="panel"><div class="form-grid">${input("Tên sản phẩm", "name", p.name || "", "text", 'required maxlength="200"')}<label class="field">Danh mục<select name="category_id" required>${cats.map((c) => `<option value="${c.id}" ${Number(p.category_id) === Number(c.id) ? "selected" : ""}>${e(c.name)}</option>`).join("")}</select></label>${input("Giá bán (₫)", "price", p.price, "number", 'required min="0" max="1000000000" step="1"')}${input("Tồn kho", "stock", p.stock, "number", 'required min="0" max="1000000" step="1"')}<label class="field">Ảnh sản phẩm<select name="thumbnail"><option value="">Dùng ảnh mặc định</option>${["iphone-15-pro-max.jpg", "samsung-s24-ultra.jpg", "xiaomi-14-ultra.jpg", "iphone-14.jpg", "samsung-z-flip5.jpg", "oppo-find-n3.jpg", "macbook-air-m3.jpg", "dell-xps-15.jpg", "macbook-pro-14.jpg", "asus-rog-zephyrus.jpg", "thinkpad-x1.jpg", "hp-envy-16.jpg", "airpods-pro-2.jpg", "cap-usb-c.jpg", "anker-gan-65w.jpg", "logitech-mx-master-3s.jpg", "keychron-k2-pro.jpg", "baseus-blade-100w.jpg", "ipad-pro-m4.jpg", "ipad-air-5.jpg", "ipad-gen-10.jpg", "galaxy-tab-s9.jpg", "xiaomi-pad-6.jpg", "lenovo-tab-p12.jpg"].map((f) => `<option value="products/${f}" ${p.thumbnail === "products/" + f ? "selected" : ""}>${f}</option>`).join("")}</select></label><label class="field">Trạng thái<select name="status"><option value="active">Đang bán</option><option value="inactive" ${p.status === "inactive" ? "selected" : ""}>Ngừng bán</option></select></label><label class="field span-2">Mô tả / thông số kỹ thuật<textarea name="description" maxlength="10000">${e(p.description || "")}</textarea></label></div><p class="subtle">Bản demo chọn ảnh có sẵn trong uploads/products. Giá và tồn kho phải là số nguyên không âm.</p><button class="button">Lưu sản phẩm</button> <a class="button secondary" href="${link("products.html")}">Quay lại</a><p id="save-status" role="status"></p></form>`;
      document
        .querySelector("#product-form")
        .addEventListener("submit", async (ev) => {
          ev.preventDefault();
          const form = ev.currentTarget,
            button = form.querySelector("button");
          button.disabled = true;
          try {
            await admin(
              "products",
              {
                method: id ? "PUT" : "POST",
                body: Object.fromEntries(new FormData(form)),
              },
              id ? "&id=" + id : "",
            );
            location.href = link("products.html");
          } catch (err) {
            document.querySelector("#save-status").textContent = err.message;
            button.disabled = false;
          }
        });
    }
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
      root.innerHTML =
        S.orderDetail(o) +
        `<div class="panel" style="margin-top:20px"><h2>Cập nhật trạng thái</h2><div class="table-actions">${allowed[o.status].map((s) => `<button class="button ${s === "cancelled" ? "danger" : ""}" data-status="${s}">${e(S.labels[s])}</button>`).join("") || '<p class="subtle">Đơn đã kết thúc, không thể đổi trạng thái.</p>'}</div><p class="subtle">Hủy đơn sẽ hoàn tồn kho một lần. Đơn đang giao chỉ có thể chuyển sang hoàn thành.</p></div>`;
      root.addEventListener("click", async (ev) => {
        const button = ev.target.closest("[data-status]");
        if (!button) return;
        if (
          button.dataset.status === "cancelled" &&
          !confirm("Hủy đơn và hoàn tồn kho?")
        )
          return;
        root.querySelectorAll("button").forEach((b) => {
          b.disabled = true;
        });
        try {
          await admin(
            "orders",
            { method: "PUT", body: { status: button.dataset.status } },
            "&id=" + id,
          );
          location.reload();
        } catch (err) {
          S.toast(err.message, true);
          root.querySelectorAll("button").forEach((b) => {
            b.disabled = false;
          });
        }
      });
    }
    if (view === "admin-categories") {
      const { data: cats } = await admin("categories");
      root.innerHTML = `<div class="catalog-layout"><form class="panel" id="category-form"><h2>Thêm danh mục</h2>${input("Tên danh mục", "name", "", "text", 'required maxlength="100"')}${input("Mô tả", "description", "", "text", 'maxlength="1000"')}<button class="button">Thêm danh mục</button><p id="save-status" role="status"></p></form><div class="panel table-wrap"><table><thead><tr><th>Mã</th><th>Tên danh mục</th><th>Mô tả</th></tr></thead><tbody>${cats.map((c) => `<tr><td>#${c.id}</td><td>${e(c.name)}</td><td>${e(c.description)}</td></tr>`).join("")}</tbody></table></div></div>`;
      document
        .querySelector("#category-form")
        .addEventListener("submit", async (ev) => {
          ev.preventDefault();
          const form = ev.currentTarget,
            button = form.querySelector("button");
          button.disabled = true;
          try {
            await admin("categories", {
              method: "POST",
              body: Object.fromEntries(new FormData(form)),
            });
            location.reload();
          } catch (err) {
            document.querySelector("#save-status").textContent = err.message;
            button.disabled = false;
          }
        });
    }
  } catch (err) {
    notice(err);
  }
});
