<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Email\MailService;
use Vie\Http\RateLimiter;
use Vie\Repository\QuoteInquiryRepository;
use Vie\Repository\RoomRepository;
use Vie\Service\Settings\EmailSettings;
use Vie\Support\ClientIp;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\QuoteInquiryValidation;

final class QuoteInquiryController
{
    public static function create(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($denied = RateLimiter::check('quote_inquiry', 10, 5 * MINUTE_IN_SECONDS)) {
            return $denied;
        }

        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, QuoteInquiryValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $cross = QuoteInquiryValidation::crossValidate($v->validated());
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        $clean = $v->validated();
        $item  = $clean['item'];

        try {
            $room = Container::get(RoomRepository::class)->findOrFail((int) $item['room_id']);
        } catch (\Throwable) {
            return ResponseEnvelope::notFound('Phòng');
        }

        $childAges = is_array($item['child_ages'] ?? null) ? array_values($item['child_ages']) : [];

        $payload = [
            'room_id'        => (int) $item['room_id'],
            'hotel_id'       => (int) ($room['hotel_id'] ?? 0),
            'booking_type'   => (string) $item['booking_type'],
            'checkin'        => (string) $item['checkin'],
            'checkout'       => (string) $item['checkout'],
            'adults'         => (int) $item['adults'],
            'children'       => count($childAges),
            'child_ages'     => $childAges,
            'user_rooms'     => (int) ($item['user_rooms'] ?? 1),
            'customer_name'  => trim((string) $clean['customer']['name']),
            'customer_phone' => trim((string) $clean['customer']['phone']),
            'customer_email' => isset($clean['customer']['email']) ? trim((string) $clean['customer']['email']) : null,
            'note'           => isset($clean['note']) ? (string) $clean['note'] : null,
            'status'         => 'pending',
            'ip'             => ClientIp::clientIp() ?: null,
            'user_agent'     => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
        ];

        try {
            $row = Container::get(QuoteInquiryRepository::class)->create($payload);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error([
                ['code' => 'save_failed', 'field' => null, 'message' => $e->getMessage()],
            ], 500);
        }

        self::notifyAdmin($row, $room);

        return ResponseEnvelope::success([
            'id'      => (int) $row['id'],
            'status'  => (string) $row['status'],
            'message' => 'Đã ghi nhận yêu cầu báo giá. Chúng tôi sẽ liên hệ trong thời gian sớm nhất.',
        ]);
    }

    private static function notifyAdmin(array $inquiry, array $room): void
    {
        try {
            $settings = Container::get(EmailSettings::class);
            $recipients = $settings->adminRecipients();
            if ($recipients === []) {
                return;
            }

            $roomName = (string) ($room['name'] ?? ('#' . $inquiry['room_id']));
            $subject  = sprintf('[Vielimousine] Yêu cầu báo giá mới — %s', $roomName);

            $bodyLines = [
                '<p>Có yêu cầu báo giá mới:</p>',
                '<ul>',
                '<li><strong>Khách:</strong> ' . esc_html((string) $inquiry['customer_name']) . '</li>',
                '<li><strong>SĐT:</strong> ' . esc_html((string) $inquiry['customer_phone']) . '</li>',
            ];
            if (!empty($inquiry['customer_email'])) {
                $bodyLines[] = '<li><strong>Email:</strong> ' . esc_html((string) $inquiry['customer_email']) . '</li>';
            }
            $bodyLines[] = '<li><strong>Phòng:</strong> ' . esc_html($roomName) . '</li>';
            $bodyLines[] = '<li><strong>Loại:</strong> ' . ($inquiry['booking_type'] === 'combo' ? 'Combo (phòng + vé)' : 'Chỉ phòng') . '</li>';
            $bodyLines[] = '<li><strong>Ngày:</strong> ' . esc_html((string) $inquiry['checkin']) . ' → ' . esc_html((string) $inquiry['checkout']) . '</li>';
            $bodyLines[] = '<li><strong>Khách:</strong> ' . (int) $inquiry['adults'] . ' người lớn, ' . (int) $inquiry['children'] . ' trẻ em</li>';
            if (!empty($inquiry['user_rooms'])) {
                $bodyLines[] = '<li><strong>Số phòng yêu cầu:</strong> ' . (int) $inquiry['user_rooms'] . '</li>';
            }
            if (!empty($inquiry['note'])) {
                $bodyLines[] = '<li><strong>Ghi chú:</strong> ' . nl2br(esc_html((string) $inquiry['note'])) . '</li>';
            }
            $bodyLines[] = '</ul>';
            $bodyLines[] = '<p>ID: #' . (int) $inquiry['id'] . '</p>';

            Container::get(MailService::class)->send(
                $recipients,
                $subject,
                implode("\n", $bodyLines),
                ['type' => 'quote_inquiry', 'template' => 'quote_inquiry']
            );
        } catch (\Throwable) {
            // Không cho lỗi mail làm gãy response success — đã ghi DB rồi.
        }
    }
}
