document.addEventListener("DOMContentLoaded", function () {
    const productList = document.querySelector("#productList");
    const sortFilter = document.querySelector("#sortFilter");
    const loading = document.querySelector("#loading");
    const productCount = document.querySelector("#productCount");

    const API_URL = "/LTWEB-NHOM12/backend/api/products.php";

    let allProducts = [];

    // Hiển thị danh sách sản phẩm
    function displayProducts(products) {
        if (products.length === 0) {
            productList.innerHTML = "<p>Chưa có sản phẩm nào.</p>";
            return;
        }

        productList.innerHTML = products.map(product => {
            const image = product.thumbnail || "";

            return `
                <div class="product-card">

                    <div class="product-image-wrap">
                        ${image ? `
                            <img
                                src="${image}"
                                alt="${product.name}"
                                onerror="this.style.display='none'"
                            >
                        ` : ""}
                    </div>

                    <div class="product-info">
                        <p class="product-category">
                            ${product.category_name || "Sản phẩm công nghệ"}
                        </p>

                        <h4>${product.name}</h4>

                        <p class="product-price">
                            ${Number(product.price).toLocaleString("vi-VN")} VNĐ
                        </p>

                        <p class="product-stock">
                            Còn lại: ${product.stock}
                        </p>
                    </div>

                </div>
            `;
        }).join("");

        if (productCount) {
            productCount.textContent =
                `Đang hiển thị ${products.length} sản phẩm`;
        }
    }

    // Xử lý sắp xếp sản phẩm
    function sortProducts() {
        let sortedProducts = [...allProducts];

        const sortValue = sortFilter ? sortFilter.value : "newest";

        if (sortValue === "price-asc") {
            sortedProducts.sort((a, b) =>
                Number(a.price) - Number(b.price)
            );
        }

        else if (sortValue === "price-desc") {
            sortedProducts.sort((a, b) =>
                Number(b.price) - Number(a.price)
            );
        }

        displayProducts(sortedProducts);
    }

    // Tải sản phẩm từ API
    async function loadProducts() {
        try {
            const response = await fetch(API_URL);

            if (!response.ok) {
                throw new Error("Không thể kết nối API");
            }

            const result = await response.json();

            allProducts = result.data?.items || [];

            if (loading) {
                loading.style.display = "none";
            }

            sortProducts();

        } catch (error) {
            console.error("Lỗi tải sản phẩm:", error);

            if (loading) {
                loading.style.display = "none";
            }

            productList.innerHTML =
                "<p>Không thể tải sản phẩm. Vui lòng thử lại.</p>";
        }
    }

    // Khi người dùng thay đổi lựa chọn sắp xếp
    if (sortFilter) {
        sortFilter.addEventListener("change", sortProducts);
    }

    loadProducts();
});
