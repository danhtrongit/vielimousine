<?php
declare(strict_types=1);

namespace Vie\Repository;

final class CustomerRepository extends AbstractRepository
{
    protected function tableName(): string
    {
        return 'vie_customer';
    }

    protected function fillable(): array
    {
        return [
            'phone', 'name', 'email', 'note',
            'vat_company_name', 'vat_tax_code', 'vat_address', 'vat_email',
            'booking_count',
        ];
    }

    protected function casts(): array
    {
        return [
            'id'            => 'int',
            'booking_count' => 'int',
        ];
    }

    protected function searchableColumns(): array
    {
        return ['phone', 'name', 'email'];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'DESC'];
    }

    public function availableSorts(): array
    {
        return ['created_at', 'name', 'booking_count'];
    }

    protected function filterConfig(): array
    {
        return [
            'has_vat'   => ['type' => 'bool', 'column' => 'vat_tax_code'],
            'date_from' => ['type' => 'date_from', 'column' => 'created_at'],
            'date_to'   => ['type' => 'date_to',   'column' => 'created_at'],
            'q'         => ['type' => 'search'],
        ];
    }

    public function findByPhone(string $phone): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE phone = %s LIMIT 1", $phone),
            ARRAY_A
        );
        return $row !== null ? $this->castRow($row) : null;
    }

    public function findOrCreateByPhone(string $phone, array $data): array
    {
        $normalized = self::normalizePhone($phone);
        $existing   = $this->findByPhone($normalized);

        if ($existing !== null) {
            $update = [];
            if (!empty($data['name']) && empty($existing['name'])) {
                $update['name'] = (string) $data['name'];
            }
            if (!empty($data['email']) && empty($existing['email'])) {
                $update['email'] = (string) $data['email'];
            }
            if ($update !== []) {
                return $this->update((int) $existing['id'], $update);
            }
            return $existing;
        }

        return $this->create([
            'phone'         => $normalized,
            'name'          => (string) ($data['name']  ?? ''),
            'email'         => $data['email'] ?? null,
            'booking_count' => 0,
        ]);
    }

    public static function normalizePhone(string $phone): string
    {
        $phone   = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits  = preg_replace('/\D+/', '', $phone) ?? '';
        return $hasPlus ? '+' . $digits : $digits;
    }
}
