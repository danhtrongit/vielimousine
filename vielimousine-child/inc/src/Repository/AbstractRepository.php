<?php
declare(strict_types=1);

namespace Vie\Repository;

use Vie\Support\QueryBuilder;

abstract class AbstractRepository
{
    abstract protected function tableName(): string;
    abstract protected function fillable(): array;
    abstract protected function casts(): array;
    abstract protected function searchableColumns(): array;
    abstract protected function defaultSort(): array;
    abstract protected function filterConfig(): array;

    public function availableSorts(): array
    {
        return ['created_at'];
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    public function findOrFail(int $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new RepositoryException("Record #{$id} not found in {$this->tableName()}");
        }
        return $row;
    }

    public function all(array $params = []): array
    {
        $qb = new QueryBuilder($this->table());

        if (isset($params['fields'])) {
            $requested = array_map('trim', explode(',', $params['fields']));
            $allowed   = array_merge(['id', 'created_at', 'updated_at'], $this->fillable());
            $columns   = array_values(array_intersect($requested, $allowed));
            if ($columns !== []) {
                $qb->select($columns);
            }
        }

        $filtersApplied = $this->applyFilters($qb, $params);
        $sortInfo       = $this->applySorting($qb, $params);
        $pagination     = $this->applyPagination($qb, $params);

        global $wpdb;

        $countQb = clone $qb;
        $countSql = $countQb->toCountSql();
        $total = (int) ($countQb->hasParams()
            ? $wpdb->get_var($wpdb->prepare($countSql, ...$countQb->getParams()))
            : $wpdb->get_var($countSql));

        $selectSql = $qb->toSelectSql();
        $rows = $qb->hasParams()
            ? $wpdb->get_results($wpdb->prepare($selectSql, ...$qb->getParams()), ARRAY_A)
            : $wpdb->get_results($selectSql, ARRAY_A);

        $rows = $rows ?: [];
        $data = array_map([$this, 'castRow'], $rows);

        $page      = $pagination['page'];
        $perPage   = $pagination['per_page'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data'            => $data,
            'pagination'      => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
                'has_next'    => $page < $totalPages,
                'has_prev'    => $page > 1,
            ],
            'sort'            => $sortInfo,
            'filters_applied' => $filtersApplied,
        ];
    }

    public function count(array $params = []): int
    {
        $qb = new QueryBuilder($this->table());
        $this->applyFilters($qb, $params);

        global $wpdb;
        $sql = $qb->toCountSql();
        return (int) ($qb->hasParams()
            ? $wpdb->get_var($wpdb->prepare($sql, ...$qb->getParams()))
            : $wpdb->get_var($sql));
    }

    public function create(array $data): array
    {
        global $wpdb;

        $insert = $this->filterFillable($data);
        $insert = $this->encodeJsonFields($insert);
        $insert['created_at'] = current_time('mysql');
        $insert['updated_at'] = current_time('mysql');

        $wpdb->insert($this->table(), $insert);

        if ($wpdb->insert_id === 0) {
            throw new RepositoryException("Insert failed: {$wpdb->last_error}");
        }

        return $this->findOrFail((int) $wpdb->insert_id);
    }

    public function update(int $id, array $data): array
    {
        $this->findOrFail($id);

        $update = $this->filterFillable($data);
        if ($update === []) {
            return $this->findOrFail($id);
        }

        $update = $this->encodeJsonFields($update);
        $update['updated_at'] = current_time('mysql');

        global $wpdb;
        $wpdb->update($this->table(), $update, ['id' => $id]);

        return $this->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        $this->findOrFail($id);

        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }

    public function exists(string $column, mixed $value, ?int $excludeId = null): bool
    {
        global $wpdb;
        if ($excludeId !== null) {
            $sql = $wpdb->prepare(
                "SELECT 1 FROM {$this->table()} WHERE {$column} = %s AND id != %d LIMIT 1",
                $value,
                $excludeId
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT 1 FROM {$this->table()} WHERE {$column} = %s LIMIT 1",
                $value
            );
        }
        return (bool) $wpdb->get_var($sql);
    }

    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . $this->tableName();
    }

    protected function applyFilters(QueryBuilder $qb, array $params): array
    {
        $applied = [];
        $config  = $this->filterConfig();

        foreach ($config as $key => $def) {
            if ($def['type'] === 'search') {
                if (!empty($params['q']) && $this->searchableColumns() !== []) {
                    $pattern = '%' . $params['q'] . '%';
                    $qb->whereOrLike($this->searchableColumns(), $pattern);
                    $applied['q'] = $params['q'];
                }
                continue;
            }

            if (!isset($params[$key]) || $params[$key] === '') {
                continue;
            }

            $column = $def['column'];
            $value  = $params[$key];

            switch ($def['type']) {
                case 'exact':
                    $qb->where($column, '=', $value);
                    $applied[$key] = $value;
                    break;

                case 'bool':
                    $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($boolVal !== null) {
                        $qb->where($column, '=', $boolVal ? 1 : 0);
                        $applied[$key] = $boolVal;
                    }
                    break;

                case 'in':
                    $values = is_array($value) ? $value : explode(',', (string) $value);
                    $values = array_filter(array_map('trim', $values), fn($v) => $v !== '');
                    if ($values !== []) {
                        $qb->whereIn($column, $values);
                        $applied[$key] = $values;
                    }
                    break;

                case 'range_min':
                    $qb->where($column, '>=', $value);
                    $applied[$key] = $value;
                    break;

                case 'range_max':
                    $qb->where($column, '<=', $value);
                    $applied[$key] = $value;
                    break;

                case 'date_from':
                    $qb->where($column, '>=', $value);
                    $applied[$key] = $value;
                    break;

                case 'date_to':
                    $qb->where($column, '<=', $value . ' 23:59:59');
                    $applied[$key] = $value;
                    break;
            }
        }

        return $applied;
    }

    protected function applySorting(QueryBuilder $qb, array $params): array
    {
        if (isset($params['sort']) && $params['sort'] !== '') {
            $sortParam = (string) $params['sort'];
            $direction = 'ASC';

            if (str_starts_with($sortParam, '-')) {
                $direction  = 'DESC';
                $sortParam  = substr($sortParam, 1);
            } elseif (isset($params['order'])) {
                $direction = strtoupper($params['order']) === 'DESC' ? 'DESC' : 'ASC';
            }

            if (in_array($sortParam, $this->availableSorts(), true)) {
                $qb->orderBy($sortParam, $direction);
                return ['field' => $sortParam, 'order' => strtolower($direction)];
            }
        }

        $default = $this->defaultSort();
        foreach ($default as $col => $dir) {
            $qb->orderBy($col, $dir);
        }
        $firstCol = array_key_first($default);
        return ['field' => $firstCol, 'order' => strtolower($default[$firstCol])];
    }

    /** Trần per_page cho list endpoint (chặn full-table scan). Repo con có thể nâng (vd báo cáo). */
    protected function maxPerPage(): int
    {
        return 100;
    }

    protected function applyPagination(QueryBuilder $qb, array $params): array
    {
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = min($this->maxPerPage(), max(1, (int) ($params['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $qb->limit($perPage);
        $qb->offset($offset);

        return ['page' => $page, 'per_page' => $perPage];
    }

    protected function castRow(array $row): array
    {
        $casts = $this->casts();
        foreach ($row as $key => &$value) {
            if ($value === null || !isset($casts[$key])) {
                continue;
            }
            $value = match ($casts[$key]) {
                'int'   => (int) $value,
                'float' => (float) $value,
                'bool'  => (bool) $value,
                'json'  => json_decode($value, true),
                default => $value,
            };
        }
        return $row;
    }

    protected function filterFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->fillable()));
    }

    protected function encodeJsonFields(array $data): array
    {
        $casts = $this->casts();
        foreach ($data as $key => &$value) {
            if (isset($casts[$key]) && $casts[$key] === 'json' && is_array($value)) {
                $value = wp_json_encode($value);
            }
        }
        return $data;
    }
}
