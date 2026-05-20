<?php
declare(strict_types=1);

namespace Vie\Email;

use Vie\Container;
use Vie\Repository\HotelRepository;
use Vie\Repository\OrderItemRepository;
use Vie\Repository\PaymentLogRepository;
use Vie\Repository\RoomRepository;
use Vie\Service\HookRegistry;
use Vie\Service\Order\OrderService;
use Vie\Service\Settings\EmailSettings;
use Vie\Support\Money;

final class OrderEmailService
{
    /** WP-cron hook để gửi email bất đồng bộ (sau khi order/payment đã commit). */
    public const QUEUE_HOOK = 'vie_send_order_email';

    /** Số lần retry tối đa nếu wp_mail() fail. */
    private const MAX_RETRY = 3;

    /** Khoảng cách giữa các lần retry (giây). */
    private const RETRY_DELAY = 300;

    private const DEFAULT_SUBJECTS = [
        'pending_payment'    => '[{site_name}] Đặt phòng #{order_code} – Chờ thanh toán',
        'paid'               => '[{site_name}] Đã nhận thanh toán – #{order_code}',
        'partial'            => '[{site_name}] Đã nhận cọc {paid_amount} – #{order_code}',
        'confirmed'          => '[{site_name}] Xác nhận đặt phòng #{order_code}',
        'completed'          => '[{site_name}] Cảm ơn quý khách – #{order_code}',
        'cancelled'          => '[{site_name}] Đã hủy đặt phòng #{order_code}',
        'checkin_code'       => '[{site_name}] Mã nhận phòng #{order_code}',
        'admin_notification' => '[ĐẶT PHÒNG MỚI] #{order_code} – {customer_name} – Seats:{total_seats}',
        'admin_paid'         => '[THU TIỀN] #{order_code} +{payment_amount}',
        'admin_cancelled'    => '[HỦY] #{order_code} hoàn {refund_amount}',
    ];

    public function __construct(
        private readonly MailService $mail,
        private readonly TemplateRenderer $renderer,
        private readonly EmailSettings $settings,
        private readonly OrderService $orderService,
        private readonly OrderItemRepository $itemRepo,
        private readonly PaymentLogRepository $paymentRepo,
        private readonly HotelRepository $hotelRepo,
        private readonly RoomRepository $roomRepo,
        private readonly \Vie\Repository\OrderRepository $orderRepo,
    ) {
    }

    public static function register(): void
    {
        $self = Container::get(self::class);
        // Domain hooks → enqueue scheduled event (không block request hiện tại).
        add_action(HookRegistry::ORDER_CREATED,   [$self, 'onOrderCreated'],   20, 2);
        add_action(HookRegistry::PAYMENT_LOGGED,  [$self, 'onPaymentLogged'],  20, 3);
        add_action(HookRegistry::ORDER_CONFIRMED, [$self, 'onOrderConfirmed'], 20, 2);
        add_action(HookRegistry::ORDER_CANCELLED, [$self, 'onOrderCancelled'], 20, 3);
        // WP-cron dispatcher — gọi MailService thực sự.
        add_action(self::QUEUE_HOOK, [$self, 'dispatchScheduled'], 10, 4);
    }

    // ---------- Hook handlers (chỉ enqueue, không gọi wp_mail trực tiếp) ----------

    public function onOrderCreated(int $orderId, ?array $order): void
    {
        if (!is_array($order)) return;
        $this->enqueue($orderId, 'order_created', []);
    }

    public function onPaymentLogged(int $paymentId, ?array $payment, ?array $order): void
    {
        if (!is_array($payment) || !is_array($order)) return;
        $type = (string) ($payment['type'] ?? '');
        if (!in_array($type, ['deposit', 'payment'], true)) {
            return;
        }
        $this->enqueue((int) $order['id'], 'payment_logged', ['payment_id' => $paymentId]);
    }

    public function onOrderConfirmed(int $orderId, ?array $order): void
    {
        if (!is_array($order)) return;
        $this->enqueue($orderId, 'order_confirmed', []);
    }

    public function onOrderCancelled(int $orderId, ?array $order, $refund = null): void
    {
        if (!is_array($order)) return;
        // refund là array nhỏ (paid_amount, refund_amount, reason, ...) — an toàn để serialize.
        $refundData = is_array($refund) ? $refund : [];
        $this->enqueue($orderId, 'order_cancelled', ['refund' => $refundData]);
    }

    private function enqueue(int $orderId, string $event, array $extra): void
    {
        wp_schedule_single_event(time(), self::QUEUE_HOOK, [$orderId, $event, $extra, 0]);
    }

    /**
     * WP-cron callback. Reload order/payment từ DB rồi gửi email bằng logic gốc.
     * Nếu wp_mail fail → reschedule với attempt+1, tối đa MAX_RETRY lần.
     */
    public function dispatchScheduled(int $orderId, string $event, array $extra, int $attempt): void
    {
        try {
            $order = $this->orderRepo->find($orderId);
            if ($order === null) {
                return; // order đã bị xóa cứng — không thể gửi
            }

            switch ($event) {
                case 'order_created':
                    $this->doOrderCreated($orderId, $order);
                    break;
                case 'payment_logged':
                    $paymentId = (int) ($extra['payment_id'] ?? 0);
                    $payment   = $paymentId > 0 ? $this->paymentRepo->find($paymentId) : null;
                    if (is_array($payment)) {
                        $this->doPaymentLogged($payment, $order);
                    }
                    break;
                case 'order_confirmed':
                    $this->doOrderConfirmed($orderId, $order);
                    break;
                case 'order_cancelled':
                    $this->doOrderCancelled($orderId, $order, $extra['refund'] ?? null);
                    break;
            }
        } catch (\Throwable $e) {
            if ($attempt < self::MAX_RETRY) {
                wp_schedule_single_event(
                    time() + self::RETRY_DELAY,
                    self::QUEUE_HOOK,
                    [$orderId, $event, $extra, $attempt + 1]
                );
            }
        }
    }

    // ---------- Real send logic (chỉ gọi trong dispatchScheduled) ----------

    private function doOrderCreated(int $orderId, array $order): void
    {
        $ctx = $this->buildContext($orderId, $order);
        if (($order['source'] ?? '') === 'website' && !empty($order['customer_email'])) {
            $this->sendByType('pending_payment', $ctx, (string) $order['customer_email']);
        }
        $this->sendByType('admin_notification', $ctx, $this->settings->adminRecipients());
    }

    private function doPaymentLogged(array $payment, array $order): void
    {
        $ctx = $this->buildContext((int) $order['id'], $order, $payment);
        $this->sendByType('admin_paid', $ctx, $this->settings->adminRecipients());

        if (empty($order['customer_email'])) return;
        $paymentStatus = (string) ($order['payment_status'] ?? '');
        if ($paymentStatus === 'paid') {
            $this->sendByType('paid', $ctx, (string) $order['customer_email']);
        } elseif ($paymentStatus === 'partial' || $paymentStatus === 'deposit') {
            $this->sendByType('partial', $ctx, (string) $order['customer_email']);
        }
    }

    private function doOrderConfirmed(int $orderId, array $order): void
    {
        if (empty($order['customer_email'])) return;
        $ctx = $this->buildContext($orderId, $order);
        $this->sendByType('confirmed', $ctx, (string) $order['customer_email']);
    }

    private function doOrderCancelled(int $orderId, array $order, $refund): void
    {
        $ctx = $this->buildContext($orderId, $order, null, $refund);
        if (!empty($order['customer_email'])) {
            $this->sendByType('cancelled', $ctx, (string) $order['customer_email']);
        }
        $this->sendByType('admin_cancelled', $ctx, $this->settings->adminRecipients());
    }

    /**
     * Gửi email mã nhận phòng cho khách. Gọi đồng bộ (admin click "Gửi").
     * Trả về true nếu wp_mail trả true, false nếu fail/không có dữ liệu.
     */
    public function sendCheckinCode(int $orderId): bool
    {
        $order = $this->orderRepo->find($orderId);
        if (!is_array($order)) {
            return false;
        }
        $email = (string) ($order['customer_email'] ?? '');
        $code  = (string) ($order['checkin_code'] ?? '');
        if ($email === '' || $code === '') {
            return false;
        }
        $ctx = $this->buildContext($orderId, $order);
        return $this->sendByType('checkin_code', $ctx, $email);
    }

    // ---------- Public API (cũng dùng cho test mail) ----------

    /**
     * @param string|array<int,string> $to
     */
    public function sendByType(string $type, array $ctx, $to): bool
    {
        if (!$this->settings->isTemplateEnabled($type)) {
            return false;
        }
        $templateFile = $this->templateFileName($type);
        $body    = $this->renderer->render($templateFile, $ctx);
        $subject = $this->renderer->renderSubject($type, self::DEFAULT_SUBJECTS[$type] ?? '', $ctx);
        return $this->mail->send($to, $subject, $body, [
            'template' => $templateFile,
            'type'     => $type,
            'order_id' => (int) ($ctx['order_id'] ?? 0),
        ]);
    }

    private function templateFileName(string $type): string
    {
        return str_replace('_', '-', $type);
    }

    // ---------- Context building ----------

    public function buildContext(int $orderId, array $order, ?array $payment = null, $refund = null): array
    {
        $items = $this->loadItemsWithNames($orderId);

        $totalSeats = 0;
        foreach ($items as $it) {
            $totalSeats += (int) ($it['ticket_count'] ?? 0);
        }

        $total       = (int) ($order['total'] ?? 0);
        $paidAmount  = (int) ($order['paid_amount'] ?? 0);
        $remaining   = max(0, $total - $paidAmount);
        $subtotal    = (int) ($order['subtotal'] ?? 0);
        $discount    = (int) ($order['discount'] ?? 0);

        // Format items theo "labelled" array — string đã format
        $formattedItems = array_map([$this, 'formatItem'], $items);

        $code  = (string) ($order['code'] ?? '');
        $phone = (string) ($order['customer_phone'] ?? '');

        $ctx = [
            // Identity
            'order_id'        => (int) ($order['id'] ?? $orderId),
            'order_code'      => $code,
            'customer_name'   => (string) ($order['customer_name'] ?? ''),
            'customer_phone'  => $phone,
            'customer_email'  => (string) ($order['customer_email'] ?? ''),
            'source'          => $this->labelSource((string) ($order['source'] ?? '')),
            'sales_user'      => $this->salesUserName((int) ($order['sales_user_id'] ?? 0)),

            // Booking
            'checkin'         => (string) ($order['checkin'] ?? ''),
            'checkout'        => (string) ($order['checkout'] ?? ''),
            'nights'          => (int) ($order['nights'] ?? 0),
            'adults'          => (int) ($order['adults'] ?? 0),
            'children'        => (int) ($order['children'] ?? 0),
            'child_ages'      => (string) ($order['child_ages'] ?? ''),
            'total_seats'     => $totalSeats,

            // Items
            'items'           => $formattedItems,

            // Money (đã format VND)
            'subtotal'        => Money::vnd($subtotal),
            'discount'        => Money::vnd($discount),
            'total'           => Money::vnd($total),
            'paid_amount'     => Money::vnd($paidAmount),
            'remaining_amount'=> Money::vnd($remaining),
            'cost_total'      => Money::vnd((int) ($order['cost_total'] ?? 0)),
            'profit_total'    => Money::vnd((int) ($order['profit_total'] ?? 0)),

            // Coupon / voucher
            'coupon_code'     => (string) ($order['coupon_code'] ?? ''),
            'voucher_code'    => (string) ($order['voucher_code'] ?? ''),
            'checkin_code'    => (string) ($order['checkin_code'] ?? ''),

            // Notes
            'customer_note'   => (string) ($order['customer_note'] ?? ''),
            'order_description' => $this->buildOrderDescription($items, $order),

            // Payment-specific
            'payment_amount'  => $payment !== null ? Money::vnd((int) ($payment['amount'] ?? 0)) : Money::vnd(0),
            'payment_method'  => $payment !== null ? $this->labelPaymentMethod((string) ($payment['method'] ?? '')) : '',
            'payment_status'  => $this->labelPaymentStatus((string) ($order['payment_status'] ?? '')),

            // Cancel-specific
            'refund_amount'   => $this->refundAmount($refund),
            'penalty_amount'  => $this->penaltyAmount($refund),
            'cancel_reason'   => is_array($refund) ? (string) ($refund['reason'] ?? '') : '',

            // URLs
            'admin_url'       => home_url('/vie-admin/orders/' . (int) ($order['id'] ?? $orderId)),
            'lookup_url'      => home_url('/dat-phong-thanh-cong/') . '?' . http_build_query([
                'code'  => $code,
                'phone' => $phone,
            ]),
        ];

        return $ctx;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function loadItemsWithNames(int $orderId): array
    {
        $items = $this->itemRepo->all(['order_id' => $orderId, 'per_page' => 100])['data'] ?? [];
        $hotelCache = [];
        $roomCache  = [];
        foreach ($items as &$item) {
            $hotelId = (int) ($item['hotel_id'] ?? 0);
            $roomId  = (int) ($item['room_id'] ?? 0);
            if ($hotelId > 0 && !isset($hotelCache[$hotelId])) {
                $hotelCache[$hotelId] = $this->hotelRepo->find($hotelId);
            }
            if ($roomId > 0 && !isset($roomCache[$roomId])) {
                $roomCache[$roomId] = $this->roomRepo->find($roomId);
            }
            $item['hotel_name'] = $hotelCache[$hotelId]['name'] ?? '';
            $item['room_name']  = $roomCache[$roomId]['name']   ?? (string) ($item['name'] ?? '');
        }
        unset($item);
        return $items;
    }

    private function formatItem(array $item): array
    {
        return [
            'hotel_name'           => (string) ($item['hotel_name'] ?? ''),
            'room_name'            => (string) ($item['room_name']  ?? ''),
            'booking_type'         => $this->labelBookingType((string) ($item['booking_type'] ?? '')),
            'quantity'             => (int) ($item['quantity'] ?? 1),
            'unit_label'           => (string) ($item['unit_label'] ?? 'phòng'),
            'checkin'              => (string) ($item['checkin'] ?? ''),
            'checkout'             => (string) ($item['checkout'] ?? ''),
            'nights'               => (int) ($item['nights'] ?? 0),
            'adults'               => (int) ($item['adults'] ?? 0),
            'children'             => (int) ($item['children'] ?? 0),
            'child_ages'           => $this->formatChildAges($item['child_ages'] ?? null),
            'ticket_count'         => (int) ($item['ticket_count'] ?? 0),
            'billable_seats'       => (int) ($item['billable_seats'] ?? 0),
            'free_child_seats'     => (int) ($item['free_child_seats'] ?? 0),
            'partner_name'         => (string) ($item['partner_name'] ?? ''),
            'supplier_booking_code'=> (string) ($item['supplier_booking_code'] ?? ''),
            'room_subtotal'        => Money::vnd((int) ($item['room_subtotal'] ?? 0)),
            'extra_adult_total'    => Money::vnd((int) ($item['extra_adult_total'] ?? 0)),
            'child_surcharge_total'=> Money::vnd((int) ($item['child_surcharge_total'] ?? 0)),
            'ticket_subtotal'      => Money::vnd((int) ($item['ticket_subtotal'] ?? 0)),
            'line_total'           => Money::vnd((int) ($item['line_total'] ?? 0)),
        ];
    }

    private function formatChildAges($raw): string
    {
        if (is_array($raw)) {
            return implode(', ', array_map('intval', $raw));
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return implode(', ', array_map('intval', $decoded));
            }
            return $raw;
        }
        return '';
    }

    private function buildOrderDescription(array $items, array $order): string
    {
        $count = count($items);
        $nights = (int) ($order['nights'] ?? 0);
        $adults = (int) ($order['adults'] ?? 0);
        $children = (int) ($order['children'] ?? 0);
        return sprintf(
            '%d× phòng — %d đêm — %d người lớn%s',
            $count,
            $nights,
            $adults,
            $children > 0 ? " + {$children} trẻ em" : ''
        );
    }

    private function labelSource(string $src): string
    {
        return match ($src) {
            'website' => 'Website',
            'admin'   => 'Admin tạo',
            'manual'  => 'Thủ công',
            ''        => '—',
            default   => $src,
        };
    }

    private function labelBookingType(string $type): string
    {
        return match ($type) {
            'room'  => 'Phòng',
            'combo' => 'Combo (phòng + vé)',
            default => $type,
        };
    }

    private function labelPaymentMethod(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'Chuyển khoản',
            'sepay'         => 'SePay',
            'cash'          => 'Tiền mặt',
            'manual'        => 'Thủ công',
            ''              => '—',
            default         => $method,
        };
    }

    private function labelPaymentStatus(string $s): string
    {
        return match ($s) {
            'pending' => 'Chờ thanh toán',
            'partial' => 'Đã cọc 1 phần',
            'deposit' => 'Đã cọc',
            'paid'    => 'Đã thanh toán',
            'refunded'=> 'Đã hoàn',
            ''        => '—',
            default   => $s,
        };
    }

    private function salesUserName(int $userId): string
    {
        if ($userId <= 0) return '—';
        $user = get_userdata($userId);
        return $user ? (string) $user->display_name : '—';
    }

    private function refundAmount($refund): string
    {
        if (is_array($refund)) {
            return Money::vnd((int) ($refund['refund_amount'] ?? $refund['amount'] ?? 0));
        }
        if (is_object($refund) && isset($refund->refundAmount)) {
            return Money::vnd((int) $refund->refundAmount);
        }
        return Money::vnd(0);
    }

    private function penaltyAmount($refund): string
    {
        if (is_array($refund)) {
            return Money::vnd((int) ($refund['penalty_amount'] ?? $refund['penalty'] ?? 0));
        }
        if (is_object($refund) && isset($refund->penaltyAmount)) {
            return Money::vnd((int) $refund->penaltyAmount);
        }
        return Money::vnd(0);
    }
}
