<?php
/**
 * Shared partials — không dùng trực tiếp; include từ template con.
 *
 * @var array $ctx
 */

if (!function_exists('vie_email_render_items')) {
    /**
     * Render bảng items (dùng chung cho admin/customer).
     */
    function vie_email_render_items(array $items, bool $showInternals = false): string
    {
        if (empty($items)) {
            return '';
        }
        $out = '';
        foreach ($items as $item) {
            $hotel  = esc_html((string) ($item['hotel_name'] ?? ''));
            $room   = esc_html((string) ($item['room_name'] ?? ''));
            $type   = esc_html((string) ($item['booking_type'] ?? ''));
            $checkin  = esc_html((string) ($item['checkin'] ?? ''));
            $checkout = esc_html((string) ($item['checkout'] ?? ''));
            $nights = (int) ($item['nights'] ?? 0);
            $qty    = (int) ($item['quantity'] ?? 1);
            $unit   = esc_html((string) ($item['unit_label'] ?? ''));
            $adults = (int) ($item['adults'] ?? 0);
            $children = (int) ($item['children'] ?? 0);
            $childAges = esc_html((string) ($item['child_ages'] ?? ''));
            $seats  = (int) ($item['ticket_count'] ?? 0);
            $partner = esc_html((string) ($item['partner_name'] ?? ''));
            $supplierCode = esc_html((string) ($item['supplier_booking_code'] ?? ''));
            $lineTotal = esc_html((string) ($item['line_total'] ?? ''));

            $out .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:6px;margin:0 0 12px;border-collapse:separate;">';
            $out .= '<tr><td colspan="2" style="background:#f9fafb;padding:8px 12px;font-weight:600;border-bottom:1px solid #e5e7eb;">';
            $out .= $hotel !== '' ? $hotel : 'Khách sạn';
            $out .= '</td></tr>';
            $out .= '<tr><td style="padding:6px 12px;color:#6b7280;width:42%;">Phòng / Loại</td><td style="padding:6px 12px;">' . ($room !== '' ? $room : '—') . ($type !== '' ? " ({$type})" : '') . '</td></tr>';
            $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">Nhận → Trả</td><td style="padding:6px 12px;">' . $checkin . ' → ' . $checkout . ($nights ? " ({$nights} đêm)" : '') . ($qty > 1 ? " × {$qty} {$unit}" : '') . '</td></tr>';
            $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">Khách</td><td style="padding:6px 12px;">' . $adults . ' người lớn' . ($children > 0 ? ", {$children} trẻ em" : '') . ($childAges !== '' ? " ({$childAges})" : '') . '</td></tr>';
            if ($seats > 0) {
                $billable = (int) ($item['billable_seats'] ?? $seats);
                $free     = (int) ($item['free_child_seats'] ?? 0);
                $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">Số chỗ ngồi</td><td style="padding:6px 12px;"><strong>' . $seats . '</strong> (tính phí ' . $billable . ($free > 0 ? ", miễn {$free} bé" : '') . ')</td></tr>';
            }
            if ($showInternals) {
                if ($partner !== '') {
                    $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">Đối tác</td><td style="padding:6px 12px;">' . $partner . '</td></tr>';
                }
                if ($supplierCode !== '') {
                    $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">Mã đối tác</td><td style="padding:6px 12px;">' . $supplierCode . '</td></tr>';
                }
                $internalRows = [
                    'room_subtotal'         => 'Tiền phòng',
                    'extra_adult_total'     => 'Phụ thu người lớn',
                    'child_surcharge_total' => 'Phụ thu trẻ em',
                    'ticket_subtotal'       => 'Tiền vé xe',
                ];
                foreach ($internalRows as $key => $label) {
                    if (!empty($item[$key])) {
                        $out .= '<tr><td style="padding:6px 12px;color:#6b7280;">' . esc_html($label) . '</td><td style="padding:6px 12px;">' . esc_html((string) $item[$key]) . '</td></tr>';
                    }
                }
            }
            $out .= '<tr><td style="padding:8px 12px;border-top:1px solid #e5e7eb;font-weight:600;">Thành tiền</td><td style="padding:8px 12px;border-top:1px solid #e5e7eb;font-weight:600;text-align:right;color:#b91c1c;">' . $lineTotal . '</td></tr>';
            $out .= '</table>';
        }
        return $out;
    }
}

if (!function_exists('vie_email_render_totals')) {
    /**
     * Render bảng tổng (subtotal/discount/total/paid/remaining).
     */
    function vie_email_render_totals(array $ctx, bool $showInternals = false): string
    {
        $rows = [
            ['Tạm tính',        (string) ($ctx['subtotal'] ?? '')],
            ['Giảm giá' . (!empty($ctx['coupon_code']) ? ' (' . esc_html($ctx['coupon_code']) . ')' : ''), '-' . (string) ($ctx['discount'] ?? '0 ₫')],
            ['Tổng đơn',        '<strong>' . (string) ($ctx['total'] ?? '') . '</strong>', true],
            ['Đã thanh toán',   (string) ($ctx['paid_amount'] ?? '')],
            ['Còn lại',         '<strong>' . (string) ($ctx['remaining_amount'] ?? '') . '</strong>', true],
        ];
        if ($showInternals) {
            if (!empty($ctx['cost_total']))   $rows[] = ['Giá vốn', (string) $ctx['cost_total']];
            if (!empty($ctx['profit_total'])) $rows[] = ['Lợi nhuận dự kiến', '<strong>' . (string) $ctx['profit_total'] . '</strong>', true];
        }
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;border-collapse:separate;margin:12px 0;">';
        foreach ($rows as $r) {
            $bold = $r[2] ?? false;
            $html .= '<tr><td style="padding:6px 12px;color:#6b7280;">' . esc_html($r[0]) . '</td>';
            $html .= '<td style="padding:6px 12px;text-align:right;' . ($bold ? 'font-weight:600;' : '') . '">' . $r[1] . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }
}

if (!function_exists('vie_email_render_pickup_dropoff')) {
    /**
     * Render thông tin đưa/đón (chỉ hiện nếu có — đơn combo). Dùng chung admin/customer.
     */
    function vie_email_render_pickup_dropoff(array $ctx): string
    {
        $pickup  = trim((string) ($ctx['pickup'] ?? ''));
        $dropoff = trim((string) ($ctx['dropoff'] ?? ''));
        if ($pickup === '' && $dropoff === '') {
            return '';
        }
        $rows = '';
        if ($pickup !== '') {
            $rows .= '<tr><td style="padding:6px 12px;color:#6b7280;width:42%;">Điểm đón</td><td style="padding:6px 12px;">' . esc_html($pickup) . '</td></tr>';
        }
        if ($dropoff !== '') {
            $rows .= '<tr><td style="padding:6px 12px;color:#6b7280;">Điểm trả</td><td style="padding:6px 12px;">' . esc_html($dropoff) . '</td></tr>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:6px;margin:0 0 12px;border-collapse:separate;">'
            . '<tr><td colspan="2" style="background:#f9fafb;padding:8px 12px;font-weight:600;border-bottom:1px solid #e5e7eb;">🚐 Thông tin đưa đón</td></tr>'
            . $rows
            . '</table>';
    }
}

if (!function_exists('vie_email_render_vat')) {
    /**
     * Render thông tin hóa đơn VAT (chỉ hiện nếu có). $ctx['vat'] = mảng {company_name,tax_code,address,email}.
     */
    function vie_email_render_vat(array $ctx): string
    {
        $vat = $ctx['vat'] ?? [];
        if (!is_array($vat) || $vat === []) {
            return '';
        }
        $labels = [
            'company_name' => 'Tên công ty',
            'tax_code'     => 'Mã số thuế',
            'address'      => 'Địa chỉ',
            'email'        => 'Email nhận hóa đơn',
        ];
        $rows = '';
        foreach ($labels as $key => $label) {
            $val = trim((string) ($vat[$key] ?? ''));
            if ($val === '') {
                continue;
            }
            $rows .= '<tr><td style="padding:6px 12px;color:#6b7280;width:42%;">' . esc_html($label) . '</td><td style="padding:6px 12px;">' . esc_html($val) . '</td></tr>';
        }
        if ($rows === '') {
            return '';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e5e7eb;border-radius:6px;margin:0 0 12px;border-collapse:separate;">'
            . '<tr><td colspan="2" style="background:#f9fafb;padding:8px 12px;font-weight:600;border-bottom:1px solid #e5e7eb;">🧾 Hóa đơn VAT</td></tr>'
            . $rows
            . '</table>';
    }
}
