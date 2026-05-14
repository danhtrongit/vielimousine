# 13 — Testing

## 13.1. Phương pháp

- MVP **không** dùng PHPUnit (giảm scope). Test bằng:
    - Trang admin SPA → tool "Price Check" + bảng case kiểm tra thủ công.
    - Script CLI nhỏ `wp eval-file inc/tests/run.php` chạy assertion (xem 13.5).
    - Postman / Bruno collection cho REST.
- Phase sau: PHPUnit cho Pricing engine + Cancellation calculator (logic thuần, dễ test).

## 13.2. Bảng test pricing — match Excel sheet "Cách tính giá chi tiết"

Hotel `Mercure VT`, `ticket_free_children_max_age=5`, `ticket_free_children_count=1`. Phòng `Premier 2 người chuẩn`: `included_adults=2, max_adults=3, max_children=2, free_children_count=1`.

| # | Input | Expected `num_rooms` | Expected `seat_count` | Expected `billable` | Expected ghi chú |
|---|---|---|---|---|---|
| 1 | 2 NL + 1 bé 5T, Room | 1 | n/a | n/a | Miễn 1 bé |
| 2 | 2 NL + 2 bé (5T,4T), Room (free=1) | 1 | n/a | n/a | Phụ thu 1 bé |
| 3 | 2 NL + 2 bé (5T,4T), Room (free=2 family room) | 1 | n/a | n/a | Miễn 2 bé |
| 4 | 2 NL + 1 bé 7T, Room | 1 (bé 7T = NL) | n/a | n/a | Phụ thu buffet bé 7T |
| 5 | 3 NL + 2 bé (5T,9T), Premier 2 chuẩn | 2 (bé 9T thành NL → eff=4) | n/a | n/a | 2 phòng |
| 6 | 2 NL + 1 bé 5T, Combo | 1 | 3 | 2 | Miễn 1 vé |
| 7 | 2 NL + 1 bé 7T, Combo | 1 (bé 7T = NL) | 3 | 3 | Tính 3 vé |
| 8 | 2 NL + 2 bé (5T,4T), Combo | 1 | 4 | 3 | Miễn 1 vé |
| 9 | 2 NL + 2 bé (5T,7T), Combo | 1 (eff=3) | 4 | 3 | bé 7T=NL, bé 5T miễn vé |
| 10 | 2 NL + 2 bé (7T,8T), Combo | 2 (eff=4) | 4 | 4 | Cả 2 bé tính NL |
| 11 | 4 NL + 3 bé (5T,4T,9T), Combo | 2 (eff=5) | 7 | 6 | Miễn 1 bé, bé 9T=NL |

## 13.3. Bảng test cancellation

Policy default: 100% nếu ≥ 72h, 50% nếu ≥ 24h, 0% nếu < 24h.

| # | Tình huống | now | checkin | Expected refund % |
|---|---|---|---|---|
| 1 | Hủy trước 5 ngày | 2026-06-10 | 2026-06-15 | 100% |
| 2 | Hủy đúng 72h | 2026-06-12 14:00 | 2026-06-15 14:00 | 100% |
| 3 | Hủy 48h trước | 2026-06-13 | 2026-06-15 | 50% |
| 4 | Hủy 12h trước | 2026-06-14 14:00 | 2026-06-15 02:00 | 0% |
| 5 | No-show | 2026-06-15 23:00 | 2026-06-15 14:00 | 0% |

## 13.4. Bảng test thanh toán (sổ cái)

| # | Action | Expected `paid_amount` | Expected `payment_status` |
|---|---|---|---|
| 1 | order total = 4.000.000 | 0 | pending |
| 2 | + deposit 2.000.000 | 2.000.000 | partial |
| 3 | + payment 2.000.000 | 4.000.000 | paid + auto confirmed |
| 4 | + refund -500.000 | 3.500.000 | partial |
| 5 | + void -500.000 (void refund?) | 4.000.000 | paid |
| 6 | order total = 4.000.000, nhập sai deposit 4.000.000 → void -4.000.000 → deposit 400.000 | 400.000 | partial |
| 7 | IPN duplicate (same gateway+transaction_id) | giữ nguyên, log activity bỏ qua | giữ nguyên |

## 13.5. CLI runner đơn giản

```php
// inc/tests/run.php
declare(strict_types=1);
require_once dirname(__DIR__,2) . '/wp-load.php';

$cases = require __DIR__ . '/cases/pricing.cases.php';
$pass = $fail = 0;

foreach ($cases as $name => $case) {
    $request = new \Vie\Pricing\QuoteRequest(...$case['input']);
    $calc    = new \Vie\Pricing\PriceCalculator(...);
    $br      = $calc->calculate($request);

    foreach ($case['expect'] as $key => $expected) {
        $actual = $br->{$key};
        if ($actual !== $expected) {
            echo "FAIL: $name [$key] expected $expected, got $actual\n";
            $fail++;
        } else {
            $pass++;
        }
    }
}
echo "Passed: $pass, Failed: $fail\n";
```

Chạy: `wp eval-file inc/tests/run.php`.

## 13.6. Smoke test E2E (manual)

Checklist deploy lên staging:

- [ ] Đăng nhập SPA admin với role `administrator` thành công, refresh token hoạt động.
- [ ] Tạo hotel mới qua SPA, sync `post_id` với CPT WP.
- [ ] Tạo room + bulk update price 30 ngày bằng weekday rule.
- [ ] Search hotel ở single-hotel page → list room hiện giá realtime.
- [ ] Đặt phòng Room Only → tạo order, gửi email khách + admin (đủ field `Số chỗ ngồi`).
- [ ] Đặt combo → ticket_count đúng theo bảng test.
- [ ] Submit order, redirect SePay (sandbox), thanh toán → IPN ghi ledger, order `paid` + `confirmed`.
- [ ] Admin huỷ 1 item: refund tính đúng theo policy hotel; ledger có dòng `refund`.
- [ ] Admin tạo đơn từ SPA (sales role): chỉ thấy đơn của mình.
- [ ] Hotel manager thấy chỉ hotel được gán.
- [ ] Báo cáo Doanh thu / Theo hotel / Theo nguồn / Theo sales hiển thị đúng số.
- [ ] Export CSV mở được trong Excel, dấu tiếng Việt đúng.
- [ ] Activity log ghi đủ các thao tác.
- [ ] `GET /health` xanh, schema versions đầy đủ.

## 13.7. Performance test

- Seed 10k orders + 30k items + 50k payment logs (script seeder).
- Đo p95 cho:
    - `GET /orders?page=1&per_page=50` (đa filter) → < 200ms
    - `GET /reports/revenue?granularity=day` (30 ngày) → < 300ms
    - `POST /quote` → < 100ms
- Profile MySQL slow query log; thêm index nếu cần.

## 13.8. Security test

- [ ] Login brute force 10 lần fail → bị block 15 phút.
- [ ] Refresh token reuse → kill family, user phải đăng nhập lại.
- [ ] Public endpoints không lộ `internal_note`, `cost_total`, `profit_total`.
- [ ] `Sales` role không thấy đơn người khác qua `GET /orders/{id}`.
- [ ] SQL injection: thử filter `?status=' OR 1=1 --` → 422.
- [ ] XSS: nhập `<script>` vào customer_note → render frontend escape đầy đủ.
- [ ] CORS: domain lạ không được phép → preflight reject.
