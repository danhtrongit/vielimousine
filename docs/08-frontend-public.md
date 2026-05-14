# 08 — Frontend public (theme cha)

Public-facing dùng shortcode PHP + JS thuần. **Không** Vue.

## 8.1. Shortcode

| Shortcode | View | Mục đích |
|---|---|---|
| `[vie_hotel_search]` | `templates/frontend/search-form.php` | Form tìm phòng (date, người lớn, trẻ em…) |
| `[vie_hotel_rooms hotel_id=…]` | `templates/frontend/room-card.php` | List phòng + giá realtime |
| `[vie_checkout]` | `templates/frontend/checkout.php` | Form đặt phòng |
| `[vie_order_success]` | `templates/frontend/success.php` | Trang cảm ơn / hướng dẫn thanh toán |

Tự inject shortcode vào page theo slug (giống code cũ):

```php
add_filter('the_content', function ($c) {
    if (!is_page()) return $c;
    $map = [
        'dat-phong'            => '[vie_checkout]',
        'dat-phong-thanh-cong' => '[vie_order_success]',
    ];
    $slug = get_post_field('post_name');
    if (isset($map[$slug]) && !str_contains($c, $map[$slug])) $c .= $map[$slug];
    return $c;
});
```

## 8.2. Single-hotel template

`single-hotel.php` (theme con, kế thừa theme cha) render:

- Header + gallery + thông tin KS (post field WP)
- `[vie_hotel_search hotel_id=...]`
- `[vie_hotel_rooms hotel_id=...]`
- Section **Chính sách giá** (render từ `vie_hotel.pricing_policy`)
- Section **Chính sách hoàn huỷ** (render bảng từ `cancellation_policy.rules`)

## 8.3. Asset

`Frontend\Assets::register()`:

- `flatpickr` (date picker) + locale vi
- `sweetalert2`
- `swiper` (gallery)
- 4 file CSS/JS bundle theo shortcode (lazy register, chỉ enqueue khi shortcode hoạt động).

Localize:

```php
wp_localize_script('vie-checkout', 'VieRest', [
  'root'  => esc_url_raw(rest_url('vie/v1/')),
  'nonce' => wp_create_nonce('wp_rest'),  // guest-OK; xem ghi chú dưới
  'hotelPolicies' => ... // nếu cần truyền inline
]);
```

> **Guest nonce**: `wp_create_nonce()` đối với user chưa đăng nhập dùng `user_id = 0` + session token, valid ~12h. Đây chỉ là **soft-CSRF** check, không phải auth, không phải session. Khi nonce hết hạn (do trang đứng yên quá `nonce_life`), submit sẽ 403 — frontend bắt lỗi và `location.reload()` lấy nonce mới.
>
> Bảo vệ thực sự cho public endpoints (chống spam / brute-force) đến từ tầng dưới:
> - Rate limit IP 60 req/min (xem [05-rest-api.md §5.1.10](05-rest-api.md#5110-rate-limit)).
> - Idempotency key cho `POST /orders` (xem [04-business-rules.md §4.2](04-business-rules.md#42-idempotency-key)) — tránh double-submit.
> - Cloudflare Turnstile bật khi `vie_captcha_enabled = 1` (Settings → Security).

## 8.4. JS API helper (vanilla)

```js
window.Vie = window.Vie || {};
Vie.api = {
  fetch(path, opts = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': VieRest.nonce,
      ...(opts.headers || {})
    };
    if (opts.idempotencyKey) headers['X-Idempotency-Key'] = opts.idempotencyKey;
    return fetch(VieRest.root + path, { ...opts, headers })
      .then(async r => {
        const json = await r.json();
        if (!json.success) throw new Vie.ApiError(json.errors, r.status);
        return json.data;
      });
  },
  get: (p, q)    => Vie.api.fetch(p + (q ? '?' + new URLSearchParams(q) : ''), { method: 'GET' }),
  post: (p, b)   => Vie.api.fetch(p, { method: 'POST', body: JSON.stringify(b) }),
};

Vie.ApiError = class extends Error {
  constructor(errors, status) { super(errors?.[0]?.message || 'API error'); this.errors = errors; this.status = status; }
};
```

## 8.5. Frontend flow

```
[Trang single-hotel]
   │ user nhập date / khách
   ▼
POST /quote (mỗi 300ms debounce)
   │ list room với giá realtime
   ▼
User chọn "Đặt phòng" / "Đặt combo"
   │ ↳ /dat-phong/?room_id=…&type=…&checkin=…
   ▼
[Trang /dat-phong] [vie_checkout]
   │ render form: customer info, breakdown realtime
   ▼
POST /quote lần cuối (lock total)
   │ user submit
   ▼
POST /orders (X-Idempotency-Key = sha256(phone+room+total+checkin))
   │ ↳ trả order + redirect_url (SePay)
   ▼
Redirect SePay → success/cancel callback
   │
   ▼
[Trang /dat-phong-thanh-cong/?code=...]
   │ [vie_order_success]
   ▼
GET /orders/lookup?code=...&phone=... → render đơn
```

## 8.6. Validate ngay trên client

- Số đêm 1–30
- Tuổi 0–17
- Số khách 1–20
- Phone regex VN: `^0[0-9]{9,10}$` (hoặc số quốc tế nếu có dấu `+`)
- Email
- Phải accept "đồng ý điều khoản"

## 8.7. Hiển thị "Yêu cầu báo giá"

Khi `data.requires_quote == true`:

- Ẩn nút "Đặt phòng" / "Đặt combo".
- Hiện CTA "Liên hệ tư vấn" với form mini (tên, sđt, nội dung) → `POST /lead` (nếu có).
- Render `messages[]` warning.

## 8.8. SeO & schema.org

Single-hotel render JSON-LD `Hotel` schema:

```json
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "...",
  "address": "...",
  "starRating": {"@type":"Rating","ratingValue":4},
  "telephone": "..."
}
```

Hệ thống không can thiệp meta của theme cha; chỉ thêm JSON-LD nếu chưa có.

## 8.9. Tracking

`OrderService` fire WP action sau khi tạo:

```php
do_action('vie_order_created', $order, $items);
do_action('vie_order_paid',    $order);
```

Code GA4 / FB Pixel có thể hook vào để track conversion. Giá trị truyền: `total`, `currency`, `transaction_id = order.code`.
