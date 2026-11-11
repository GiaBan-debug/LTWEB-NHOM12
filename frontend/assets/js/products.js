const API_URL = "";
const IMG = "../../assets/images/";
const CART_KEY = "cart";

const CATEGORIES = [
  { slug: "pc-gaming", name: "PC Gaming" },
  { slug: "pc-van-phong", name: "PC Văn phòng" },
  { slug: "laptop", name: "Laptop", children: [
    { slug: "laptop-gaming", name: "Laptop Gaming" },
    { slug: "laptop-van-phong", name: "Laptop Văn phòng" },
  ] },
  { slug: "linh-kien", name: "Linh kiện máy tính", children: [
    { slug: "cpu", name: "CPU" },
    { slug: "ram", name: "RAM" },
    { slug: "mainboard", name: "Mainboard" },
    { slug: "vga", name: "VGA" },
  ] },
];

const MO_TA = "Máy tính để bàn gaming. Liên hệ hotline 012.345.6789 để được tư vấn cấu hình.";
const SAMPLE_PRODUCTS = [
  { id: 1, name: "PC TTG GAMING i5 12400F - 16GB DDR4 - RTX 5060 8GB (All NEW - Bảo hành 36 tháng)", category: "pc-gaming", price: 25280000, oldPrice: 27990000, image: "pc1.jpeg", stock: 10, description: MO_TA },
  { id: 2, name: "PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5060 Ti 8GB", category: "pc-gaming", price: 37980000, oldPrice: 39990000, image: "pc2.jpg", stock: 5, description: MO_TA },
  { id: 3, name: "PC TTG AMD GAMING Ryzen 7 5700X - RTX 3050 8GB (All NEW - Bảo hành 36 tháng)", category: "pc-gaming", price: 18280000, oldPrice: 19990000, image: "pc3.png", stock: 8, description: MO_TA },
  { id: 4, name: "PC TTG GAMING CORE ULTRA 5 250K PLUS - RTX 5070 12GB OC", category: "pc-gaming", price: 48980000, oldPrice: 53990000, image: "pc4.png", stock: 3, description: MO_TA },
  { id: 5, name: "PC TTG AMD Ryzen 5 5500 - RTX 5050 8GB OC (Cấu hình gốc)", category: "pc-gaming", price: 19680000, oldPrice: 24990000, image: "pc5.png", stock: 6, description: MO_TA },
];

let PRODUCTS = [];

const $ = (s, r = document) => r.querySelector(s);
const fmt = n => n.toLocaleString("vi-VN") + " VND";
const plain = s => s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").toLowerCase();
const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const imgSrc = p => (/^(https?:|\.|\/)/.test(p.image) ? p.image : IMG + p.image);
const discount = p => (p.oldPrice > p.price ? Math.round((1 - p.price / p.oldPrice) * 100) : 0);
const flatCats = () => CATEGORIES.flatMap(c => [c, ...(c.children || [])]);
const catSlugs = slug => {
  const c = flatCats().find(x => x.slug === slug);
  return c ? [c.slug, ...(c.children || []).map(k => k.slug)] : [];
};

const normalize = p => ({
  id: Number(p.id),
  name: p.name,
  category: p.category || p.category_slug || "",
  price: Number(p.price),
  oldPrice: Number(p.oldPrice ?? p.old_price ?? 0),
  image: p.image || "",
  stock: Number(p.stock ?? 1),
  description: p.description || "",
});

async function loadProducts() {
  if (API_URL) {
    try {
      const res = await fetch(API_URL);
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      return (Array.isArray(data) ? data : data.data).map(normalize);
    } catch (e) {
      console.warn("Không gọi được API, dùng dữ liệu mẫu:", e);
    }
  }
  return SAMPLE_PRODUCTS.map(normalize);
}

function cardHTML(p) {
  const d = discount(p), out = p.stock <= 0, url = `product-detail.html?id=${p.id}`;
  return `<div class="card">
    <a href="${url}"><img class="card-image" src="${esc(imgSrc(p))}" alt="${esc(p.name)}"></a>
    <h3 class="card-name"><a href="${url}">${esc(p.name)}</a></h3>
    <div class="card-price"><span class="new-price">${fmt(p.price)}</span>${d ? `<span class="sales">-${d}%</span>` : ""}</div>
    ${d ? `<del class="old-price">${fmt(p.oldPrice)}</del>` : ""}
    <div class="card-bottom">
      <div class="cart-group">
        <img class="shop-icon" src="${IMG}shopicon.png" alt="">
        <button class="btn-cart" data-add="${p.id}" ${out ? "disabled" : ""}>THÊM VÀO GIỎ</button>
      </div>
      <span class="stock ${out ? "out" : ""}">${out ? "Hết hàng" : "Còn hàng"}</span>
    </div>
    <div class="buy-item" data-buy="${p.id}">MUA NGAY</div>
  </div>`;
}

/* ---------- Giỏ hàng ---------- */
const getCart = () => { try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch { return []; } };
const saveCart = c => { localStorage.setItem(CART_KEY, JSON.stringify(c)); renderCart(); };

function addToCart(p, qty = 1) {
  const cart = getCart(), item = cart.find(i => i.id === p.id);
  if (item) item.qty = Math.min(item.qty + qty, p.stock);
  else cart.push({ id: p.id, name: p.name, price: p.price, image: p.image, stock: p.stock, qty: Math.min(qty, p.stock) });
  saveCart(cart);
}

function toggleCart(open) { document.body.classList.toggle("cart-open", open); }

function toast(msg) {
  const el = $("#toast");
  el.textContent = msg;
  el.classList.add("show");
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.classList.remove("show"), 1800);
}

function injectCart() {
  document.body.insertAdjacentHTML("beforeend", `
    <div id="cart-overlay"></div>
    <aside id="cart-drawer" aria-label="Giỏ hàng">
      <div class="cart-head"><h3>Giỏ hàng</h3><button id="cart-close" aria-label="Đóng giỏ hàng">&times;</button></div>
      <div id="cart-items"></div>
      <div class="cart-foot">
        <div class="cart-total"><span>Tổng cộng</span><strong id="cart-total"></strong></div>
        <button class="btn-checkout" id="btn-checkout">THANH TOÁN</button>
      </div>
    </aside>
    <div id="toast" role="status"></div>`);
  const badge = document.createElement("span");
  badge.id = "cart-badge";
  badge.className = "cart-badge";
  $("[data-cart-toggle] .icon-circle")?.append(badge);
}

function renderCart() {
  const cart = getCart();
  $("#cart-items").innerHTML = cart.length ? cart.map(i => `
    <div class="cart-item">
      <img src="${esc(imgSrc(i))}" alt="">
      <div class="cart-info">
        <p class="cart-name">${esc(i.name)}</p>
        <span class="cart-price">${fmt(i.price)}</span>
        <div class="cart-qty"><button data-dec="${i.id}" aria-label="Giảm">−</button><span>${i.qty}</span><button data-inc="${i.id}" aria-label="Tăng">+</button></div>
      </div>
      <button class="cart-remove" data-del="${i.id}" aria-label="Xóa khỏi giỏ">&times;</button>
    </div>`).join("") : `<p class="empty">Giỏ hàng trống. Hãy chọn sản phẩm bạn thích.</p>`;
  $("#cart-total").textContent = fmt(cart.reduce((s, i) => s + i.price * i.qty, 0));
  const n = cart.reduce((s, i) => s + i.qty, 0), badge = $("#cart-badge");
  if (badge) { badge.textContent = n; badge.hidden = n === 0; }
}

function onClick(e) {
  const t = e.target.closest("[data-add],[data-buy],[data-inc],[data-dec],[data-del],[data-cart-toggle],#cart-close,#cart-overlay,#btn-checkout");
  if (!t) return;
  const d = t.dataset;
  if ("cartToggle" in d) { e.preventDefault(); return toggleCart(true); }
  if (t.id === "cart-close" || t.id === "cart-overlay") return toggleCart(false);
  if (t.id === "btn-checkout") return alert("Chức năng thanh toán sẽ được nối với backend.");

  const id = Number(d.add || d.buy || d.inc || d.dec || d.del);
  if (d.add || d.buy) {
    const p = PRODUCTS.find(x => x.id === id);
    if (!p || p.stock <= 0) return;
    addToCart(p, "detail" in d ? Math.max(1, Number($("#qty")?.value) || 1) : 1);
    if (d.buy) toggleCart(true); else toast("Đã thêm vào giỏ hàng");
    return;
  }
  const cart = getCart(), i = cart.findIndex(x => x.id === id);
  if (i < 0) return;
  if (d.inc) cart[i].qty = Math.min(cart[i].qty + 1, cart[i].stock);
  if (d.dec) cart[i].qty -= 1;
  if (d.del || cart[i].qty <= 0) cart.splice(i, 1);
  saveCart(cart);
}

/* ---------- Trang danh sách sản phẩm ---------- */
function pageProducts() {
  const params = new URLSearchParams(location.search);
  const state = { cat: params.get("cat") || "", q: params.get("q") || "", sort: "" };
  const selCat = $("#filter-cat"), selSort = $("#sort"), grid = $("#product-grid"), info = $("#result-info");
  selCat.innerHTML = `<option value="">Tất cả danh mục</option>` + flatCats().map(c => `<option value="${c.slug}">${c.name}</option>`).join("");
  selCat.value = state.cat;
  if (state.q && $("#search-input")) $("#search-input").value = state.q;

  function render() {
    const list = PRODUCTS.filter(p =>
      (!state.cat || catSlugs(state.cat).includes(p.category)) &&
      (!state.q || plain(p.name).includes(plain(state.q))));
    if (state.sort === "asc") list.sort((a, b) => a.price - b.price);
    if (state.sort === "desc") list.sort((a, b) => b.price - a.price);
    info.textContent = `${list.length} sản phẩm` + (state.q ? ` cho "${state.q}"` : "");
    grid.innerHTML = list.length ? list.map(cardHTML).join("") : `<p class="empty">Chưa có sản phẩm nào trong mục này.</p>`;
  }
  selCat.onchange = () => { state.cat = selCat.value; render(); };
  selSort.onchange = () => { state.sort = selSort.value; render(); };
  render();
}

/* ---------- Trang chi tiết sản phẩm  ---------- */
function pageDetail() {
  const id = Number(new URLSearchParams(location.search).get("id"));
  const p = PRODUCTS.find(x => x.id === id), box = $("#product-detail");
  if (!p) {
    box.innerHTML = `<p class="empty">Không tìm thấy sản phẩm này. <a href="products.html">Xem tất cả sản phẩm</a></p>`;
    return;
  }
  document.title = p.name + " - SHOP DEMO";
  const d = discount(p), out = p.stock <= 0, cat = flatCats().find(c => c.slug === p.category);
  box.innerHTML = `
    <div class="detail-img"><img src="${esc(imgSrc(p))}" alt="${esc(p.name)}"></div>
    <div class="detail-info">
      <p class="breadcrumb"><a href="../../index.html">Trang chủ</a> / <a href="products.html">Sản phẩm</a>${cat ? ` / <a href="products.html?cat=${cat.slug}">${cat.name}</a>` : ""}</p>
      <h1>${esc(p.name)}</h1>
      <div class="card-price"><span class="new-price">${fmt(p.price)}</span>${d ? `<span class="sales">-${d}%</span>` : ""}</div>
      ${d ? `<del class="old-price">${fmt(p.oldPrice)}</del>` : ""}
      <p><span class="stock ${out ? "out" : ""}">${out ? "Hết hàng" : "Còn " + p.stock + " sản phẩm"}</span></p>
      <p class="detail-desc">${esc(p.description)}</p>
      <div class="detail-actions">
        <input id="qty" type="number" min="1" max="${Math.max(p.stock, 1)}" value="1" aria-label="Số lượng" ${out ? "disabled" : ""}>
        <button class="buy-item" data-add="${p.id}" data-detail ${out ? "disabled" : ""}>THÊM VÀO GIỎ</button>
      </div>
    </div>`;
  const related = PRODUCTS.filter(x => x.category === p.category && x.id !== p.id).slice(0, 4);
  $("#related").innerHTML = related.length ? `<h2>Sản phẩm liên quan</h2><div class="product-grid">${related.map(cardHTML).join("")}</div>` : "";
}

/* ---------- Trang danh mục ---------- */
function pageCategories() {
  const count = slug => PRODUCTS.filter(p => catSlugs(slug).includes(p.category)).length;
  const tile = c => `<div class="cat-tile">
    <a class="cat-main" href="products.html?cat=${c.slug}"><strong>${c.name}</strong><span>${count(c.slug)} sản phẩm</span></a>
    ${c.children ? `<ul>${c.children.map(k => `<li><a href="products.html?cat=${k.slug}">${k.name} (${count(k.slug)})</a></li>`).join("")}</ul>` : ""}
  </div>`;
  $("#category-list").innerHTML = CATEGORIES.map(tile).join("");
}

/* ---------- Khởi chạy ---------- */
document.addEventListener("DOMContentLoaded", async () => {
  injectCart();
  renderCart();
  document.addEventListener("click", onClick);
  $("#search-input")?.addEventListener("keydown", e => {
    if (e.key === "Enter") location.href = `products.html?q=${encodeURIComponent(e.target.value.trim())}`;
  });
  PRODUCTS = await loadProducts();
  ({ products: pageProducts, detail: pageDetail, categories: pageCategories })[document.body.dataset.page]?.();
});
