<?php
declare(strict_types=1);

namespace Vie\Service\Pricing;

/**
 * Phân loại nhóm khách:
 *
 * - "Người lớn hiệu dụng" (effectiveAdults) = `adults` đầu vào + số bé từ
 *   `ADULT_AGE_THRESHOLD` tuổi trở lên được quy đổi sang người lớn.
 *
 * - "Trẻ em hiệu dụng" (effectiveChildren) = bé có tuổi < ADULT_AGE_THRESHOLD.
 *   Đây là tập sẽ vào `ChildPolicy`/`SurchargeCalculator` để tính miễn phí
 *   / phụ thu / giường phụ.
 *
 * Threshold mặc định = 12 (chốt theo nghiệp vụ vielimousine):
 *   "Trẻ em đủ 12 tuổi trở lên đều phải tính là người lớn."
 *
 * Threshold có thể override qua filter `vie_adult_age_threshold` hoặc option
 * `vie_adult_age_threshold` để BA tinh chỉnh theo từng khách sạn về sau.
 */
final class GuestComposition
{
    public const ADULT_AGE_THRESHOLD = 12;

    private int   $effectiveAdults;
    /** @var int[] bé có tuổi < threshold — vào ChildPolicy */
    private array $childAges;
    /** @var int[] bé ≥ threshold — đã quy đổi sang adult */
    private array $convertedAdultAges;

    /**
     * @param int   $adults    số người lớn từ input
     * @param int[] $childAges danh sách tuổi trẻ em từ input
     * @param int|null $adultAgeThreshold override threshold (testing)
     */
    public function __construct(int $adults, array $childAges, ?int $adultAgeThreshold = null)
    {
        $threshold = $adultAgeThreshold ?? self::resolveThreshold();
        $ages = array_map('intval', $childAges);

        $this->convertedAdultAges = array_values(array_filter(
            $ages,
            static fn(int $a): bool => $a >= $threshold,
        ));
        $this->childAges = array_values(array_filter(
            $ages,
            static fn(int $a): bool => $a < $threshold,
        ));
        $this->effectiveAdults = $adults + count($this->convertedAdultAges);
    }

    public function effectiveAdults(): int
    {
        return $this->effectiveAdults;
    }

    public function effectiveChildren(): int
    {
        return count($this->childAges);
    }

    /**
     * Trẻ em < threshold — vào ChildPolicy/Surcharge.
     * Tên giữ nguyên để backwards-compat với PriceCalculator caller.
     *
     * @return int[]
     */
    public function childrenUnderFloor(): array
    {
        return $this->childAges;
    }

    /**
     * Bé ≥ threshold đã quy đổi adult — dùng để hiển thị/log nếu cần.
     *
     * @return int[]
     */
    public function convertedAdultAges(): array
    {
        return $this->convertedAdultAges;
    }

    private static function resolveThreshold(): int
    {
        if (function_exists('get_option')) {
            $opt = (int) get_option('vie_adult_age_threshold', self::ADULT_AGE_THRESHOLD);
            if ($opt > 0 && $opt < 100) {
                $threshold = $opt;
            } else {
                $threshold = self::ADULT_AGE_THRESHOLD;
            }
        } else {
            $threshold = self::ADULT_AGE_THRESHOLD;
        }
        if (function_exists('apply_filters')) {
            $threshold = (int) apply_filters('vie_adult_age_threshold', $threshold);
        }
        return max(1, $threshold);
    }
}
