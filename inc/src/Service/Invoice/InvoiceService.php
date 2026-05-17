<?php
declare(strict_types=1);

namespace Vie\Service\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;
use Vie\Container;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Support\NumberToText;

final class InvoiceService
{
    public function __construct(
        private OrderRepository $orders,
        private OrderItemRepository $items,
        private InvoiceSettings $settings,
    ) {}

    /**
     * Return order's invoice_number; allocate + persist if empty.
     *
     * Khoá `LOCK TABLES vie_order WRITE` quanh `allocateNumber()` + `UPDATE`
     * để đảm bảo số hoá đơn LIÊN TỤC, KHÔNG LỖ — yêu cầu pháp lý VN cho
     * hoá đơn điện tử tự phát hành (NĐ 123/2020, TT 78).
     *
     * Race trước đây: 2 request đồng thời có thể `allocateNumber()` ra 2 số
     * khác nhau nhưng chỉ 1 UPDATE thắng → số kia bị "lỗ" trong dải.
     */
    public function getOrAssignNumber(int $orderId): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'vie_order';

        // Fast-path: nếu đã có số rồi, không cần LOCK.
        $existing = (string) $wpdb->get_var(
            $wpdb->prepare("SELECT invoice_number FROM {$table} WHERE id = %d", $orderId)
        );
        if ($existing !== '') {
            return $existing;
        }

        $wpdb->query("LOCK TABLES {$table} WRITE");
        try {
            // Re-check sau khi lock: trong race, request kia có thể đã ghi số xong.
            $existing = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT invoice_number FROM {$table} WHERE id = %d", $orderId)
            );
            if ($existing !== '') {
                return $existing;
            }

            $number = $this->settings->allocateNumber();
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET invoice_number = %s WHERE id = %d AND (invoice_number IS NULL OR invoice_number = '')",
                    $number,
                    $orderId
                )
            );
            if ($result === false) {
                throw new \RuntimeException('Invoice number UPDATE failed: ' . $wpdb->last_error);
            }
            return $number;
        } finally {
            $wpdb->query('UNLOCK TABLES');
        }
    }

    /**
     * Render order to PDF binary.
     *
     * @param string $template 'receipt' | 'tax_invoice'
     */
    public function renderPdf(int $orderId, string $template): string
    {
        if (!in_array($template, InvoiceSettings::TEMPLATES, true)) {
            throw new \InvalidArgumentException("Invalid template: {$template}");
        }

        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new \RuntimeException("Order #{$orderId} not found");
        }

        $itemsResp = $this->items->all([
            'order_id' => $orderId,
            'per_page' => 200,
        ]);
        $items = $itemsResp['data'] ?? [];

        // Enrich items with hotel name/address from wp_vie_hotel.
        $hotelMap = $this->fetchHotelMap(array_map(static fn($i) => (int) $i['hotel_id'], $items));
        foreach ($items as &$it) {
            $h = $hotelMap[(int) $it['hotel_id']] ?? null;
            $it['hotel_name']    = $h['name']    ?? $it['name'];
            $it['hotel_address'] = $h['address'] ?? '';
        }
        unset($it);

        $invoiceNumber = $this->getOrAssignNumber($orderId);
        $order['invoice_number'] = $invoiceNumber;

        $cfg = $this->settings->all();

        $html = $this->renderTemplate($template, $order, $items, $cfg);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($template === 'receipt' ? 'A5' : 'A4', 'portrait');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * @param array<int,int> $hotelIds
     * @return array<int,array{name:string,address:string}>
     */
    private function fetchHotelMap(array $hotelIds): array
    {
        $hotelIds = array_values(array_unique(array_filter($hotelIds, static fn($id) => $id > 0)));
        if ($hotelIds === []) return [];

        global $wpdb;
        $place = implode(',', array_fill(0, count($hotelIds), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, address FROM {$wpdb->prefix}vie_hotel WHERE id IN ({$place})",
                ...$hotelIds
            ),
            ARRAY_A
        );
        $map = [];
        foreach ((array) $rows as $r) {
            $map[(int) $r['id']] = [
                'name'    => (string) ($r['name'] ?? ''),
                'address' => (string) ($r['address'] ?? ''),
            ];
        }
        return $map;
    }

    private function renderTemplate(string $template, array $order, array $items, array $cfg): string
    {
        $file = VIE_CHILD_PATH . '/inc/templates/invoice/' . ($template === 'receipt' ? 'receipt.php' : 'tax-invoice.php');
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$file}");
        }

        $ctx = [
            'order'        => $order,
            'items'        => $items,
            'cfg'          => $cfg,
            'money'        => static fn(int|float $n): string => number_format((float) $n, 0, ',', '.') . ' ₫',
            'amountInWords'=> static fn(int $n): string => NumberToText::vnd($n),
            'esc'          => static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];

        ob_start();
        (static function (string $__file, array $__ctx): void {
            extract($__ctx, EXTR_SKIP);
            include $__file;
        })($file, $ctx);
        return (string) ob_get_clean();
    }
}
