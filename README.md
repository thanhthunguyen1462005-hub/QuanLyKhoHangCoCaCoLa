# HỆ THỐNG QUẢN LÝ KHO NHÀ MÁY NƯỚC GIẢI KHÁT (COCA-COLA WMS)

> Ứng dụng mẫu quản lý kho nguyên liệu, bao bì và nước ngọt thành phẩm trên môi trường **XAMPP (PHP & MySQL)**.
> Hệ thống được thiết kế chuẩn hóa theo **20 Yêu cầu Chức năng (FR-01 -> FR-20)** và **12 Yêu cầu Phi chức năng (NFR-01 -> NFR-12)**.

> Dữ liệu minh họa tập trung vào quy trình sản xuất nước giải khát: đường/siro, nước cốt, CO₂ thực phẩm, bao bì chai/lon, kiểm định QA/QC và xuất thành phẩm theo FEFO. Đây là dữ liệu mẫu phục vụ học tập, không phải dữ liệu vận hành chính thức của Coca-Cola.

---

## 1. Cấu Trúc Thư Mục Dự Án (Project Structure)

```
c:\xampp\htdocs\QuanLyKhoHang\
├── assets/
│   ├── css/
│   │   └── style.css            # CSS giao diện Dashboard công nghiệp hiện đại
│   └── js/
│       └── main.js              # Xử lý sự kiện, giả lập đầu đọc máy quét Barcode, Toast
├── config/
│   ├── database.php             # Kết nối MySQL bằng PDO (Prepared Statements, Transaction)
│   └── constants.php            # Định nghĩa 9 Vai trò, 11 kho nước giải khát, Helper functions
├── database/
│   └── schema.sql               # File SQL đầy đủ các bảng và Seeder dữ liệu mẫu ban đầu
├── includes/
│   ├── header.php               # Head, Bootstrap 5.3, FontAwesome 6, Chart.js
│   ├── navbar.php               # Top Navbar, thông tin tài khoản và đăng xuất
│   ├── sidebar.php              # Menu được lọc theo quyền vai trò
│   └── footer.php               # Footer, script closing tags
├── auth/
│   ├── login.php                # Đăng nhập bằng tài khoản trong database
│   └── logout.php               # Đăng xuất qua POST có CSRF token
├── pages/
│   ├── dashboard/               # Dashboard theo vai trò
│   ├── warehouses/              # FR-02: 5 kho nguyên liệu/bao bì, 5 kho thành phẩm, 1 kho cách ly
│   ├── receipts/                # FR-06, FR-08: Quản lý nhập kho (liên kết PO & QA/QC kiểm định)
│   ├── issues/                  # FR-07, FR-09: Quản lý xuất kho (tự động gợi ý FEFO/FIFO, chặn xuất âm)
│   ├── inventory/               # FR-12, FR-13, FR-14: Tra cứu tồn kho tức thời, cảnh báo Min/Max/Date
│   ├── audits/                  # FR-15: Phê duyệt biên bản kiểm kê
│   ├── reports/                 # FR-17: Báo cáo quản trị từ số liệu database
│   ├── audit_logs/              # FR-18: Tra cứu nhật ký thao tác hệ thống
│   └── scanner/                 # NFR-10: Quét mã Barcode / QR Code bằng máy quét hoặc Camera
├── index.php                    # Trang chủ công khai; chuyển tới dashboard khi đã đăng nhập
└── README.md                    # Tài liệu hướng dẫn phân công và phát triển
```

## 2. Phạm Vi Yêu Cầu

### Yêu cầu chức năng

| Mã | Chức năng | Nội dung chính | Actor chính | Actor liên quan |
|---|---|---|---|---|
| FR-01 | Đăng nhập và phân quyền | Đăng nhập; giới hạn chức năng theo vai trò được cấp. | Người dùng được cấp tài khoản | Các actor sử dụng chức năng theo quyền |
| FR-02 | Quản lý hệ thống kho | Quản lý 5 kho nguyên liệu, 5 kho thành phẩm và kho hàng lỗi/trả về. | Quản lý kho | Ban Giám đốc xem thông tin |
| FR-03 | Quản lý hàng hóa | Quản lý SKU, Barcode/QR, tên, loại, đơn vị, lô, hạn dùng và định mức tồn. | Quản lý kho | Nhân viên kho, QA/QC, Mua hàng |
| FR-04 | Quản lý vị trí lưu trữ | Ghi nhận vị trí theo kho, khu vực, dãy, kệ, tầng/ô. | Nhân viên kho | Quản lý kho giám sát |
| FR-05 | Quản lý nhà cung cấp | Lưu thông tin nhà cung cấp và liên kết nguồn gốc với lô nguyên liệu. | Mua hàng | Quản lý kho tra cứu |
| FR-06 | Nhập nguyên liệu | Tiếp nhận PO, liên kết QC/QA và tự điền chứng từ nhập. | Quản lý kho, Nhân viên kho | Mua hàng cung cấp PO; QA/QC kiểm định |
| FR-07 | Xuất nguyên liệu | Căn cứ kế hoạch sản xuất/yêu cầu xưởng; gợi ý FEFO/FIFO và lập phiếu xuất. | Quản lý kho, Nhân viên kho | Lập kế hoạch; Xưởng |
| FR-08 | Nhập thành phẩm | Nhận yêu cầu từ Xưởng; chỉ điều phối sau khi QA/QC xác nhận đạt. | Quản lý kho, Nhân viên kho | Xưởng; QA/QC |
| FR-09 | Xuất thành phẩm | Tiếp nhận đơn hàng, điều phối và lập phiếu giao khách hàng. | Quản lý kho, Nhân viên kho | Ban Giám đốc theo dõi |
| FR-10 | Điều chuyển kho | Lập phiếu và theo dõi điều chuyển nguyên liệu/thành phẩm nội bộ. | Quản lý kho, Nhân viên kho | — |
| FR-11 | Hàng lỗi và hàng trả về | Ghi nhận hàng lỗi/trả về; QA/QC xác nhận trước khi xử lý, tái đóng gói hoặc trả NCC. | Quản lý kho, Nhân viên kho, QA/QC | Xưởng; Mua hàng |
| FR-12 | Quản lý tồn kho | Chỉ cập nhật tồn sau khi phiếu hợp lệ được duyệt, chỉ định kho và đạt QC khi cần; không xuất quá tồn khả dụng hoặc ghi nhận trùng biến động. | Quản lý kho, Nhân viên kho | Ban Giám đốc, Lập kế hoạch |
| FR-13 | Cảnh báo tồn kho và hạn dùng | Cảnh báo tồn min/max, kho đầy và lô cận hạn. | Quản lý kho, Nhân viên kho | Ban Giám đốc, Mua hàng, Lập kế hoạch |
| FR-14 | Tìm kiếm hàng hóa | Tra cứu SKU, tên, lô, hạn dùng, kho, vị trí và nhà cung cấp theo phạm vi quyền. | Actor có quyền tra cứu | Người dùng được phân quyền |
| FR-15 | Kiểm kê và cân bằng | Lập đợt, ghi nhận chênh lệch; Quản lý kho xác minh, Ban Giám đốc duyệt; chỉ điều chỉnh tồn sau duyệt và lưu lịch sử. | Ban kiểm kê, Quản lý kho | Ban Giám đốc phê duyệt |
| FR-16 | Quản lý chứng từ | Lưu trữ và truy xuất phiếu nhập/xuất, yêu cầu, QA/QC và biên bản kiểm kê theo quyền. | Actor được cấp quyền | Người dùng truy xuất chứng từ liên quan |
| FR-17 | Báo cáo và thống kê | Dashboard Nhập–Xuất–Tồn, hiệu suất kho và hàng lỗi theo khoảng thời gian. | Ban Giám đốc | Quản lý kho |
| FR-18 | Lịch sử thao tác | Ghi người thực hiện, thời điểm và nội dung của thao tác quan trọng. | Ban Giám đốc, Quản lý kho | Hệ thống ghi nhận thao tác người dùng |
| FR-19 | Trạng thái xử lý | Theo dõi chờ duyệt, đã điều phối, đã nhập/xuất hoặc từ chối. | Quản lý kho, Nhân viên kho, QA/QC | Ban Giám đốc, Mua hàng, Lập kế hoạch, Xưởng |
| FR-20 | Quản lý tài khoản | Quản trị tạo, cập nhật, khóa tài khoản và thiết lập quyền truy cập. | Quản trị hệ thống | Nhân sự được cấp tài khoản |

### Yêu cầu phi chức năng

| Mã | Yêu cầu | Tiêu chí |
|---|---|---|
| NFR-01 | Dễ sử dụng | Giao diện rõ ràng; tự điền dữ liệu từ chứng từ liên quan để giảm nhập liệu thủ công. |
| NFR-02 | Hiệu năng | Tra cứu, lập phiếu và duyệt điều phối phản hồi trong khoảng 2–3 giây. |
| NFR-03 | Đồng thời | Hỗ trợ nhiều vai trò thao tác mà không nghẽn hoặc mất nhất quán dữ liệu. |
| NFR-04 | Chính xác | Tồn kho nhất quán sau giao dịch hợp lệ; không ghi nhận trùng biến động. |
| NFR-05 | Toàn vẹn | Không xuất vượt tồn khả dụng; phiếu phải có chứng từ căn cứ. |
| NFR-06 | Bảo mật | Băm mật khẩu an toàn, phân quyền theo vai trò; chỉ Quản trị được quản lý tài khoản/quyền. |
| NFR-07 | Ổn định | Mục tiêu uptime ít nhất 99,8% trong thời gian vận hành nhà máy. |
| NFR-08 | Sao lưu và phục hồi | Sao lưu tự động hàng ngày; có khả năng phục hồi dữ liệu khi sự cố. |
| NFR-09 | Mở rộng | Có thể bổ sung xưởng, kho, nhóm hàng và tài khoản. |
| NFR-10 | Tương thích | Hỗ trợ Chrome, Edge, Firefox và máy quét Barcode/QR. |
| NFR-11 | Bảo trì | Kiến trúc mô-đun, thuận tiện nâng cấp và sửa lỗi. |
| NFR-12 | Theo dõi | Lưu lịch sử thao tác quan trọng phục vụ đối soát và kiểm toán. |

### Trạng thái phiên bản hiện tại

Đây là **prototype** cho quy trình kho nước giải khát. Đăng nhập, phân quyền, danh mục kho, tra cứu tồn kho, phê duyệt biên bản kiểm kê, dashboard Ban Giám đốc, báo cáo quản trị và tra cứu nhật ký sử dụng dữ liệu MySQL. Báo cáo theo kỳ tổng hợp chứng từ đã hoàn tất có trong database, nhóm lượng nhập/xuất theo SKU và đơn vị; giá trị nhập lấy từ đơn giá dòng phiếu, giá trị xuất chỉ là ước tính theo giá vốn hiện tại. Điều chuyển mới có bảng đầu phiếu, chưa có bảng dòng hàng để tổng hợp số lượng. Các biểu mẫu nhập/xuất và quét mã vẫn chưa lưu giao dịch kho; dashboard của các vai trò ngoài Ban Giám đốc vẫn có dữ liệu minh họa. Không dùng dữ liệu mẫu cho vận hành thực tế.

---

## 3. Hướng Dẫn Cài Đặt Nhanh Trên XAMPP

1. Khởi động **Apache** và **MySQL** trên **XAMPP Control Panel**.
2. Mở trình duyệt truy cập: `http://localhost/phpmyadmin/`.
3. Nhấp vào tab **Import** (Nhập) &rarr; Chọn file `c:\xampp\htdocs\QuanLyKhoHang\database\schema.sql` &rarr; Nhấn **Go** (Thực hiện).
   * Script sẽ tự động tạo database `quanlykhohang_db` với 11 kho, 9 tài khoản mẫu, danh mục hàng hóa, số lô, tồn kho và các bảng nghiệp vụ; đồng thời tạo nhiều biên bản kiểm kê mẫu (chênh lệch giảm/tăng và không chênh lệch) đang chờ duyệt.
   * **Lưu ý:** schema.sql xóa và tạo lại các bảng mẫu. Hãy sao lưu dữ liệu trước khi import lại vào database đang sử dụng.
   * Nếu database đã được cài đặt trước đó, hãy sao lưu rồi chạy file `database/migrations/20261101_audit_approval.sql` để bổ sung cấu trúc phê duyệt, thông báo và biên bản mẫu đầu tiên mà không xóa dữ liệu hiện có.
   * Để thêm các biên bản mẫu dùng thử, chạy tiếp `database/migrations/20261101_audit_approval_samples.sql`. Có thể chạy lại migration mẫu; các mã biên bản đã tồn tại sẽ không bị thêm trùng.
4. Mở trình duyệt truy cập website:
   ```
   http://localhost/QuanLyKhoHang/
   ```

---

## 4. Đăng Nhập Và Phân Quyền
Trang chủ công khai tại `http://localhost/QuanLyKhoHang/` cho phép khách chưa đăng nhập xem giới thiệu hệ thống. Sau khi đăng nhập, dashboard và menu được hiển thị theo vai trò; các trang nghiệp vụ cũng kiểm tra quyền ở phía máy chủ. Đăng xuất sử dụng POST kèm CSRF token.

Sau khi import `database/schema.sql`, có thể dùng các tài khoản demo sau (tất cả dùng mật khẩu `123456`):

| Tên đăng nhập | Vai trò |
|---|---|
| `admin` | Quản trị hệ thống |
| `giamdoc` | Ban Giám đốc |
| `quanlykho` | Quản lý kho |
| `thukho` | Nhân viên kho |
| `qaqc` | QA/QC Chất lượng |
| `muahang` | Bộ phận Mua hàng |
| `xuong1` | Xưởng sản xuất |
| `kehoach` | Lập kế hoạch |
| `banhang` | Bộ phận Bán hàng |

Mật khẩu được xác thực bằng `password_verify`; tài khoản bị khóa không thể đăng nhập. Tài khoản và mật khẩu mẫu chỉ dành cho dữ liệu demo, cần thay đổi trước khi triển khai thật.

### Phê duyệt biên bản kiểm kê

Đăng nhập bằng tài khoản `giamdoc` (hoặc `admin`) và mở **Phê duyệt biên bản kiểm kê** để xem các biên bản đã được Quản lý kho xác minh. Dữ liệu mẫu có trường hợp chênh lệch giảm, chênh lệch tăng và không chênh lệch. Khi phê duyệt, hệ thống khóa biên bản và các dòng tồn liên quan trong transaction, kiểm tra tồn sổ sách có thay đổi không rồi mới cập nhật tồn theo số thực đếm; lỗi sẽ rollback toàn bộ giao dịch. Khi từ chối, cần nhập lý do; hệ thống lưu thông báo trong ứng dụng và gửi email đến người lập biên bản nếu tài khoản có email hợp lệ.

Email phụ thuộc cấu hình SMTP của PHP/XAMPP. Nếu chưa cấu hình máy chủ SMTP, thao tác từ chối vẫn được lưu cùng thông báo trong ứng dụng và màn hình sẽ báo email chưa gửi được.

### Báo cáo quản trị và nhật ký hệ thống

Đăng nhập `giamdoc` hoặc `admin`. Dashboard Ban Giám đốc hiển thị KPI truy vấn trực tiếp từ database; biểu đồ và giao dịch minh họa cũ không hiển thị trong dashboard này. Trang **Hệ thống kho** và **Tra cứu tồn kho** đọc danh mục, vị trí, lô, số lượng, định mức và cảnh báo trực tiếp từ database; không dùng số liệu dựng sẵn. Quản lý kho có thể thêm/cập nhật danh mục kho; các vai trò được cấp quyền còn lại chỉ tra cứu. Tra cứu tồn kho lọc phía máy chủ theo từ khóa, kho, loại hàng và cảnh báo, có phân trang và xuất CSV theo bộ lọc hiện tại.

- **Báo cáo quản trị (FR-17):** chọn khoảng thời gian tối đa 366 ngày để thống kê phiếu nhập/xuất/điều chuyển và quyết định kiểm kê đã hoàn tất; có lượng nhập/xuất theo SKU, giá trị nhập theo đơn giá phiếu và chi phí xuất ước tính theo giá vốn hiện tại. Các thẻ tồn kho, giá trị theo giá vốn, SKU dưới định mức, lô cận hạn và lô quá hạn còn tồn là ảnh chụp hiện tại. Biểu đồ đếm chứng từ; không cộng gộp lượng của các đơn vị hàng hóa khác nhau.
- **Nhật ký hệ thống (FR-18):** lọc theo từ khóa, thao tác, phân hệ, người thực hiện và ngày; có phân trang. Hệ thống ghi nhận đăng nhập thành công/thất bại, đăng xuất, thêm/cập nhật kho và thao tác phê duyệt/từ chối biên bản. Các màn hình nhập/xuất dạng minh họa chưa lưu chứng từ nên chưa thể ghi nhật ký hay thống kê giao dịch từ các màn hình đó.
- Các chức năng báo cáo và nhật ký chỉ cấp cho Ban Giám đốc và Quản trị hệ thống. Nhật ký hiện có từ trước khi cập nhật không được tự tạo lại; dữ liệu mẫu `INITIALIZE` và các quyết định kiểm kê đã ghi được hiển thị nếu có.

---

## 5. Bảng Phân Chia Công Việc Cho Các Thành Viên Nhóm

Mỗi bạn trong nhóm có thể phụ trách một nhóm FR độc lập:

| Thành Viên Phụ Trách | Nhóm Chức Năng | Các FR Cần Làm | Thư Mục Triển Khai |
| :--- | :--- | :--- | :--- |
| **Thành viên 1** | Quản trị & Phân quyền | FR-01, FR-18, FR-20, NFR-06 | `pages/users/`, `pages/audit_logs/`, `config/` |
| **Thành viên 2** | Quản lý Kho, Vị trí & Hàng hóa | FR-02, FR-03, FR-04, FR-05 | `pages/warehouses/`, `pages/locations/`, `pages/products/` |
| **Thành viên 3** | Nghiệp vụ Nhập kho & QA/QC | FR-06, FR-08, FR-11 | `pages/po/`, `pages/receipts/`, `pages/qc/` |
| **Thành viên 4** | Nghiệp vụ Xuất kho (FEFO/FIFO) | FR-07, FR-09, FR-10 | `pages/issues/`, `pages/transfers/` |
| **Thành viên 5** | Tồn kho, Cảnh báo & Kiểm kê | FR-12, FR-13, FR-14, FR-15, FR-17 | `pages/inventory/`, `pages/audits/`, `pages/reports/` |

---

## 6. Quy Ước Lập Trình Cho Nhóm
* **Nguyên tắc FEFO / FIFO (FR-07):** Khi xuất kho, luôn thực hiện query `ORDER BY expiry_date ASC, created_at ASC` để ưu tiên lô hàng cận hạn nhất.
* **Chống xuất âm (NFR-05):** Luôn dùng Transaction `BEGIN TRANSACTION` $\to$ `SELECT quantity FOR UPDATE` $\to$ kiểm tra nếu `quantity >= requested_qty` thì mới cho trừ kho $\to$ `COMMIT`.
* **Ghi nhật ký hệ thống (FR-18):** Với nghiệp vụ đã triển khai, ghi log cùng transaction cho thay đổi dữ liệu để tránh bản ghi bị thiếu hoặc không khớp. Các luồng demo chưa lưu dữ liệu chưa phát sinh log giao dịch.
