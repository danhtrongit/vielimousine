# 14 — Roadmap

Phân chia theo phase. Mỗi phase có deliverable cụ thể, có thể QA độc lập.

## Phase 0 — Skeleton (0.5 ngày)

- [ ] `functions.php`, `style.css`, hằng số, autoloader.
- [ ] `Plugin::boot()` hook `rest_api_init`.
- [ ] Đăng ký `GET /health` → trả version.
- [ ] Cấu hình `admin-app/` với Vite, Vue 3, PrimeVue boilerplate.

**Done khi**: gọi `GET /wp-json/vie/v1/health` trả 200; mở `/vie-admin/` thấy app trắng + console không lỗi.

## Phase 1 — Schema (1 ngày)

- [ ] 15 file `*Schema` + `SchemaManager`.
- [ ] Migration chạy được trên cài mới + đổi version.
- [ ] CLI seeder mẫu (hotel + room + price 30 ngày).

**Done khi**: `wp eval-file inc/seed/run.php` chạy xong, các bảng có data; option `vie_schema_versions` đúng.

## Phase 2 — Repositories + Validation (1.5 ngày)

- [ ] CRUD đầy đủ cho 15 bảng.
- [ ] `Validation\Schemas\*` cho mọi POST/PUT.
- [ ] `ResponseEnvelope` helper.

**Done khi**: viết REST `GET /hotels` + `POST /hotels` test bằng Bruno đầy đủ envelope + 422 đẹp.

## Phase 3 — Pricing engine (2 ngày)

- [ ] `GuestComposition`, `RoomAllocation`, `ChildPolicy`, `TicketCalculator`, `SurchargeCalculator`, `PriceCalculator`.
- [ ] DTO `QuoteRequest`, `PriceBreakdown`.
- [ ] REST `POST /quote`.
- [ ] Cases trong [13-testing.md §13.2](13-testing.md#132-bảng-test-pricing--match-excel-sheet-cách-tính-giá-chi-tiết) pass.

**Done khi**: chạy `wp eval-file inc/tests/run.php` xanh hết.

## Phase 4 — Order + Customer + Coupon (2 ngày)

- [ ] `OrderCodeGenerator`, `OrderService::create/update/cancel`, `OrderStateMachine`, `OrderDescription`.
- [ ] CouponService + validate.
- [ ] Customer upsert by phone.
- [ ] REST `POST /orders`, `GET /orders`, `GET /orders/{id}`, `POST /orders/{id}/cancel`, `POST /coupons/validate`, `*/customers`.
- [ ] Cancellation calculator theo hotel policy.

**Done khi**: end-to-end qua Bruno: tạo coupon → quote → tạo order với coupon → hủy → ledger refund đúng %.

## Phase 5 — Payment + SePay (1.5 ngày)

- [ ] `PaymentLedger`, `PaymentService`, `SepayClient`, `SepayCheckout`, `SepayWebhook`.
- [ ] Auto-confirm khi `paid_amount >= total`.
- [ ] Activity log mọi entry.

**Done khi**: chạy IPN giả lập trên sandbox; manual entry + refund + void hoạt động đúng.

## Phase 6 — Auth + Roles (1.5 ngày)

- [ ] `JwtService` (self-impl HS256).
- [ ] `TokenRepository` rotate + reuse detection.
- [ ] `LoginController`, `RefreshController`, `LogoutController`, `MeController`.
- [ ] `RoleInstaller` apply caps; hotel_managed meta.
- [ ] CORS handler.
- [ ] `AuthMiddleware` cho REST routes; map permission_callback đầy đủ.

**Done khi**: SPA login với user `sales` → chỉ gọi `GET /orders` thấy đơn của mình; reuse refresh token → bị kick.

## Phase 7 — Admin SPA core (3 ngày)

- [ ] Layout, sidebar, breadcrumb, theme PrimeVue preset.
- [ ] Login view + auth store + interceptor.
- [ ] `DataTablePanel`, `FilterBar`, `SortHeader` (auto từ meta).
- [ ] Lookup store + composables.
- [ ] Trang Dashboard (3 card + 1 chart).
- [ ] Trang **Orders list + detail + create**.
- [ ] Trang **Customers list + detail**.
- [ ] Trang **Hotels list + detail + policy**.

**Done khi**: full flow tạo đơn + xem chi tiết + hủy item hoạt động từ SPA.

## Phase 8 — Admin SPA inventory + pricing (2.5 ngày)

- [ ] Trang **Rooms list + detail**.
- [ ] Trang **Price Matrix** (grid lớn, inline edit).
- [ ] Trang **Bulk Update** (3 bước wizard).
- [ ] Trang **Surcharge Matrix**, **Ticket Price Matrix**.
- [ ] Trang **Product Codes**.

**Done khi**: bulk update giá 30 ngày × 5 phòng trong vòng < 2s.

## Phase 9 — Admin SPA coupons + payments + reports (2 ngày)

- [ ] **Coupons** list + form + usage view.
- [ ] **Payments** global ledger.
- [ ] 6 trang **Reports** + chart + export CSV.

**Done khi**: số tổng doanh thu/lợi nhuận trên Dashboard khớp với Report Revenue 30 ngày.

## Phase 10 — Frontend public (2 ngày)

- [ ] 4 shortcode + 4 template + CSS.
- [ ] `single-hotel.php` render policy.
- [ ] Vie.api JS helper.
- [ ] Flow quote → checkout → SePay → success.

**Done khi**: khách đặt online thành công, nhận email đầy đủ.

## Phase 11 — Email + Activity + Settings (1 ngày)

- [ ] 8 template email (subject + body).
- [ ] Settings page SPA: general / email / sepay.
- [ ] Activity log view.
- [ ] Cron daily: `vie_security_sweep`, `vie_no_show_sweep`, `vie_token_cleanup`.

**Done khi**: admin gửi test mail thành công; cron đánh dấu `no_show` cho đơn quá hạn.

## Phase 12 — Hardening + QA + Deploy (1.5 ngày)

- [ ] Smoke test [§13.6](13-testing.md#136-smoke-test-e2e-manual).
- [ ] Security test [§13.8](13-testing.md#138-security-test).
- [ ] Performance test với seed 10k.
- [ ] Tài liệu hướng dẫn người dùng (1 trang).
- [ ] Deploy staging → UAT → production.

**Tổng ước tính**: ~21 ngày dev (3 tuần làm việc).

## Mốc kiểm tra

| Mốc | Cuối phase | Cần demo |
|---|---|---|
| M1 — API core | 4 | Tạo đơn end-to-end qua REST, hủy + refund |
| M2 — SPA MVP | 7 | Admin SPA quản lý đơn full flow |
| M3 — Inventory & report | 9 | Bulk price + báo cáo + lợi nhuận |
| M4 — Go-live | 12 | Khách đặt online, email + SePay hoạt động |

## Risk

| Risk | Mitigation |
|---|---|
| PrimeVue v4 API change | Pin version, đọc changelog trước khi nâng |
| SePay IPN trễ / mất | Cron retry, manual "Re-check" button trong order detail |
| Edge case pricing (5+ NL, 3+ bé) | Cover trong [13-testing.md §13.2](13-testing.md#132-bảng-test-pricing--match-excel-sheet-cách-tính-giá-chi-tiết) |
| MySQL cũ không hỗ trợ `JSON` native | Đã dùng `LONGTEXT`, parse ở app |
| CORS / cookie cross-domain | Mặc định same-domain, fallback sub-domain với cấu hình `vie_cors_origins` |
| Conflict với plugin SEO / cache | SPA tách URL `/vie-admin/*`, exclude khỏi cache; REST có `nocache_headers()` |
