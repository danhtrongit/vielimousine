# 09 — Email

## 9.1. Tổng quan

Email gửi qua `wp_mail()`. `MailService` wrap:

- Set `from_name`, `from_email`, `reply_to` từ settings.
- Set content type HTML.
- Try/catch + log fail vào `vie_activity_log`.
- Hỗ trợ multiple recipients (admin).

`OrderMailer` & `AdminOrderMailer` là 2 service chính, gọi từ `OrderService` và `PaymentService`.

## 9.2. Templates

Đặt ở `inc/src/Email/Templates/`:

| File | Tình huống |
|---|---|
| `pending-payment.php` | Khách đặt xong, chờ thanh toán |
| `paid.php` | Đã nhận đủ tiền |
| `partial.php` | Đã cọc 1 phần |
| `confirmed.php` | Admin manual confirm |
| `completed.php` | Đơn hoàn tất (đã checkout) |
| `cancelled.php` | Đơn bị hủy + thông tin hoàn tiền |
| `admin-notification.php` | Gửi admin/sales khi có đơn mới |
| `admin-paid.php` | Gửi admin khi đơn được thanh toán |
| `admin-cancelled.php` | Gửi admin khi có hủy |

## 9.3. Biến template (variables)

Tất cả template nhận `$ctx` array. Biến chuẩn:

| Biến | Nguồn |
|---|---|
| `{site_name}` | `get_bloginfo('name')` |
| `{site_url}` | `home_url('/')` |
| `{logo_url}` | settings |
| `{order_code}` | `vie_order.code` |
| `{customer_name}` | |
| `{customer_phone}` | |
| `{customer_email}` | |
| `{source}` | label tiếng Việt |
| `{sales_user}` | display_name |
| `{checkin}` / `{checkout}` / `{nights}` | |
| `{adults}` / `{children}` / `{child_ages}` | |
| `{items}` | array — render block lặp |
| `{item.hotel_name}` | |
| `{item.room_name}` | |
| `{item.product_code}` | |
| `{item.booking_type}` | `room` / `combo` |
| `{item.quantity}` / `{item.unit_label}` | |
| `{item.ticket_count}` | **Số chỗ ngồi** |
| `{item.billable_seats}` | |
| `{item.free_child_seats}` | |
| `{item.partner_name}` | |
| `{item.supplier_booking_code}` | |
| `{item.room_subtotal}` | |
| `{item.extra_adult_total}` | |
| `{item.child_surcharge_total}` | |
| `{item.ticket_subtotal}` | |
| `{item.line_total}` | |
| `{subtotal}` / `{discount}` / `{total}` | |
| `{paid_amount}` / `{remaining_amount}` | |
| `{cost_total}` / `{profit_total}` | admin only |
| `{coupon_code}` / `{voucher_code}` | |
| `{payment_method}` | label |
| `{payment_status}` | label |
| `{cancellation_policy_html}` | render từ JSON |
| `{order_description}` | từ `OrderDescription::build()` |
| `{admin_url}` | link `/vie-admin/orders/{id}` |
| `{lookup_url}` | link `/dat-phong-thanh-cong/?code={code}&phone={phone}` |

Tất cả số tiền hiển thị format VND: `1.234.567 ₫` (helper `Vie\Support\Money::vnd()`).

## 9.4. Subject mặc định

| Mail | Subject |
|---|---|
| `pending_payment` | `[{site_name}] Đặt phòng #{order_code} – Chờ thanh toán` |
| `paid` | `[{site_name}] Đã nhận thanh toán – #{order_code}` |
| `partial` | `[{site_name}] Đã nhận cọc {paid_amount} – #{order_code}` |
| `confirmed` | `[{site_name}] Xác nhận đặt phòng #{order_code}` |
| `completed` | `[{site_name}] Cảm ơn quý khách – #{order_code}` |
| `cancelled` | `[{site_name}] Đã hủy đặt phòng #{order_code}` |
| `admin_notification` | `[ĐẶT PHÒNG MỚI] #{order_code} – {customer_name} – Seats:{total_seats}` |
| `admin_paid` | `[THU TIỀN] #{order_code} +{amount}` |
| `admin_cancelled` | `[HỦY] #{order_code} hoàn {refund_amount}` |

> `total_seats = Σ items.ticket_count`. Subject admin chứa **Seats** giúp filter nhanh trong inbox.

## 9.5. Template `admin-notification.php` (chi tiết)

Phần body **bắt buộc**:

```html
<table>
  <tr><th>Mã đơn</th><td><strong>{order_code}</strong></td></tr>
  <tr><th>Khách hàng</th><td>{customer_name} – {customer_phone} – {customer_email}</td></tr>
  <tr><th>Nguồn</th><td>{source}</td></tr>
  <tr><th>Sales</th><td>{sales_user}</td></tr>
  <tr><th>Tổng số chỗ ngồi</th><td><strong>{total_seats}</strong></td></tr>
</table>

{# loop items #}
{% for item in items %}
<table class="item">
  <tr><th colspan="2">{item.product_code} – {item.hotel_name}</th></tr>
  <tr><td>Phòng / Loại</td><td>{item.room_name} ({item.booking_type})</td></tr>
  <tr><td>Checkin → Checkout</td><td>{item.checkin} → {item.checkout} ({item.nights}đ) × {item.quantity} {item.unit_label}</td></tr>
  <tr><td>Người</td><td>{item.adults} NL + {item.children} TE ({item.child_ages})</td></tr>
  <tr><td><strong>Số chỗ ngồi</strong></td><td><strong>{item.ticket_count}</strong> (tính phí {item.billable_seats}, miễn {item.free_child_seats} bé)</td></tr>
  <tr><td>Tuyến</td><td>{item.transfer_route}</td></tr>
  <tr><td>Đối tác</td><td>{item.partner_name} – Khu vực: {item.hotel_area}</td></tr>
  <tr><td>Mã đối tác</td><td>{item.supplier_booking_code}</td></tr>
  <tr><td>Tiền phòng</td><td>{item.room_subtotal}</td></tr>
  <tr><td>Phụ thu NL</td><td>{item.extra_adult_total}</td></tr>
  <tr><td>Phụ thu TE</td><td>{item.child_surcharge_total}</td></tr>
  <tr><td>Tiền vé xe</td><td>{item.ticket_subtotal}</td></tr>
  <tr><td><strong>Thành tiền</strong></td><td><strong>{item.line_total}</strong></td></tr>
</table>
{% endfor %}

<table>
  <tr><td>Subtotal</td><td>{subtotal}</td></tr>
  <tr><td>Coupon ({coupon_code})</td><td>-{discount}</td></tr>
  <tr><td><strong>Tổng đơn</strong></td><td><strong>{total}</strong></td></tr>
  <tr><td>Đã thu</td><td>{paid_amount}</td></tr>
  <tr><td>Còn lại</td><td>{remaining_amount}</td></tr>
  <tr><td>Giá vốn</td><td>{cost_total}</td></tr>
  <tr><td><strong>Lợi nhuận DK</strong></td><td><strong>{profit_total}</strong></td></tr>
</table>

<p>Ghi chú khách: {customer_note}</p>
<p>Mô tả đơn: <code>{order_description}</code></p>
<p><a href="{admin_url}">Xem chi tiết →</a></p>
```

> Trong template thật dùng `<?php foreach ($ctx['items'] as $item): ?>` thay cho jinja-ish. Trên là pseudo-format.

## 9.6. Settings

Admin SPA → Settings → Email:

| Field | Mô tả |
|---|---|
| `from_name` | |
| `from_email` | |
| `reply_to` | |
| `admin_recipients` | textarea, mỗi dòng/ngắt phẩy 1 email |
| Toggle bật/tắt từng template | checkbox |
| Subject từng template | input |
| Body từng template | editor TinyMCE (placeholder list bên cạnh) |
| **Test gửi** | nút "Gửi thử tới {admin_email} với đơn mẫu" |

Lưu vào `wp_options.vie_email_settings`.

## 9.7. Send queue (tuỳ chọn)

- MVP: send sync sau `OrderService::create()` → có thể chậm. Wrap try/catch không throw để không fail order.
- Nâng cấp: queue qua `wp_schedule_single_event('vie_send_mail', time(), [$type, $orderId])` → cron xử lý.
- Track gửi trong `vie_activity_log` `entity_type='mail'`, `action='sent'|'fail'`.

## 9.8. Test fixtures

`Vie\Email\Fixtures::sampleOrder()` trả `Order + items + customer` mock để render preview trong settings page và snapshot test.
