<?php
declare(strict_types=1);

namespace Vie\Email;

use Vie\Support\Money;

final class Fixtures
{
    /**
     * Trả về 1 đơn hàng mẫu (đã build context) để dùng cho test mail.
     */
    public function sampleOrder(): array
    {
        $items = [[
            'hotel_name'           => 'The Capella Vũng Tàu',
            'room_name'            => 'Phòng Deluxe Hướng Biển',
            'booking_type'         => 'combo',
            'quantity'             => 1,
            'unit_label'           => 'phòng',
            'nights'               => 2,
            'checkin'              => '2026-06-10',
            'checkout'             => '2026-06-12',
            'adults'               => 2,
            'children'             => 1,
            'child_ages'           => '5',
            'ticket_count'         => 3,
            'billable_seats'       => 2,
            'free_child_seats'     => 1,
            'partner_name'         => 'CapellaVT',
            'supplier_booking_code'=> 'CV-20260610-X42',
            'room_subtotal'        => Money::vnd(2_400_000),
            'extra_adult_total'    => Money::vnd(0),
            'child_surcharge_total'=> Money::vnd(200_000),
            'ticket_subtotal'      => Money::vnd(1_200_000),
            'line_total'           => Money::vnd(3_800_000),
        ]];

        $total = 3_800_000;
        $paid  = 1_900_000;
        $remaining = $total - $paid;

        return [
            // Required for templates / placeholders
            'order_id'        => 9999,
            'order_code'      => 'VIE-DEMO',
            'customer_name'   => 'Nguyễn Văn Demo',
            'customer_phone'  => '0901234567',
            'customer_email'  => (string) get_option('admin_email'),
            'source'          => 'Demo',
            'sales_user'      => 'Vielimo Bot',
            'checkin'         => '2026-06-10',
            'checkout'        => '2026-06-12',
            'nights'          => 2,
            'adults'          => 2,
            'children'        => 1,
            'child_ages'      => '5',
            'total_seats'     => 3,

            'items'           => $items,

            'subtotal'        => Money::vnd($total + 100_000),
            'discount'        => Money::vnd(100_000),
            'total'           => Money::vnd($total),
            'paid_amount'     => Money::vnd($paid),
            'remaining_amount'=> Money::vnd($remaining),
            'payment_amount'  => Money::vnd($paid),
            'payment_method'  => 'Chuyển khoản',
            'payment_status'  => 'Đã cọc 1 phần',

            'cost_total'      => Money::vnd(2_900_000),
            'profit_total'    => Money::vnd(900_000),

            'coupon_code'     => 'WELCOME10',
            'voucher_code'    => '',
            'checkin_code'    => 'CK-DEMO-2026',

            'order_description'        => '1× phòng — 2 đêm — 2 người lớn + 1 trẻ',
            'cancellation_policy_html' => '<p>Miễn phí hủy trước 48 giờ. Sau đó phí 50%.</p>',
            'customer_note'            => 'Khách yêu cầu phòng tầng cao.',

            'penalty_amount'  => Money::vnd(0),
            'refund_amount'   => Money::vnd($paid),
            'cancel_reason'   => 'Đơn mẫu — không phải hủy thật',

            'admin_url'       => home_url('/vie-admin/orders/9999'),
            'lookup_url'      => home_url('/dat-phong-thanh-cong/?code=VIE-DEMO&phone=0901234567'),
        ];
    }
}
