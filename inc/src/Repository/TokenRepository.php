<?php
declare(strict_types=1);

namespace Vie\Repository;

final class TokenRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_token';
    }

    protected function fillable(): array
    {
        // SYSTEM-MANAGED FIELDS (chỉ TokenService được phép write):
        //   - 'hash'        → sha256 của refresh token raw (TokenService::issueRefresh)
        //   - 'family'      → UUID rotation lineage (TokenService quản lý)
        //   - 'revoked_at'  → set bởi TokenRepository::revoke / revokeFamily
        // TokenController chỉ register GET + DELETE — KHÔNG có route POST/PUT exposes
        // các fields này. Validation layer cũng không list chúng (không có Validation class
        // cho /tokens vì không có write endpoint).
        return [
            'user_id', 'hash', 'family',
            'ip', 'ua', 'expires_at', 'revoked_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'      => 'int',
            'user_id' => 'int',
        ];
    }

    protected function searchableColumns(): array
    {
        return [];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'expires_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'user_id' => ['type' => 'exact', 'column' => 'user_id'],
            'family'  => ['type' => 'exact', 'column' => 'family'],
        ];
    }

    public function create(array $data): array
    {
        global $wpdb;

        $insert = $this->filterFillable($data);
        $insert['created_at'] = current_time('mysql');

        $wpdb->insert($this->table(), $insert);

        if ($wpdb->insert_id === 0) {
            throw new RepositoryException("Insert failed: {$wpdb->last_error}");
        }

        return $this->findOrFail((int) $wpdb->insert_id);
    }

    public function findByHash(string $hash): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE hash = %s LIMIT 1", $hash),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    public function revoke(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table(),
            ['revoked_at' => current_time('mysql')],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
    }

    public function revokeFamily(string $family): int
    {
        global $wpdb;
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET revoked_at = %s WHERE family = %s AND revoked_at IS NULL",
            current_time('mysql'),
            $family
        ));
        return (int) $result;
    }

    public function listActiveByUser(int $userId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE user_id = %d AND revoked_at IS NULL AND expires_at > %s",
                $userId,
                current_time('mysql')
            ),
            ARRAY_A
        );
        return array_map([$this, 'castRow'], $rows ?: []);
    }
}
