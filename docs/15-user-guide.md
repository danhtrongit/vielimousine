# 15 — Hướng dẫn sử dụng nhanh

## Cài đặt

1. Vào WP Admin → **Appearance → Themes** → activate **Vielimousine Child**.
2. Mở `http://your-site/wp-json/vie/v1/health` để kiểm tra envelope chuẩn.
3. Build SPA admin:
   ```bash
   cd wp-content/themes/vielimousine-child/admin-app
   npm install
   npm run build
   ```
4. Mở `http://your-site/vie-admin/` → đăng nhập bằng tài khoản WP có role `administrator`.

## Phân quyền

| Role | Mục đích |
|---|---|
| `administrator` | Quản trị toàn bộ |
| `hotel_manager` | Chỉ quản lý KS được gán (`user_meta.vie_managed_hotels`) |
| `sales` | Chỉ tạo / xem đơn của mình |
| `accountant` | Xem báo cáo, ghi nhận thanh toán |

## Tạo hotel mới

- Cách 1: tạo WP post type `hotel` → tự động sync sang `vie_hotel`.
- Cách 2: gọi `POST /hotels` từ SPA → tự tạo post WP.

## Thanh toán

- **SePay**: cấu hình `merchant_id` + `secret_key` trong Settings → SePay. Đặt webhook URL: `https://your-site/wp-json/vie/v1/payments/sepay/webhook`.
- **Thu tay**: vào Order detail → tab Thanh toán → "Ghi nhận TT".
- **Hoàn / Void**: cùng UI; sổ cái append-only, không sửa được.

## Chính sách hoàn huỷ

Đặt **theo từng KS** trong SPA → Khách sạn → ⚙️ Chính sách. Khi huỷ đơn, hệ thống dùng policy của KS để tính refund cho từng item.

## Quy tắc miễn phí (mới)

- **Phòng**: `room.free_children_count` × `num_rooms` bé < 6 được miễn.
- **Vé xe**: tối đa `hotel.ticket_free_children_count` bé < 6 / **booking**. Bé ≥ 6 → tính 1 vé như NL. Số chỗ ngồi (`ticket_count`) luôn bao gồm bé miễn vé.

## Endpoint chính

- `POST /quote` — báo giá realtime
- `POST /orders` — tạo đơn (idempotent via `X-Idempotency-Key`)
- `GET /orders` — list có pagination + filter + sort
- `POST /orders/{id}/cancel` — huỷ + tự refund
- `POST /payments/sepay/webhook` — IPN
- `GET /reports/revenue`, `/by-hotel`, `/by-source`, `/by-sales`, `/received-cash`, `/receivable`
- `GET /health` — diagnostic

## Verify

```bash
# Pure-logic test
php wp-content/themes/vielimousine-child/inc/tests/run.php

# Health
curl http://your-site/wp-json/vie/v1/health
```
