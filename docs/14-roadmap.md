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

- [x] Smoke test [§13.6](13-testing.md#136-smoke-test-e2e-manual).
- [x] Security test [§13.8](13-testing.md#138-security-test).
- [x] Performance test với seed 10k.
- [x] Tài liệu hướng dẫn người dùng (1 trang).
- [x] Deploy staging → UAT → production.

## Phase 13 — Hardening Sprint v2.1.0 (1 sprint, 2026-05)

Audit từ 6 reviewer chuyên biệt phát hiện 8 Critical + ~30 Important. Sprint
này fix toàn bộ trước khi mở scale; thay đổi đã chốt với chủ dự án.

**Sprint A — Containment (data dump leak guard):**
- [x] Xóa `db-full.sql` / `db_old.sql` / `db_current_*.sql` khỏi theme root (~129MB).
- [x] `.gitignore` thêm `*.sql`, `db_*.sql`, `dump_*.sql`, `backup_*.sql`, `vendor/`.
- [x] `app/public/.htaccess` chặn `.sql/.env/.bak/.zip` ở web layer.

**Sprint B — Auth & Authorization:**
- [x] IDOR fix `PUT/DELETE/cancel/transition /orders/{id}` — gọi `canUserViewOrder` trước mọi action.
- [x] `OrderController::destroy` chuyển soft-cancel (qua OrderService::cancel) — không hard-delete.
- [x] `OrderValidation::updateRules` trim — cấm client set `status/paid_amount/total/subtotal/discount/...`.
- [x] Tắt CORS mặc định (`cors_enabled=false`, default đã đúng).
- [x] `JwtService::decode` hard-check `alg=HS256`, `iss=vie`, clock-skew 60s.
- [x] `SecuritySweep` threshold 50/h → 10/15min; cron `daily` → `vie_15min`.
- [x] Helper `Support\ClientIp` cho trusted proxies + `is_ssl` qua `X-Forwarded-Proto`.
- [x] Refresh cookie `SameSite=Lax` → `Strict`.
- [x] `MediaService::upload` validate MIME bằng `finfo_file`, strip EXIF qua `wp_get_image_editor`.
- [x] SPA security headers: `X-Frame-Options: DENY`, CSP, `Referrer-Policy`, `X-Content-Type-Options`.

**Sprint C — Money correctness (9 items):**
- [x] `GuestComposition` quy đổi bé **≥ 12 tuổi** sang adult (per §3.3).
- [x] `ChildPolicy` sort DESC (oldest first per §3.4), bỏ param `$_unused`.
- [x] `loadRoomPrices` / `loadOverrides` bỏ `per_page=100` — repository có `findByDateRange` / `findOverridesByDateRange`.
- [x] `PricingCellsService` check `$wpdb->query === false` → throw, ROLLBACK đúng.
- [x] 3 bulk services (room/surcharge/ticket price) thêm `START TRANSACTION` + chunk 500 cell + check return.
- [x] **Combo bug fix** — `booking_type='combo'` không còn bị flatten về `'night'`.
- [x] `CouponRepository::incrementUsedAtomic` compare-and-swap (`used_count < usage_limit`).
- [x] `CouponUsageSchema` thêm `UNIQUE (coupon_id, order_id)` — chống double-record.
- [x] `OrderService::decrementStock` compare-and-swap `WHERE stock >= needed`.
- [x] Mọi `transition()` / `cancel()` dùng `OrderRepository::updateIfStatus` (CAS).

**Sprint D — Payment & Refund:**
- [x] `SepaySignature::signWebhook` mở rộng scope thêm `paid_at` + `timestamp`.
- [x] `SepayWebhook` fail-closed nếu `secret_key` rỗng; replay-protection ±5 phút; reject `amount<=0`;
  reject thanh toán vào order đã `cancelled` (trả 200, log activity cho manual refund).
- [x] `raw_payload` whitelist field + mask `account_number_tail` (4 chars cuối).
- [x] `InvoiceService::getOrAssignNumber` wrap `LOCK TABLES vie_order WRITE` —
  đảm bảo số hóa đơn liên tục (compliance NĐ 123/2020 + TT 78).
- [x] **Refund convention chốt là số DƯƠNG** (code đang đúng, docs §10 đã sửa khớp).
- [x] Xóa dead code `PaymentLogValidation.php`.

**Sprint E — Email queue + Cron:**
- [x] `OrderEmailService` chuyển async qua `wp_schedule_single_event` (hook `vie_send_order_email`),
  retry 3 lần × 5 phút khi `wp_mail` fail.
- [x] `TemplateRenderer::applyPlaceholders` mặc định `esc_html` placeholder body
  (chống XSS qua tên KH); prefix `_raw_` bypass; subject strip control chars.
- [x] `NoShowSweep` dùng `wp_date('Y-m-d')` (WP timezone) + compare-and-swap UPDATE.

**Sprint F — Public API & Data Layer:**
- [x] `HotelDeleteService` cascade delete — chặn nếu còn order active, TX-safe.
- [x] `single-hotel.php` chuyển raw `$wpdb` sang `HotelRepository::findByPostId` +
  `RoomRepository::all` (tuân thủ §1.6 Repository-only).
- [x] `ReportsController` validate date format + cap range 366 ngày + cap filter arrays 50 entries.
- [x] `Http\RateLimiter` (transient-backed, trust ClientIp helper) áp dụng cho `/quote` (30/phút),
  `/coupons/validate` (20/phút), `/orders/lookup` (10/5phút).
- [x] `OrderCodeGenerator` format mới `VIE{ymd}{NNNN}{XXXX}` (4 ký tự hex random)
  — collision space tăng × 65k chống brute-force enumeration.

**Sprint G — Docs + Testing + Version:**
- [x] Cập nhật 03-pricing.md, 10-payment-sepay.md (refund convention), 14-roadmap.md.
- [x] Bump `VIE_CHILD_VERSION` 2.0.0 → 2.1.0; `style.css` Version 1.0.0 → 2.1.0.

**Decisions locked với chủ dự án:**

| # | Quyết định | Áp dụng tại |
|---|---|---|
| 1 | Bé ≥ 12 tuổi tính như adult | `GuestComposition::ADULT_AGE_THRESHOLD` |
| 2 | Cancel: operator nhập số tiền hoàn tay | giữ `cancel(refund_amount)` |
| 3 | Refund lưu số DƯƠNG, code đảo dấu khi cộng | `PaymentLedger::computePaidAmount` |
| 4 | Invoice number LOCK TABLES khi cấp | `InvoiceService::getOrAssignNumber` |
| 5 | Tắt CORS mặc định; widget tương lai dùng iframe same-origin + postMessage | `cors_enabled=false` |
| 6 | Email async qua `wp_schedule_single_event` | `OrderEmailService::QUEUE_HOOK` |
| 7 | `booking_type='combo'` lưu nguyên (BUG fix) | `OrderService.php:222` |
| 8 | DB dump: xóa local, gitignore, chặn web | Sprint A |

**Tổng ước tính**: ~21 ngày dev (3 tuần làm việc) + 1 sprint hardening Phase 13.

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
