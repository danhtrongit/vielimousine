<?php
declare(strict_types=1);

namespace Vie\Support;

/**
 * Cache transient nhẹ cho các truy vấn ĐỌC nặng (vd: pull báo cáo với per_page lớn).
 *
 * - Dùng WP Object Cache nếu có, fallback wp_options (giống RateLimiter).
 * - TTL ngắn: chấp nhận dữ liệu trễ vài phút để đổi lấy giảm tải DB.
 * - Chỉ nên áp cho đường đọc nặng + ít thay đổi (báo cáo), KHÔNG cho bảng tương tác.
 *
 * Lưu ý: key phải gồm ngữ cảnh phân quyền (vd user id) khi kết quả khác nhau theo user,
 * vì cache không tự áp applyUserScope.
 */
final class QueryCache
{
    /** TTL mặc định cho cache báo cáo (giây). */
    public const TTL = 300; // 5 phút

    /**
     * Trả giá trị từ cache nếu có, ngược lại chạy $cb, lưu cache rồi trả về.
     *
     * @template T
     * @param callable():T $cb
     * @return T
     */
    public static function remember(string $key, int $ttl, callable $cb)
    {
        $k   = 'vie_qc_' . md5($key);
        $hit = get_transient($k);
        if ($hit !== false) {
            return $hit;
        }
        $val = $cb();
        set_transient($k, $val, $ttl);
        return $val;
    }
}
