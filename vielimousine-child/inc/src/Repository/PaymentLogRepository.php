<?php
declare(strict_types=1);

namespace Vie\Repository;

final class PaymentLogRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_payment_log';
    }

    /** Báo cáo cần kéo toàn bộ giao dịch trong khoảng (client tự tổng hợp) — nâng trần per_page. */
    protected function maxPerPage(): int
    {
        return 10000;
    }

    protected function fillable(): array
    {
        // SYSTEM-MANAGED FIELDS:
        //   - 'raw_payload' → chỉ SepayWebhook ghi (toàn bộ IPN payload).
        //                     PaymentEntryValidation KHÔNG list raw_payload → manual entries
        //                     từ admin sẽ bị Validator strip → không thể giả mạo gateway data.
        // 'gateway' + 'transaction_id' admin CÓ THỂ set (vd: ghi manual deposit kèm mã GD ngân hàng);
        // UNIQUE(gateway, transaction_id) constraint + idempotency pre-check trong PaymentLedger
        // chặn collision/replay.
        return [
            'order_id', 'type', 'amount', 'method',
            'gateway', 'transaction_id', 'note',
            'paid_at', 'created_by', 'raw_payload',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'          => 'int',
            'order_id'    => 'int',
            'amount'      => 'float',
            'created_by'  => 'int',
            'raw_payload' => 'json',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['transaction_id'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'amount'];
    }

    protected function filterConfig(): array
    {
        return [
            'order_id'  => ['type' => 'exact',     'column' => 'order_id'],
            'type'      => ['type' => 'in',        'column' => 'type'],
            'method'    => ['type' => 'in',        'column' => 'method'],
            'gateway'   => ['type' => 'exact',     'column' => 'gateway'],
            'date_from' => ['type' => 'date_from', 'column' => 'created_at'],
            'date_to'   => ['type' => 'date_to',   'column' => 'created_at'],
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
        throw new RepositoryException('Payment logs are immutable — update is not allowed');
    }

    public function delete(int $id): bool
    {
        throw new RepositoryException('Payment logs are immutable — delete is not allowed');
    }
}
