<?php
declare(strict_types=1);

namespace Vie\Service\Order;

use Vie\Repository\OrderRepository;

final class OrderCodeGenerator
{
    public function __construct(private readonly OrderRepository $orderRepo)
    {
    }

    public function next(): string
    {
        global $wpdb;
        $prefix = 'VIE' . wp_date('ymd');
        $table  = $wpdb->prefix . 'vie_order';

        for ($i = 0; $i < 5; $i++) {
            $max = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(code) FROM {$table} WHERE code LIKE %s",
                $prefix . '%'
            ));

            $counter = $max === null
                ? 1
                : ((int) substr((string) $max, -4)) + 1;

            if ($counter > 9999) {
                return $prefix . strtoupper(bin2hex(random_bytes(2)));
            }

            $candidate = $prefix . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            if ($this->orderRepo->findByCode($candidate) === null) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Cannot generate unique order code after 5 retries');
    }
}
