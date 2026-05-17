<?php
declare(strict_types=1);

namespace Vie\Service\Order;

use Vie\Repository\OrderRepository;

final class OrderCodeGenerator
{
    public function __construct(private readonly OrderRepository $orderRepo)
    {
    }

    /**
     * Format: `VIE{YYMMDD}{NNNN}{XXXX}` (length 16).
     *
     * - `NNNN`: sequence counter cho ngày (4 chữ số, dễ debug).
     * - `XXXX`: 4 ký tự HEX random (16 bit entropy).
     *
     * Tổng collision space mỗi ngày ≈ 9999 × 65536 ≈ 650 triệu — chống brute-force
     * enumeration (kết hợp với rate-limit ở /orders/lookup). Code trước đây
     * predictable (`VIE{ymd}{NNNN}`) chỉ có 9999 candidates/ngày.
     */
    public function next(): string
    {
        global $wpdb;
        $prefix = 'VIE' . wp_date('ymd');
        $table  = $wpdb->prefix . 'vie_order';

        for ($i = 0; $i < 5; $i++) {
            // Lấy counter cao nhất trong ngày (chỉ xét 4 chữ số sequence, bỏ qua suffix random).
            $max = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(SUBSTRING(code, 10, 4)) FROM {$table} WHERE code LIKE %s",
                $prefix . '%'
            ));

            $counter = $max === null ? 1 : ((int) $max) + 1;
            if ($counter > 9999) {
                // Quá 9999 đơn/ngày — fallback random 6 chars để chắc chắn unique.
                $rand = strtoupper(bin2hex(random_bytes(3)));
                $candidate = $prefix . $rand;
            } else {
                $rand = strtoupper(bin2hex(random_bytes(2)));
                $candidate = $prefix . str_pad((string) $counter, 4, '0', STR_PAD_LEFT) . $rand;
            }

            if ($this->orderRepo->findByCode($candidate) === null) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Cannot generate unique order code after 5 retries');
    }
}
