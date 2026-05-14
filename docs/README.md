# Vielimousine Child — Rebuild v2

Tài liệu thiết kế để dựng lại child theme `vielimousine-child` từ con số 0.
Không kế thừa dữ liệu, không migration; tất cả bảng tạo mới, schema mới.

## Stack

- **WordPress** 6.5+
- **PHP** 8.2+ (`declare(strict_types=1)`, `readonly` class)
- **MySQL** 8 / MariaDB 10.5+, InnoDB, `utf8mb4`
- **Frontend public**: PHP shortcode + vanilla JS (flatpickr, sweetalert2, swiper)
- **Admin**: **SPA độc lập Vue 3 + PrimeVue** (latest), Vue Router, Pinia, Axios — nói chuyện 100% qua REST `vie/v1/*`
- **Payment**: SePay
- **Auth SPA**: JWT access token (15') + refresh token cookie HttpOnly (30d), state ở `vie_token`

## Mục lục

| # | File | Nội dung |
|---|---|---|
| 01 | [architecture.md](01-architecture.md) | Triết lý kiến trúc, MVC layered, cấu trúc thư mục, autoload, bootstrap |
| 02 | [database.md](02-database.md) | Schema 15 bảng, ERD, JSON shapes, index, quy tắc đặt tên |
| 03 | [pricing.md](03-pricing.md) | Pricing engine: `RoomAllocation`, `ChildPolicy`, `TicketCalculator`, breakdown |
| 04 | [business-rules.md](04-business-rules.md) | `order.code`, mô tả đơn, cancellation, child rules mới, combo, seat_count |
| 05 | [rest-api.md](05-rest-api.md) | Toàn bộ endpoint `vie/v1/*`, envelope, pagination, filter, error |
| 06 | [admin-spa.md](06-admin-spa.md) | Vue 3 + PrimeVue: layout, router, store, component, các trang admin |
| 07 | [auth.md](07-auth.md) | Login flow, JWT, refresh rotation, `vie_token`, CORS |
| 08 | [frontend-public.md](08-frontend-public.md) | Shortcode: search, rooms, checkout, success |
| 09 | [email.md](09-email.md) | Templates, biến, mail admin có `seat_count` |
| 10 | [payment-sepay.md](10-payment-sepay.md) | Checkout init, webhook, sổ cái `vie_payment_log`, idempotency |
| 11 | [reporting.md](11-reporting.md) | 6 báo cáo + export, query shape |
| 12 | [roles.md](12-roles.md) | Roles + capabilities cho cả WP backend & SPA |
| 13 | [testing.md](13-testing.md) | Bảng test case cho pricing & flow |
| 14 | [roadmap.md](14-roadmap.md) | 13 phase (Phase 0 → 12), ước lượng thời lượng |
| 15 | [user-guide.md](15-user-guide.md) | Hướng dẫn sử dụng nhanh: cài đặt, phân quyền, endpoint chính |

## Quy ước trong tài liệu

- Tên bảng viết đầy đủ với prefix wp: `{$wpdb->prefix}vie_order` → trong doc gọi tắt `vie_order`.
- Tiền VND luôn `DECIMAL(12,0)` (không lưu phần thập phân).
- Snapshot JSON dùng `LONGTEXT` (an toàn cho MySQL cũ); chỉ chuyển sang kiểu `JSON` native khi production xác nhận.
- Datetime lưu theo timezone của WordPress (`wp_timezone()`), không UTC ép buộc.
- Mọi entity vận hành có `created_at` + `updated_at`.
- Không dùng foreign key cấp DB — quan hệ thực thi ở Service + index.

## Yêu cầu mới được wire-in (Phase 2)

| Yêu cầu | Bảng / hằng số đáp ứng |
|---|---|
| Trẻ em ≥ 6 tuổi tính giá vé / như NL | `vie_hotel.ticket_free_children_max_age` (default 5 = "dưới 6") + logic `ChildPolicy` |
| 1 booking miễn phí tối đa 1 bé < 6 (vé xe) | `vie_hotel.ticket_free_children_count` (default 1) |
| Email admin thêm trường Số chỗ ngồi | `vie_order_item.ticket_count` + template `admin-notification` |
| Combo = Hotel + Vé xe khứ hồi | `vie_ticket_price` (theo hotel × date) + `vie_order_item.ticket_*` |
| Mô tả đơn hàng đầy đủ thông tin | `BookingDescription` (1 nguồn sự thật, dùng cho SePay + bank + email) |
| Đổi "Mã đặt phong" → "Mã đặt phòng" + dùng đối soát | `vie_order.code` UNIQUE = `VIEyymmddXXXX`, đẩy sang SePay làm `order_invoice_number` |
| Chính sách hoàn huỷ theo từng KS (MVC) | `vie_hotel.cancellation_policy` JSON + `BookingService::cancel()` |
| Mã đối tác từ workbook | `vie_order_item.supplier_booking_code` |
| Thu tiền nhiều lần (cọc + còn lại) | `vie_payment_log` sổ cái immutable |
| Báo cáo theo nguồn / sales / hotel | `vie_order.source`, `sales_user_id`, `vie_order_item.hotel_id` |
| Giá vốn & lợi nhuận | `vie_order_item.cost_total`, `vie_order.cost_total`, `profit_total` |

Xem chi tiết từng yêu cầu trong [04-business-rules.md](04-business-rules.md).
