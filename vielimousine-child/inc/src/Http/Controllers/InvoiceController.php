<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\Invoice\InvoiceService;
use Vie\Service\Settings\InvoiceSettings;
use Vie\Support\NumberToText;
use Vie\Support\CostVisibility;
use Vie\Support\ResponseEnvelope;

final class InvoiceController
{
    /**
     * Return invoice render data as JSON — order, items (with hotel names),
     * settings snapshot, assigned invoice_number, and amount-in-words string.
     * Used by Vue-based InvoicePreview component (window.print for PDF).
     */
    public static function data(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId  = (int) $request['id'];
        $template = (string) ($request->get_param('template') ?: 'receipt');

        if (!in_array($template, InvoiceSettings::TEMPLATES, true)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'template', 'message' => 'Mẫu hoá đơn không hợp lệ'],
            ], 422);
        }

        // Kiểm tra quyền sở hữu đơn TRƯỚC mọi xử lý (chống IDOR — vie_sales chỉ in được đơn của mình).
        if ($denied = self::ensureOrderAccessible($orderId)) {
            return $denied;
        }

        $settings = Container::get(InvoiceSettings::class);
        if (!$settings->isConfigured()) {
            return ResponseEnvelope::error([
                ['code' => 'not_configured', 'field' => null, 'message' => 'Chưa cấu hình thông tin công ty trong Settings → Hoá đơn'],
            ], 412);
        }

        $orderRepo = Container::get(OrderRepository::class);
        $order = $orderRepo->find($orderId);
        if ($order === null) {
            return ResponseEnvelope::error([
                ['code' => 'not_found', 'field' => null, 'message' => 'Không tìm thấy đơn hàng'],
            ], 404);
        }

        $itemsResp = Container::get(OrderItemRepository::class)->all([
            'order_id' => $orderId,
            'per_page' => 200,
        ]);
        $items = $itemsResp['data'] ?? [];

        // Enrich items with hotel name/address.
        $hotelIds = array_values(array_unique(array_map(static fn($i) => (int) $i['hotel_id'], $items)));
        $hotelMap = [];
        if ($hotelIds !== []) {
            global $wpdb;
            $place = implode(',', array_fill(0, count($hotelIds), '%d'));
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, name, address FROM {$wpdb->prefix}vie_hotel WHERE id IN ({$place})",
                    ...$hotelIds
                ),
                ARRAY_A
            );
            foreach ((array) $rows as $r) {
                $hotelMap[(int) $r['id']] = [
                    'name'    => (string) ($r['name'] ?? ''),
                    'address' => (string) ($r['address'] ?? ''),
                ];
            }
        }
        foreach ($items as &$it) {
            $h = $hotelMap[(int) $it['hotel_id']] ?? null;
            $it['hotel_name']    = $h['name']    ?? '';
            $it['hotel_address'] = $h['address'] ?? '';
        }
        unset($it);

        // Assign + persist invoice_number on first call.
        $invSvc        = Container::get(InvoiceService::class);
        $invoiceNumber = $invSvc->getOrAssignNumber($orderId);
        $order['invoice_number'] = $invoiceNumber;

        $order = CostVisibility::stripOrder($order);
        $items = CostVisibility::stripItemRows($items);

        return ResponseEnvelope::success([
            'order'            => $order,
            'items'            => $items,
            'settings'         => $settings->all(),
            'amount_in_words'  => NumberToText::vnd((int) ($order['total'] ?? 0)),
            'template'         => $template,
        ]);
    }

    /**
     * Stream invoice PDF for an order. Returns binary application/pdf.
     */
    public static function pdf(\WP_REST_Request $request): \WP_REST_Response
    {
        $orderId  = (int) $request['id'];
        $template = (string) ($request->get_param('template') ?: 'receipt');
        $download = (bool) $request->get_param('download');

        if (!in_array($template, InvoiceSettings::TEMPLATES, true)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'template', 'message' => 'Mẫu hoá đơn không hợp lệ'],
            ], 422);
        }

        // Kiểm tra quyền sở hữu đơn TRƯỚC khi render (chống IDOR — vie_sales chỉ in được đơn của mình).
        if ($denied = self::ensureOrderAccessible($orderId)) {
            return $denied;
        }

        $settings = Container::get(InvoiceSettings::class);
        if (!$settings->isConfigured()) {
            return ResponseEnvelope::error([
                ['code' => 'not_configured', 'field' => null, 'message' => 'Chưa cấu hình thông tin công ty trong Settings → Hoá đơn'],
            ], 412);
        }

        try {
            $pdf = Container::get(InvoiceService::class)->renderPdf($orderId, $template);
        } catch (\InvalidArgumentException $e) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'template', 'message' => $e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return ResponseEnvelope::error([
                ['code' => 'not_found', 'field' => null, 'message' => $e->getMessage()],
            ], 404);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'render_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 500);
        }

        $filename    = sprintf('invoice-%d-%s.pdf', $orderId, $template);
        $disposition = $download ? 'attachment' : 'inline';

        // Hook into WP REST's pre-serve filter so we replace JSON output entirely.
        // Returning true tells WP we've handled the response — no further serialization.
        add_filter('rest_pre_serve_request', function ($served) use ($pdf, $filename, $disposition) {
            // Clear any active output buffers (WP REST starts one).
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // header_remove() drops the JSON Content-Type WP already queued.
            header_remove('Content-Type');
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
            header('Cache-Control: private, max-age=0, must-revalidate');
            echo $pdf;
            return true;
        }, 10, 1);

        // Return a minimal response — body is replaced by the filter above.
        $resp = new \WP_REST_Response('', 200);
        $resp->header('Content-Type', 'application/pdf');
        return $resp;
    }

    /**
     * Chặn nếu đơn không tồn tại (404) hoặc user không có quyền xem đơn (403).
     * vie_view_all_orders xem mọi đơn; vie_view_own_orders chỉ xem đơn của mình.
     */
    private static function ensureOrderAccessible(int $orderId): ?\WP_REST_Response
    {
        $orderRepo = Container::get(OrderRepository::class);
        $order = $orderRepo->find($orderId);
        if ($order === null) {
            return ResponseEnvelope::error([
                ['code' => 'not_found', 'field' => null, 'message' => 'Không tìm thấy đơn hàng'],
            ], 404);
        }
        if (!$orderRepo->canUserViewOrder((int) get_current_user_id(), $order)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền xem hoá đơn của đơn này'],
            ], 403);
        }
        return null;
    }

    /**
     * Settings: GET /settings/invoice
     */
    public static function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $s = Container::get(InvoiceSettings::class);
        return ResponseEnvelope::success([
            'config' => $s->all(),
        ]);
    }

    /**
     * Settings: PUT /settings/invoice
     */
    public static function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => null, 'message' => 'Body phải là JSON object'],
            ], 422);
        }
        $s = Container::get(InvoiceSettings::class);
        $updated = $s->update($data);
        return ResponseEnvelope::success([
            'config' => $updated,
        ]);
    }
}
