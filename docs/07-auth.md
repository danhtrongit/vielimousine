# 07 — Auth cho Admin SPA

JWT access token (RAM) + Refresh token cookie HttpOnly. Refresh state lưu trong `vie_token`.

## 7.1. Flow tổng quan

```
┌─────────┐  POST /auth/login           ┌─────────┐
│  SPA    │ ─────────────────────────►  │  BE     │
│         │                             │         │
│         │ ◄─── access JWT (15')       │         │
│         │      Set-Cookie: vie_refresh│         │
└─────────┘      (HttpOnly, 30d)        └─────────┘
     │
     │  Bearer access JWT (mỗi request)
     ▼
┌─────────┐
│  /api   │
└─────────┘
     │
     │  401 → SPA gọi /auth/refresh (cookie)
     ▼
┌─────────┐  POST /auth/refresh
│  SPA    │ ─────────────────────────►  cookie vie_refresh
│         │ ◄─── access JWT mới + cookie vie_refresh mới (rotate)
└─────────┘
```

## 7.2. Access token (JWT)

- Algorithm: **HS256** (đơn giản, share secret giữa BE & verify ở chính BE).
- TTL: **15 phút**.
- Claims:

```json
{
  "iss": "vie",
  "sub": "{user_id}",
  "iat": 1715512345,
  "exp": 1715513245,
  "jti": "{uuid}",
  "caps": ["vie_manage_orders", "..."],
  "role": "administrator",
  "email": "user@example.com",
  "name": "Display Name"
}
```

- Secret: `wp_options.vie_jwt_secret` (sinh khi cài, 64 bytes base64). Có thể rotate qua REST `POST /settings/rotate-jwt-secret` (invalidate tất cả access token đang phát hành; refresh vẫn dùng được — sẽ phát lại access mới).
- Verify: `JwtService::verify($token)`.
- Library: dùng `firebase/php-jwt` qua require Composer **nhỏ chỉ riêng cho 1 vendor**, hoặc tự implement HS256 (200 dòng). Ưu tiên tự implement để tránh Composer.

## 7.3. Refresh token

- Random 64 bytes (`random_bytes(64)`), base64url → chuỗi ~86 ký tự.
- Lưu cookie:
    - Name: `vie_refresh`
    - `HttpOnly`, `Secure` (production), `SameSite=Lax`
    - `Path=/wp-json/vie/v1/auth/`
    - `Max-Age=2592000` (30 ngày)
- Lưu DB ở `vie_token`:
    - `hash = hash('sha256', $rawToken)` — chỉ lưu hash
    - `family = uuid v4` — cùng family qua các lần rotate
    - `expires_at = now + 30d`
- Khi `/auth/refresh`:
    1. Lấy raw từ cookie, hash, tìm row chưa `revoked_at`, chưa expired.
    2. **Revoke** row hiện tại (`revoked_at = now`).
    3. **Issue** row mới cùng `family`, cùng `user_id`.
    4. Set cookie mới với raw mới.
    5. Trả access token mới.
- **Phát hiện reuse**: nếu nhận raw → hash match một row đã `revoked_at != NULL` → coi là cookie bị steal → revoke **toàn bộ** family → bắt login lại.

```php
final class TokenRepository {
    public function issue(int $userId, string $family, string $ip, string $ua): string {
        $raw  = Token::randomBase64Url(64);
        $hash = hash('sha256', $raw);
        $this->insert([
            'user_id' => $userId,
            'hash'    => $hash,
            'family'  => $family,
            'ip'      => $ip,
            'ua'      => substr($ua, 0, 500),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400 * 30),
        ]);
        return $raw;
    }

    public function rotate(string $rawOld, string $ip, string $ua): ?array {
        $hash = hash('sha256', $rawOld);
        $row  = $this->findByHash($hash);
        if (!$row) return null;

        if ($row->revoked_at !== null) {
            // reuse detected
            $this->revokeFamily($row->family);
            return null;
        }
        if (strtotime($row->expires_at) < time()) return null;

        $this->revoke($row->id);
        $rawNew = $this->issue((int)$row->user_id, $row->family, $ip, $ua);
        return ['user_id' => (int)$row->user_id, 'raw' => $rawNew];
    }
}
```

## 7.4. Login

### `POST /auth/login`

Body:

```json
{ "username": "huynh_ha", "password": "...", "remember": true }
```

Verify qua `wp_authenticate()` (WordPress core). Nếu thành công:

1. Sinh `family = uuid_v4()`.
2. `TokenRepository::issue($user->ID, $family, $ip, $ua)` → raw.
3. Set cookie `vie_refresh = raw`.
4. Sinh access JWT, ttl 15'.
5. Activity log `action='login'`.

Response:

```json
{
  "access_token": "eyJ...",
  "expires_in": 900,
  "token_type": "Bearer",
  "user": {
    "id": 5,
    "username": "huynh_ha",
    "display_name": "Huỳnh Hà",
    "email": "ha@example.com",
    "roles": ["sales"],
    "caps": ["vie_create_orders","vie_view_own_orders",...]
  }
}
```

### Rate limit: 5 lần / 5 phút / IP (`POST /auth/login`).

## 7.5. Refresh

### `POST /auth/refresh`

- Đọc cookie `vie_refresh`.
- Gọi `rotate()` (xem §7.3).
- Trả response giống `/auth/login`.

### Concurrent refresh

SPA có thể spawn nhiều request 401 cùng lúc. Interceptor (§6.5) đảm bảo **chỉ gọi refresh 1 lần**, các request kia chờ.

## 7.6. Logout

### `POST /auth/logout`

- Lấy cookie, hash, tìm row, set `revoked_at`. (Không revoke cả family — user có thể đang đăng nhập trên thiết bị khác.)
- Clear cookie (Set-Cookie với `Max-Age=0`).
- Activity log `action='logout'`.

### `POST /auth/logout-all`

Revoke toàn bộ token của user. Buộc đăng nhập lại trên mọi thiết bị.

## 7.7. Me

### `GET /auth/me`

Auth required. Trả thông tin user (roles, caps, hotel_managed nếu là hotel_manager).

## 7.8. CORS

`vie_cors_origins` setting (array origin allowed). Header response (mỗi REST request):

```
Access-Control-Allow-Origin: {matched_origin}
Access-Control-Allow-Credentials: true
Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Idempotency-Key
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Vary: Origin
```

Preflight `OPTIONS` được handler trong `Http\RestRouter::register()` trả 204.

## 7.9. CSRF

- Cookie refresh `SameSite=Lax` + `HttpOnly` đã ngăn được hầu hết CSRF.
- Endpoint state-changing public (`/orders`, `/quote`) yêu cầu `X-WP-Nonce` (theo chuẩn WP).
- SPA admin (authed) chỉ chấp nhận `Authorization: Bearer ...`, không nhận query token.

## 7.10. Brute force protect

Bảng `vie_activity_log` ghi mọi login fail (`action='login_fail'`). Cron `vie_security_sweep` (daily):

- Nếu 1 IP > 50 fail / 1h → lưu vào option `vie_blocked_ips` (TTL 24h). REST router check option đầu request.

## 7.11. Application Password / WP nonce — fallback

Không khuyến nghị cho SPA admin (UX kém), nhưng để hỗ trợ tooling:

- WP-CLI / IDE call REST: dùng `wp_application_passwords`.
- WP nonce cho public endpoints (xem §5.1).

## 7.12. Multi-tab

Refresh token rotate gây vấn đề nếu nhiều tab cùng refresh:

- Tab A gọi refresh → cookie raw A bị mark revoked, raw B set vào cookie.
- Tab B (chưa nhận response) vẫn cầm A → 401 → gọi refresh → A đã revoked → reuse detection → kill family!

Giải pháp: **broadcast channel** giữa các tab (`BroadcastChannel` API). Khi 1 tab refresh thành công → post message; các tab khác đọc access token mới từ memory (Pinia store) thay vì tự refresh.

```ts
// stores/auth.store.ts
const channel = new BroadcastChannel('vie-auth');
channel.onmessage = (e) => {
  if (e.data.type === 'access') auth.accessToken = e.data.token;
  if (e.data.type === 'logout') auth.logoutLocal();
};
```

## 7.13. Token rotation cleanup

Cron daily: xóa row `vie_token` đã `expires_at < now()` quá 7 ngày, hoặc `revoked_at < now() - 7d`. Giữ 7 ngày để debug forensic.
