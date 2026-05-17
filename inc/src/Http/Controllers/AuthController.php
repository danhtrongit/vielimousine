<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Cron\SecuritySweep;
use Vie\DTO\LoginRequest;
use Vie\Service\Auth\AuthService;
use Vie\Service\Auth\InvalidCredentialsException;
use Vie\Service\Auth\InvalidTokenException;
use Vie\Service\Auth\TokenReuseException;
use Vie\Service\Settings\AuthSettings;
use Vie\Support\ClientIp;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\LoginValidation;

final class AuthController
{
    public static function login(\WP_REST_Request $request): \WP_REST_Response
    {
        $ip = ClientIp::clientIp();
        if ($ip !== '' && SecuritySweep::isBlocked($ip)) {
            return ResponseEnvelope::error([
                ['code' => 'ip_blocked', 'field' => null, 'message' => 'Truy cập tạm thời bị khóa do quá nhiều lần đăng nhập sai.'],
            ], 429);
        }

        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, LoginValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        try {
            $req = LoginRequest::fromArray(
                $v->validated(),
                $ip !== '' ? $ip : null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            $tokens = Container::get(AuthService::class)->login($req);
            return self::respondWithTokens($tokens, 200);
        } catch (InvalidCredentialsException $e) {
            return ResponseEnvelope::error([
                ['code' => 'invalid_credentials', 'field' => null, 'message' => $e->getMessage()],
            ], 401);
        }
    }

    public static function refresh(\WP_REST_Request $request): \WP_REST_Response
    {
        $raw = self::readRefreshCookie();
        if ($raw === null) {
            return ResponseEnvelope::error([
                ['code' => 'no_refresh', 'field' => null, 'message' => 'Thiếu refresh token'],
            ], 401);
        }

        try {
            $ip     = ClientIp::clientIp();
            $tokens = Container::get(AuthService::class)->refresh(
                $raw,
                $ip !== '' ? $ip : null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            );
            return self::respondWithTokens($tokens, 200);
        } catch (TokenReuseException $e) {
            self::clearRefreshCookie();
            return ResponseEnvelope::error([
                ['code' => 'token_reuse_detected', 'field' => null, 'message' => $e->getMessage()],
            ], 401);
        } catch (InvalidTokenException $e) {
            self::clearRefreshCookie();
            return ResponseEnvelope::error([
                ['code' => 'invalid_token', 'field' => null, 'message' => $e->getMessage()],
            ], 401);
        }
    }

    public static function logout(\WP_REST_Request $request): \WP_REST_Response
    {
        $raw = self::readRefreshCookie();
        if ($raw !== null) {
            try {
                Container::get(AuthService::class)->logout($raw, (int) get_current_user_id());
            } catch (\Throwable $e) {
                // ignore — luôn clear cookie + 200
            }
        }
        self::clearRefreshCookie();
        return ResponseEnvelope::success(['logged_out' => true]);
    }

    public static function me(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) get_current_user_id();
        if ($userId === 0) {
            return ResponseEnvelope::error([
                ['code' => 'not_authenticated', 'field' => null, 'message' => 'Chưa đăng nhập'],
            ], 401);
        }
        try {
            $data = Container::get(AuthService::class)->me($userId);
            return ResponseEnvelope::success($data);
        } catch (InvalidTokenException $e) {
            return ResponseEnvelope::error([
                ['code' => 'user_not_found', 'field' => null, 'message' => $e->getMessage()],
            ], 401);
        }
    }

    private static function respondWithTokens(array $tokens, int $status): \WP_REST_Response
    {
        $settings = Container::get(AuthSettings::class);
        /** @var \DateTimeImmutable $expiresAt */
        $expiresAt = $tokens['refresh_expires_at'];

        // Set HttpOnly refresh cookie. PHP setcookie() phải gọi trước header — nhưng WP REST cho phép vì response chưa flush.
        if (!headers_sent()) {
            setcookie(
                $settings->refreshCookieName(),
                (string) $tokens['refresh_token_raw'],
                [
                    'expires'  => $expiresAt->getTimestamp(),
                    'path'     => $settings->refreshCookiePath(),
                    'domain'   => '',
                    'secure'   => ClientIp::isSecure(),
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]
            );
        }

        return ResponseEnvelope::success([
            'access_token' => $tokens['access_token'],
            'expires_in'   => $tokens['expires_in'],
            'token_type'   => $tokens['token_type'],
            'user'         => $tokens['user'],
        ], [], $status);
    }

    private static function readRefreshCookie(): ?string
    {
        $settings = Container::get(AuthSettings::class);
        $name     = $settings->refreshCookieName();
        $value    = $_COOKIE[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    private static function clearRefreshCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $settings = Container::get(AuthSettings::class);
        setcookie(
            $settings->refreshCookieName(),
            '',
            [
                'expires'  => time() - 3600,
                'path'     => $settings->refreshCookiePath(),
                'domain'   => '',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
