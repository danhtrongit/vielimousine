<?php
declare(strict_types=1);

namespace Vie\Support;

use Vie\Container;
use Vie\Service\Settings\AuthSettings;

/**
 * Trust-aware client info helper.
 *
 * - clientIp(): trả về IP thực của client. Nếu REMOTE_ADDR nằm trong whitelist
 *   `vie_trusted_proxies` (CIDR/IP list), tin tưởng header X-Forwarded-For (lấy
 *   IP đầu tiên). Ngược lại trả thẳng REMOTE_ADDR. Tránh kẻ tấn công giả IP qua
 *   header khi không có proxy.
 *
 * - isSecure(): true nếu request đến qua HTTPS — bao gồm cả khi đứng sau reverse
 *   proxy gửi `X-Forwarded-Proto: https`.
 */
final class ClientIp
{
    public static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote === '') {
            return '';
        }
        if (!self::isTrustedProxy($remote)) {
            return $remote;
        }
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff === '') {
            return $remote;
        }
        $first = trim(explode(',', $xff)[0] ?? '');
        return filter_var($first, FILTER_VALIDATE_IP) !== false ? $first : $remote;
    }

    public static function isSecure(): bool
    {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote !== '' && self::isTrustedProxy($remote)) {
            $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($proto === 'https') {
                return true;
            }
        }
        return false;
    }

    private static function isTrustedProxy(string $ip): bool
    {
        $settings = Container::get(AuthSettings::class);
        foreach ($settings->trustedProxies() as $cidr) {
            if (self::ipMatches($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    private static function ipMatches(string $ip, string $cidr): bool
    {
        if ($cidr === '') {
            return false;
        }
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bytes = (int) floor($bits / 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        return (($ipBin[$bytes] ?? "\0") & $mask) === (($subnetBin[$bytes] ?? "\0") & $mask);
    }
}
