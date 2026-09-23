<?php
declare(strict_types=1);

namespace Vie\Repository;

use Vie\Validation\Schemas\BookingQuoteValidation;

final class BookingQuoteRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_booking_quote';
    }

    protected function fillable(): array
    {
        return [
            'public_id', 'code', 'sales_user_id', 'status',
            'customer_name', 'customer_phone', 'customer_email', 'title', 'image_url',
            'greeting', 'trip_start', 'trip_end', 'description', 'inclusions', 'exclusions', 'terms',
            'contact_name', 'contact_phone', 'contact_zalo', 'lines', 'discount',
            'deposit_type', 'deposit_value', 'subtotal', 'total', 'deposit_amount',
            'paid_amount', 'payment_review', 'valid_until', 'brand', 'published_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sales_user_id' => 'int',
            'lines' => 'json',
            'discount' => 'int',
            'deposit_value' => 'int',
            'subtotal' => 'int',
            'total' => 'int',
            'deposit_amount' => 'int',
            'paid_amount' => 'int',
            'payment_review' => 'bool',
            'brand' => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['code', 'customer_name', 'customer_phone', 'customer_email', 'title'];
    }

    protected function defaultSort(): array
    {
        return ['id' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['id', 'created_at', 'valid_until', 'total', 'code', 'status'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => ['type' => 'exact', 'column' => 'status'],
            'q' => ['type' => 'search'],
        ];
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1",
            $id
        ), ARRAY_A);
        if ($row === null && (string) ($wpdb->last_error ?? '') !== '') {
            throw new RepositoryException('Booking quote read failed');
        }
        return $row !== null ? $this->castRow($row) : null;
    }

    /** Own-scoped list with effective `expired` filtering and trustworthy pagination. */
    public function all(array $params = []): array
    {
        global $wpdb;
        $where  = [];
        $values = [];
        $filters = [];

        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            $where[] = '1 = 0';
        } elseif (user_can($userId, 'vie_view_all_booking_quotes')) {
            // No owner predicate for administrators.
        } elseif (user_can($userId, 'vie_view_own_booking_quotes')) {
            $where[] = 'sales_user_id = %d';
            $values[] = $userId;
        } else {
            $where[] = '1 = 0';
        }

        $qRaw = $params['q'] ?? '';
        $q = is_scalar($qRaw) ? trim((string) $qRaw) : '';
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(code LIKE %s OR customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s OR title LIKE %s)';
            array_push($values, $like, $like, $like, $like, $like);
            $filters['q'] = $q;
        }

        $statusRaw = $params['status'] ?? '';
        $status = is_scalar($statusRaw) ? trim((string) $statusRaw) : '';
        $now = current_time('mysql');
        if (in_array($status, ['draft', 'revoked'], true)) {
            $where[] = 'status = %s';
            $values[] = $status;
            $filters['status'] = $status;
        } elseif ($status === 'expired') {
            $where[] = "status = 'published' AND paid_amount = 0 AND valid_until IS NOT NULL AND valid_until <= %s";
            $values[] = $now;
            $filters['status'] = 'expired';
        } elseif ($status === 'published') {
            $where[] = "status = 'published' AND (paid_amount > 0 OR valid_until IS NULL OR valid_until > %s)";
            $values[] = $now;
            $filters['status'] = 'published';
        }

        $pageRaw = $params['page'] ?? 1;
        $perPageRaw = $params['per_page'] ?? 20;
        $page    = max(1, is_scalar($pageRaw) ? (int) $pageRaw : 1);
        $perPage = min(100, max(1, is_scalar($perPageRaw) ? (int) $perPageRaw : 20));
        $offset  = ($page - 1) * $perPage;
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', array_map(static fn(string $part): string => "({$part})", $where));

        $sortRaw = $params['sort'] ?? '-id';
        $sortParam = is_scalar($sortRaw) ? (string) $sortRaw : '-id';
        $direction = str_starts_with($sortParam, '-') ? 'DESC' : 'ASC';
        $sortField = ltrim($sortParam, '-');
        if (!in_array($sortField, $this->availableSorts(), true)) {
            $sortField = 'id';
            $direction = 'DESC';
        }
        if (isset($params['order']) && is_scalar($params['order'])) {
            $direction = strtoupper((string) $params['order']) === 'ASC' ? 'ASC' : 'DESC';
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table()}{$whereSql}";
        $selectSql = "SELECT * FROM {$this->table()}{$whereSql} ORDER BY {$sortField} {$direction} LIMIT {$perPage} OFFSET {$offset}";
        $countValue = $values === [] ? $wpdb->get_var($countSql) : $wpdb->get_var($wpdb->prepare($countSql, ...$values));
        if ($countValue === null && (string) ($wpdb->last_error ?? '') !== '') {
            throw new RepositoryException('Booking quote count query failed');
        }
        $total = (int) $countValue;
        $rows = $values === []
            ? $wpdb->get_results($selectSql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($selectSql, ...$values), ARRAY_A);
        if (!is_array($rows)) {
            throw new RepositoryException('Booking quote list query failed');
        }

        $totalPages = (int) ceil($total / $perPage);
        return [
            'data' => array_map([$this, 'castRow'], $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
            'sort' => ['field' => $sortField, 'order' => strtolower($direction)],
            'filters_applied' => $filters,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE public_id = %s LIMIT 1",
            $publicId
        ), ARRAY_A);
        if ($row === null && (string) ($wpdb->last_error ?? '') !== '') {
            throw new RepositoryException('Booking quote public read failed');
        }
        return $row !== null ? $this->castRow($row) : null;
    }

    public function lockForUpdate(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1 FOR UPDATE",
            $id
        ), ARRAY_A);
        if ($row === null && (string) ($wpdb->last_error ?? '') !== '') {
            throw new RepositoryException('Booking quote lock failed');
        }
        return $row !== null ? $this->castRow($row) : null;
    }

    public function lockForUpdateByPublicId(string $publicId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE public_id = %s LIMIT 1 FOR UPDATE",
            $publicId
        ), ARRAY_A);
        if ($row === null && (string) ($wpdb->last_error ?? '') !== '') {
            throw new RepositoryException('Booking quote public lock failed');
        }
        return $row !== null ? $this->castRow($row) : null;
    }

    public function canUserAccess(int $userId, array $quote): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (user_can($userId, 'vie_view_all_booking_quotes')) {
            return true;
        }
        return user_can($userId, 'vie_view_own_booking_quotes')
            && (int) ($quote['sales_user_id'] ?? 0) === $userId;
    }

    public function create(array $data): array
    {
        global $wpdb;
        $insert = $this->encodeJsonFields($this->filterFillable($data));
        $insert['created_at'] = current_time('mysql');
        $insert['updated_at'] = current_time('mysql');
        $result = $wpdb->insert($this->table(), $insert);
        if ($result === false || (int) $wpdb->insert_id <= 0) {
            throw new RepositoryException('Booking quote insert failed');
        }
        return $this->findOrFail((int) $wpdb->insert_id);
    }

    /** Direct repository updates are draft-only; financial state has a separate method. */
    public function update(int $id, array $data): array
    {
        return $this->updateDraftChecked($id, $data);
    }

    public function updateDraftChecked(int $id, array $data): array
    {
        $allowed = array_diff($this->fillable(), [
            'public_id', 'code', 'sales_user_id', 'status', 'paid_amount', 'payment_review', 'brand', 'published_at',
        ]);
        $safe = array_intersect_key($data, array_flip($allowed));
        $this->checkedConditionalUpdate($id, 'draft', $safe, false);
        return $this->findOrFail($id);
    }

    public function publishChecked(int $id, array $snapshot): array
    {
        $allowed = ['brand', 'subtotal', 'discount', 'total', 'deposit_amount', 'published_at'];
        $safe = array_intersect_key($snapshot, array_flip($allowed));
        $safe['status'] = 'published';
        $this->checkedConditionalUpdate($id, 'draft', $safe, true);
        return $this->findOrFail($id);
    }

    public function revokeChecked(int $id): array
    {
        $this->checkedConditionalUpdate($id, 'published', ['status' => 'revoked'], true);
        return $this->findOrFail($id);
    }

    /** Caller must hold a quote row lock in an active transaction. */
    public function updateFinancialStateChecked(int $id, array $patch): array
    {
        $safe = array_intersect_key($patch, array_flip(['paid_amount', 'payment_review']));
        if (array_key_exists('paid_amount', $safe)) {
            if (!is_int($safe['paid_amount']) || $safe['paid_amount'] < 0 || $safe['paid_amount'] > BookingQuoteValidation::MAX_MONEY) {
                throw new RepositoryException('Invalid quote paid amount');
            }
        }
        if (array_key_exists('payment_review', $safe)) {
            $safe['payment_review'] = !empty($safe['payment_review']) ? 1 : 0;
        }
        if ($safe === []) {
            throw new RepositoryException('No financial state supplied');
        }
        $this->checkedUpdateById($id, $safe);
        return $this->findOrFail($id);
    }

    private function checkedConditionalUpdate(int $id, string $expectedStatus, array $patch, bool $mustChange): void
    {
        if ($patch === []) {
            return;
        }
        global $wpdb;
        [$setSql, $values] = $this->buildSet($patch);
        $values[] = $id;
        $values[] = $expectedStatus;
        $sql = "UPDATE {$this->table()} SET {$setSql} WHERE id = %d AND status = %s";
        $affected = $wpdb->query($wpdb->prepare($sql, ...$values));
        if ($affected === false) {
            throw new RepositoryException('Booking quote update failed');
        }
        if ($mustChange && (int) $affected !== 1) {
            throw new RepositoryException('Booking quote state changed concurrently');
        }
    }

    private function checkedUpdateById(int $id, array $patch): void
    {
        global $wpdb;
        [$setSql, $values] = $this->buildSet($patch);
        $values[] = $id;
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table()} SET {$setSql} WHERE id = %d",
            ...$values
        ));
        if ($affected === false) {
            throw new RepositoryException('Booking quote financial update failed');
        }
    }

    /** @return array{0:string,1:array} */
    private function buildSet(array $patch): array
    {
        $patch = $this->encodeJsonFields($patch);
        $patch['updated_at'] = current_time('mysql');
        $parts = [];
        $values = [];
        foreach ($patch as $column => $value) {
            if ($value === null) {
                $parts[] = "{$column} = NULL";
                continue;
            }
            $parts[] = "{$column} = " . (is_int($value) || is_bool($value) ? '%d' : '%s');
            $values[] = $value;
        }
        return [implode(', ', $parts), $values];
    }
}
