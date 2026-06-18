# Backup & Restore (admin) — Design

**Ngày:** 2026-06-18
**Trạng thái:** Đã duyệt thiết kế — chờ lập kế hoạch

## 1. Mục tiêu
Cho phép **administrator** sao lưu (chọn từng bảng `vie_*`) và phục hồi dữ liệu nghiệp vụ ngay trong admin SPA, không phụ thuộc công cụ ngoài (`mysqldump`/`shell_exec`) để chạy được cả local lẫn production cPanel.

## 2. Bối cảnh (đã xác minh)
- 15 bảng `wpte_vie_*`, tổng ~26MB (lớn nhất `vie_room_price` 16.6MB/50k dòng). Dump thuần PHP khả thi.
- `mariadb-dump` trong `vie_cli` không auth được MySQL 8 (`caching_sha2_password`); prod cPanel thường chặn `shell_exec`. → **Backup/restore phải thuần PHP** (`$wpdb` + `SHOW CREATE TABLE`).
- Hệ thống: WP child theme, admin SPA (Vue 3, route `/vie-admin/*`), REST API namespace `Vie\Http\Controllers`, gate quyền qua `AuthMiddleware::requireCap()` + `auth.can()` (đọc `user.caps` từ payload login/refresh). Prefix bảng `wpte_`.

## 3. Quyết định thiết kế
- Hình thức: **trang admin Vue + REST API**.
- Phạm vi: **chỉ bảng `wpte_vie_*`** (chọn từng bảng). KHÔNG đụng bảng WP core.
- Restore: **trong UI, có rào chắn** (xác nhận + auto-snapshot + allowlist).
- Quyền: cap mới **`vie_manage_backup`**, chỉ cấp cho `administrator`.
- Định dạng backup: **SQL** (`.sql`), không nén (YAGNI; DB nhỏ).

## 4. Phân quyền — `vie_manage_backup`
- Thêm hằng `CAP_MANAGE_BACKUP = 'vie_manage_backup'` trong `RoleInstaller`.
- Cấp cho **administrator only** (đặt cạnh `CAP_MANAGE_USERS`, KHÔNG đưa vào `ALL_CAPS` để `hotel_manager` không nhận).
- `RoleInstaller::install()` chạy khi activate/migration → cap có mặt; tự chảy vào `user.caps` như các cap `vie_*` khác.
- Frontend gate `auth.can('vie_manage_backup')` cho nav + route + nút.

## 5. Backend — `Vie\Service\Backup\BackupService` (thuần PHP)
Đặt tại `inc/src/Service/Backup/BackupService.php`.

- `const PREFIX_ALLOW` = `$wpdb->prefix . 'vie_'` (chỉ bảng bắt đầu bằng đây).
- `listTables(): array` → `[ {name, rows, size_mb}, ... ]` cho mọi bảng `wpte_vie_*` (query `information_schema.tables`).
- `isAllowed(string $table): bool` → table phải bắt đầu `wpte_vie_` (chống path/table injection).
- `export(array $tables): string` → trả chuỗi SQL:
  - Header comment (ngày giờ, danh sách bảng, prefix).
  - `SET FOREIGN_KEY_CHECKS=0;` `SET NAMES utf8mb4;`
  - Mỗi bảng (đã lọc qua `isAllowed`): `DROP TABLE IF EXISTS` + `SHOW CREATE TABLE` (lấy câu CREATE) + `INSERT INTO` theo **lô 500 dòng/câu**, escape giá trị qua `$wpdb->prepare`/`esc_sql` (xử lý NULL/số/chuỗi/nhị phân).
  - Kết `SET FOREIGN_KEY_CHECKS=1;`.
  - Bảng không nằm trong allowlist → bỏ qua (không export).
- `restore(string $sql): array` → 
  - **Pre-scan allowlist (không tự tách câu):** dùng regex bắt mọi tên bảng trong `DROP TABLE IF EXISTS` / `CREATE TABLE` / `INSERT INTO` (`` (?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO)\s+`?(\w+)`? ``). Nếu CÓ tên bảng không bắt đầu `wpte_vie_` → **từ chối toàn bộ restore** (422, không chạy gì). Tránh tự split theo `;` (dễ sai vì `;` nằm trong giá trị text).
  - **Thực thi nguyên khối** qua `mysqli::multi_query` (lấy `$wpdb->dbh`) — server tự parse nhiều câu lệnh, đúng cả CREATE đa dòng lẫn INSERT lớn. Bọc `SET FOREIGN_KEY_CHECKS=0` ... `=1`.
  - Đếm bảng đã chạm + bắt lỗi mysqli; trả `{tables_restored:[], statements:int, errors:[]}`.

## 6. REST endpoints — `Vie\Http\Controllers\BackupController` (gate `vie_manage_backup`)
Đăng ký trong `RestRouter`:
- `GET /backup/tables` → `BackupService::listTables()`.
- `POST /backup/export` body `{tables:[...]}` → validate tables ⊆ allowlist; trả **nội dung `.sql`** trong envelope `data.sql` + `data.filename` (`vie-backup-YYYYMMDD-HHMMSS.sql`). SPA tải file phía client (Blob + JWT header, giống nút Xuất CSV).
- `POST /backup/restore` body `{sql: "<nội dung file>", confirm: "RESTORE"}` →
  - Bắt buộc `confirm === "RESTORE"` (nếu không → 422).
  - Xác định bảng trong `sql`; **auto-snapshot** các bảng đó qua `BackupService::export()` ghi ra `wp-content/uploads/vie-backups/auto-YYYYMMDD-HHMMSS.sql` (tạo thư mục nếu chưa có).
  - Gọi `BackupService::restore($sql)`; nếu có bảng ngoài allowlist → 422, không chạy gì.
  - Trả `{restored:[...], snapshot_file:"...", statements:int}`.

(Upload file: SPA đọc file `.sql` bằng `FileReader` → gửi nội dung text trong `sql`. Tránh xử lý multipart phức tạp; phù hợp DB nhỏ.)

## 7. Rào chắn Restore (an toàn)
- Gate cap `vie_manage_backup` (admin only).
- Bắt gõ chính xác chuỗi `RESTORE`.
- **Auto-snapshot** các bảng bị ghi đè trước khi restore (lớp khôi phục chính, vì DDL `DROP/CREATE` không rollback được trong transaction MySQL).
- Allowlist tên bảng `wpte_vie_*`: file chứa bảng khác → từ chối toàn bộ.
- `FOREIGN_KEY_CHECKS=0` trong lúc restore.

## 8. Frontend — `BackupView.vue` (mục "Hệ thống", route `/backup`)
- Thêm route `{ path: 'backup', component: BackupView, meta: { cap: 'vie_manage_backup' } }` và NavItem (group `system`, `show: auth.can('vie_manage_backup')`).
- API client `src/api/backup.api.ts`: `listTables()`, `exportTables(tables)`, `restore(sql, confirm)`.
- UI:
  - Bảng checkbox bảng (tên / số dòng / dung lượng); nút **"Sao lưu (tải .sql)"** → gọi export → tải file client-side. `vie_token` **bỏ tick mặc định** (cảnh báo chứa token).
  - Khu **Restore**: input file `.sql` (đọc bằng FileReader) → ô text gõ `RESTORE` → cảnh báo đỏ → nút "Phục hồi" (disabled tới khi gõ đúng) → hiện kết quả + tên file snapshot.
- Gate nav/route/nút bằng `auth.can('vie_manage_backup')`.

## 9. Kiểm thử
### Backend (e2e thuần PHP, chạy bằng lệnh composite — KHÔNG dùng `run.php` vì auth-e2e fatal pre-existing)
- `listTables()` trả các bảng `vie_*`.
- `export([vie_coupon])` → SQL chứa `DROP TABLE`, `CREATE TABLE`, `INSERT` cho đúng bảng; không chứa bảng khác.
- **Round-trip**: export `vie_coupon` → xoá/sửa 1 dòng → `restore(sql)` → dữ liệu trở lại đúng.
- `restore()` với SQL chứa bảng ngoài allowlist (vd `wpte_users`) → từ chối, không thực thi.
- (Controller) `POST /backup/restore` thiếu `confirm` → 422.
### Frontend (vitest — harness đã có)
- BackupView render danh sách bảng (mock API).
- Nút "Phục hồi" disabled tới khi gõ đúng `RESTORE`.
### Build
- Rebuild dist admin-app + commit (prod deploy bằng git pull).

## 10. Ngoài phạm vi (YAGNI)
- Không backup bảng WP core (users/posts/options).
- Không lịch/cron tự động.
- Không nén gzip (DB ~26MB).
- Không lưu nhiều bản backup trên server (ngoài auto-snapshot trước restore); không UI quản lý danh sách snapshot (chỉ trả tên file mới nhất).
- Không restore kiểu merge — chỉ ghi đè (DROP+CREATE+INSERT) theo file.

## 11. Tiêu chí hoàn thành
- Admin (có `vie_manage_backup`) chọn bảng → tải `.sql` hợp lệ; `hotel_manager`/`sales` không thấy/không gọi được.
- Restore từ file `.sql` hợp lệ khôi phục đúng dữ liệu; có auto-snapshot; từ chối bảng ngoài allowlist và thiếu xác nhận.
- Test backend round-trip + allowlist + gate; test frontend gating; dist rebuilt.
