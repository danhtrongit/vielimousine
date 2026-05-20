<?php
declare(strict_types=1);

namespace Vie\Repository;

final class ActivityLogRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_activity_log';
    }

    protected function fillable(): array
    {
        return [
            'actor_user_id', 'entity_type', 'entity_id',
            'action', 'before_json', 'after_json',
            'ip', 'user_agent',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'             => 'int',
            'actor_user_id'  => 'int',
            'entity_id'      => 'int',
            'before_json'    => 'json',
            'after_json'     => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['entity_type', 'action'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'actor_user_id' => ['type' => 'exact',     'column' => 'actor_user_id'],
            'entity_type'   => ['type' => 'exact',     'column' => 'entity_type'],
            'entity_id'     => ['type' => 'exact',     'column' => 'entity_id'],
            'action'        => ['type' => 'in',        'column' => 'action'],
            'date_from'     => ['type' => 'date_from', 'column' => 'created_at'],
            'date_to'       => ['type' => 'date_to',   'column' => 'created_at'],
        ];
    }

    public function create(array $data): array
    {
        global $wpdb;

        $insert = $this->filterFillable($data);
        $insert = $this->encodeJsonFields($insert);
        $insert['created_at'] = current_time('mysql');

        $wpdb->insert($this->table(), $insert);

        if ($wpdb->insert_id === 0) {
            throw new RepositoryException("Insert failed: {$wpdb->last_error}");
        }

        return $this->findOrFail((int) $wpdb->insert_id);
    }

    public function update(int $id, array $data): array
    {
        throw new RepositoryException('Activity logs are immutable — update is not allowed');
    }

    public function delete(int $id): bool
    {
        throw new RepositoryException('Activity logs are immutable — delete is not allowed');
    }
}
