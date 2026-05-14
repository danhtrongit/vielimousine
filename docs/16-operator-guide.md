# 16 — Hướng dẫn vận hành (Operator Guide)

Tài liệu dành cho team vận hành Vielimousine. 1 trang, ngắn gọn, không cần kiến thức kỹ thuật sâu.

## 1. Đăng nhập

- URL: `https://<site>/vie-admin/login`
- Tài khoản WordPress thường: nếu role là `Administrator`, `Vie Manager`, `Vie Sales`, hay `Vie Hotel Manager` thì vào được.
- Quên mật khẩu: dùng "Quên mật khẩu" của WordPress (trang `/wp-login.php?action=lostpassword`).

## 2. Quy trình hàng ngày

| Sáng | Trưa | Chiều |
|---|---|---|
| Mở **Dashboard** → xem đơn mới hôm qua | Xác nhận đơn `pending` đã thanh toán → đổi `confirmed` | Cập nhật bảng giá tuần tới (nếu cần) |
| Kiểm **Nhật ký** → mail fail / lỗi cron | Xử lý refund/hủy (nếu có) | Đóng đơn `completed` cho đơn đã trả phòng |

## 3. Tác vụ thường gặp

### Tạo đơn nội bộ (Sales)
1. Sidebar → **Đơn hàng** → nút "Tạo đơn mới".
2. Nhập SĐT khách → hệ thống auto-fill nếu khách cũ.
3. Chọn phòng + ngày + số khách → giá realtime hiện ở bảng phải.
4. Nhập ghi chú, voucher (nếu có) → "Tạo đơn".
5. Đơn xuất hiện ở danh sách với mã `VIE-XXXX`.

### Hủy đơn + Refund
1. Vào chi tiết đơn → nút "Hủy đơn" (chỉ role `vie_cancel_orders`).
2. Nhập lý do → hệ thống tự tính phí hủy theo chính sách khách sạn.
3. Xác nhận → ledger thêm dòng `refund`.

### Tạo mã giảm giá (Coupon)
1. Sidebar → **Mã giảm giá** → "Tạo mã".
2. Nhập code (auto-uppercase), loại (%, fixed VND), value, ngày bắt đầu/hết hạn.
3. Tab **Phạm vi**: chọn hotel/room/booking_type áp dụng (để trống = tất cả).
4. Lưu → khách public có thể nhập mã ở checkout.

### Gửi email thử
1. Sidebar → **Cài đặt** → tab **Email**.
2. Cuộn xuống template muốn test (ví dụ "Khách: Chờ thanh toán").
3. Nhập email nhận (mặc định = admin) → nút "Gửi thử".
4. Check inbox / mailpit Local Sites.

### Đổi nội dung email
- **Cài đặt → Email** → mở template → sửa Subject/Body → nút "Lưu cài đặt email".
- Body để trống = dùng template HTML mặc định trong code.
- Placeholder hỗ trợ: `{order_code}`, `{customer_name}`, `{total}`, v.v. — list bên dưới mỗi editor.

### Bật/tắt SePay
- **Cài đặt → SePay** → bật toggle "Bật cổng SePay".
- Chọn môi trường `sandbox` (test) hoặc `production`.
- Nhập merchant_id + secret_key (key chỉ hiện khi nhập mới — đã lưu thì bỏ trống = giữ nguyên).

## 4. Troubleshooting

| Vấn đề | Cách xử lý |
|---|---|
| **Mail không gửi** | Sidebar → **Nhật ký** → filter `entity_type=mail`, `action=fail` → đọc cột `error`. Thường là sai SMTP của hosting. |
| **SePay không auto-confirm** | **Cài đặt → SePay** → kiểm "Auto-confirm khi thanh toán đủ" có bật chưa. Nếu IPN webhook chưa thông, check log server. |
| **IP bị khóa nhầm** | SSH server → `wp option get vie_blocked_ips --format=json` → tìm IP → `wp option update vie_blocked_ips '{}' --format=json` (hoặc xóa thủ công). |
| **Đơn `pending` quá hạn** | Cron `vie_no_show_sweep` tự chuyển `no_show` hàng đêm. Nếu cần chạy ngay: `wp cron event run vie_no_show_sweep`. |
| **Báo cáo trống** | Kiểm filter ngày + role có cap `vie_view_reports` không. |
| **Không vào được admin SPA** | Xóa cache trình duyệt → re-login. Nếu vẫn 401 → check JWT secret trong **Cài đặt → SePay** chưa thay đổi giữa chừng. |

## 5. Cron daily

| Cron hook | Tác dụng | Tự chạy lúc |
|---|---|---|
| `vie_security_sweep` | Block IP > 50 login fail/giờ | 00:01 (theo schedule init) |
| `vie_no_show_sweep` | `pending` + checkin quá hạn + paid=0 → `no_show` | 00:02 |
| `vie_token_cleanup` | Xóa token revoked > 30 ngày, expired > 7 ngày | 00:03 |

Kiểm: `wp cron event list | grep vie_`.

## 6. Liên hệ

- Bug khẩn cấp: Slack `#vielimo-ops`
- Hỗ trợ kỹ thuật: dev team — `support@vielimousine.vn`
- Nhật ký kỹ thuật chi tiết: SSH server → `wp eval 'echo "OK";'` để xác nhận WP còn sống.
