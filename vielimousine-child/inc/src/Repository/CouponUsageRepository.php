<?php
declare(strict_types=1);

namespace Vie\Repository;

final class CouponUsageRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_coupon_usage';
    }

    protected function fillable(): array
    {
        return [
            'coupon_id', 'order_id', 'user_email', 'user_phone', 'discount',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'        => 'int',
            'coupon_id' => 'int',
            'order_id'  => 'int',
            'discount'  => 'float',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['user_email'];
    }

    protected function defaultSort(): array
    {
        return ['used_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['used_at'];
    }

    protected function filterConfig(): array
    {
        return [
            'coupon_id' => ['type' => 'exact', 'column' => 'coupon_id'],
            'order_id'  => ['type' => 'exact', 'column' => 'order_id'],
        ];
    }

    /**
     * Đếm số lượt đã dùng mã theo danh tính khách (SĐT hoặc email).
     *
     * Khớp cả hai vì dữ liệu cũ có thể chỉ có email (email là tùy chọn ở form
     * đặt phòng). Chuỗi rỗng bị loại để các row thiếu dữ liệu không bị tính
     * thành "cùng một khách".
     */
    public function countForIdentity(int $couponId, ?string $phone, ?string $email): int
    {
        $phone = trim((string) $phone);
        $email = trim((string) $email);
        if ($phone === '' && $email === '') {
            return 0;
        }

        $conditions = [];
        $params     = [$couponId];
        if ($phone !== '') {
            $conditions[] = 'user_phone = %s';
            $params[]     = $phone;
        }
        if ($email !== '') {
            $conditions[] = 'user_email = %s';
            $params[]     = $email;
        }

        global $wpdb;
        $sql = "SELECT COUNT(*) FROM {$this->table()} WHERE coupon_id = %d AND (" . implode(' OR ', $conditions) . ')';

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public function create(array $data): array
    {
        global $wpdb;

        $insert = $this->filterFillable($data);
        $insert['used_at'] = current_time('mysql');

        $wpdb->insert($this->table(), $insert);

        if ($wpdb->insert_id === 0) {
            throw new RepositoryException("Insert failed: {$wpdb->last_error}");
        }

        return $this->findOrFail((int) $wpdb->insert_id);
    }

    public function update(int $id, array $data): array
    {
        throw new RepositoryException('Coupon usage records cannot be updated');
    }

    public function delete(int $id): bool
    {
        throw new RepositoryException('Coupon usage records cannot be deleted');
    }
}
