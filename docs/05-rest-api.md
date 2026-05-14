# 05 — REST API `vie/v1/*`

Toàn bộ nghiệp vụ expose qua REST. Admin SPA Vue 3 + PrimeVue gọi 100% qua đây.
AJAX `admin-ajax.php` chỉ là legacy bridge.

## 5.1. Quy ước chung

### 5.1.1. Base URL

`{site_url}/wp-json/vie/v1/`

### 5.1.2. Headers

| Header | Khi dùng |
|---|---|
| `Authorization: Bearer {access_token}` | SPA admin (xem [07-auth.md](07-auth.md)) |
| `X-WP-Nonce: {nonce}` | Public frontend (logged-out) |
| `X-Idempotency-Key: {sha256}` | POST `/orders` từ public |
| `Content-Type: application/json` | Mọi POST/PUT/PATCH |
| `Accept-Language: vi` | i18n |

### 5.1.3. Envelope response — chuẩn hoá

**Mọi response** (success + error) đều dùng envelope sau, dù là list, detail, hay action:

```json
{
  "success": true,
  "data": <object | array | null>,
  "meta": {
    "request_id": "req_018f23b1e4...",
    "timestamp": "2026-05-12T10:23:45+07:00",
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 145,
      "total_pages": 8,
      "has_next": true,
      "has_prev": false
    },
    "sort": { "field": "created_at", "order": "desc" },
    "filters_applied": { "status": ["pending","confirmed"] },
    "available_filters": [
      {"key":"status","type":"enum","values":["pending","confirmed","cancelled","completed","no_show"]},
      {"key":"date_from","type":"date"},
      {"key":"date_to","type":"date"}
    ],
    "available_sorts": ["created_at","total","paid_amount","checkin"],
    "links": {
      "self":  "/wp-json/vie/v1/orders?page=1&per_page=20",
      "next":  "/wp-json/vie/v1/orders?page=2&per_page=20",
      "first": "/wp-json/vie/v1/orders?page=1&per_page=20",
      "last":  "/wp-json/vie/v1/orders?page=8&per_page=20"
    }
  },
  "errors": null
}
```

Error:

```json
{
  "success": false,
  "data": null,
  "meta": { "request_id":"...", "timestamp":"..." },
  "errors": [
    { "code": "validation_error", "field": "phone", "message": "Số điện thoại không hợp lệ" },
    { "code": "validation_error", "field": "email", "message": "Email sai định dạng" }
  ]
}
```

HTTP status code:

| Code | Khi |
|---|---|
| 200 | Success |
| 201 | Created |
| 204 | No content (DELETE OK) |
| 400 | Validation / business rule |
| 401 | Thiếu / token sai |
| 403 | Không đủ quyền |
| 404 | Not found |
| 409 | Conflict (idempotency, state machine) |
| 422 | Schema validate fail |
| 429 | Rate limit |
| 500 | Server error |

### 5.1.4. Pagination

Query: `?page=1&per_page=20` (mặc định 20, max 100). Response meta `pagination` luôn có.

### 5.1.5. Sort

Query: `?sort=created_at&order=desc` hoặc multi-field `?sort=-created_at,total` (prefix `-` = desc).

### 5.1.6. Filter

- Equality: `?status=pending`
- IN: `?status=pending,confirmed`
- Range: `?total_min=1000000&total_max=5000000`
- Date range: `?date_from=2026-01-01&date_to=2026-01-31` (mặc định lọc theo `created_at`, có thể đổi qua `?date_field=checkin`)
- Full-text search: `?q=Anh+Hưng` (search `customer_name`, `customer_phone`, `order.code`)
- Boolean: `?is_active=1`

Mỗi endpoint list có **`meta.available_filters`** mô tả filter hỗ trợ → SPA tự build UI filter động.

### 5.1.7. Fields selection

`?fields=id,code,customer_phone,total` (whitelist). Cho list, default trả tất cả các field "list view".

### 5.1.8. Embed (relations)

`?embed=customer,items,payments,sales_user` để SPA tránh N+1.

### 5.1.9. Validation

Mọi POST/PUT/PATCH chạy qua `Validation\Schemas\*`. Lỗi trả 422 với `errors[].field`.

### 5.1.10. Rate limit

- Public: 60 req/min/IP.
- Authed: 600 req/min/user.
- Webhook SePay: bypass.
- Rate exceeded → 429 + header `Retry-After`.

### 5.1.11. Public nonce + rate limit

Public endpoints (`POST /quote`, `POST /orders`, `POST /coupons/validate`, `POST /lead`, `GET /orders/lookup`) yêu cầu `X-WP-Nonce` từ guest. Cơ chế:

- Theme cha render trang chứa shortcode `[vie_checkout]` / `[vie_hotel_rooms]` sẽ `wp_localize_script('vie-frontend', 'VieRest', { nonce: wp_create_nonce('wp_rest') })`.
- `wp_create_nonce()` cho **guest** dùng `user_id = 0` + IP-binding nhẹ qua WP session token; valid 12–24h (mặc định `nonce_life`). Đây là **soft-CSRF** check, KHÔNG phải auth.
- Bot tự bypass nonce dễ → tầng phòng vệ chính là:
    1. Rate limit IP (xem [§5.1.10](#5110-rate-limit)).
    2. Idempotency key + transaction lock cho `POST /orders` (xem [04-business-rules.md §4.2](04-business-rules.md#42-idempotency-key)).
    3. Captcha (cloudflare turnstile) cho `POST /lead` & `POST /orders` khi flag `vie_captcha_enabled = 1`.
- Nonce sai → 403 `nonce_invalid`. Frontend phải refresh trang để lấy nonce mới (không có endpoint refresh nonce — tránh lộ).

### 5.1.12. CORS

Domain SPA admin: cho phép qua option `vie_cors_origins` (mặc định cùng domain). Header trả: `Access-Control-Allow-Origin`, `Allow-Credentials: true`, `Allow-Headers: Authorization,Content-Type,X-WP-Nonce,X-Idempotency-Key`.

---

## 5.2. Authentication

### `POST /auth/login`

Body: `{ "username": "...", "password": "..." }`.

Response data: `{ "access_token", "expires_in", "token_type":"Bearer", "user": {...} }` + Set-Cookie `vie_refresh` (HttpOnly, Secure, SameSite=Lax, 30d).

### `POST /auth/refresh`

Cookie `vie_refresh` → response data tương tự `/login`. Rotate token (xem [07-auth.md](07-auth.md)).

### `POST /auth/logout`

Revoke refresh token; clear cookie. Chỉ revoke session hiện tại.

### `POST /auth/logout-all`

Authenticated. Revoke **toàn bộ** refresh token của user → bắt login lại trên mọi thiết bị. Xem [07-auth.md §7.6](07-auth.md#76-logout).

### `GET /auth/me`

Trả user hiện tại + capabilities.

---

## 5.3. Hotels

### `GET /hotels`

Query filter: `is_active`, `city`, `star_rating_min`, `q`.
Embed: `rooms`, `policy`.
Sort: `sort_order`, `name`, `created_at`.

Response `data[]`:

```json
{
  "id": 12,
  "post_id": 345,
  "name": "Mercure Vũng Tàu",
  "slug": "mercure-vung-tau",
  "city": "VT",
  "star_rating": 4,
  "default_checkin": "14:00:00",
  "default_checkout": "12:00:00",
  "default_ticket_price": 350000,
  "ticket_free_children_count": 1,
  "ticket_free_children_max_age": 5,
  "is_active": 1,
  "sort_order": 10
}
```

### `GET /hotels/{id}` — chi tiết

Trả full bao gồm `pricing_policy`, `cancellation_policy`, `gallery`.

### `POST /hotels` / `PUT /hotels/{id}` / `DELETE /hotels/{id}`

Cap: `vie_manage_hotels`. Activity log.

### `GET /hotels/{id}/policy`

Public. Trả `pricing_policy` + `cancellation_policy`.

### `GET /hotels/{id}/rooms`

List room thuộc hotel + nightly price kèm option `?date_from&date_to&adults&children&child_ages&booking_type&num_rooms` → embed quote luôn (tiết kiệm round-trip cho list-rooms page).

---

## 5.4. Rooms

### `GET /rooms`

Filter: `hotel_id`, `is_active`, `q`, `min_adults`, `max_adults`.

### `GET /rooms/{id}` / `POST` / `PUT` / `DELETE`

Cap `vie_manage_inventory`.

---

## 5.5. Room Prices

### `GET /room-prices`

Filter: `room_id`, `hotel_id`, `date_from`, `date_to`, `is_active`, `source`.

Response: list đêm.

### `POST /room-prices/bulk`

Body:

```json
{
  "scope": {
    "room_ids": [1,2,3],          // hoặc hotel_id để áp toàn hotel
    "date_from": "2026-06-01",
    "date_to":   "2026-06-30",
    "weekdays":  [1,2,3,4,5,7]   // 1=T2 ... 7=CN; null = mọi ngày
  },
  "values": {
    "price": 1500000,
    "extra_adult_price": 300000,
    "stock": 10,
    "is_active": 1,
    "source": "weekday_rule"
  }
}
```

Upsert vào `vie_room_price`. Activity log 1 dòng tổng.

### `POST /room-prices/holiday-override`

Tương tự nhưng tag `source = holiday_override` cho dải ngày lễ.

### `PUT /room-prices/{id}` / `DELETE /room-prices/{id}`

---

## 5.6. Surcharges

### `GET /surcharges` / `POST` / `PUT` / `DELETE`

Filter `room_id`, `guest_type`.

### `GET /surcharge-prices` / `POST/bulk`

Tương tự bulk room price.

---

## 5.7. Ticket Prices

### `GET /ticket-prices`

Filter `hotel_id`, `date_from`, `date_to`.

### `POST /ticket-prices/bulk`

```json
{ "scope": {...}, "values": { "ticket_price": 350000, "is_active": 1 } }
```

---

## 5.8. Product Codes

### `GET /product-codes`

Filter `hotel_id`, `room_id`, `booking_type`, `is_active`, `q`.

### `POST /product-codes` / `PUT` / `DELETE`

Bulk import CSV → `POST /product-codes/import` (multipart).

---

## 5.9. Customers

### `GET /customers`

Filter `q` (phone/name/email), `has_vat`, `date_from`, `date_to` (created).
Sort: `booking_count`, `created_at`.

### `GET /customers/{id}` — embed `orders`

### `POST /customers` / `PUT /customers/{id}`

### `GET /customers/lookup?phone=...`

Tra cứu nhanh khi tạo đơn (admin SPA).

---

## 5.10. Quote

### `POST /quote`

Public + nonce. Không tạo đơn.

Body:

```json
{
  "room_id": 5,
  "checkin": "2026-06-15",
  "checkout": "2026-06-17",
  "adults": 2,
  "child_ages": [5, 7],
  "user_rooms": 0,
  "booking_type": "combo",
  "coupon_code": "SUMMER10"
}
```

Response `data`:

```json
{
  "num_rooms": 1,
  "nights": 2,
  "seat_count": 4,
  "billable_seats": 3,
  "free_child_seats": 1,
  "extra_adult_beds": 0,
  "effective_adults": 3,
  "effective_children": 1,
  "nightly": [...],
  "child_assessments": [
    {"age":5,"is_free":true,"treated_as_adult":false},
    {"age":7,"is_free":false,"treated_as_adult":true}
  ],
  "room_subtotal": 3000000,
  "extra_adult_subtotal": 0,
  "child_surcharge_total": 0,
  "ticket_subtotal": 1050000,
  "subtotal": 4050000,
  "discount": 405000,
  "total": 3645000,
  "cost_total": 0,
  "requires_quote": false,
  "messages": [
    "Miễn phí 1 vé bé dưới 6",
    "Bé 7 tuổi được tính như người lớn (chiếm slot phòng + 1 vé)"
  ],
  "unavailable_date": null
}
```

---

## 5.11. Coupons

### `GET /coupons`

Filter `is_active`, `valid`, `q`, `sales_only`, `hotel_id`.

### `POST /coupons/validate`

Body: `{ "code":"...", "order_subtotal":..., "hotel_id":..., "room_id":..., "booking_type":"...", "user_email":"..." }`.

Response: `{ "valid": true, "discount": 150000, "coupon": {...} }`.

### `POST /coupons` / `PUT` / `DELETE`

### `GET /coupons/{id}/usage` — list `vie_coupon_usage`

---

## 5.12. Orders

### `GET /orders`

**Filter** (`available_filters`):

| Key | Type | Mô tả |
|---|---|---|
| `q` | string | search `code`, `customer_phone`, `customer_name` |
| `status` | enum multi | `pending,confirmed,cancelled,completed,no_show` |
| `payment_status` | enum multi | |
| `partner_payment_status` | enum multi | |
| `source` | enum multi | |
| `sales_user_id` | int | |
| `hotel_id` | int | qua items |
| `room_id` | int | qua items |
| `customer_id` | int | |
| `customer_phone` | string | |
| `date_field` | enum | `created_at` (default), `checkin`, `confirmed_at` |
| `date_from` / `date_to` | date | |
| `total_min` / `total_max` | int | |
| `has_unpaid` | bool | `paid_amount < total` |
| `has_voucher` | bool | |
| `has_invoice` | bool | |
| `coupon_code` | string | |

**Sort**: `created_at`, `total`, `paid_amount`, `checkin`, `code`.

**Embed**: `items`, `payments`, `customer`, `sales_user`, `coupon`.

Response data row:

```json
{
  "id": 1234,
  "code": "VIE260512007",
  "status": "confirmed",
  "payment_status": "partial",
  "partner_payment_status": "created",
  "source": "tiktok",
  "checkin": "2026-06-15",
  "checkout": "2026-06-17",
  "nights": 2,
  "adults": 2,
  "children": 1,
  "child_ages": [5],
  "subtotal": 4350000,
  "discount": 435000,
  "total": 3915000,
  "paid_amount": 2000000,
  "remaining": 1915000,
  "cost_total": 3000000,
  "profit_total": 915000,
  "currency": "VND",
  "customer": {
    "id": 88, "name": "Huỳnh Tuyết Anh", "phone": "0855774888", "email": null
  },
  "sales_user": { "id": 5, "display_name": "Huỳnh Hà" },
  "items_count": 1,
  "created_at": "2026-05-12T10:23:45+07:00"
}
```

### `GET /orders/{id}` — full detail

Embed mặc định: `items`, `payments`, `customer`, `sales_user`, `coupon`, `activity_log`.

### `POST /orders` — tạo đơn

Public form (idempotent) hoặc admin/sales.

Body:

```json
{
  "customer": {
    "phone": "0901234567",
    "name": "Nguyễn Văn A",
    "email": "a@example.com",
    "vat": null
  },
  "source": "website",
  "sales_user_id": null,
  "customer_note": "...",
  "pickup":  { "date":"2026-06-15", "time":"06:00", "address":"...", "note":"..." },
  "dropoff": { ... },
  "items": [
    {
      "room_id": 5,
      "booking_type": "combo",
      "checkin": "2026-06-15",
      "checkout": "2026-06-17",
      "adults": 2,
      "child_ages": [5, 7],
      "user_rooms": 0
    }
  ],
  "coupon_code": "SUMMER10",
  "voucher_code": null,
  "payment_method": "sepay"
}
```

Service (trong **1 transaction**):

1. Resolve/Upsert `vie_customer` (phone unique).
2. Cho mỗi item: chạy `PriceCalculator` → bắt `requires_quote` reject 422.
3. **`SELECT … FOR UPDATE`** trên `vie_room_price` cho mọi đêm/phòng. Nếu `stock < numRooms` → ROLLBACK, 409 `stock_unavailable` (xem [04-business-rules.md §4.11](04-business-rules.md#411-tồn-kho-phòng--check--hold)).
4. **`UPDATE vie_room_price SET stock = stock - numRooms`** cho từng đêm.
5. Insert `vie_order` (status `pending`, payment_status `pending`, `code` sinh tự động, snapshot customer_name/email/vat).
6. Insert `vie_order_item[]` với `pricing_snapshot.rooms_allocated = numRooms`.
7. Phân bổ `discount` → `line_discount` theo tỉ lệ (xem [§4.12](04-business-rules.md#412-phân-bổ-giảm-giá-discount-allocation)).
8. Tổng hợp `subtotal/discount/total/cost_total`.
9. Apply coupon → ghi `vie_coupon_usage`.
10. COMMIT.
11. (ngoài transaction) Nếu `payment_method = sepay` → gọi `SepayCheckout::init()` → trả `redirect_url`.
12. Dispatch `OrderMailer::pendingPayment()` + `AdminOrderMailer::newOrder()`.

Response:

```json
{
  "order": {...},
  "redirect_url": "https://pay.sepay.vn/..."  // null nếu method khác
}
```

### `PUT /orders/{id}` — update header

Cho phép sửa: `customer_note`, `internal_note`, `sales_user_id`, `invoice_number`, `voucher_code`, `pickup/dropoff`, `partner_payment_status`.
Không cho phép sửa: `code`, `total`, `customer_id` (xóa & tạo lại nếu cần).

### `POST /orders/{id}/confirm`

Manual confirm (admin override).

### `POST /orders/{id}/cancel`

Body: `{ "reason": "..." }`.
Áp dụng cancellation policy (xem §4.7).

### `POST /orders/{id}/complete`

Manual mark complete.

### `GET /orders/{id}/items` / `POST` / `PUT /items/{itemId}` / `DELETE`

Sửa item: chỉ field "non-pricing" (partner_name, supplier_booking_code, hotel_area, status, cancel_reason). Sửa pricing → phải re-quote.

### `POST /orders/{id}/items/{itemId}/cancel`

Hủy 1 item (xem §4.7.4).

### `POST /orders/{id}/recalculate-cost`

Cập nhật `cost_total` cho item & order (admin nhập tay).

### `GET /orders/lookup` — tra cứu công khai

Public + nonce. Dùng cho trang `/dat-phong-thanh-cong/` sau khi khách thanh toán.

Query: `?code=VIE2605120007&phone=0901234567`.

Rule:
- `code` + `phone` phải khớp đúng `vie_order.code` và `vie_order.customer_phone` (snapshot).
- Sai 1 trong 2 → 404 (không leak phân biệt code vs phone sai).
- Rate limit 30 req/min/IP.

Response: subset của `GET /orders/{id}` (không trả `internal_note`, `cost_total`, `profit_total`).

### `GET /orders/{id}/description` — preview chuỗi `OrderDescription`

### `POST /orders/{id}/print` — render HTML in / PDF

### `POST /orders/{id}/resend-email` — gửi lại email theo `type`

---

## 5.13. Payments

### `GET /orders/{id}/payments` — ledger

### `POST /orders/{id}/payments` — admin nhập tay

Body:

```json
{
  "type": "deposit",
  "amount": 2000000,
  "method": "bank_transfer",
  "transaction_id": "FT...",
  "paid_at": "2026-05-12T14:00:00+07:00",
  "note": "Cọc 50% qua VietinBank"
}
```

### `POST /orders/{id}/payments/refund`

Body: `{ "amount": 500000, "method":"bank_transfer", "note":"...", "transaction_id":"..." }`.

### `POST /orders/{id}/payments/{logId}/void`

Tạo bản ghi `void` bù trừ.

### `POST /payments/sepay/webhook`

Public, verify HMAC. Xem [10-payment-sepay.md](10-payment-sepay.md).

### `GET /payments` — global ledger view (admin)

Filter `order_code`, `method`, `gateway`, `type`, `date_from/to`, `amount_min/max`.

---

## 5.14. Reports

Tất cả `?date_from&date_to&granularity=day|month|year`. Default 30 ngày gần nhất.

### `GET /reports/revenue`

Group by `granularity` + (optional) `source` / `sales_user_id` / `hotel_id`.

Response:

```json
{
  "summary": {
    "orders": 145,
    "revenue": 580000000,
    "paid": 420000000,
    "outstanding": 160000000,
    "cost": 410000000,
    "profit": 170000000
  },
  "series": [
    {"bucket":"2026-05-01","orders":5,"revenue":...,"paid":...,"cost":...,"profit":...},
    ...
  ]
}
```

### `GET /reports/by-hotel`
### `GET /reports/by-source`
### `GET /reports/by-sales`
### `GET /reports/received-cash` — theo `paid_at` của `vie_payment_log`
### `GET /reports/receivable` — `paid_amount < total`

### `GET /reports/by-order` — detail theo đơn

Cùng filter / sort như `GET /orders` nhưng response đã flatten kèm các cột workbook (paid, remaining, partner_payment_status, supplier_booking_code, voucher_code, …). Hỗ trợ `?format=csv|xlsx` để xuất trực tiếp định dạng workbook. Xem [11-reporting.md §11.9](11-reporting.md#119-detail-theo-đơn--get-reportsby-order).

### `GET /reports/{type}/export?format=csv|xlsx`

Stream file download.

---

## 5.15. Activity Log

### `GET /activity-log`

Filter `actor_user_id`, `entity_type`, `entity_id`, `action`, `date_from/to`.

---

## 5.16. Settings

### `GET /settings` / `PUT /settings`

Categories:

- `email`: SMTP / templates / recipients
- `sepay`: merchant_id, secret_key, env
- `general`: timezone, currency, default_route, pricing_round_to (default 1000)
- `cors`: origins
- `cron`: thresholds for no_show / completed

Cap: `manage_options` (administrator only).

### `POST /settings/rotate-jwt-secret`

Cap `manage_options`. Sinh secret mới base64 64 bytes, ghi `wp_options.vie_jwt_secret`. Tất cả access token đã phát hành **bị vô hiệu** (refresh token vẫn dùng được — tự xoay sang access mới ở lần `/auth/refresh` kế tiếp). Activity log `action='settings:rotate_jwt'`. Xem [07-auth.md §7.2](07-auth.md#72-access-token-jwt).

---

## 5.17. Public utility

### `POST /lead`

Public + nonce. Dùng khi quote trả `requires_quote = true` (xem [08-frontend-public.md §8.7](08-frontend-public.md#87-hiển-thị-yêu-cầu-báo-giá)).

Body: `{ "name":"...", "phone":"...", "email":null, "message":"...", "context": {"hotel_id":12, "room_id":5, "checkin":"...", "checkout":"...", "adults":2, "child_ages":[5,7,9]} }`.

Service: insert `vie_customer` nếu chưa có (qua phone unique), gửi mail admin `lead_received`. Không tạo order. Rate limit 10 req/h/IP.

Response: `{ "ok": true, "lead_id": 42 }`.

### `GET /users/sales`

Cap `manage_options`. List user role `sales` + metric (đơn/tháng, doanh thu/tháng, trạng thái). Xem [12-roles.md §12.6](12-roles.md#126-sales-user-account).

### `POST /users/sales`

Cap `manage_options`. Tạo user role `sales` + gán `vie_managed_hotels`.

Body: `{ "username":"...", "email":"...", "display_name":"...", "password":"...", "managed_hotels":[12,15], "source_label":"Facebook Ads" }`.

### `PUT /users/sales/{id}`

Reset password, disable, đổi managed_hotels.

---

## 5.18. Lookup / Utility endpoints

| Endpoint | Mục đích |
|---|---|
| `GET /lookup/cities` | distinct city values |
| `GET /lookup/sources` | enum nguồn (giúp SPA build dropdown) |
| `GET /lookup/statuses` | enum status + label tiếng Việt |
| `GET /lookup/payment-methods` | |
| `GET /lookup/sales-users` | WP users có role `sales`/`administrator` |
| `GET /lookup/weekday-patterns` | `CN-T5`, `T6-T7`, `T7` |

---

## 5.19. Health

`GET /health` — public. Trả version, db status, schema version map, sepay status.

---

## 5.20. Permission map

Mỗi controller dòng đầu khai báo `permission_callback` map theo capability. Bảng trong [12-roles.md](12-roles.md).

**Quy tắc đọc bảng**: cột Cap là một danh sách OR — chỉ cần thoả 1 cap là pass. Repository tự thêm scope WHERE (xem [12-roles.md §12.4](12-roles.md#124-hotel-ownership)) để giới hạn dữ liệu trả về theo cap nào trúng.

| Endpoint nhóm | Cap (OR) |
|---|---|
| `POST /quote`, `POST /orders`, `POST /coupons/validate`, `POST /payments/sepay/webhook`, `GET /hotels`, `GET /hotels/{id}`, `GET /hotels/{id}/rooms`, `GET /hotels/{id}/policy`, `POST /lead` | public + nonce (xem [§5.1.11](#5111-public-nonce--rate-limit)) |
| `GET /orders/lookup` | public + (`code` + `phone` khớp) |
| `*/auth/*` | public / token tùy route |
| `GET /orders` (list) | `vie_manage_orders` ∨ `vie_view_all_orders` ∨ `vie_view_orders_own_hotel` ∨ `vie_view_own_orders` |
| `GET /orders/{id}` (detail) | cùng list cap + scope check trong service |
| `POST /orders` (admin) | `vie_create_orders` |
| `PUT /orders/{id}`, `POST /orders/{id}/{confirm\|complete}` | `vie_manage_orders` |
| `POST /orders/{id}/cancel`, `POST /orders/{id}/items/{itemId}/cancel` | `vie_cancel_orders` |
| `POST /orders/{id}/payments`, `POST /orders/{id}/payments/refund`, `POST /orders/{id}/payments/{logId}/void`, `GET /payments` | `vie_manage_payments` |
| `*/rooms`, `*/room-prices`, `*/surcharges`, `*/ticket-prices`, `*/product-codes` (full) | `vie_manage_inventory` |
| `PUT /room-prices/*`, `POST /room-prices/bulk` (giới hạn hotel) | `vie_manage_inventory_own_hotel` (scope theo `vie_managed_hotels`) |
| `*/customers` | `vie_manage_customers` |
| `*/coupons` | `vie_manage_coupons` |
| `GET /reports/*` (toàn bộ) | `vie_view_reports` |
| `GET /reports/*` (giới hạn hotel mình quản) | `vie_view_reports_own_hotel` |
| `*/activity-log` | `vie_view_audit` |
| `POST /settings/rotate-jwt-secret`, `PUT /settings`, `*/users/sales` | `manage_options` |

---

## 5.21. Validation schemas

Mỗi POST có 1 schema class trong `Validation\Schemas\*`. Output thông báo lỗi tiếng Việt. Ví dụ:

```php
final class CreateOrderSchema {
    public static function rules(): array { return [
        'customer.phone' => 'required|phone',
        'customer.name'  => 'required|string|max:255',
        'customer.email' => 'nullable|email',
        'source'         => 'required|in:website,facebook,tiktok,zalo,hotline,mail,repurchase,direct',
        'items'          => 'required|array|min:1|max:10',
        'items.*.room_id'      => 'required|int|exists:vie_room,id',
        'items.*.booking_type' => 'required|in:room,combo',
        'items.*.checkin'      => 'required|date',
        'items.*.checkout'     => 'required|date|after:items.*.checkin',
        'items.*.adults'       => 'required|int|min:1|max:20',
        'items.*.child_ages'   => 'array|max:10',
        'items.*.child_ages.*' => 'int|min:0|max:17',
        'items.*.user_rooms'   => 'nullable|int|min:0|max:20', // 0 = auto
        // 'items.*.quantity' KHÔNG được client gửi — server tự sinh từ quote
        'coupon_code'    => 'nullable|string|max:50',
        'payment_method' => 'nullable|in:sepay,bank_transfer,cash,none',
    ]; }
}
```
