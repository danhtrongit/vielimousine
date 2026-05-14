# 17 — Quy trình Deploy

Tài liệu cho dev/devops khi đẩy `vielimousine-child` lên staging/UAT/production.

## 1. Pre-flight checklist (trước khi rời máy local)

- [ ] Tests xanh: `wp eval-file inc/tests/run.php` → tất cả pass
- [ ] SPA build mới nhất: `cd admin-app && npm run build` (commit `admin-app/dist/`)
- [ ] PHP lint sạch: `find inc/src inc/tests inc/seed -name '*.php' -exec php -l {} \;`
- [ ] `SchemaManager::VERSION` của bảng có schema đổi đã bump
- [ ] Cron mới (nếu thêm) đã trong `Cron\CronRegistry::HOOKS`
- [ ] Mail settings có default an toàn (không lộ secret)
- [ ] Git: commit + tag (ví dụ `v1.0.0-staging`, `v1.0.0`)
- [ ] Database backup gần đây (production: bắt buộc backup ngay trước deploy)
- [ ] Maintenance plan (production): thời điểm thấp tải

## 2. Staging deploy

```bash
# Trên máy local
git push origin main

# Trên server staging (SSH)
cd /var/www/staging.vielimo.vn/wp-content/themes/
git -C vielimousine-child pull origin main

# Theme activate (nếu lần đầu)
wp theme activate vielimousine-child --path=/var/www/staging.vielimo.vn/

# Re-flush rewrites (nếu thay shortcode/route)
wp rewrite flush --path=/var/www/staging.vielimo.vn/

# Health check
curl -sS https://staging.vielimo.vn/wp-json/vie/v1/health | jq .

# Smoke + security test
wp eval-file inc/tests/run.php --path=/var/www/staging.vielimo.vn/
```

**Expected**: `Total: X passed, 0 failed`. Nếu fail → rollback ngay.

## 3. UAT (1 ngày)

Trên staging, gửi link + tài khoản test cho stakeholder. Họ chạy 14-item smoke checklist (xem [docs §13.6](13-testing.md#136-smoke-test-e2e-manual)) + 4 item manual:

- Search hotel UI realtime
- Email render đẹp trên Gmail/Outlook
- CSV export mở Excel encoding đúng tiếng Việt
- SePay sandbox flow (redirect + webhook)

Bug log → fix → re-deploy staging → re-test cho đến khi clean.

## 4. Production deploy

```bash
# 1. Bật maintenance mode
wp maintenance-mode activate --path=/var/www/vielimo.vn/

# 2. Database backup
mysqldump --single-transaction --routines --triggers \
  -u $DB_USER -p $DB_NAME > /backups/vielimo-$(date +%Y%m%d-%H%M).sql.gz

# 3. Pull code
cd /var/www/vielimo.vn/wp-content/themes/vielimousine-child
git fetch origin
git checkout v1.0.0   # tag production

# 4. Re-flush rewrites + schema upgrade tự động chạy khi load WP lần đầu
wp rewrite flush --path=/var/www/vielimo.vn/

# 5. Sanity: tests trên prod (chỉ pricing — không chạy mutation tests trên prod!)
wp eval-file inc/tests/cases.php --path=/var/www/vielimo.vn/ 2>&1 | head -20

# 6. Health
curl -sS https://vielimo.vn/wp-json/vie/v1/health

# 7. Tắt maintenance
wp maintenance-mode deactivate --path=/var/www/vielimo.vn/

# 8. Smoke test 1 đơn thật bằng email nội bộ — verify SePay flow
```

## 5. Rollback

Nếu phát hiện lỗi nghiêm trọng trong 1h sau deploy:

```bash
# A. Code rollback
cd /var/www/vielimo.vn/wp-content/themes/vielimousine-child
git checkout <previous-tag>
wp rewrite flush --path=/var/www/vielimo.vn/

# B. Database rollback (chỉ nếu schema breaking change)
wp maintenance-mode activate --path=/var/www/vielimo.vn/
gunzip < /backups/vielimo-YYYYMMDD-HHMM.sql.gz | mysql -u $DB_USER -p $DB_NAME
wp maintenance-mode deactivate --path=/var/www/vielimo.vn/

# C. Notify
# Slack #vielimo-ops: rollback + lý do
```

## 6. Post-deploy monitoring (1 giờ đầu)

| Kiểm tra | Lệnh / Nơi |
|---|---|
| Tỷ lệ mail fail | SPA → Nhật ký → filter `entity_type=mail, action=fail` (kỳ vọng <5%) |
| Cron status | `wp cron event list --path=/var/www/vielimo.vn/` |
| Slow query | `tail -f /var/log/mysql/slow.log` |
| PHP error | `tail -f /var/log/php/error.log` |
| Web access | `tail -f /var/log/nginx/vielimo.vn.access.log` |
| Đơn mới được tạo | SPA → Dashboard hoặc `wp db query "SELECT COUNT(*) FROM wp_vie_order WHERE created_at >= NOW() - INTERVAL 1 HOUR"` |

## 7. Performance baseline (chạy 1 lần sau staging deploy)

```bash
wp eval-file inc/seed/perf.php --path=/var/www/staging.vielimo.vn/   # ~5 phút
wp eval-file inc/tests/bench.php --path=/var/www/staging.vielimo.vn/
```

**Targets**:
- `GET /orders?per_page=50` p95 < 200ms
- `GET /payments?per_page=50` p95 < 300ms
- `POST /quote` p95 < 100ms

Nếu vượt: kiểm `EXPLAIN` query, thêm index, hoặc tăng RAM cache.

**Cleanup perf data** sau khi bench xong (production tuyệt đối không seed perf):

```php
// wp eval
\Vie\Schema\Seeders\PerfSeeder::clear($wpdb);
```

## 8. Cron setup trên production

WP cron mặc định chạy khi có traffic. Nếu site ít traffic, dùng system cron:

```cron
*/5 * * * * cd /var/www/vielimo.vn && wp cron event run --due-now > /dev/null 2>&1
```

Và disable WP internal cron trong `wp-config.php`:

```php
define('DISABLE_WP_CRON', true);
```

## 9. Environment matrix

| Env | URL | Branch | Theme version | Auto-deploy |
|---|---|---|---|---|
| Local | http://vielimo.local | feature/* | dev | manual |
| Staging | https://staging.vielimo.vn | main | latest commit | manual git pull |
| UAT | https://uat.vielimo.vn | release/* | RC tag | manual |
| Production | https://vielimo.vn | tags | semver tag | manual |

## 10. Secrets

- JWT secret: `vie_jwt_secret` trong `wp_options` (tự generate lần đầu)
- SePay secret: SPA Cài đặt → SePay (không log vào git)
- DB password: server env (không commit)

Nếu rotate JWT secret: tất cả user phải đăng nhập lại — tránh giờ cao điểm.
