<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

use Vie\Container;

final class AuthMiddleware
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    /**
     * Parse Authorization: Bearer X, validate JWT, set current_user.
     */
    public function authenticate(\WP_REST_Request $request): bool
    {
        $auth = (string) $request->get_header('Authorization');
        if ($auth === '') {
            // Fallback: some servers don't pass Authorization through
            $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        }
        if (!str_starts_with($auth, 'Bearer ')) {
            return false;
        }
        $token = substr($auth, 7);

        try {
            $payload = $this->jwt->decode($token);
        } catch (InvalidTokenException $e) {
            return false;
        }

        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId === 0) {
            return false;
        }
        wp_set_current_user($userId);
        return true;
    }

    public static function requireCap(string $cap): callable
    {
        return static function (\WP_REST_Request $request) use ($cap): bool {
            $self = Container::get(self::class);
            $self->authenticate($request);
            return current_user_can($cap);
        };
    }

    public static function requireLogin(): callable
    {
        return static function (\WP_REST_Request $request): bool {
            $self = Container::get(self::class);
            return $self->authenticate($request) && is_user_logged_in();
        };
    }
}
