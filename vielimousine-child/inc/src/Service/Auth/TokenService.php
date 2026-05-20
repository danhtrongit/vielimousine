<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

use Vie\Repository\ActivityLogRepository;
use Vie\Repository\TokenRepository;
use Vie\Service\HookRegistry;
use Vie\Service\Settings\AuthSettings;

final class TokenService
{
    public function __construct(
        private readonly TokenRepository $tokenRepo,
        private readonly AuthSettings $settings,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    public function issueRefresh(int $userId, ?string $ip, ?string $ua, ?string $family = null): array
    {
        $raw     = bin2hex(random_bytes(48));
        $hash    = hash('sha256', $raw);
        $family ??= wp_generate_uuid4();
        $expires = (new \DateTimeImmutable())->modify('+' . $this->settings->refreshTtl() . ' seconds');

        $this->tokenRepo->create([
            'user_id'    => $userId,
            'hash'       => $hash,
            'family'     => $family,
            'ip'         => $ip,
            'ua'         => $ua !== null ? substr($ua, 0, 500) : null,
            'expires_at' => $expires->format('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);

        return [
            'raw'        => $raw,
            'hash'       => $hash,
            'family'     => $family,
            'expires_at' => $expires,
        ];
    }

    public function rotateRefresh(string $rawRefresh, ?string $ip, ?string $ua): array
    {
        $hash = hash('sha256', $rawRefresh);
        $row  = $this->tokenRepo->findByHash($hash);

        if ($row === null) {
            throw new InvalidTokenException('not_found');
        }

        $userId = (int) $row['user_id'];
        $family = (string) $row['family'];

        if (!empty($row['revoked_at'])) {
            $revokedCount = $this->tokenRepo->revokeFamily($family);
            $this->logActivity('token_reuse_detected', $userId, [
                'family'        => $family,
                'revoked_count' => $revokedCount,
            ], $ip, $ua);
            do_action(HookRegistry::TOKEN_REUSE_DETECTED, $userId, $family);
            throw new TokenReuseException(['family' => $family, 'user_id' => $userId]);
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            throw new InvalidTokenException('expired');
        }

        $this->tokenRepo->revoke((int) $row['id']);
        $new = $this->issueRefresh($userId, $ip, $ua, $family);

        $this->logActivity('refresh_success', $userId, [
            'family' => $family,
        ], $ip, $ua);

        do_action(HookRegistry::TOKEN_REFRESH_ISSUED, $userId, $family);

        return [
            'user_id' => $userId,
            'family'  => $family,
            'new'     => $new,
        ];
    }

    public function revokeOneByRaw(string $rawRefresh, int $actorUserId): void
    {
        $hash = hash('sha256', $rawRefresh);
        $row  = $this->tokenRepo->findByHash($hash);
        if ($row === null) {
            return;
        }
        if (empty($row['revoked_at'])) {
            $this->tokenRepo->revoke((int) $row['id']);
            do_action(HookRegistry::TOKEN_REVOKED, (int) $row['user_id'], (string) $row['family'], 'logout');
        }
    }

    public function revokeAllForUser(int $userId): int
    {
        $active = $this->tokenRepo->listActiveByUser($userId);
        $count  = 0;
        foreach ($active as $row) {
            $this->tokenRepo->revoke((int) $row['id']);
            $count++;
        }
        return $count;
    }

    private function logActivity(string $action, int $userId, array $after, ?string $ip, ?string $ua): void
    {
        $this->activityRepo->create([
            'actor_user_id' => $userId,
            'entity_type'   => 'auth_token',
            'entity_id'     => 0,
            'action'        => $action,
            'before_json'   => null,
            'after_json'    => $after,
            'ip'            => $ip,
            'user_agent'    => $ua !== null ? substr($ua, 0, 500) : null,
        ]);
    }
}
