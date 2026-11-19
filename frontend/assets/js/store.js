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
      // Định dạng giá có dấu chấm phân cách hàng nghìn
function formatPrice(value) {
    const digits = String(value || "").replace(/\D/g, "");
    return digits ? Number(digits).toLocaleString("vi-VN") : "";
}

// Chuyển giá có dấu chấm về số thuần
function parsePrice(value) {
    return String(value || "").replace(/\D/g, "");
}

// Khôi phục bộ lọc từ URL
for (const key of ["search", "category_id", "min_price", "max_price"]) {
    const value = params.get(key) || "";

    form.elements[key].value =
        key === "min_price" || key === "max_price"
            ? formatPrice(value)
            : value;
}

// Tự thêm dấu chấm khi người dùng nhập giá
for (const key of ["min_price", "max_price"]) {
    const input = form.elements[key];

    input.addEventListener("input", () => {
        const rawValue = parsePrice(input.value);
        input.value = formatPrice(rawValue);
    });
}
      sort.value = params.get("sort") || "newest";
      if (!sort.value) sort.value = "newest";
      async function load(page = 1) {
        const number = ++requestNumber;
        list.innerHTML =
          '<div class="skeleton" role="status">Đang tải sản phẩm…</div>';
        document.querySelector("#pagination").innerHTML = "";
        const formData = new FormData(form);

// Loại bỏ dấu chấm khỏi giá trước khi gửi API và tạo URL
formData.set("min_price", parsePrice(formData.get("min_price")));
formData.set("max_price", parsePrice(formData.get("max_price")));

const query = new URLSearchParams(formData);
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
      const inStock = Number(p.stock) > 0;

      // Lấy danh sách ảnh từ database
      const productImages = (Array.isArray(p.images) ? p.images : [])
      .map((item) => typeof item === "string" ? item : item?.image_path)
      .filter(Boolean);

      // Thêm ảnh đại diện nếu chưa có
      if (p.thumbnail && !productImages.includes(p.thumbnail)) {
        productImages.unshift(p.thumbnail);
      }

      // Đọc thông số kỹ thuật
      let specifications = p.specs || {};

if (typeof specifications === "string") {
  try {
    specifications = JSON.parse(specifications);
  } catch {
    specifications = specifications
      .split(";")
      .map((item) => item.trim())
      .filter(Boolean)
      .map((item, index) => ({
        label: "Thông số " + (index + 1),
        value: item
      }));
  }
}

if (Array.isArray(specifications)) {
  specifications = specifications.map((item, index) => {
    if (typeof item === "string") {
      return ["Thông số " + (index + 1), item];
    }

    return [
      item.label || item.name || ("Thông số " + (index + 1)),
      item.value ?? ""
    ];
  });
} else if (specifications && typeof specifications === "object") {
  specifications = Object.entries(specifications);
} else {
  specifications = [];
}

const specEntries = specifications.filter(
  ([, value]) => value !== null && value !== ""
);

const warrantyMonths = Number(p.warranty_months);

// Hiển thị giao diện chi tiết sản phẩm
root.innerHTML = `
<div class="detail-photo">

  <div class="detail-gallery-main">
    <img
      id="detail-main-image"
      src="${S.image(productImages[0] || p.thumbnail)}"
      alt="${e(p.name)}"
    >
  </div>

  ${
    productImages.length > 1
      ? `
      <div class="detail-gallery-thumbs">
        ${productImages.map((src, index) => `
          <button
            type="button"
            class="detail-thumb${index === 0 ? " is-active" : ""}"
            data-gallery-index="${index}"
          >
            <img
              src="${S.image(src)}"
              alt="${e(p.name)} - ảnh ${index + 1}"
            >
          </button>
        `).join("")}
      </div>
      `
      : ""
  }

</div>

<div class="detail-info">
  <span class="eyebrow">${e(p.category_name || "Sản phẩm")}</span>

  <h1>${e(p.name)}</h1>

  ${
  p.brand
    ? `<p class="product-brand">
         <strong>Thương hiệu:</strong> ${e(p.brand)}
       </p>`
    : ""
}

  <strong class="price">${S.money(p.price)}</strong>

  <p class="description">
    ${e(p.description || "Thông tin sản phẩm đang được cập nhật.")}
  </p>

  <p class="stock-info">
    ${
      inStock
        ? `<span class="stock-ok">● Còn hàng</span> — ${Number(p.stock)} sản phẩm`
        : `<span class="stock-out">● Tạm hết hàng</span>`
    }
  </p>

  ${
    warrantyMonths > 0
      ? `<p><strong>Bảo hành:</strong> ${warrantyMonths} tháng</p>`
      : ""
  }

  <div class="quantity-row">
    <span class="quantity-label">Số lượng</span>

    <input
      id="buy-quantity"
      class="quantity-input"
      type="number"
      value="1"
      min="1"
      max="${Math.max(1, Math.min(99, Number(p.stock) || 1))}"
      ${inStock ? "" : "disabled"}
    >
  </div>

  <div class="detail-actions">
    <button class="button" data-add="${p.id}" ${inStock ? "" : "disabled"}>
      Thêm vào giỏ hàng
    </button>

    <a class="button secondary" href="${S.page("cart")}">
      Xem giỏ hàng
    </a>
  </div>

  <p class="detail-note">
    Thanh toán khi nhận hàng (COD) · Giao hàng toàn quốc
  </p>
</div>

${
  specEntries.length
    ? `
      <section class="detail-specs">
        <h2>Thông số kỹ thuật</h2>

        <div class="detail-specs-table">
          ${specEntries.map(([label, value]) => `
            <div class="detail-spec-row">
              <strong>${e(label)}</strong>
              <span>${e(String(value))}</span>
            </div>
          `).join("")}
        </div>
      </section>
    `
    : ""
}
`;

// Bấm ảnh nhỏ để đổi ảnh lớn
root.querySelectorAll("[data-gallery-index]").forEach((button) => {
  button.addEventListener("click", () => {
    const index = Number(button.dataset.galleryIndex);
    const mainImage = root.querySelector("#detail-main-image");

    if (!mainImage || !productImages[index]) return;

    mainImage.src = S.image(productImages[index]);

    root.querySelectorAll("[data-gallery-index]").forEach((thumb) => {
      thumb.classList.toggle("is-active", thumb === button);
    });
  });
});
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
        const cancel =
          data.status === "pending"
            ? `<form id="cancel-order" class="panel" style="margin-top:16px"><h2>Hủy đơn</h2><p class="subtle">Đơn đang chờ xác nhận. Hủy sẽ hoàn sản phẩm về kho.</p><button class="button danger" type="submit">Yêu cầu hủy đơn</button></form>`
            : "";
        content.innerHTML =
          S.orderDetail(data) +
          (data.status === 'pending' ? '<button class="button danger" id="cancel-order">Hủy đơn đang chờ</button> ' : '') +
          `<a class="button secondary" href="${S.page("order-history")}">← Tất cả đơn hàng</a>`;
        document.querySelector('#cancel-order')?.addEventListener('click', async event => {
          if (!confirm('Hủy đơn đang chờ xác nhận?')) return;
          const button = event.currentTarget; button.disabled = true;
          try { await S.api('orders.php', {method:'PUT', body:{id:Number(data.id),action:'cancel'}}); location.href = S.page('order-history', '?id=' + data.id); }
          catch (error) { S.toast(error.message, true); button.disabled = false; }
        });
      } else {
        const { data } = await S.api(
          "orders.php?page=" + (Number(params.get("page")) || 1),
        );
        content.innerHTML = data.items.length
          ? data.items
              .map(
                (o) =>
                  `<article class="panel"><div class="order-head"><h2>Đơn #${o.id}</h2><span class="status ${e(o.status)}">${e(S.labels[o.status])}</span></div><p class="subtle">${e(o.created_at)} · Tạm tính ${S.money(o.total_amount)} · Phí giao ${S.money(o.shipping_fee || 0)}</p><div class="order-head"><strong>${S.money(S.payable(o))}</strong><a class="button secondary small" href="${S.page("order-history", "?id=" + o.id)}">Xem chi tiết →</a></div></article>`,
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
      form.elements.name.value = data.name || "";
      form.elements.email.value = data.email || "";
      form.elements.phone.value = data.phone || "";
      form.elements.address.value = data.address || "";
      const clearProfileErrors = () => {
        form.querySelectorAll(".field--invalid").forEach((el) => el.classList.remove("field--invalid"));
        form.querySelectorAll("[data-error-for]").forEach((el) => { el.textContent = ""; });
      };
      form.addEventListener("submit", async (ev) => {
        ev.preventDefault();
        clearProfileErrors();
        const name = form.elements.name.value.trim();
        const phoneRaw = form.elements.phone.value.trim();
        const phone = phoneRaw ? S.normalizePhone(phoneRaw) : "";
        const address = form.elements.address.value.trim();
        const errors = {};
        if (name.length < 2) errors.name = "Vui lòng nhập họ tên.";
        if (phoneRaw && !phone) errors.phone = "Số điện thoại không hợp lệ (VD: 0912 345 678 hoặc +84 912 345 678).";
        if (address && address.length < 5) errors.address = "Địa chỉ nhận hàng cần ít nhất 5 ký tự.";
        if (Object.keys(errors).length) {
          Object.entries(errors).forEach(([key, message]) => {
            const small = form.querySelector(`[data-error-for="${key}"]`);
            if (!small) return;
            small.textContent = message;
            small.closest(".field")?.classList.add("field--invalid");
          });
          return;
        }
        const button = form.querySelector("button");
        button.disabled = true;
        button.textContent = "Đang lưu…";
        try {
          const saved = await S.api("me.php", {
            method: "PUT",
            body: { name, phone, address },
          });
          form.elements.phone.value = saved.data.phone || "";
          form.elements.address.value = saved.data.address || "";
          await Auth.mountHeader();
          S.toast("Đã cập nhật thông tin.");
        } catch (err) {
          const fieldErrors = err.payload?.data?.errors || {};
          Object.entries(fieldErrors).forEach(([key, message]) => {
            const small = form.querySelector(`[data-error-for="${key}"]`);
            if (!small) return;
            small.textContent = message;
            small.closest(".field")?.classList.add("field--invalid");
          });
          if (err.status === 401) S.login();
          else S.toast(err.message, true);
        } finally {
          button.disabled = false;
          button.textContent = "Lưu thông tin";
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
