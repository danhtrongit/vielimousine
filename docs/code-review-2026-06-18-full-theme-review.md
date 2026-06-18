# Code Review toàn diện — `vielimousine` + `vielimousine-child`

**Ngày:** 2026-06-18
**Phạm vi:** Toàn bộ 2 theme (parent `vielimousine` ~137 file PHP; child `vielimousine-child` ~218 file PHP + 2 SPA Vue 3/TS).
**Phương pháp:** 14 reviewer chạy song song theo từng subsystem (DI/Container, Auth/JWT, REST authz, SQL/data, Payment/webhook, Pricing, Order, Backup/Restore, Validation, Invoice/Media, Coupon/Settings/Email, Bootstrap/Cron, admin-app, public-app, parent-theme). Mỗi finding mức critical/high/medium được **một agent độc lập đọc lại đúng `file:line` để xác nhận** (chống false-positive).

> **Lưu ý phương pháp:** 5 dimension (Payment, Pricing, Bootstrap/Cron, Coupon/Settings, admin-app) chạy trên Opus 4.8; 9 dimension còn lại bị bộ lọc cybersecurity của Opus chặn nên được chạy lại trên **Sonnet 4.6**. Tất cả finding bên dưới đều đã được verify bằng cách đọc lại code thật.

## Tổng kết số liệu

| Mức | Số lượng | Đã xác minh |
|-----|----------|-------------|
| 🔴 Critical | 2 | ✅ (verifier confidence: high) |
| 🟠 High | 14 (≈12 sau khi gộp trùng) | ✅ |
| 🟡 Medium | 32 | ✅ |
| ⚪ Low | 57 | một phần |
| ℹ️ Info | 11 | — |
| ❌ Bị bác bỏ | 2 | (xem cuối) |

**Đánh giá tổng thể:** Kiến trúc child theme **rất tốt** — DI container, Repository/Service layering, prepared statements xuyên suốt, state machine + CAS cho order, idempotency + HMAC constant-time cho webhook, refresh-token rotation + reuse detection, stock decrement bằng `SELECT ... FOR UPDATE`. Các vấn đề nghiêm trọng tập trung ở: **(1) lộ dữ liệu** (backup SQL công khai, cost_total trong quote public, AES key commit vào git), và **(2) thiếu kiểm tra quyền sở hữu** ở vài controller (Invoice, OrderItem) cho role `vie_sales`. Parent theme yếu hơn về output escaping và xử lý khóa mã hóa.

---

## 🔴 CRITICAL

### C1. File backup SQL được ghi vào thư mục uploads công khai (tải được qua HTTP, không cần auth)
- **File:** `vielimousine-child/inc/src/Http/Controllers/BackupController.php:55-68` + `Service/Backup/BackupService.php`
- **Vấn đề:** Mỗi lần `POST /backup/restore`, hệ thống ghi auto-snapshot toàn bộ các bảng `vie_*` vào `wp-content/uploads/vie-backups/` bằng `wp_mkdir_p()`, **không** tạo `.htaccess`/`index.php`. Tên file theo mẫu `auto-YYYYMMDD-HHmmss-XXXXXX.sql` (timestamp đoán được, hậu tố 6 ký tự từ `uniqid()`). Đường dẫn tương đối còn được trả về trong response `snapshot_file`. ⇒ Bất kỳ ai biết/đoán được URL đều tải được full dump booking + khách hàng + tài chính, **bỏ qua toàn bộ xác thực**. Verifier xác nhận thư mục tồn tại với file SQL thật trên đĩa.
- **Khuyến nghị:** Ghi `.htaccess` (`Require all denied`) + `index.php` rỗng ngay khi tạo thư mục; tốt hơn là đặt snapshot **ngoài web root** (vd `WP_CONTENT_DIR/../private-backups/`). Dùng `bin2hex(random_bytes(16))` cho tên file thay vì `uniqid()`.

### C2. Khóa AES bị hard-code và commit vào git (còn nhân đôi làm fallback trong trait)
- **File:** `vielimousine/inc/encryption-key.php:5-6` và `vielimousine/inc/classes/Utilities/Traits/Encryption.php:29`
- **Vấn đề:** Secret key AES-128-CBC (`'d24eebeca3db6407c18d4de572fff114'`) nằm plaintext trong theme và đã commit vào git; trait `Encryption` còn chứa **đúng key đó** làm fallback `??`. Ai có quyền đọc repo đều giải mã được mọi ciphertext. Key tĩnh ⇒ xoay vòng key đòi hỏi re-encrypt toàn bộ dữ liệu.
- **Khuyến nghị:** Chuyển key sang hằng số trong `wp-config.php` (ngoài version control); **xóa fallback hard-code** trong trait để thiếu key thì ném exception thay vì âm thầm dùng key đã lộ.

---

## 🟠 HIGH

> Đã gộp các mục trùng nhau giữa các reviewer. "Snapshot tải được qua HTTP" (high) đã được nâng lên Critical C1.

### H1. Ledger ghi `paid_amount`/`status` không lock row, không CAS → lost-update khi có thanh toán đồng thời
- **File:** `vielimousine-child/inc/src/Service/Payment/PaymentLedger.php:45-98, 163-176`
- **Vấn đề:** `recordInTx` đọc order bằng `find()` (SELECT thường, **không** `FOR UPDATE`), re-sum ledger rồi `wpdb->update` order theo id. Dưới REPEATABLE READ, 2 webhook đồng thời cho cùng order chèn 2 payment-row khác nhau (UNIQUE(gateway,transaction_id) **không** trùng), mỗi cái tính SUM chưa thấy row của cái kia rồi ghi đè `paid_amount` ⇒ commit sau xóa kết quả commit trước. Codebase đã có sẵn `OrderRepository::updateIfStatus` (CAS) nhưng ledger không dùng.
- **Khuyến nghị:** `SELECT ... FOR UPDATE` order trong transaction trước khi tính lại, hoặc cập nhật `paid_amount` bằng biểu thức SQL atomic; auto-confirm dùng lại `updateIfStatus`.

### H2. Coupon `sales_only` không bao giờ được enforce → checkout public dùng được coupon nội bộ
- **File:** `vielimousine-child/inc/src/Service/Coupon/CouponService.php:22-104`
- **Vấn đề:** Schema/repo có cờ `sales_only` nhưng `validate()` **không hề đọc** nó. `/coupons/validate` và `/public/orders` đều public ⇒ khách ẩn danh áp được coupon dành riêng kênh sales. `grep sales_only` chỉ thấy ở schema/repo/validation, **0 call site enforce**.
- **Khuyến nghị:** Truyền channel/source vào `validate()`, reject khi `sales_only=true` mà request không phải sales user đã auth. Thêm regression test cho coupon sales_only qua `/public/orders`.

### H3. Hai luồng refresh token không đồng bộ → kích hoạt reuse-detection, logout toàn bộ session
- **File:** `vielimousine-child/admin-app/src/stores/auth.store.ts:38-46` + `src/api/client.ts:29-33`
- **Vấn đề:** Có 2 đường gọi `/auth/refresh`. Interceptor axios khóa bằng singleton `refreshing` (client.ts), nhưng router guard gọi `auth.tryRefresh()` → `refresh()` **bỏ qua** khóa đó. Server (`rotateRefresh`) rotate + reuse-detection: lần 2 mang token đã thu hồi bị coi là tấn công → `revokeFamily()` hủy mọi session + log `token_reuse_detected`. Mở 2 tab admin cùng lúc là đủ trigger.
- **Khuyến nghị:** Dồn mọi refresh qua **một in-flight promise dùng chung** trong auth store; phối hợp đa tab qua `BroadcastChannel`/Web Lock.

### H4. `InvoiceController` không kiểm tra quyền sở hữu order — sales in được hóa đơn của bất kỳ order nào
- **File:** `vielimousine-child/inc/src/Http/Controllers/InvoiceController.php:22-95` (data), `101-162` (pdf)
- **Vấn đề:** 2 route gated bằng `vie_print_order` (cả `vie_sales` có). `data()`/`pdf()` **không** gọi `canUserViewOrder()`. Sales truyền order ID bất kỳ → nhận full invoice (PII khách, tài chính, invoice_number) hoặc stream PDF. Trong khi `GET /orders/{id}` lại enforce đúng qua `ensureCanAccess()`.
- **Khuyến nghị:** Gọi `$repo->canUserViewOrder(get_current_user_id(), $order)` trong `data()` và `pdf()`, trả 403 nếu fail. Helper đã có sẵn.

### H5. IDOR trên `GET /order-items` và `GET /order-items/{id}` — sales đọc được line-item mọi order
- **File:** `vielimousine-child/inc/src/Http/Controllers/OrderItemController.php:15-37`
- **Vấn đề:** Route gated `vie_view_own_orders` (sales có). `OrderItemRepository` **không** override `applyUserScope` (chỉ `OrderRepository` có) ⇒ sales đọc được `partner_name`, `supplier_booking_code`, `pricing_snapshot`, `cost_total`, `profit_total` của mọi booking (trước khi CostVisibility strip). Write path (PUT/DELETE) gated `vie_manage_orders` nên IDOR này chỉ read-only. *(2 reviewer độc lập cùng phát hiện.)*
- **Khuyến nghị:** Thêm `applyUserScope` cho `OrderItemRepository` (JOIN `vie_order`, lọc `sales_user_id`), hoặc kiểm tra parent order qua `canUserViewOrder` trong controller.

### H6. `OrderItemController` POST/DELETE bypass `OrderService` — sai tồn kho & tổng tiền
- **File:** `vielimousine-child/inc/src/Http/Controllers/OrderItemController.php:39-87`
- **Vấn đề:** POST/DELETE đi thẳng repository, bỏ qua `OrderService`: (1) tạo item **không** trừ stock → double-book; (2) xóa item **không** hoàn stock → tồn kho sai vĩnh viễn; (3) **không** tính lại subtotal/total/discount của order; (4) validation không chặn status nên thêm/xóa được item trên order đã cancel/complete.
- **Khuyến nghị:** Bỏ action `store`/`destroy` công khai, hoặc đưa vào method trong `OrderService` xử lý stock + recompute tổng tiền trong transaction và chỉ cho sửa khi order `pending`.

### H7. `restore()` thực thi câu SQL không có tham chiếu bảng mà không chặn (SELECT INTO OUTFILE / SET GLOBAL / GRANT)
- **File:** `vielimousine-child/inc/src/Service/Backup/BackupService.php:114-136`
- **Vấn đề:** Allowlist hoạt động bằng cách quét tên bảng sau verb DML/DDL rồi kiểm tra prefix `vie_*`. Câu SQL **không có** identifier bảng (`SELECT 1 INTO OUTFILE '/var/www/html/shell.php'`, `SET GLOBAL ...`, `GRANT ALL ... TO 'evil'@'%'`) ⇒ `$tables` rỗng, `$bad` rỗng, guard không kích hoạt, câu lệnh chạy tự do. Đây là **blocklist** chứ không phải allowlist statement-form.
- **Khuyến nghị:** Thêm allowlist **loại câu lệnh** trước khi quét bảng — chỉ chấp nhận `DROP TABLE IF EXISTS`, `CREATE TABLE`, `INSERT INTO`, `SET NAMES`, `SET FOREIGN_KEY_CHECKS`, `TRUNCATE TABLE` trên bảng `vie_*`; reject mọi verb khác.

### H8. `/quote` public lộ `cost_total` (giá vốn nội bộ) trong mọi response
- **File:** `vielimousine-child/inc/src/DTO/PriceBreakdown.php:59`
- **Vấn đề:** `toArray()` luôn kèm `cost_total`, và `QuoteController` trả thẳng `$breakdown->toArray()` cho endpoint `/quote` public. Mọi khách xem giá đều nhận giá vốn phòng (TS type cũng model `Quote.cost_total`). Lộ dữ liệu giá vốn cho đối thủ/khách.
- **Khuyến nghị:** Tạo `publicToArray()` bỏ `cost_total`, chỉ giữ field này trong `pricing_snapshot` lưu DB lúc tạo order.

### H9. Key AES dẫn xuất từ 16 ký tự đầu của chuỗi hex SHA-256 (chỉ 64-bit entropy, không MAC)
- **File:** `vielimousine/inc/classes/Utilities/Traits/Encryption.php:51`
- **Vấn đề:** `substr(hash('sha256', $secret), 0, 16)` — `hash()` trả hex (mỗi ký tự 1 trong 16 giá trị) ⇒ 16 ký tự = **64-bit** entropy, không phải 128-bit. Ngoài ra ciphertext không có MAC nên không phát hiện được tampering.
- **Khuyến nghị:** `hash('sha256', $secret, true)` (raw 32 byte) cho AES-256-CBC + HMAC trên IV+ciphertext, hoặc chuyển sang `sodium_crypto_secretbox` (AEAD sẵn).

### H10. Bỏ qua CSRF check khi field token vắng mặt (lost-password)
- **File:** `vielimousine/inc/classes/Themes/Optimizer.php:231-243`
- **Vấn đề:** `verify_csrf_token_on_lostpassword_post()` chỉ verify nonce **khi** `$_POST['lostpassword_csrf_token']` tồn tại, không có nhánh else. POST tới `wp-login.php?action=lostpassword` chỉ cần **bỏ field** là bypass hoàn toàn.
- **Khuyến nghị:** Bỏ guard `isset()`, luôn verify: `$nonce = $_POST['lostpassword_csrf_token'] ?? ''; if (!wp_verify_nonce($nonce, ...)) wp_die();`

### H11. Nhiều field ACF echo không escape trên template/shortcode hotel (stored XSS)
- **File:** `vielimousine/inc/hotel-functions.php:79, 100-103, 113`; `functions.php:330-331, 497-499`; `single-hotel.php:47, 167`; `Themes/Shortcode.php:441, 488, 523, 558, 593`
- **Vấn đề:** ACF text/URL field echo thẳng vào HTML không qua `esc_*`. Admin/editor sửa được post-meta/ACF có thể inject HTML/JS vào front end.
- **Khuyến nghị:** `esc_html()` cho text, `esc_url()` cho URL/href/src, `esc_attr()` trong attribute; `wp_kses_post()` cho field rich (WYSIWYG/repeater).

### H12. `uploadFileFromUrl` ghi file ra đĩa **trước khi** validate MIME
- **File:** `vielimousine/inc/classes/Utilities/Traits/File.php:270-278`
- **Vấn đề:** Ghi body remote vào uploads (line 271) rồi mới `wp_check_filetype` (276). Nếu MIME bị reject, file đã nằm trên đĩa và **không** được dọn. Script PHP giả dạng URL ảnh có thể tồn tại dưới `wp-content/uploads/` và bị thực thi nếu server cấu hình sai. Tên file lấy từ `parse_url(basename())` không sanitize.
- **Khuyến nghị:** Validate MIME (qua `finfo`/content-type header) **trước** khi ghi; nếu fail sau khi ghi thì `wp_delete_file()`; `sanitize_file_name()` cho filename.

---

## 🟡 MEDIUM (32) — nhóm theo subsystem

**Payment / Order / Pricing (data-integrity & correctness)**
- Auto-confirm dùng snapshot status cũ trong transaction, không CAS → có thể confirm order vừa bị cancel — `PaymentLedger.php:88-98`
- Giá vé combo lệch giữa quote và order do **rớt `route_id`** ở đường tạo order — `OrderService.php:54-63`
- `OrderCodeGenerator` `SELECT MAX` ngoài row lock → 2 transaction sinh cùng candidate code — `OrderCodeGenerator.php:31-48`
- Order total (đã làm tròn) lệch tổng line_total (chưa làm tròn) — `OrderService.php:106,176,218,240`

**Coupon**
- Order nhiều item: ràng buộc hotel/room/booking_type chỉ check **item đầu tiên** trong khi discount áp cho cả subtotal — `OrderService.php:87-104`
- `usage_limit_per_user` không race-safe, không re-check atomic lúc redeem — `CouponService.php:64-70`

**Auth**
- JWT decode chấp nhận token **không có `exp`** → access token không hết hạn nếu thiếu exp — `JwtService.php:63`
- `SecuritySweep` chỉ chặn login sau cron 15 phút → cửa sổ brute-force tới 15 phút — `SecuritySweep.php`
- `TokenController::destroy()` **xóa cứng** token row, phá audit trail reuse-detection — `TokenController.php:36-48`

**REST authz / data layer**
- `UserController::show()` không giới hạn theo Vie role → trả profile bất kỳ WP user theo ID — `UserController.php:44-54`
- `AbstractRepository::exists()` nhận `$column` tùy ý, nội suy thẳng vào SQL — `AbstractRepository.php:149-165`
- `AbstractRepository::update()` nuốt lỗi `$wpdb` (không check sau update) — `AbstractRepository.php:136`
- `applyFilters()` không validate format chuỗi date trước khi đưa vào so sánh range — `AbstractRepository.php:218-235`
- Filter bool `has_vat` map vào cột VARCHAR → kết quả luôn sai — `CustomerRepository.php:48`
- `migrateOrderDraftColumns()` chạy ALTER mỗi request, **không** transaction, set cờ done kể cả khi fail một phần — `SchemaManager.php:138-163`
- `OrderRepository::count()` bỏ qua user-scope guard — *(xem batch SQL)*

**Backup**
- Restore một phần để bảng ở trạng thái hỏng/lẫn lộn — không transaction, lỗi vẫn trả HTTP 200 — `BackupService.php:140-163`
- Regex snapshot trong controller bỏ sót TRUNCATE/UPDATE/DELETE/ALTER/DROP(no IF EXISTS) → bảng bị sửa không được backup trước — `BackupController.php:52-53`
- `export()` nạp cả bảng vào RAM một query → cạn memory với bảng lớn — `BackupService.php:76`

**Validation / Support**
- `validateExists`/`validateUnique` nội suy table+column vào SQL không allow-list — `Validator.php:248-296`
- `validateInt` chấp nhận chuỗi float (`'1.5'`) → sai semantics int — `Validator.php:145-152`
- Rate-limiter `PublicOrderController` dùng `REMOTE_ADDR` thô thay vì `ClientIp::clientIp()` — `PublicOrderController.php:24`
- `customer_note`/`internal_note` không giới hạn độ dài ở nhiều schema — `OrderValidation.php:28-29,61-62`

**Public-app / lookup**
- Order lookup trả **số điện thoại đầy đủ** (không mask) + field vận hành nội bộ cho caller ẩn danh — `OrderLookupController.php:64-89`
- Rate-limiter public reset TTL mỗi request → cửa sổ trượt, kéo dài vô hạn — `PublicOrderController.php:99-108`
- Không validate server-side rằng `checkin` không ở quá khứ → tạo order cho ngày đã qua — `PublicOrderValidation.php:13-27`

**Media**
- Kết quả `save()` strip-EXIF không được kiểm tra → fail âm thầm, file vẫn còn EXIF — `MediaService.php:59-62`

**Parent theme**
- Header CORS wildcard gửi trên **mọi request** (kể cả `wp-login.php`), không chỉ REST — `Rest.php:16,72-82`
- Shortcode `main_faq` dùng ACF `$sl_heading` làm **tên thẻ HTML thô** + echo `ques`/`ans` không escape — `Shortcode.php:699,708,711`
- `bulkInsertRows` không backtick-quote tên cột trong INSERT — `Db.php:87`
- Shortcode `posts` đọc key sai (`$atts['wrapper']` vs `'wrapper_tag'`) — `Shortcode.php:136-138`

---

## ⚪ LOW (57) & ℹ️ INFO (11) — điểm đáng chú ý

Phần lớn là hardening & dọn dẹp. Một số đáng làm sớm:
- Webhook không validate **currency** → notification khác tiền tệ vẫn ghi là paid — `SepayWebhook.php:52-59,108-120`
- Check timestamp chống replay thực chất **tùy chọn**, tự tắt khi SePay bỏ field — `SepayWebhook.php:61-70`
- `/pricing/cells` không có validation schema → ghi được giá/stock âm, ID mồ côi — `PricingCellsController.php:12-37`
- Public `/coupons/validate` trả **nguyên row coupon** (usage_limit, used_count, created_by, targeting IDs) — `CouponActionController.php:33-42`
- Coupon `%` không có cận trên (>100% được chấp nhận), chỉ dựa vào clamp downstream — `CouponValidation.php:13-14`
- Migration on-page-load không có cross-request lock; `RoleInstaller::install()` chạy mỗi request — `SchemaManager.php`, `Plugin.php:8-14`
- `next`/redirect param ở Login dùng không validate — `LoginView.vue:30-31`
- Cookie SameSite/secure không nhất quán giữa set và clear; thiếu xử lý CORS preflight (OPTIONS) — Auth subsystem

**INFO (ghi nhận điểm TỐT, không phải lỗi):** `NoShowSweep` scope đúng `status='pending'` + CAS; autoloader an toàn path-traversal; cron callback không lộ qua REST/AJAX; money là integer VND (DECIMAL(12,0)) end-to-end nên không có lỗi làm tròn phân số; webhook HMAC-SHA256 + `hash_equals` + UNIQUE(gateway,transaction_id); refresh-token rotation + reuse detection; stock `SELECT ... FOR UPDATE`.

---

## ❌ Đã bác bỏ (2) — minh bạch

- *"QueryBuilder nội suy column/operator không allow-list"* — **bác bỏ**: QueryBuilder có nội suy thật, nhưng **mọi call site** trong `AbstractRepository` đều có kiểm soát (orderBy qua `in_array`, select qua `array_intersect` với fillable). Không có đường nào đưa request input thẳng vào. → giữ làm "latent risk" mức low, không phải lỗ hổng.
- *"AbstractRepository::select() nội suy column từ tham số fields"* — **bác bỏ**: dòng 45-51 đã `array_intersect` với allowlist hard-code + fillable trước khi truyền vào QueryBuilder.

---

## 🛠️ Lộ trình khắc phục đề xuất

**P0 — làm ngay (lộ dữ liệu / RCE tiềm ẩn):**
1. **C1** — chặn HTTP thư mục `vie-backups` (`.htaccess`/đưa ra ngoài web root) + tên file ngẫu nhiên mạnh.
2. **C2** — chuyển AES key sang `wp-config.php`, xóa fallback hard-code, **xoay key** (key cũ đã lộ trong git history — cân nhắc `git filter-repo` để gỡ).
3. **H7** — allowlist loại câu lệnh trong `restore()` (chặn INTO OUTFILE / SET GLOBAL / GRANT).
4. **H8** — gỡ `cost_total` khỏi response `/quote` public.

**P1 — tuần này (access-control & toàn vẹn dữ liệu):**
5. **H4 + H5** — thêm `canUserViewOrder()`/`applyUserScope` cho Invoice & OrderItem (IDOR role sales).
6. **H6** — chặn POST/DELETE order-item đi tắt qua repository.
7. **H1** — `FOR UPDATE`/CAS cho ghi `paid_amount` trong ledger.
8. **H2** — enforce `sales_only` coupon.
9. **H10 + H11 + H12** — parent theme: CSRF lost-password, escape ACF output, validate MIME trước khi ghi file.

**P2 — sprint tới:** H3 (refresh token đa tab), toàn bộ MEDIUM (đặc biệt currency webhook, lookup lộ SĐT, migration lock, validation gaps).

**P3 — backlog:** LOW/INFO hardening.

---

## Phụ lục — dữ liệu thô
- Findings đã verify (JSON): `/tmp/review-all-confirmed.json` (116 mục)
- Workflow scripts: `/tmp/theme-review-workflow.mjs`, `/tmp/theme-review-sonnet.mjs`
