# Phân công 6 thành viên — hoàn thiện demo cửa hàng điện tử

Ngày: 16/11/2026. Đây là phân công đề xuất cho phần còn lại, không yêu cầu làm lại chức năng đã chạy. Mục tiêu: demo local/hosting PHP + MySQL, đủ luồng khách mua COD và admin xử lý đơn. TV5 là người dùng, dự định chuyển xác thực sang Firebase Authentication. Tài liệu này chưa triển khai Firebase hoặc thay đổi code ứng dụng.

## Kiến trúc cần chốt trước

- Firebase Authentication: quản lý tài khoản đăng nhập, mật khẩu, xác minh email và khôi phục mật khẩu.
- MySQL: vẫn lưu users, quyền user/admin, hồ sơ, sản phẩm, giỏ, đơn và dữ liệu vận hành. Không chuyển toàn bộ database sang Firebase chỉ vì dùng Firebase Auth.
- Đề xuất tích hợp ít thay đổi: frontend đăng nhập Firebase → lấy Firebase ID token → gửi endpoint PHP qua HTTPS trên host → PHP xác minh token, tìm user theo firebase_uid, lấy quyền từ MySQL → tạo PHP session hiện có. API giỏ/đơn/admin tiếp tục dùng session và CSRF.
- PHP phải xác minh chữ ký, hạn dùng, issuer, audience và project của token theo tài liệu Firebase. Không chỉ decode JWT hoặc tin UID/email/role do frontend tự gửi.
- Đây là PHP session của ứng dụng sau khi kiểm tra Firebase ID token, không gọi là Firebase session cookie do Admin SDK phát hành. Cần chốt thời hạn session và thời điểm xác thực lại.
- Logout phải thoát cả Firebase và PHP; cần kiểm thử tải lại trang không tự đăng nhập lại ngoài ý muốn.
- Triển khai Email/Password trước. Google login là mở rộng nếu còn thời gian; chưa cần SMS/OTP điện thoại.
- Giữ bản đăng nhập hiện tại làm mốc kiểm thử trong lúc phát triển nhánh Firebase. Khi chuyển chính thức, cần cập nhật/ràng buộc endpoint cũ và test, không để hai hệ thống xác thực hoạt động ngoài dự kiến.

Nguồn: [Firebase Password Authentication](https://firebase.google.com/docs/auth/web/password-auth), [Firebase ID token verification](https://firebase.google.com/docs/auth/admin/verify-id-tokens). Firebase hướng dẫn dùng thư viện JWT bên thứ ba nếu ngôn ngữ server không được Admin SDK hỗ trợ trực tiếp. Chọn thư viện PHP được duy trì, không tự viết thuật toán xác minh token.

## TV1 — Giao diện chung, nội dung doanh nghiệp và tích hợp

**Chủ trì:** giao diện chung, nội dung, review/merge tích hợp.

- [ ] Sửa hero mobile: không để chữ/cam kết/nút bị ảnh che, giữ tương phản rõ.
- [ ] Sửa ảnh thẻ sản phẩm để nhìn trọn thiết bị; tách dòng giá và tồn kho, thống nhất thẻ dài/ngắn.
- [ ] Đồng bộ header/footer, trạng thái tài khoản và điều hướng trên tất cả trang.
- [ ] Dùng cùng component danh mục, loại bỏ sự lệch giữa icon cũ trong store.js và SVG mới trong Shop.
- [ ] Bổ sung trang liên hệ/giới thiệu, giao hàng, bảo hành/đổi trả, quyền riêng tư; dữ liệu doanh nghiệp giả lập phải được ghi rõ.
- [ ] Sửa nội dung banner theo chức năng thực sự có: chốt miễn ship, đổi “yêu thích nhất” nếu chưa có số liệu; không quảng bá thu cũ như chức năng đã hoạt động.
- [ ] Bổ sung accessible name cho link giỏ và kiểm tra focus/nhãn các thao tác chính.
- [ ] Quản lý board, review PR và tích hợp sau khi check chức năng; kiểm tra responsive 390/768/1440 px.

**Nghiệm thu:** không có link chính sách rỗng, thông tin giữa các trang thống nhất, ảnh không cắt mất thiết bị, nội dung không hứa tính năng chưa có.

## TV2 — Sản phẩm và trải nghiệm chọn hàng

**Chủ trì:** catalogue, chi tiết và API đọc sản phẩm; phối hợp TV6 về dữ liệu.

- [ ] Hoàn thiện chi tiết với thông số kỹ thuật và thời hạn bảo hành theo danh mục.
- [ ] Hiển thị gallery ảnh phụ từ product_images; chọn ảnh và ảnh lỗi có fallback.
- [ ] Chốt SKU/cấu hình: bản demo có thể dùng mỗi cấu hình là một sản phẩm; chỉ làm bảng biến thể khi nhóm thực sự cần lựa chọn màu/dung lượng và tồn kho riêng.
- [ ] Chọn cách hiển thị sản phẩm nổi bật: mới nhất hoặc cờ featured; phối hợp TV1 đổi tiêu đề và TV4 làm phần quản trị nếu dùng featured.
- [ ] Hoàn thiện lọc, sắp xếp, phân trang, kết quả rỗng và URL khi mở lại/chia sẻ bộ lọc.
- [ ] Phối hợp TV1 sửa khung ảnh, với TV3 kiểm tra thêm giỏ từ danh sách/chi tiết sau khi đăng nhập.
- [ ] Phối hợp TV6 chuẩn bị sản phẩm đủ đa dạng, tên/ảnh/cấu hình/giá khớp nhau.

**Nghiệm thu:** khách tìm theo nhu cầu, xem đủ cấu hình/bảo hành, chọn ảnh, thêm đúng sản phẩm/số lượng vào giỏ. Giới hạn trách nhiệm auth: dùng interface của TV5, không tự làm thêm hệ thống login.

## TV3 — Hồ sơ, giỏ hàng, checkout và đơn của khách

**Điều chỉnh so với phân công cũ:** Firebase login/register do TV5 chủ trì; TV3 tập trung hồ sơ và luồng mua hàng, nhận thêm checkout phía khách từ TV4 để cân bằng khối lượng.

- [ ] Bổ sung hồ sơ: tên, điện thoại và địa chỉ nhận hàng mặc định; phối hợp TV5/TV6 về API và schema.
- [ ] Tự điền thông tin nhận hàng từ hồ sơ, vẫn cho phép thay đổi riêng cho một đơn.
- [ ] Sửa chuẩn hóa điện thoại ở frontend, phối hợp TV5 sửa backend; kiểm tra số có khoảng trắng/dấu chấm/+84.
- [ ] Hiển thị tạm tính, phí ship, tổng tiền theo cùng quy tắc backend, không tự quyết định số tiền đặt hàng.
- [ ] Hoàn thiện trạng thái giỏ rỗng, sản phẩm ngừng bán/hết hàng và thông báo lỗi theo field.
- [ ] Hoàn thiện xác nhận, lịch sử, chi tiết và tiến trình đơn; thêm yêu cầu hủy khi pending theo API đã chốt.
- [ ] Hiển thị mã vận đơn/thông tin giao hàng nếu TV4 đã nhập.
- [ ] Tích hợp trạng thái Firebase qua interface của TV5; giữ đúng trang quay lại sau login.

**Nghiệm thu:** dùng tài khoản Firebase mua COD hoàn chỉnh, nhập điện thoại định dạng phổ biến không lỗi bất nhất, tổng tiền khớp admin, chỉ thấy đơn của mình.

## TV4 — Quản trị và xử lý đơn hàng

**Chủ trì:** frontend admin và API quản trị; TV5 review xác thực/phân quyền.

- [ ] Thêm upload/thay ảnh sản phẩm, kiểm tra định dạng và dung lượng, cấm thực thi script; phần gallery phối hợp TV2.
- [ ] Hoàn thiện thêm/sửa/ngừng bán sản phẩm và các trường kỹ thuật/bảo hành đã chốt.
- [ ] Bổ sung sửa danh mục và ẩn/xóa có kiểm tra quan hệ với sản phẩm.
- [ ] Bổ sung lọc đơn theo trạng thái/ngày, tìm mã/người nhận.
- [ ] Thêm đơn vị vận chuyển, mã vận đơn, ngày giao dự kiến theo dạng nhập tay; chưa tích hợp hãng giao hàng.
- [ ] Ghi trạng thái thu tiền COD riêng nếu đưa vào phạm vi; dashboard phải nói rõ doanh thu tính theo tiêu chí nào.
- [ ] Ghi lịch sử thay đổi trạng thái, người thao tác, thời gian và lý do hủy; giữ quy tắc hoàn tồn kho một lần.
- [ ] Làm bản in phiếu đơn hàng/phiếu giao đơn giản phục vụ demo.
- [ ] Kiểm tra toàn bộ API admin với tài khoản Firebase user/admin, không dựa vào việc ẩn nút ở frontend.

**Nghiệm thu:** admin thêm sản phẩm với ảnh mới, nhận đơn từ khách, cập nhật giao hàng/trạng thái, in phiếu, hủy hoàn kho đúng và xem lịch sử xử lý.

## TV5 — Bạn: Firebase Auth, API chung và bảo vệ hệ thống

**Chủ trì:** toàn bộ auth migration, middleware dùng chung và API khách; review API do TV2/TV4 bổ sung.

- [ ] Tạo/cấu hình Firebase project, bật Email/Password và cấu hình domain cần dùng cho các luồng xác thực/email link.
- [ ] Tích hợp Firebase Web SDK vào đăng ký/đăng nhập; xử lý email trùng, sai mật khẩu, lỗi mạng và trạng thái đang gửi.
- [ ] Thêm xác minh email và gửi email đặt lại mật khẩu; chốt có bắt buộc email verified trước checkout hay không để frontend/backend cùng áp dụng.
- [ ] Xây endpoint PHP nhận và xác minh Firebase ID token bằng thư viện phù hợp; không tự tin dữ liệu user/role từ client.
- [ ] Phối hợp TV6 thêm firebase_uid unique và liên kết với users.id đang được giỏ/đơn tham chiếu.
- [ ] Giữ user_id nội bộ và quyền admin lấy từ MySQL; tài khoản mới mặc định user. Cấp admin bằng quy trình quản trị/CLI được kiểm soát.
- [ ] Chốt migration tài khoản cũ: không tự động gắn tài khoản Firebase mới vào user/admin cũ chỉ vì trùng email. Tạo/bind tài khoản demo theo UID bằng quy trình có xác minh; bảo toàn giỏ và lịch sử đơn.
- [ ] Thống nhất nguồn dữ liệu hồ sơ: MySQL giữ hồ sơ nghiệp vụ, Firebase giữ danh tính/credentials; không để hai bản tên/email cập nhật lẫn nhau không có quy tắc.
- [ ] Khi dùng Firebase-only, không lưu mật khẩu Firebase trong MySQL; phối hợp migration cột password cũ trước khi bỏ auth cũ.
- [ ] Giữ/cập nhật PHP session, CSRF, me.php và kiểm tra role; chốt thời hạn phiên, đăng xuất hai phía và xử lý token không hợp lệ/hết hạn.
- [ ] Sửa chuẩn hóa điện thoại phía server; bổ sung API hồ sơ/địa chỉ và yêu cầu hủy đơn của khách theo hợp đồng với TV3.
- [ ] Xây quy tắc tính phí ship dùng chung phía server; lưu phí vào đơn và trả tổng tiền thống nhất cho TV3/TV4.
- [ ] Viết hợp đồng API auth mới và hướng dẫn tích hợp cho cả nhóm; review auth/ownership của API admin và sản phẩm.

**Nghiệm thu:** đăng ký Firebase → xác minh/đăng nhập → PHP nhận đúng user → mua hàng → logout cả hai phía. User không tự lên admin, không lấy đơn người khác; đăng nhập lại vẫn giữ đúng lịch sử. Test phải bao gồm token giả, token khác project, hết hạn, thiếu session và email collision với tài khoản cũ.

**Điểm cần thử sớm:** cài thư viện PHP phù hợp phiên bản PHP local và host, PHP truy cập được endpoint chứng thư/token cần thiết qua HTTPS, gói dependency được đóng kèm khi host không có Composer. Không đưa service-account private key lên frontend hoặc commit vào Git. Firebase Auth online cần mạng khi demo; dùng Auth Emulator cho kiểm thử local nếu nhóm cần.

## TV6 — Database, dữ liệu mẫu, kiểm thử và triển khai demo

**Chủ trì:** mọi schema/migration, dữ liệu seed, kiểm thử tích hợp và gói chạy/deploy.

- [ ] Thiết kế migration users.firebase_uid unique cùng TV5, giữ users.id và khóa ngoại hiện tại.
- [ ] Chốt các trường hồ sơ/địa chỉ, phí ship chụp tại lúc mua, cấu hình/bảo hành, thông tin vận chuyển, trạng thái thu tiền và lịch sử xử lý theo các nghiệp vụ nhóm chọn.
- [ ] Không thêm bảng chỉ để đủ tên; thông tin giao hàng đơn giản có thể ở orders, bảng deliveries chỉ dùng khi thực sự có nghiệp vụ riêng.
- [ ] Viết migration bảo toàn dữ liệu và hướng dẫn thứ tự chạy, cập nhật ERD/tài liệu bảng.
- [ ] Chuẩn bị khoảng 20–30 sản phẩm demo, nhiều danh mục, ảnh đúng, có hàng/hết hàng/ngừng bán; đơn ở các trạng thái khác nhau.
- [ ] Cùng TV5 chuẩn bị tài khoản Firebase demo user/admin; không ghi mật khẩu/key thật vào Git và không giả lập UID trong seed như tài khoản hoạt động.
- [ ] Cập nhật bộ test đang dùng login PHP: sau khi chuyển Firebase, test phải đi qua auth bridge mới; không giữ kết quả Pass cũ như bằng chứng Firebase đã chạy.
- [ ] Chạy hồi quy mua hàng, phí ship dưới/trên ngưỡng, điện thoại có khoảng trắng, phân quyền, đơn trùng, tồn kho, hủy và đăng xuất.
- [ ] Chạy kiểm thử desktop/mobile và cài mới từ Git trên DB sạch; không dùng DB demo để test phá dữ liệu.
- [ ] Cập nhật README, .env.example và tài liệu cấu hình Firebase/PHP; thử host miễn phí mục tiêu và tạo lại ZIP từ frontend hiện tại.
- [ ] Viết kịch bản demo, dữ liệu khởi đầu và checklist trước buổi trình bày.

**Nghiệm thu:** thành viên khác dựng được dự án theo README; Firebase auth và mua COD chạy trên môi trường mục tiêu; ZIP đúng phiên bản, không chứa bí mật hoặc database local.

## Tránh sửa trùng file

| Khu vực | Chủ trì | Người phối hợp/review |
| --- | --- | --- |
| store.css, header/footer, nội dung trang chủ/chính sách | TV1 | TV2/TV3/TV4 kiểm tra trang mình |
| shop.js (component/transport dùng chung) | TV1 tích hợp | TV5 chốt transport/session; không sửa đồng thời hai PR |
| products/detail/categories UI, products API và ảnh gallery | TV2 | TV4 cung cấp dữ liệu admin, TV5 review API |
| cart.js, checkout/hồ sơ/lịch sử UI | TV3 | TV5 cung cấp API khách |
| admin.js, admin pages, admin API/upload | TV4 | TV5 review quyền; TV2 chốt trường sản phẩm |
| auth.js/Firebase module, auth endpoints, session/CSRF, API khách | TV5 | TV3 dùng interface, TV6 viết test |
| schema/seed/migration, scripts/test/docs triển khai | TV6 | TV5 chốt UID, TV2/TV3/TV4 chốt dữ liệu |

## Hợp đồng cần chốt chung trước khi làm song song

1. Firebase ID token đổi sang PHP session hay mọi API dùng Bearer token: đề xuất session bridge để giữ nền tảng hiện tại, không trộn hai cách tùy trang.
2. Firebase UID mapping, cách cấp admin và kế hoạch tài khoản cũ: TV5 + TV6.
3. Trường sản phẩm, bảo hành, ảnh và SKU: TV2 + TV4 + TV6.
4. Biểu phí ship, ngưỡng miễn phí và cách trả tổng tiền: TV3 + TV5 + TV6; TV1 hiển thị cùng nội dung.
5. Trạng thái đơn/thu tiền, quyền hủy và hoàn kho: TV3 + TV4 + TV5 + TV6.

## Thứ tự tích hợp

1. **Chốt hợp đồng và thử Firebase tối thiểu:** TV5 + TV6 chứng minh login → PHP xác minh → MySQL user → gọi một API có quyền trên local/host mục tiêu. Các TV khác vẫn làm UI/nội dung độc lập với interface đã chốt.
2. **Hoàn thiện từng phần:** TV1 giao diện/chính sách, TV2 sản phẩm, TV3 mua hàng, TV4 quản trị, TV5 auth/API, TV6 DB/test. Mỗi phần có dữ liệu và điều kiện nghiệm thu riêng.
3. **Hồi quy và bàn giao:** chuyển hẳn auth theo kế hoạch, chạy test lại, cài từ Git sạch, đóng ZIP và thử demo trên môi trường trình bày.

Không ưu tiên SMS, thanh toán online, tích hợp hãng vận chuyển hoặc module thu cũ tự động ở giai đoạn này. COD cùng giao hàng nhập tay đủ để trình bày quy trình doanh nghiệp, nếu giao diện/nội dung và dữ liệu khớp nhau.
