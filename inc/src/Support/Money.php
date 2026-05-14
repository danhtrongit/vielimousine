<?php
declare(strict_types=1);

namespace Vie\Support;

final class Money
{
    public static function roundVND(int|float $value): int
    {
        return (int) (round($value / 1000) * 1000);
    }

    public static function vnd(int|float|null $value): string
    {
        $n = (int) round((float) ($value ?? 0));
        return number_format($n, 0, ',', '.') . ' ₫';
    }
}
