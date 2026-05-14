<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

use Vie\Service\Settings\AuthSettings;

final class JwtService
{
    public function __construct(private readonly AuthSettings $settings)
    {
    }

    public function encode(array $payload): string
    {
        $header = self::b64UrlEncode((string) wp_json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = self::b64UrlEncode((string) wp_json_encode($payload));
        $sig    = hash_hmac('sha256', "{$header}.{$body}", $this->settings->jwtSecret(), true);
        return "{$header}.{$body}." . self::b64UrlEncode($sig);
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidTokenException('malformed', 'Token sai định dạng');
        }
        [$h, $b, $s] = $parts;

        $expected = self::b64UrlEncode(
            hash_hmac('sha256', "{$h}.{$b}", $this->settings->jwtSecret(), true)
        );
        if (!hash_equals($expected, $s)) {
            throw new InvalidTokenException('invalid_signature');
        }

        $decoded = self::b64UrlDecode($b);
        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            throw new InvalidTokenException('invalid_payload');
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            throw new InvalidTokenException('expired', 'Token đã hết hạn');
        }
        return $payload;
    }

    public function issueAccess(int $userId, array $caps, array $extra = []): array
    {
        $now = time();
        $payload = array_merge([
            'iss'  => 'vie',
            'sub'  => (string) $userId,
            'iat'  => $now,
            'exp'  => $now + $this->settings->accessTtl(),
            'jti'  => wp_generate_uuid4(),
            'caps' => array_values($caps),
        ], $extra);

        return [
            'token'      => $this->encode($payload),
            'expires_in' => $this->settings->accessTtl(),
            'payload'    => $payload,
        ];
    }

    private static function b64UrlEncode(string $bin): string
    {
        return strtr(rtrim(base64_encode($bin), '='), '+/', '-_');
    }

    private static function b64UrlDecode(string $str): string
    {
        $remainder = strlen($str) % 4;
        if ($remainder !== 0) {
            $str .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($str, '-_', '+/')) ?: '';
    }
}
