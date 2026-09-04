<?php
declare(strict_types=1);

namespace Vie\Service\Coupon;

final class CouponCodeGenerator
{
    /**
     * Bảng chữ sinh mã — bỏ các ký tự dễ đọc nhầm khi khách đọc/gõ lại mã:
     * O vs 0, I vs 1. Còn 32 ký tự → 4 ký tự random đã cho ~1M tổ hợp.
     */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Trần độ dài cột vie_coupon.code — prefix + random + suffix không được vượt. */
    public const CODE_MAX_LENGTH = 50;

    public const MIN_RANDOM_LENGTH = 4;
    public const MAX_RANDOM_LENGTH = 16;

    /** Chỉ cho phép ký tự an toàn ở tiền tố/hậu tố (URL, email, đọc qua điện thoại). */
    public const AFFIX_PATTERN = '/^[A-Z0-9_-]*$/';

    /**
     * Sinh tối đa $count mã duy nhất dạng {prefix}{random}{suffix}.
     *
     * $taken là set mã đã bị chiếm (key = code) — dùng để tránh trùng với mã
     * đã sinh ở vòng trước hoặc mã đã có trong DB. Vòng lặp có trần cứng để
     * không spin vô hạn khi keyspace gần cạn; caller kiểm tra số lượng trả về.
     *
     * @param array<string,true> $taken
     * @return string[]
     */
    public function generate(int $count, string $prefix, string $suffix, int $length, array $taken = []): array
    {
        if ($count <= 0 || $length <= 0) {
            return [];
        }

        $maxIndex = strlen(self::ALPHABET) - 1;
        $maxSpins = $count * 20 + 100;
        $codes    = [];

        for ($spin = 0; $spin < $maxSpins && count($codes) < $count; $spin++) {
            $random = '';
            for ($i = 0; $i < $length; $i++) {
                $random .= self::ALPHABET[random_int(0, $maxIndex)];
            }

            $code = $prefix . $random . $suffix;
            if (isset($taken[$code])) {
                continue;
            }

            $taken[$code] = true;
            $codes[]      = $code;
        }

        return $codes;
    }

    /** Chuẩn hoá tiền tố/hậu tố: bỏ khoảng trắng + in hoa (mã lưu dạng in hoa). */
    public static function normalizeAffix(mixed $affix): string
    {
        return strtoupper(trim((string) $affix));
    }
}
