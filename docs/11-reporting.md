# 11 — Reporting

## 11.1. Nguyên tắc

- Báo cáo derive từ ledger + orders, **không** cache vào bảng riêng (MVP).
- Tất cả query có index hỗ trợ (`KEY(created_at)`, `KEY(source)`, `KEY(sales_user_id)`, `KEY(hotel_id)`).
- Nếu cần tăng tốc → bổ sung bảng materialized view `vie_report_daily` (phase sau).

## 11.2. Endpoint chung

`GET /reports/{type}?date_from&date_to&granularity=day|month|year&group_by=...&format=json|csv|xlsx`.

Defaults:

- `date_from = today - 30d`
- `date_to = today`
- `granularity = day`
- `format = json`

## 11.3. Doanh thu — `GET /reports/revenue`

Query:

```sql
SELECT
    DATE_FORMAT(o.created_at, %granularity_fmt%) AS bucket,
    COUNT(*) AS orders,
    SUM(o.total) AS revenue,
    SUM(o.paid_amount) AS paid,
    SUM(o.total - o.paid_amount) AS outstanding,
    SUM(o.cost_total) AS cost,
    SUM(o.profit_total) AS profit
FROM {prefix}vie_order o
WHERE o.status NOT IN ('cancelled')
  AND o.created_at >= %date_from% AND o.created_at < %date_to_plus_1d%
GROUP BY bucket
ORDER BY bucket ASC;
```

`granularity_fmt`:

- `day` → `%Y-%m-%d`
- `month` → `%Y-%m`
- `year` → `%Y`

Response: xem [05-rest-api.md §5.14](05-rest-api.md#514-reports).

## 11.4. Theo khách sạn — `GET /reports/by-hotel`

```sql
SELECT
    i.hotel_id, h.name,
    COUNT(DISTINCT i.order_id) AS orders,
    SUM(i.line_total)  AS revenue,
    SUM(i.cost_total)  AS cost,
    SUM(i.profit_total) AS profit
FROM {prefix}vie_order_item i
JOIN {prefix}vie_order o ON o.id = i.order_id
JOIN {prefix}vie_hotel h ON h.id = i.hotel_id
WHERE o.status NOT IN ('cancelled')
  AND i.status = 'active'
  AND o.created_at >= ? AND o.created_at < ?
GROUP BY i.hotel_id
ORDER BY revenue DESC;
```

Có thể thêm `?segment_by=city` để group theo `vie_hotel.city`.

## 11.5. Theo nguồn — `GET /reports/by-source`

```sql
SELECT
    o.source,
    COUNT(*) AS orders,
    SUM(o.total) AS revenue,
    SUM(o.cost_total) AS cost,
    SUM(o.profit_total) AS profit,
    AVG(o.total) AS aov
FROM {prefix}vie_order o
WHERE o.status NOT IN ('cancelled')
  AND o.created_at >= ? AND o.created_at < ?
GROUP BY o.source
ORDER BY revenue DESC;
```

## 11.6. Theo sales — `GET /reports/by-sales`

```sql
SELECT
    o.sales_user_id, u.display_name,
    COUNT(*) AS orders,
    SUM(o.total) AS revenue,
    SUM(o.cost_total) AS cost,
    SUM(o.profit_total) AS profit
FROM {prefix}vie_order o
LEFT JOIN {prefix}users u ON u.ID = o.sales_user_id
WHERE o.status NOT IN ('cancelled')
  AND o.sales_user_id IS NOT NULL
  AND o.created_at >= ? AND o.created_at < ?
GROUP BY o.sales_user_id
ORDER BY revenue DESC;
```

## 11.7. Thực thu — `GET /reports/received-cash`

Group theo `paid_at` (ngày thực nhận tiền), không phải `created_at`:

```sql
SELECT
    DATE(p.paid_at) AS bucket,
    p.method,
    COUNT(*) AS txns,
    SUM(p.amount) AS amount
FROM {prefix}vie_payment_log p
WHERE p.amount > 0
  AND p.paid_at >= ? AND p.paid_at < ?
GROUP BY bucket, p.method
ORDER BY bucket ASC, p.method;
```

Filter: `?method=cash,bank_transfer,sepay`.

## 11.8. Công nợ — `GET /reports/receivable`

Đơn `paid_amount < total` và `status NOT IN ('cancelled','no_show')`:

```sql
SELECT
    o.id, o.code, o.customer_phone,
    c.name AS customer_name,
    o.checkin, o.total, o.paid_amount,
    (o.total - o.paid_amount) AS remaining,
    o.sales_user_id, o.partner_payment_status
FROM {prefix}vie_order o
JOIN {prefix}vie_customer c ON c.id = o.customer_id
WHERE o.paid_amount < o.total
  AND o.status NOT IN ('cancelled','no_show')
ORDER BY o.checkin ASC;
```

Filter: `?overdue=1` → chỉ lấy `checkin < today`. `?sales_user_id`, `?hotel_id` (qua items).

## 11.9. Detail theo đơn — `GET /reports/by-order`

Cơ bản là `/orders` list nhưng với cột derived sẵn (paid, remaining, partner_payment_status, supplier_booking_code, voucher_code…) khớp với workbook input. Hỗ trợ export trực tiếp định dạng workbook.

## 11.10. Export

`?format=csv` hoặc `?format=xlsx`. Response stream:

- CSV: `Content-Type: text/csv; charset=UTF-8`, BOM cho Excel VN.
- XLSX: nếu `phpoffice/phpspreadsheet` đã cài (Composer optional) → dùng; nếu không → fallback CSV.

Filename: `vie-revenue-2026-05-01_2026-05-31.csv`.

## 11.11. UI

Xem [06-admin-spa.md §6.15](06-admin-spa.md#615-reports-views).

## 11.12. Đơn vị mô tả workbook

Mapping cột workbook ↔ field xem [04-business-rules.md §4.13](04-business-rules.md#413-workbook-field-mapping).

## 11.13. Performance budget

- Mỗi report query phải xong < 500ms với 100k orders + 200k items + 500k payment logs.
- Index check:
    - `vie_order(status, created_at)`
    - `vie_order(sales_user_id)`
    - `vie_order(source)`
    - `vie_order_item(order_id)`
    - `vie_order_item(hotel_id)`
    - `vie_payment_log(paid_at)`
    - `vie_payment_log(order_id)`

Nếu vượt → cân nhắc bảng materialized `vie_report_daily` build qua cron đêm.
