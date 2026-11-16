# Rà soát sau khi cập nhật frontend

Ngày kiểm tra: 16/11/2026. Mục tiêu: demo local hoặc hosting PHP/MySQL miễn phí, mô phỏng một cửa hàng điện tử có nghiệp vụ thực tế. Đợt này chỉ kiểm tra và ghi báo cáo, không sửa mã ứng dụng, không đóng gói lại bản frontend của bạn.

## Kết luận

Luồng COD cơ bản đã có thể demo: xem/tìm/lọc sản phẩm → đăng nhập → giỏ → đặt hàng → xem đơn → admin xử lý. Frontend mới không làm hỏng các luồng chuẩn trong bộ test hiện tại. Tuy nhiên chưa nên giới thiệu là bản demo nghiệp vụ doanh nghiệp đầy đủ: còn một lỗi nhập điện thoại đã tái hiện, chính sách hiển thị chưa khớp nghiệp vụ, quản trị ảnh/danh mục còn hạn chế và chưa thử trên host thực tế.

Không tái sử dụng phần trăm hoàn thành từ các lần rà soát cũ; phạm vi “giống doanh nghiệp thật” cần được nghiệm thu bằng checklist, không bằng số file hoặc vẻ ngoài.

## Kiểm thử mới thực hiện

| Nhóm | Kết quả |
| --- | --- |
| Cú pháp PHP | 22 file qua |
| Cú pháp JavaScript | 11 file qua |
| API integration hiện có | 73 assertions qua |
| Browser integration trên frontend mới | 29 assertions qua |
| Responsive trong bộ test | Không tràn ngang trang ở 390/768/1440 px trên home, catalog, giỏ, lịch sử và admin products |
| Probe nhập điện thoại có khoảng trắng | Phát hiện frontend chấp nhận, backend từ chối 422 |
| Kiểm tra ZIP hosting | index.html, store.css và shop.js trong ZIP khác bản đang có trên đĩa |

MariaDB portable chạy cổng 3307; các test ghi dữ liệu dùng database `ltweb_nhom12_review_20261116`, tách khỏi database demo của bạn. Screenshot đã xem trực tiếp: `.local-test/home-desktop.png` và `.local-test/home-mobile.png`. Test không tràn ngang không có nghĩa mọi chi tiết giao diện đã đẹp/đúng; các lỗi bố cục bên dưới được phát hiện bằng quan sát ảnh.

73 + 29 là số assertions trong bộ test, không phải số chức năng hoặc bằng chứng hệ thống đã sẵn sàng bán hàng thật. Chưa kiểm thử Apache/host miễn phí hoặc tải đồng thời nhiều worker.

## P1 — Cần xử lý trước buổi demo hoàn chỉnh

### 1. Đồng bộ kiểm tra điện thoại — TV3 + TV5

- Tái hiện: nhập `0912 345 678` ở checkout. `cart.js:270` bỏ khoảng trắng/dấu chấm/dấu gạch khi kiểm tra, nên frontend xem là hợp lệ, nhưng dữ liệu gửi vẫn giữ nguyên.
- `backend/api/orders.php:29` kiểm tra chuỗi chưa chuẩn hóa, trả 422 “Hãy kiểm tra họ tên, điện thoại và địa chỉ.”
- Hậu quả: khách nhập số phổ biến có khoảng trắng bị kẹt tại đặt hàng, dù không có lỗi field ở frontend.
- [ ] Chuẩn hóa điện thoại thống nhất ở backend và frontend trước khi kiểm tra/lưu, trả lỗi cụ thể cho field.
- [ ] Thêm ca kiểm thử số có khoảng trắng, dấu chấm, +84 và số sai độ dài.

### 2. Thống nhất nội dung quảng bá và nghiệp vụ — TV1 + TV4 + TV5

- Trang chủ quảng bá “Miễn phí giao hàng toàn quốc cho đơn từ 2 triệu” (`frontend/index.html:20`, `70`), nhưng giỏ/chi tiết ghi phí demo 0 ₫ và backend luôn trả `shipping_fee = 0` (`backend/includes/orders.php:16`). Đơn dưới 2 triệu chưa có biểu phí tương ứng.
- Banner “Thu cũ đổi mới, trợ giá lên đến 3 triệu” (`frontend/index.html:137`) chỉ dẫn đến danh sách sản phẩm; chưa có quy trình/điều kiện tiếp nhận thu cũ.
- “Được yêu thích nhất” (`frontend/index.html:117`) đang lấy danh sách mặc định theo ngày tạo, chưa dựa trên lượt yêu thích hoặc doanh số.
- [ ] Chốt biểu phí giao hàng demo, ngưỡng miễn phí và lưu số tiền vào đơn để lịch sử đơn không đổi khi chính sách thay đổi.
- [ ] Nếu chưa triển khai thu cũ, thay banner bằng nội dung cửa hàng đang hỗ trợ; nếu giữ, cần ít nhất trang mô tả điều kiện và cách yêu cầu tư vấn.
- [ ] Chọn sản phẩm nổi bật bằng cờ quản trị hoặc đổi tiêu đề thành sản phẩm mới; không mô tả là yêu thích nhất nếu không có dữ liệu.

### 3. Bổ sung thông tin doanh nghiệp và trang chính sách — TV1 + TV4

Hiện footer chủ yếu có link sản phẩm/tài khoản và ghi website demo. Chưa thấy các trang liên hệ, giao hàng, đổi trả, bảo hành và quyền riêng tư trong danh sách file.

- [ ] Bổ sung tên cửa hàng, địa chỉ, email/hotline và giờ hỗ trợ; thông tin giả lập phải ghi là dữ liệu demo.
- [ ] Bổ sung trang/section chính sách, liên kết từ footer và checkout.
- [ ] Giải thích điều kiện “đổi mới 30 ngày”, “bảo hành chính hãng”, phạm vi áp dụng và cách liên hệ. Không cần triển khai hệ thống bảo hành đầy đủ để demo, nhưng thông tin phải nhất quán.

### 4. Hoàn thiện quản trị sản phẩm và danh mục — TV4 + TV5

- Admin tạo/sửa/ngừng bán sản phẩm đã hoạt động, nhưng ảnh mới chỉ chọn từ 8 tên file viết cứng trong `frontend/assets/js/admin.js`.
- API danh mục chỉ có đọc và thêm; chưa sửa/xóa hoặc ẩn danh mục.
- [ ] Cho phép admin upload/thay ảnh mới, kiểm tra loại file/dung lượng và cấm thực thi script; có thể ưu tiên một ảnh đại diện cho bản demo.
- [ ] Hoàn thiện sửa danh mục và ngừng sử dụng/xóa có kiểm tra sản phẩm liên quan.
- [ ] Chuẩn bị dữ liệu demo phong phú hơn 8 sản phẩm để thể hiện phân trang, tìm kiếm, hết hàng và ngừng bán ngay trong buổi trình bày.

### 5. Nâng nội dung chi tiết sản phẩm — TV2 + TV6

Hiện có tên, giá, tồn kho và mô tả text; chưa có trải nghiệm bảng thông số, bộ ảnh và lựa chọn cấu hình/màu như một cửa hàng điện tử.

- [ ] Bổ sung thông số cần thiết: hãng, CPU/RAM/SSD hoặc màn hình/bộ nhớ tùy nhóm, thời hạn bảo hành và tình trạng hàng.
- [ ] Hiển thị các ảnh phụ từ `product_images` mà API đã trả về.
- [ ] Chốt cách biểu diễn SKU: có thể mỗi cấu hình là một sản phẩm riêng cho demo. Chỉ cần bảng biến thể nếu thực sự có lựa chọn màu/dung lượng với giá và tồn kho riêng.

### 6. Sửa các điểm hình ảnh mobile/desktop — TV1 + TV2

- Ảnh thẻ sản phẩm dùng `object-fit: cover` ở `store.css:536`, làm cắt phần thân/cạnh của điện thoại, tai nghe và laptop; trên mobile ảnh phóng lớn, khó xem toàn bộ thiết bị.
- Hero mobile đặt ảnh phía sau vùng nút và các cam kết, làm chữ bên dưới giảm tương phản và phần ảnh bị che. Nên tách vùng ảnh hoặc giảm vùng phủ, kiểm tra lại 390 px.
- Giá và tồn kho đang nằm sát nhau, một số thẻ xuống dòng không đồng đều; nên tách rõ hai dòng.
- [ ] Dùng ảnh phù hợp khung sản phẩm, ưu tiên xem trọn thiết bị; kiểm tra chất lượng ảnh hero khi phóng lớn.
- [ ] Giữ kích thước/nhịp thẻ nhất quán và kiểm tra cả trạng thái tên dài, hết hàng, số lượng lớn.

### 7. Chuẩn bị đúng bản để người khác chạy/demo — TV1 + TV5 + TV6

- ZIP `dist/nhom12-store-demo.zip` hiện là bản cũ: so sánh SHA256 của index.html/store.css/shop.js đều không khớp frontend mới. Không upload ZIP này rồi kỳ vọng thấy thiết kế hiện tại.
- Nhiều file quan trọng vẫn chưa được Git theo dõi, gồm API admin/csrf, helper và JS/CSS mới. Nếu chỉ push phần đang staged, máy thành viên khác có thể nhận thiếu code.
- [ ] Sau khi chốt frontend, chạy lại script package-host và kiểm tra gói đủ file, không chứa `.env`, `.git`, database local hoặc dependency test.
- [ ] Review rồi đưa đủ file ứng dụng/tài liệu/test vào Git; không commit `.local-test`/`dist` hay thông tin kết nối thật.
- [ ] Thử clone/cài mới theo README và chạy lại luồng demo.
- [ ] Nếu trình bày bằng link online, deploy thử trên host mục tiêu và kiểm tra session, CSRF, method PUT/DELETE, đường dẫn chữ hoa/thường, import schema và ảnh. Hiện mới xác nhận local.

## P2 — Nên bổ sung để demo sát quy trình doanh nghiệp

| Đầu việc | Hiện trạng | Phụ trách |
| --- | --- | --- |
| Hồ sơ và địa chỉ | Mới đổi tên, email cố định; chưa lưu điện thoại/địa chỉ nhận hàng dùng lại | TV3 + TV5 + TV6 |
| Đổi mật khẩu | Chưa có; quên mật khẩu qua email có thể để sau | TV3 + TV5 |
| Hủy đơn phía khách | Hiện chỉ admin hủy; có thể bổ sung yêu cầu hủy ở pending | TV3 + TV4 + TV5 |
| Thông tin vận chuyển | Chưa có đơn vị giao, mã vận đơn, ngày giao dự kiến; demo có thể nhập tay, chưa cần API hãng vận chuyển | TV4 + TV5 + TV6 |
| Trạng thái thu tiền COD | Chưa tách khỏi trạng thái xử lý đơn; doanh thu dashboard tính theo đơn completed | TV4 + TV5 + TV6 |
| Lịch sử xử lý đơn | Chưa lưu ai đổi trạng thái, lúc nào, lý do hủy | TV4 + TV5 + TV6 |
| Lọc đơn quản trị | Mới tìm người nhận/mã đơn và phân trang; nên lọc trạng thái/ngày | TV4 + TV5 |
| Phiếu đơn/in | Chưa có bản in đơn hoặc phiếu giao để trình bày quy trình cửa hàng | TV4 |
| Cấu hình nội dung | Banner/chính sách còn viết trực tiếp trong frontend; phần quản trị tương ứng đang là scaffold trống | TV1 + TV4 + TV5 |

Các file banners/posts/users/settings/category-form còn trống nhưng không có link trong menu quản trị hiện tại. Không gọi chúng là lỗi chặn luồng đang có; chỉ cần làm nếu nhóm chọn đưa vào phạm vi demo.

## P2 — Chi tiết kỹ thuật và khả năng sử dụng

- `store.js` vẫn có hàm categoryCard riêng dùng ký hiệu cũ, không gọi `Shop.categoryCard` SVG mới; nên dùng chung để tránh lệch thiết kế.
- `shop.js` và `main.js` cùng gọi updateCartCount khi tải trang; giảm request trùng, nhất là host miễn phí.
- Link giỏ hàng header chỉ có SVG ẩn với screen reader và con số; bổ sung accessible name “Giỏ hàng”, không để tên link chỉ là “0”.
- Đăng nhập từ trang admin có thể mất đích quay lại: Shop.login chỉ phân biệt users/, chưa giữ admin/. Nên kiểm tra quay lại đúng màn hình quản trị yêu cầu đăng nhập.
- Chưa có giới hạn thử đăng nhập, phục hồi mật khẩu và cơ chế backup tự động. Đây là việc cần nâng trước khi bán thật; không bắt buộc tích hợp email hoặc hệ thống lớn chỉ để demo local.
- Font tải từ Google Fonts; kiểm tra fallback nếu demo không có mạng, tránh chậm khi trình bày.

## Database có cần thêm bảng không?

Đã có users, products, categories, carts/cart_items, orders/order_items và product_images; đủ nền tảng cho COD. Không cần tạo database riêng cho từng thực thể. Có thể bổ sung cột phí ship/thông tin giao hàng vào orders cho bản demo. Bảng deliveries, addresses, variants, order_status_history chỉ nên thêm khi đã chọn nghiệp vụ tương ứng, kèm API/giao diện/kiểm thử đồng bộ.

## Thứ tự khuyến nghị

1. Sửa điện thoại; chốt phí giao hàng và nội dung khuyến mại để nội dung/giá tính khớp nhau.
2. Sửa ảnh/hero mobile, bổ sung liên hệ/chính sách và thông số sản phẩm.
3. Hoàn thiện admin ảnh/danh mục, thêm dữ liệu và kịch bản đơn hàng mẫu.
4. Bổ sung phần nghiệp vụ P2 mà nhóm muốn trình diễn; COD vẫn đủ, không bắt buộc thanh toán online.
5. Chốt code Git, dựng lại trên môi trường sạch, tạo lại ZIP và test trên host nếu cần link demo.

Kịch bản nghiệm thu đề xuất: khách đăng ký → lọc sản phẩm theo nhu cầu → xem cấu hình/bảo hành → thêm giỏ → nhập thông tin giao hàng → kiểm tra tổng tiền/phí ship → đặt COD → xem trạng thái; admin thêm sản phẩm với ảnh mới → xử lý đơn → giao/hoàn thành hoặc hủy hoàn kho → kiểm tra lịch sử và chỉ số.
