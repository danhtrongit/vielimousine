<?php
declare(strict_types=1);

namespace Vie\Service\Settings;

final class AuthSettings
{
    public function jwtSecret(): string
    {
        $secret = (string) get_option('vie_jwt_secret', '');
        if ($secret === '') {
            $secret = base64_encode(random_bytes(32));
            update_option('vie_jwt_secret', $secret, false);
        }
        return $secret;
    }

    public function accessTtl(): int
    {
        return max(60, (int) get_option('vie_auth_access_ttl', 900));
    }

    public function refreshTtl(): int
    {
        return max(3600, (int) get_option('vie_auth_refresh_ttl', 2592000));
    }

    public function corsAllowedOrigins(): array
    {
        $raw = get_option('vie_cors_origins', '');
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        $list = array_filter(array_map('trim', explode(',', (string) $raw)));
        return array_values($list);
    }

    public function corsEnabled(): bool
    {
        return (bool) get_option('vie_cors_enabled', false);
    }

    public function refreshCookieName(): string
    {
        return 'vie_refresh';
    }

    public function refreshCookiePath(): string
    {
        return '/wp-json/' . VIE_API_NAMESPACE . '/auth/';
    }

    public function update(array $values): void
    {
        $map = [
            'jwt_secret'      => 'vie_jwt_secret',
            'access_ttl'      => 'vie_auth_access_ttl',
            'refresh_ttl'     => 'vie_auth_refresh_ttl',
            'cors_enabled'    => 'vie_cors_enabled',
            'cors_origins'    => 'vie_cors_origins',
        ];
        foreach ($map as $key => $option) {
            if (array_key_exists($key, $values)) {
                update_option($option, $values[$key], false);
            }
        }
    }
}
