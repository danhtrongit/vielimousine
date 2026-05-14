<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

use Vie\DTO\LoginRequest;
use Vie\Repository\ActivityLogRepository;
use Vie\Service\HookRegistry;
use Vie\Service\Settings\AuthSettings;

final class AuthService
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly TokenService $tokenService,
        private readonly AuthSettings $settings,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    public function login(LoginRequest $req): array
    {
        $user = $this->authenticateUser($req->username, $req->password);
        if ($user === null) {
            $this->logActivity('login_failed', 0, [
                'username' => $req->username,
            ], $req->ip, $req->userAgent);
            throw new InvalidCredentialsException();
        }

        $this->logActivity('login_success', (int) $user->ID, [
            'username' => $user->user_login,
        ], $req->ip, $req->userAgent);

        return $this->issueTokens($user, $req->ip, $req->userAgent);
    }

    public function refresh(string $rawRefresh, ?string $ip, ?string $ua): array
    {
        $rotation = $this->tokenService->rotateRefresh($rawRefresh, $ip, $ua);
        $user     = get_user_by('id', $rotation['user_id']);
        if (!$user) {
            throw new InvalidTokenException('user_deleted');
        }
        return $this->buildTokens($user, $rotation['new']);
    }

    public function logout(string $rawRefresh, int $actorUserId): void
    {
        $this->tokenService->revokeOneByRaw($rawRefresh, $actorUserId);
        $this->logActivity('logout', $actorUserId, [], null, null);
    }

    public function me(int $userId): array
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            throw new InvalidTokenException('user_deleted');
        }
        return $this->userPayload($user);
    }

    private function issueTokens(\WP_User $user, ?string $ip, ?string $ua): array
    {
        $refresh = $this->tokenService->issueRefresh((int) $user->ID, $ip, $ua);
        return $this->buildTokens($user, $refresh);
    }

    private function buildTokens(\WP_User $user, array $refresh): array
    {
        $caps   = $this->extractCaps($user);
        $access = $this->jwt->issueAccess((int) $user->ID, $caps, [
            'email' => $user->user_email,
            'name'  => $user->display_name,
            'role'  => $user->roles[0] ?? null,
        ]);

        do_action(HookRegistry::USER_LOGGED_IN, (int) $user->ID, $access['token']);

        return [
            'access_token'       => $access['token'],
            'expires_in'         => $access['expires_in'],
            'token_type'         => 'Bearer',
            'refresh_token_raw'  => $refresh['raw'],
            'refresh_family'     => $refresh['family'],
            'refresh_expires_at' => $refresh['expires_at'],
            'user'               => $this->userPayload($user),
        ];
    }

    private function userPayload(\WP_User $user): array
    {
        $managedHotels = get_user_meta((int) $user->ID, 'vie_managed_hotels', true);
        return [
            'id'             => (int) $user->ID,
            'username'       => $user->user_login,
            'display_name'   => $user->display_name,
            'email'          => $user->user_email,
            'roles'          => array_values($user->roles),
            'caps'           => $this->extractCaps($user),
            'managed_hotels' => is_array($managedHotels) ? array_map('intval', $managedHotels) : [],
        ];
    }

    private function extractCaps(\WP_User $user): array
    {
        $caps = [];
        foreach ($user->allcaps as $cap => $granted) {
            if ($granted && str_starts_with((string) $cap, 'vie_')) {
                $caps[] = $cap;
            }
        }
        return array_values(array_unique($caps));
    }

    /**
     * Username hoặc email + password → WP_User|null.
     * Bypass wp_authenticate filter chain (parent theme có CSRF check) — chỉ check password crypto.
     */
    private function authenticateUser(string $usernameOrEmail, string $password): ?\WP_User
    {
        $user = get_user_by('login', $usernameOrEmail);
        if (!$user && is_email($usernameOrEmail)) {
            $user = get_user_by('email', $usernameOrEmail);
        }
        if (!$user) {
            return null;
        }
        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            return null;
        }
        return $user;
    }

    private function logActivity(string $action, int $userId, array $after, ?string $ip, ?string $ua): void
    {
        $this->activityRepo->create([
            'actor_user_id' => $userId,
            'entity_type'   => 'auth',
            'entity_id'     => 0,
            'action'        => $action,
            'before_json'   => null,
            'after_json'    => $after,
            'ip'            => $ip,
            'user_agent'    => $ua !== null ? substr($ua, 0, 500) : null,
        ]);
    }
}
