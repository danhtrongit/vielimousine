# 04 — Business Rules

## 4.1. Mã đặt phòng (`vie_order.code`)

- Là **trường duy nhất** để đối soát thanh toán SePay & bank.
- Format: `VIE` + `yymmdd` + 4 số running theo ngày → `VIE260512000X`.
- Generator (`OrderCodeGenerator::next()`):
    1. Tính `prefix = 'VIE' . date('ymd', current_time('timestamp'))`.
    2. Query `MAX(code)` của hôm nay với `LIKE 'VIE{ymd}%'`.
    3. Tăng counter +1, zero-pad 4 chữ số → `VIE260512` + `0007`.
    4. INSERT với `UNIQUE(code)` constraint; nếu rớt unique → retry (race condition).
    5. Vượt 9999 đơn/ngày → fallback `VIE{ymd}` + 4 hex từ `random_bytes(2)`.
- **Sinh ngay khi insert order**, không lazy.
- Truyền sang SePay làm `order_invoice_number`.
- Chèn vào `order_description` (xem §4.3).

## 4.2. Idempotency key

- Frontend public sinh `idempotency_key = sha256(phone + email + total + checkin + room_id + nonce)` rồi gắn vào header `X-Idempotency-Key` khi POST `/orders`.
- BE: nếu `UNIQUE(idempotency_key)` rớt → trả 200 với order đã tồn tại (idempotent).
- Cho phép null đối với order tạo từ admin SPA (vì admin tự kiểm soát).

## 4.3. Mô tả đơn hàng (`OrderDescription`)

`OrderDescription::build(Order $o, OrderItem[] $items): string`:

```
{order.code} | {customer_name} | {hotel_name}
{checkin}→{checkout} ({nights}đ) | {Σquantity}p/c | {adults}NL+{children}TE
Seats:{ticket_count_total} | Total:{total_VND} | Pay:{payment_status}
```

- Tối đa 250 ký tự (SePay limit).
- ASCII-safe khi vượt: tự bỏ dấu (`Transliterator::create('Any-Latin; Latin-ASCII')`).
- Dùng chung cho: SePay `order_description`, ghi chú bank, body email admin, "Ghi chú đơn" trong list table.
- 1 source of truth: chỉ class này được sinh chuỗi mô tả, mọi nơi khác gọi nó.

## 4.4. Email admin — bắt buộc có `Số chỗ ngồi`

Template `admin-notification.php` render (mỗi line item một block):

```
Đơn #{code}
Khách: {customer_name} | {customer_phone}
Nguồn: {source} | Sales: {sales_user}

Items:
─────────────────────────────────────
{hotel_name} | {room_name}
SP: {product_code} | Loại: {booking_type}
{checkin} → {checkout} ({nights} đêm) × {quantity} {unit_label}
Người: {adults}NL + {children}TE ({child_ages})
**Số chỗ ngồi: {ticket_count}**       ← BẮT BUỘC
Vé tính phí: {billable_seats} (miễn {free_child_seats} bé < 6)
Phòng: {room_subtotal} | Phụ thu NL: {extra_adult_total}
Phụ thu TE: {child_surcharge_total} | Vé xe: {ticket_subtotal}
Đối tác: {partner_name} | Khu vực: {hotel_area}
Mã đối tác: {supplier_booking_code}
Thành tiền: {line_total}
─────────────────────────────────────

Tổng đơn: {total}
Giá vốn: {cost_total} | Lợi nhuận DK: {profit_total}
Coupon: {coupon_code} (-{discount})
Thanh toán: {paid_amount}/{total} ({payment_status})

Ghi chú khách: {customer_note}
Link xem chi tiết: {admin_url}
```

Chi tiết template xem [09-email.md](09-email.md).

## 4.5. Combo = Hotel + Vé xe khứ hồi

- `vie_order_item.booking_type = 'combo'` luôn kèm `ticket_count > 0` và `ticket_subtotal > 0`.
- Vé khứ hồi tính **1 lần / booking** (lượt đi ngày `checkin`, lượt về ngày `checkout`). Giá lookup theo **ngày `checkin`** trong `vie_ticket_price` — đây là nguồn sự thật duy nhất cho `TicketCalculator` (xem [03-pricing.md §3.5](03-pricing.md#35-vé-xe--quy-tắc-mới-ticketcalculator)).
- Trang single-hotel chỉ hiển thị **nút "Đặt Combo"** khi:
    - `vie_ticket_price` có row active cho hotel ở ngày `checkin`, **HOẶC**
    - `vie_hotel.default_ticket_price > 0`.
- Nếu thiếu giá vé ngày checkin → fallback `default_ticket_price`. Nếu cũng = 0 → ẩn nút combo.
- Validator `OrderService::create()`: nếu `booking_type='combo'` mà tổng vé = 0 → reject (sai dữ liệu).

## 4.6. Quy tắc trẻ em (Phase 2 updates)

| # | Quy tắc | Bảng / cột |
|---|---|---|
| 1 | Bé `< 6` ở phòng: miễn phí `room.free_children_count` / phòng | `vie_room.free_children_count`, `free_children_max_age` |
| 2 | Bé `≥ 6` ở phòng: tính như NL trong phân phòng, chiếm slot adult | `hotel.ticket_free_children_max_age + 1` (default 6) |
| 3 | Bé `< 6` vé xe: miễn phí tối đa **1 bé / booking** | `vie_hotel.ticket_free_children_count` (default 1) |
| 4 | Bé `≥ 6` vé xe: tính 1 vé như NL | cùng ngưỡng quy tắc 2 |
| 5 | Bé miễn phí vẫn được tính vào `ticket_count` (chiếm ghế thực) | `vie_order_item.ticket_count` |

> **Khác biệt phòng vs vé**: với phòng, quota miễn phí nhân theo số phòng (`× num_rooms`). Với vé, quota miễn phí cố định cho **toàn booking** — đáp ứng yêu cầu mới.

## 4.7. Cancellation policy — set theo từng KS

### 4.7.1. Model

`vie_hotel.cancellation_policy` JSON (xem [02-database.md §2.4.2](02-database.md#242-cancellation_policy-json)).

### 4.7.2. Tính tỉ lệ phạt

```php
final class CancellationCalculator {
    public function compute(Order $o, OrderItem $item, DateTimeImmutable $now): float
    {
        $hotel  = $this->hotels->find($item->hotel_id);
        $policy = json_decode($hotel->cancellation_policy ?? '{}', true);
        $rules  = $policy['rules'] ?? [];
        usort($rules, fn($a, $b) => $b['hours_before_checkin'] <=> $a['hours_before_checkin']);

        $deltaHours = ($item->checkin_ts - $now->getTimestamp()) / 3600;

        foreach ($rules as $rule) {
            if ($deltaHours >= $rule['hours_before_checkin']) {
                return (float) $rule['penalty_percent'];
            }
        }
        return 100.0; // không match → mất toàn bộ
    }
}
```

### 4.7.3. Service: `OrderService::cancel($orderId, $reason, $actorId)`

1. Load order + items + per-hotel policies.
2. Đối với **mỗi item active**:
   - Tính `penalty_percent` từ hotel policy.
   - `refundable_item = item.line_total * (1 - penalty_percent/100)`.
3. Tổng `refundable = Σ refundable_item`.
4. So với `paid_amount`:
   - `actual_refund = min(refundable, paid_amount)`.
5. Nếu `actual_refund > 0`: insert `vie_payment_log` `type='refund'`, `amount = -actual_refund`.
6. Update order: `status='cancelled'`, `cancelled_at=now`, `cancel_reason`.
7. Update mỗi item: `status='cancelled'`, `cancelled_at`, `cancel_reason`.
8. Recompute `payment_status` từ ledger.
9. Log `vie_activity_log` `action='cancel'`.
10. Trigger `OrderMailer::cancelled()` → khách + admin.

### 4.7.4. Hủy 1 item (không hủy đơn)

REST: `POST /orders/{id}/items/{itemId}/cancel`.

- Tương tự nhưng chỉ với 1 item.
- Recompute `order.subtotal/total/cost_total/profit_total` = Σ items active.
- Nếu refund mà đơn còn dương → `payment_status` về `partial`. Nếu mọi item đã cancel → cũng cancel order.

### 4.7.5. View

- Single-hotel: render `cancellation_policy.rules` thành bảng đẹp.
- Admin SPA trang chi tiết đơn: tab "Hủy đơn" hiện preview tiền hoàn dự kiến cho **mỗi item** trước khi confirm.

## 4.8. Trạng thái đơn (`vie_order.status`)

```
        ┌──────────┐  confirm    ┌───────────┐  checkout_done   ┌────────────┐
        │ pending  │ ──────────► │ confirmed │ ───────────────► │ completed  │
        └─┬──┬─────┘             └─────┬─────┘                  └────────────┘
          │  │ no-show (cron: today > checkin & chưa thanh toán)
          │  └──────────────────►┌────────────┐
          │ cancel               │  no_show   │
          ▼                      └────────────┘
        ┌──────────┐
        │ cancelled│
        └──────────┘
```

State machine ở `OrderStateMachine::transition(Order $o, string $to)`, reject illegal transitions.

Auto rules:

- `payment_status` chuyển `paid` (từ ledger) **và** đang `pending` → auto `confirmed`, set `confirmed_at`.
- `today > checkout` và `status = confirmed` → cron tag `completed`.
- `today > checkin` và `status = pending` và không thanh toán → cron tag `no_show` + cancel.

## 4.9. Trạng thái thanh toán (`vie_order.payment_status`)

Tính từ ledger:

```text
paid_amount = max(0, Σ vie_payment_log.amount WHERE order_id=?)
if paid_amount <= 0:       payment_status = pending
elif paid_amount < total:  payment_status = partial
elif paid_amount >= total: payment_status = paid
```

Tham khảo §2.14 và [10-payment-sepay.md](10-payment-sepay.md).

## 4.10. Partner payment

`partner_payment_status`: trạng thái thanh toán cho đối tác (KS thực) — workbook ghi nhận:

- `not_created` (default)
- `created` ("đã tạo lệnh")
- `deposit_paid` (đã cọc)
- `paid` ("đã thanh toán")

Sales/admin update qua admin SPA. Không tự động.

## 4.11. Tồn kho phòng — check & hold

`vie_room_price.stock` là số phòng có thể bán theo đêm. Quote, tạo đơn, và hủy đơn đều phải nhất quán với tồn kho.

### 4.11.1. Quote (chỉ check, không trừ)

`PriceCalculator` đọc `room_price` cho từng đêm trong `[checkin, checkout)`. Nếu **bất kỳ đêm nào** không có row active hoặc `stock < numRooms` → trả `requires_quote = true` + `messages: ["Hết phòng đêm {date}"]`, **không** quote tiếp.

### 4.11.2. Tạo đơn — pessimistic hold trong transaction

`OrderService::create()` chạy trong **1 transaction MySQL** (REPEATABLE READ):

```sql
START TRANSACTION;

-- 1. SELECT … FOR UPDATE tất cả room_price liên quan (lock dòng)
SELECT id, date, stock
FROM   wp_vie_room_price
WHERE  room_id = ? AND date >= ? AND date < ? AND is_active = 1
FOR UPDATE;

-- 2. Nếu mọi đêm stock >= numRooms → tiếp; ngược lại ROLLBACK + 409 Conflict
-- 3. UPDATE: trừ stock đúng numRooms cho mỗi đêm
UPDATE wp_vie_room_price
SET    stock = stock - ?
WHERE  id IN (...);

-- 4. INSERT vie_order + vie_order_item (pricing_snapshot.rooms_allocated = numRooms)
COMMIT;
```

- 409 Conflict + `errors[].code = "stock_unavailable"`, `meta.unavailable_dates = [...]` để SPA/public refresh quote.
- Transaction đảm bảo 2 request song song không bán quá tồn.

### 4.11.3. Hủy đơn / hủy item — restore stock

`OrderService::cancel()` và `cancelItem()`:

- Với mỗi item đang `active`, **cộng lại** stock = `pricing_snapshot.rooms_allocated` cho từng đêm trong `[item.checkin, item.checkout)`.
- Cùng transaction với việc đổi `item.status = 'cancelled'` để tránh race.

### 4.11.4. Combo / vé xe

Vé xe (`vie_ticket_price`) **không trừ stock** ở MVP (giả định seat luôn đủ). Nếu cần khoá ghế thực, thêm cột `seat_stock` ở phase sau.

### 4.11.5. Admin override

Admin có thể tạo đơn quá tồn qua flag `?force_stock=1` (cap `vie_manage_orders`). Lúc đó stock có thể về số âm (hệ thống vẫn cho, log activity `stock:overbook`). Public form **không bao giờ** bypass.

## 4.12. Phân bổ giảm giá (discount allocation)

Coupon / discount manual chạy ở cấp đơn (`vie_order.discount`), nhưng báo cáo theo hotel sum `vie_order_item.line_total`. Để báo cáo nhất quán:

- Sau khi tính subtotal cho từng item, phân bổ `order.discount` xuống `line_discount` **theo tỉ lệ subtotal item**:

```php
$ratio = $itemSubtotal / $orderSubtotal;
$item->line_discount = (int) round($order->discount * $ratio);
$item->line_total    = $itemSubtotal - $item->line_discount;
```

- Sau khi phân bổ, làm tròn dư về item cuối cùng để `Σ line_discount = order.discount` (chống lệch 1đ).
- `cost_total` không bị ảnh hưởng (giá vốn không thay đổi do giảm giá).
- `line_total` sau phân bổ là **doanh thu thực thu** của item → an toàn cho `/reports/by-hotel`.

## 4.13. Workbook field mapping

| Workbook | Schema |
|---|---|
| Tháng | derived from `created_at` |
| Ngày đặt hàng / Ngày tạo | `vie_order.created_at` |
| Mã ĐH | `vie_order.code` |
| Mã KH | derived from `vie_customer.id` (vd `KH00123`) |
| Tên Khách Hàng | `vie_customer.name` (snapshot `vie_order.customer_phone`) |
| Số Điện Thoại | `vie_customer.phone` |
| Nguồn khách hàng | `vie_order.source` |
| Mã SP | `vie_order_item.product_code` |
| Tên sản phẩm | `vie_order_item.name` |
| Đơn vị | `vie_order_item.unit_label` |
| Nhóm sản phẩm | static `Khách Sạn` (hoặc map từ `booking_type`) |
| Số lượng | `vie_order_item.quantity` |
| Giá bán | `vie_order_item.line_total / quantity` (compute) |
| Phụ thu | `vie_order_item.extra_adult_total + child_surcharge_total` |
| Thành tiền | `vie_order_item.line_total` |
| Số tiền Thanh Toán | `vie_order.paid_amount` |
| Số tiền còn lại | `vie_order.total - vie_order.paid_amount` |
| CHI PHÍ GIÁ VỐN | `vie_order_item.cost_total` |
| LỢI NHUẬN | `vie_order_item.profit_total` |
| Hình Thức TT | latest `vie_payment_log.method` |
| Ngày thanh toán | latest `vie_payment_log.paid_at` |
| Nguồn đơn hàng | `vie_order.source` |
| Ngày đi / Ngày về | `vie_order_item.checkin` / `checkout` |
| Tháng đi | derived from checkin |
| Ghi chú | `vie_order.customer_note` |
| Đối tác | `vie_order_item.partner_name` |
| Khách sạn ở đâu? | `vie_order_item.hotel_area` |
| Mã đặt phòng (đối tác) | `vie_order_item.supplier_booking_code` |
| Số Hóa Đơn | `vie_order.invoice_number` |
| Người bán | `wp_users.display_name` via `sales_user_id` |
| Thanh toán đối tác | `vie_order.partner_payment_status` |
| Thanh toán cọc | derived: Σ `vie_payment_log.amount WHERE type='deposit'` |
| Phần còn lại phải Thanh toán | `vie_order.total - vie_order.paid_amount` |
| Mã Voucher | `vie_order.voucher_code` |
