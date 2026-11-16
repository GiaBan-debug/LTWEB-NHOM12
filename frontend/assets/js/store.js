document.addEventListener("DOMContentLoaded", async () => {
  "use strict";
  const S = window.Shop,
    e = S.escape;
  const view = document.body.dataset.view;
  const params = new URLSearchParams(location.search);
  const categoryCard = (c, i) =>
    `<a class="category-tile" href="${S.page("products", "?category_id=" + c.id)}"><span class="category-icon">${["▯", "▱", "◉", "▣"][i % 4]}</span><h3>${e(c.name)}</h3><p>${Number(c.product_count)} sản phẩm · Khám phá →</p></a>`;
  async function categories() {
    return (await S.api("categories.php")).data;
  }
  try {
    if (view === "home") {
      await Promise.allSettled([
        categories()
          .then((list) => {
            document.querySelector("#home-categories").innerHTML =
              list.map(categoryCard).join("") || "<p>Chưa có danh mục.</p>";
          })
          .catch((err) =>
            S.error(document.querySelector("#home-categories"), err.message),
          ),
        S.api("products.php?limit=8")
          .then((result) => {
            document.querySelector("#home-products").innerHTML =
              result.data.items.map(S.productCard).join("") ||
              "<p>Chưa có sản phẩm.</p>";
          })
          .catch((err) =>
            S.error(document.querySelector("#home-products"), err.message),
          ),
      ]);
    }
    if (view === "categories") {
      document.querySelector("#category-list").innerHTML =
        (await categories()).map(categoryCard).join("") ||
        '<div class="empty">Chưa có danh mục.</div>';
    }
    if (view === "products") {
      const form = document.querySelector("#catalog-form"),
        list = document.querySelector("#product-list"),
        sort = document.querySelector("#sort-filter");
      let requestNumber = 0;
      document.querySelector("#category-filter").innerHTML += (
        await categories()
      )
        .map((c) => `<option value="${c.id}">${e(c.name)}</option>`)
        .join("");
      for (const key of ["search", "category_id", "min_price", "max_price"])
        form.elements[key].value = params.get(key) || "";
      sort.value = params.get("sort") || "newest";
      if (!sort.value) sort.value = "newest";
      async function load(page = 1) {
        const number = ++requestNumber;
        list.innerHTML =
          '<div class="skeleton" role="status">Đang tải sản phẩm…</div>';
        document.querySelector("#pagination").innerHTML = "";
        const query = new URLSearchParams(new FormData(form));
        query.set("page", page);
        query.set("limit", 9);
        query.set(
          "sort_by",
          sort.value === "name"
            ? "name"
            : sort.value.startsWith("price")
              ? "price"
              : "created_at",
        );
        query.set(
          "sort_dir",
          ["price-asc", "name"].includes(sort.value) ? "ASC" : "DESC",
        );
        const visible = new URLSearchParams(query);
        visible.set("sort", sort.value);
        visible.delete("sort_by");
        visible.delete("sort_dir");
        visible.delete("limit");
        history.replaceState(null, "", "?" + visible);
        try {
          const { data } = await S.api("products.php?" + query);
          if (number !== requestNumber) return;
          document.querySelector("#product-count").textContent =
            `${data.total} sản phẩm · Trang ${data.page}`;
          list.innerHTML = data.items.length
            ? data.items.map(S.productCard).join("")
            : '<div class="empty">Không tìm thấy sản phẩm phù hợp. Hãy đổi bộ lọc.</div>';
          document.querySelector("#pagination").innerHTML = S.pagination(data);
        } catch (err) {
          if (number === requestNumber) {
            document.querySelector("#product-count").textContent = "";
            S.error(list, err.message);
          }
        }
      }
      form.addEventListener("submit", (ev) => {
        ev.preventDefault();
        load();
      });
      form.addEventListener("reset", () => {
        setTimeout(() => {
          sort.value = "newest";
          load();
        }, 0);
      });
      sort.addEventListener("change", () => load());
      document.querySelector("#pagination").addEventListener("click", (ev) => {
        const b = ev.target.closest("[data-page]");
        if (b && !b.disabled) load(Number(b.dataset.page));
      });
      await load(Number(params.get("page")) || 1);
    }
    if (view === "detail") {
      const id = params.get("id"),
        slug = params.get("slug");
      if (!id && !slug)
        throw new Error("Thiếu mã sản phẩm. Hãy chọn sản phẩm từ danh sách.");
      const { data: p } = await S.api(
        "products.php?" +
          (id
            ? "id=" + encodeURIComponent(id)
            : "slug=" + encodeURIComponent(slug)),
      );
      document.title = p.name + " · BTA STORE";
      // Update breadcrumb name
      const bc = document.querySelector("#breadcrumb-name");
      if (bc) bc.textContent = p.name;
      const root = document.querySelector("#product-detail");
      root.className = "detail-layout";
      const inStock = p.stock > 0;
      root.innerHTML = `
<div class="detail-photo">
  <img src="${S.image(p.thumbnail)}" alt="${e(p.name)}">
</div>
<div class="detail-info">
  <span class="eyebrow">${e(p.category_name)}</span>
  <h1>${e(p.name)}</h1>
  <strong class="price">${S.money(p.price)}</strong>
  <p class="description">${e(p.description || "Thông tin sản phẩm đang được cập nhật.")}</p>
  <p class="stock-info">
    ${inStock
      ? `<span class="stock-ok">● Còn hàng</span> — ${Number(p.stock)} sản phẩm`
      : `<span class="stock-out">● Tạm hết hàng</span>`}
  </p>
  <div class="quantity-row">
    <span class="quantity-label">Số lượng</span>
    <input id="buy-quantity" class="quantity-input" type="number" value="1" min="1" max="${Math.min(99, p.stock)}" ${inStock ? "" : "disabled"}>
  </div>
  <div class="detail-actions">
    <button class="button" data-add="${p.id}" ${inStock ? "" : "disabled"}>Thêm vào giỏ hàng</button>
    <a class="button secondary" href="${S.page("cart")}">Xem giỏ hàng</a>
  </div>
  <p class="detail-note">Thanh toán khi nhận hàng (COD) · Giao hàng toàn quốc · Bảo hành chính hãng</p>
</div>`;
      try {
        const { data } = await S.api(
          "products.php?category_id=" + p.category_id + "&limit=5",
        );
        document.querySelector("#related").innerHTML = data.items
          .filter((x) => x.id !== p.id)
          .slice(0, 4)
          .map(S.productCard)
          .join("");
      } catch {
        /* main product remains usable */
      }
    }
    if (view === "orders") {
      const content = document.querySelector("#orders-content");
      if (params.has("placed"))
        document.querySelector("#orders-notice").innerHTML =
          '<div class="notice" role="status">Đặt hàng thành công! Bạn có thể theo dõi trạng thái đơn tại đây.</div>';
      if (params.get("id")) {
        const { data } = await S.api(
          "orders.php?id=" + encodeURIComponent(params.get("id")),
        );
        content.innerHTML =
          S.orderDetail(data) +
          `<a class="button secondary" href="${S.page("order-history")}">← Tất cả đơn hàng</a>`;
      } else {
        const { data } = await S.api(
          "orders.php?page=" + (Number(params.get("page")) || 1),
        );
        content.innerHTML = data.items.length
          ? data.items
              .map(
                (o) =>
                  `<article class="panel"><div class="order-head"><h2>Đơn #${o.id}</h2><span class="status ${e(o.status)}">${e(S.labels[o.status])}</span></div><p class="subtle">${e(o.created_at)}</p><div class="order-head"><strong>${S.money(o.total_amount)}</strong><a class="button secondary small" href="${S.page("order-history", "?id=" + o.id)}">Xem chi tiết →</a></div></article>`,
              )
              .join("")
          : `<div class="empty"><h2>Bạn chưa có đơn hàng</h2><p>Chọn một sản phẩm yêu thích để bắt đầu.</p><a class="button" href="${S.page("products")}">Khám phá sản phẩm</a></div>`;
        document.querySelector("#pagination").innerHTML = S.pagination(data);
        document
          .querySelector("#pagination")
          .addEventListener("click", (ev) => {
            const b = ev.target.closest("[data-page]");
            if (b && !b.disabled) location.search = "?page=" + b.dataset.page;
          });
      }
    }
    if (view === "profile") {
      const { data } = await S.api("me.php");
      const form = document.querySelector("#profile-form");
      form.elements.name.value = data.name;
      form.elements.email.value = data.email;
      form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        const button = form.querySelector("button");
        button.disabled = true;
        try {
          await S.api("me.php", {
            method: "PUT",
            body: { name: form.elements.name.value },
          });
          await Auth.mountHeader();
          S.toast("Đã cập nhật thông tin.");
        } catch (err) {
          if (err.status === 401) S.login();
          else S.toast(err.message, true);
        } finally {
          button.disabled = false;
        }
      });
    }
  } catch (err) {
    if (err.status === 401) {
      S.login();
      return;
    }
    const target = document.querySelector(
      "#product-detail, #category-list, #orders-content, #profile-status, #product-list",
    );
    if (target) S.error(target, err.message);
    else S.toast(err.message, true);
  }
});
