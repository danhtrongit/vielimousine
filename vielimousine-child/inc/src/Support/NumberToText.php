<?php
declare(strict_types=1);

namespace Vie\Support;

final class NumberToText
{
    private const DIGITS = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    private const SCALES = ['', 'nghìn', 'triệu', 'tỷ'];

    /**
     * Convert non-negative integer VND amount to Vietnamese words.
     * Trailing "đồng" is appended. Returns "Không đồng" for zero.
     */
    public static function vnd(int $amount): string
    {
        if ($amount < 0) {
            return 'Âm ' . self::vnd(-$amount);
        }
        if ($amount === 0) {
            return 'Không đồng';
        }

        // Split into groups of 3 digits from the right.
        $groups = [];
        $n = $amount;
        while ($n > 0) {
            $groups[] = $n % 1000;
            $n = intdiv($n, 1000);
        }

        $parts = [];
        $count = count($groups);
        for ($i = $count - 1; $i >= 0; $i--) {
            $g = $groups[$i];
            if ($g === 0 && $i !== 0) continue;
            $isFirstGroup = ($i === $count - 1);
            $words = self::readGroup($g, !$isFirstGroup);
            if ($words === '') continue;
            $scale = self::SCALES[$i] ?? '';
            $parts[] = trim($words . ($scale !== '' ? ' ' . $scale : ''));
        }

        $text = implode(' ', $parts) . ' đồng';
        return ucfirst($text);
    }

    /**
     * Read a 0..999 group. If $padHundred, force "không trăm" prefix when value < 100.
     */
    private static function readGroup(int $n, bool $padHundred): string
    {
        if ($n === 0) return '';
        $hundred = intdiv($n, 100);
        $rest    = $n % 100;
        $tens    = intdiv($rest, 10);
        $ones    = $rest % 10;

        $out = [];
        if ($hundred > 0) {
            $out[] = self::DIGITS[$hundred] . ' trăm';
        } elseif ($padHundred) {
            $out[] = 'không trăm';
        }

        if ($tens === 0 && $ones > 0) {
            if ($hundred > 0 || $padHundred) {
                $out[] = 'lẻ ' . self::DIGITS[$ones];
            } else {
                $out[] = self::DIGITS[$ones];
            }
        } elseif ($tens === 1) {
            $out[] = 'mười';
            if ($ones === 5) {
                $out[] = 'lăm';
            } elseif ($ones > 0) {
                $out[] = self::DIGITS[$ones];
            }
        } elseif ($tens >= 2) {
            $out[] = self::DIGITS[$tens] . ' mươi';
            if ($ones === 1) {
                $out[] = 'mốt';
            } elseif ($ones === 5) {
                $out[] = 'lăm';
            } elseif ($ones > 0) {
                $out[] = self::DIGITS[$ones];
            }
        }

        return implode(' ', $out);
    }
}
