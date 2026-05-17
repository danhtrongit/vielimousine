<?php
declare(strict_types=1);

namespace Vie\Http;

use Vie\Support\ClientIp;
use Vie\Support\ResponseEnvelope;

/**
 * Lightweight transient-backed rate-limiter cho các endpoint public.
 *
 * Cách dùng trong controller:
 *
 *     $denied = RateLimiter::check('quote', 30, 60);
 *     if ($denied !== null) return $denied;
 *
 * Tham số:
 *   - $bucket   : nhãn endpoint, dùng để tách counter (vd 'quote', 'lookup').
 *   - $maxReq   : số request tối đa trong $windowSec.
 *   - $windowSec: cửa sổ thời gian (giây).
 *
 * Counter key dùng `bucket + IP đã resolve qua ClientIp` (tôn trọng trusted
 * proxies). Lưu qua WP transient — Object Cache nếu có, fallback `wp_options`.
 *
 * Lưu ý: rate-limit dựa trên IP có thể bypass bằng proxy pool; đây chỉ là lớp
 * chống abuse cơ bản. Cho protection nặng hơn → Cloudflare WAF.
 */
final class RateLimiter
{
    /**
     * Trả về null nếu trong giới hạn; trả về WP_REST_Response 429 nếu vượt.
     */
    public static function check(string $bucket, int $maxReq, int $windowSec): ?\WP_REST_Response
    {
        $ip = ClientIp::clientIp();
        if ($ip === '') {
            // Không xác định được IP → không rate-limit (đỡ false-positive khi WP-CLI).
            return null;
        }
        $key = 'vie_rl_' . md5($bucket . '|' . $ip);
        $now = time();

        $state = get_transient($key);
        if (!is_array($state) || ($state['reset'] ?? 0) < $now) {
            $state = ['count' => 0, 'reset' => $now + $windowSec];
        }

        $state['count']++;
        $remaining = max(0, $maxReq - $state['count']);
        $retryAfter = max(1, $state['reset'] - $now);

        // Lưu lại state với TTL còn lại của window.
        set_transient($key, $state, $retryAfter);

        if ($state['count'] > $maxReq) {
            $resp = ResponseEnvelope::error([
                ['code' => 'rate_limited', 'field' => null, 'message' => 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ' . $retryAfter . ' giây.'],
            ], 429);
            $resp->header('Retry-After', (string) $retryAfter);
            $resp->header('X-RateLimit-Limit', (string) $maxReq);
            $resp->header('X-RateLimit-Remaining', '0');
            return $resp;
        }

        return null;
    }
}
