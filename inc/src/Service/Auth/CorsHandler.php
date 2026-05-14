<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

use Vie\Container;
use Vie\Service\Settings\AuthSettings;

final class CorsHandler
{
    public static function register(): void
    {
        $self = Container::get(self::class);
        add_filter('rest_pre_serve_request', [$self, 'sendHeaders'], 10, 4);
    }

    public function __construct(private readonly AuthSettings $settings)
    {
    }

    public function sendHeaders($served, $result, $request, $server): bool
    {
        if (!$this->settings->corsEnabled()) {
            return (bool) $served;
        }

        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $this->settings->corsAllowedOrigins();

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Idempotency-Key, X-Sepay-Signature');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Vary: Origin');
        }

        return (bool) $served;
    }
}
