# Ẩn Giá vốn & Lợi nhuận dự kiến khỏi vai trò Sales

**Ngày:** 2026-06-18
**Trạng thái:** Đã duyệt thiết kế — chờ lập kế hoạch thực thi

## 1. Mục tiêu

Trong giao diện và dữ liệu cấp cho nhân viên kinh doanh (role `vie_sales`), **không để lộ**:
- **Giá vốn** (`cost_total`)
- **Lợi nhuận dự kiến** (`profit_total`)

Yêu cầu là ẩn ở **cả UI lẫn API** (không chỉ giấu trên giao diện): một sales user mở DevTools/gọi API trực tiếp cũng **không** đọc được hai trường này, và **không** ghi được chúng.

## 2. Hiện trạng (đã xác minh từ code)

### 2.1 Vai trò & quyền
- Roles đăng ký tại `inc/src/Service/Auth/RoleInstaller.php`.
- `vie_sales` có: `read`, `vie_create_orders`, `vie_view_own_orders`, `vie_cancel_orders`, `vie_use_price_check`, `vie_print_order`.
- `vie_sales` **không** có: `vie_view_reports`, `vie_view_all_orders`, `vie_manage_orders`.
- `vie_hotel_manager` và `administrator` có toàn bộ `ALL_CAPS` (bao gồm `vie_view_reports`).

### 2.2 Nơi cost/profit bị lộ
| Khu vực | Sales truy cập? | Tình trạng |
|---|---|---|
| Đơn hàng — danh sách (cột "Tổng giá vốn", "Lợi nhuận dự kiến", CSV) | Có (`vie_view_own_orders`) | 🔴 Lộ |
| Đơn hàng — chi tiết (thẻ "Giá vốn & Lợi nhuận") | Có | 🔴 Lộ (chỉ nút sửa bị khoá, số liệu vẫn hiện) |
| Báo cáo (RevenueReport, ByHotelReport, CSV) | Không (`vie_view_reports`) | 🟢 Đã chặn sẵn (route + nav + API) |

### 2.3 Rò rỉ ở tầng API (cốt lõi)
`order.cost_total` / `order.profit_total` (`inc/src/Repository/OrderRepository.php` casts ~58-59) và `order_item.cost_total` / `order_item.profit_total` (`inc/src/Repository/OrderItemRepository.php` casts ~47-48) được serialize vào **mọi** response, **không** lọc theo quyền:
- `GET /orders` → `OrderController::index` (`inc/src/Http/Controllers/OrderController.php:43-53`)
- `GET /orders/{id}` → `OrderController::show` → `OrderService::buildDetail` (trả `order['items'][]` lồng cost/profit; `inc/src/Service/Order/OrderService.php:486-506`)
- `POST /orders` → `OrderController::store` (`:76-137`)
- `PUT/PATCH /orders/{id}` → `OrderController::update` (`:139-156`) — trả về order row
- `GET /order-items`, `GET /order-items/{id}` → `OrderItemController::index/show` (route `crudWithCaps('order-items', …, manage='vie_manage_orders', view='vie_view_own_orders')` tại `inc/src/Http/RestRouter.php:158`) → **sales đọc được**

Ghi: `cost_total`/`profit_total` là trường ghi được trong `OrderValidation::updateRules` (`inc/src/Validation/Schemas/OrderValidation.php:67-68`). `PUT /orders/{id}` chỉ yêu cầu `vie_create_orders` → **sales hiện ghi được** cost. (`PUT /order-items` yêu cầu `vie_manage_orders` → sales không với tới. Tạo đơn đặt `cost_total=0`/`profit_total=total` cứng trong `OrderService::create:180-181,241-242` → không phải vector ghi.)

### 2.4 Hệ thống capability ở SPA
- `auth.store.ts:16-17`: `can(cap)` / `canAny(caps)` đọc từ `user.caps` (`string[]`).
- `user.caps` nạp từ response **login/refresh** (`auth.store.ts:47-51`), bootstrap khi load app qua `router.beforeEach → tryRefresh`. → `vie_view_reports` đã sẵn trong caps của admin/manager; **không cần** thay đổi backend để lộ cap ra frontend.
- Component gating: `<Can cap="…">` (`src/components/Can.vue`), hoặc `v-if="auth.can(…)"`.

## 3. Quyết định thiết kế

1. **Phạm vi:** Ẩn ở **UI + chặn API** (đọc và ghi).
2. **Cổng phân quyền:** Tái dùng **`vie_view_reports`** — đúng tập đối tượng cần (admin + hotel_manager thấy; sales ẩn), **không** tạo cap mới, **không** migration.
3. **OrderDetail:** Ẩn **toàn bộ thẻ "Giá vốn & Lợi nhuận"** với user thiếu cap (thẻ này có cả "Doanh thu" nhưng tổng đơn đã hiển thị ở header/chỗ khác).

## 4. Thiết kế

### 4.1 Cổng dùng chung
- Backend: `current_user_can('vie_view_reports')` (mẫu đã dùng tại `AuthMiddleware::requireCap` → `current_user_can`).
- Frontend: `auth.can('vie_view_reports')`.

### 4.2 Backend — chặn ĐỌC
Thêm helper dùng chung `Vie\Support\CostVisibility` (đặt cạnh `ResponseEnvelope` trong `inc/src/Support/`):
- `canView(): bool` → `current_user_can('vie_view_reports')`.
- `stripOrder(array $order): array` → nếu `!canView()`: `unset($order['cost_total'], $order['profit_total'])`; nếu có `$order['items']` thì map `stripItemRow` lên từng phần tử. Trả nguyên trạng nếu `canView()`.
- `stripItemRow(array $row): array` / `stripItemRows(array $rows): array` → bỏ 2 field trên item.
- Hằng tên trường tập trung tại một chỗ (`['cost_total','profit_total']`).

Áp dụng tại **ranh giới response** (không sửa Repository/Service nội bộ để không phá tính toán):

| File:method | Cách áp |
|---|---|
| `OrderController::index` | map `stripOrder` lên `$result['data']` trước khi `ResponseEnvelope::paginated` |
| `OrderController::show` | `stripOrder($detail)` trước `ResponseEnvelope::success` |
| `OrderController::store` | `stripOrder($detail)` trước response 201 (giữ nguyên `redirect_url`) |
| `OrderController::update` | `stripOrder($row)` trước response |
| `OrderItemController::index` | `stripItemRows($result['data'])` |
| `OrderItemController::show` | `stripItemRow($row)` |

### 4.3 Backend — chặn GHI
- `OrderController::update`: sau `Validator::validate(...)`, nếu `!CostVisibility::canView()` → `unset` `cost_total`/`profit_total` khỏi `$v->validated()` trước `$repo->update()`. (Im lặng bỏ qua, không lỗi 403 — để tránh phá UX hiện tại; sales vốn không có UI để gửi 2 field này.)
- Không đổi `/order-items` write (đã gated `vie_manage_orders`).

### 4.4 Frontend — ẩn UI
- `src/views/orders/OrderListView.vue`:
  - Ẩn 2 cột `<Column field="cost_total">` (~151-153) và `<Column field="profit_total">` (~154-156) khi `!auth.can('vie_view_reports')`.
  - CSV (`exportAll`): bỏ 2 header "Tổng giá vốn"/"Lợi nhuận dự kiến" (~90) và 2 cell `o.cost_total`/`o.profit_total` (~78-79) tương ứng khi thiếu cap.
- `src/views/orders/OrderDetailView.vue`:
  - Bọc thẻ "Giá vốn & Lợi nhuận" (~345-383) bằng `v-if="auth.can('vie_view_reports')"` (hoặc `<Can cap="vie_view_reports">`). Khi ẩn thẻ, các control sửa cost/profit bên trong cũng biến mất.

### 4.5 Không thay đổi
Khu Báo cáo và `/reports/*` đã gated đúng bằng `vie_view_reports`; sales đã không truy cập được. Giữ nguyên.

## 5. Kiểm thử (TDD)

### 5.1 Backend (PHP test suite — chạy qua `wp eval 'require run.php'` trong `vie_cli`)
- User **không** có `vie_view_reports`: `GET /orders`, `GET /orders/{id}` (kể cả `items[]`), `POST /orders`, `PUT /orders/{id}`, `GET /order-items` → response **không chứa** `cost_total`/`profit_total`.
- User **có** cap → vẫn chứa đủ 2 field.
- `PUT /orders/{id}` gửi `cost_total` từ user thiếu cap → giá trị **không** được ghi (đọc lại đơn xác nhận không đổi).
- Lưu ý vận hành: reset SecuritySweep trước khi chạy lặp test auth (tránh IP-block 127.0.0.1).

### 5.2 Frontend (vitest)
**Lưu ý:** admin-app hiện **chưa có** hạ tầng test frontend → phải dựng harness vitest (`vitest` + `@vue/test-utils` + `jsdom`, thêm block `test` vào `vite.config.ts`, script `test`). Để dễ test, tách gate vào composable `useCostVisibility()` (`canViewCost = auth.can('vie_view_reports')`) và tách phần build CSV thành helper thuần (`ordersCsv.ts`).
- `useCostVisibility`: trả `canViewCost` đúng theo caps (có/không `vie_view_reports`).
- `ordersCsv` (thuần): `canViewCost=false` → header/row bỏ "Tổng giá vốn"/"Lợi nhuận dự kiến"; độ dài header == độ dài row ở cả 2 chế độ.
- `OrderListView` (mount + stub): caps không có `vie_view_reports` → 2 cột không render; có cap → render.
- `OrderDetailView` (mount + stub, có `flushPromises`): thiếu cap → thẻ "Giá vốn & Lợi nhuận" không render; có cap → render.

### 5.3 Build
- Rebuild dist của `admin-app` và commit (dự án commit dist; deploy bằng `git pull` trên cPanel).

## 6. Ngoài phạm vi (YAGNI)
- Không tạo capability mới.
- Không đổi tầng Báo cáo.
- Không strip `cost_total` trong response báo giá (`quote.api.ts` `PriceBreakdown.cost_total`) vì không hiển thị ở UI; nếu muốn chặt chẽ tuyệt đối có thể bổ sung sau (đánh dấu là cải tiến tuỳ chọn).
- Không đụng tới `order_item` write path (đã an toàn).

## 7. Tiêu chí hoàn thành
- Sales (qua UI và qua gọi API trực tiếp) không thấy và không ghi được `cost_total`/`profit_total` ở mọi endpoint đơn hàng/order-item.
- Admin + hotel_manager không thay đổi trải nghiệm.
- Test backend + frontend xanh; dist đã rebuild & commit.
