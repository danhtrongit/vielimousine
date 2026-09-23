<?php
declare(strict_types=1);

namespace Vie\Repository;

/** Durable inbox for SePay delivery ids. */
final class SepayWebhookEventRepository
{
    public function findBySepayId(string $sepayId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_sepay_webhook_event';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE sepay_id = %s LIMIT 1",
            $sepayId
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * Insert before dispatch. The unique key is the idempotency boundary.
     * @return array{event:array,duplicate:bool}
     */
    public function create(string $sepayId, string $rawPayload, array $payload): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_sepay_webhook_event';
        $code = trim((string) ($payload['code'] ?? ''));
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert($table, [
            'sepay_id'       => $sepayId,
            'object_code'    => $code !== '' ? $code : null,
            'raw_payload'    => $rawPayload,
            'payload_hash'   => hash('sha256', $rawPayload),
            'status'         => 'pending',
            'review_reason'  => null,
            'error_message'  => null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        if ($inserted === false) {
            $existing = $this->findBySepayId($sepayId);
            if ($existing !== null) {
                return ['event' => $existing, 'duplicate' => true];
            }
            throw new \RuntimeException('Unable to persist SePay webhook event');
        }

        $event = $this->findBySepayId($sepayId);
        if ($event === null) {
            throw new \RuntimeException('Unable to read persisted SePay webhook event');
        }
        return ['event' => $event, 'duplicate' => false];
    }

    public function markProcessed(int $id): void
    {
        $this->update($id, 'processed', null, null);
    }

    public function markReview(int $id, string $reason): void
    {
        $this->update($id, 'review', $reason, null);
    }

    public function markFailed(int $id, string $message): void
    {
        $this->update($id, 'failed', null, mb_substr($message, 0, 500));
    }

    /** @return array<int,array<string,mixed>> */
    public function pending(int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_sepay_webhook_event';
        $limit = max(1, min(200, $limit));
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status IN ('pending','failed')
               AND updated_at <= UTC_TIMESTAMP()
             ORDER BY id ASC LIMIT {$limit}",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    private function update(int $id, string $status, ?string $reason, ?string $error): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_sepay_webhook_event';
        $result = $wpdb->update($table, [
            'status'        => $status,
            'review_reason' => $reason,
            'error_message' => $error,
            'updated_at'    => current_time('mysql', true),
        ], ['id' => $id], ['%s', '%s', '%s', '%s'], ['%d']);
        if ($result === false) {
            throw new \RuntimeException('Unable to update SePay webhook event');
        }
    }
}
