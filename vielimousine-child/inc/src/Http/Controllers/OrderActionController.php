<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Email\OrderEmailService;
use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;
use Vie\Service\Order\IllegalTransitionException;
use Vie\Service\Order\OrderNotFoundException;
use Vie\Service\Order\OrderService;
use Vie\Service\Settings\EmailSettings;
use Vie\Support\ResponseEnvelope;

final class OrderActionController
{
    private const ALLOWED_TARGETS = ['confirmed', 'completed', 'no_show'];

    /**
     * Object-level IDOR guard. Same logic as OrderController::ensureCanAccess.
     */
    private static function ensureCanAccess(int $orderId): ?\WP_REST_Response
    {
        $repo  = Container::get(OrderRepository::class);
        $order = $repo->find($orderId);
        if ($order === null) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }
        $userId = (int) get_current_user_id();
        if (!$repo->canUserViewOrder($userId, $order)) {
            return ResponseEnvelope::error([
                ['code' => 'forbidden', 'field' => null, 'message' => 'Bạn không có quyền thao tác với đơn này'],
            ], 403);
        }
        return null;
    }

    /**
     * Đơn nháp (status='draft') không đi qua state machine. Trả lỗi rõ ràng
     * thay vì IllegalTransitionException khó hiểu khi gọi cancel/transition lên nháp.
     */
    private static function rejectIfDraft(int $orderId): ?\WP_REST_Response
    {
        $order = Container::get(OrderRepository::class)->find($orderId);
        if ($order !== null && ($order['status'] ?? '') === 'draft') {
            return ResponseEnvelope::error([
                ['code' => 'draft_not_supported', 'field' => null, 'message' => 'Đơn nháp không hỗ trợ thao tác này — dùng "Xóa nháp" để bỏ nháp.'],
            ], 409);
        }
        return null;
    }

    public static function transition(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }
        if ($draftDenied = self::rejectIfDraft($id)) {
            return $draftDenied;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = [];
        $target = (string) ($body['status'] ?? '');

        if (!in_array($target, self::ALLOWED_TARGETS, true)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'status', 'message' => 'Trạng thái không hợp lệ. Dùng /cancel để hủy đơn.'],
            ], 422);
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $result   = $orderSvc->transition($id, $target, (int) get_current_user_id());
            return ResponseEnvelope::success($result);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        } catch (IllegalTransitionException $e) {
            return ResponseEnvelope::error([
                ['code' => 'illegal_transition', 'field' => 'status', 'message' => $e->getMessage()],
            ], 409);
        }
    }

    /**
     * POST /orders/{id}/send-checkin-code
     * Lưu mã nhận phòng (do khách sạn cấp) và gửi email cho khách.
     * Chỉ cho phép khi đơn đã thanh toán đủ (payment_status = paid).
     */
    public static function sendCheckinCode(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $code = trim((string) ($body['checkin_code'] ?? ''));

        if ($code === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'checkin_code', 'message' => 'Vui lòng nhập mã nhận phòng'],
            ], 422);
        }
        if (mb_strlen($code) > 100) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'checkin_code', 'message' => 'Mã nhận phòng tối đa 100 ký tự'],
            ], 422);
        }

        $repo  = Container::get(OrderRepository::class);
        $order = $repo->find($id);
        if ($order === null) {
            return ResponseEnvelope::notFound('Đơn hàng');
        }

        if (($order['payment_status'] ?? '') !== 'paid') {
            return ResponseEnvelope::error([
                ['code' => 'order_not_paid', 'field' => null, 'message' => 'Đơn chưa thanh toán đủ, không thể gửi mã nhận phòng'],
            ], 409);
        }
        if (($order['status'] ?? '') === 'cancelled') {
            return ResponseEnvelope::error([
                ['code' => 'order_cancelled', 'field' => null, 'message' => 'Đơn đã hủy, không thể gửi mã nhận phòng'],
            ], 409);
        }
        if (empty($order['customer_email'])) {
            return ResponseEnvelope::error([
                ['code' => 'no_customer_email', 'field' => null, 'message' => 'Đơn không có email khách hàng để gửi'],
            ], 422);
        }

        // Template phải đang bật — nếu admin tắt template trong Settings → Email,
        // sendByType sẽ silently trả false → tránh báo "email_failed" gây hiểu lầm.
        if (!Container::get(EmailSettings::class)->isTemplateEnabled('checkin_code')) {
            return ResponseEnvelope::error([
                ['code' => 'template_disabled', 'field' => null, 'message' => 'Email "Mã nhận phòng" đang tắt trong Cài đặt → Email'],
            ], 409);
        }

        // Lưu code trước (để sendCheckinCode đọc lại từ DB), nhưng KHÔNG set timestamp
        // cho tới khi wp_mail thực sự return true — tránh DB nói dối về việc đã gửi.
        $previousCode = (string) ($order['checkin_code'] ?? '');
        $repo->update($id, ['checkin_code' => $code]);

        $emailSvc = Container::get(OrderEmailService::class);
        $sent = $emailSvc->sendCheckinCode($id);

        if (!$sent) {
            return ResponseEnvelope::error([
                ['code' => 'email_failed', 'field' => null, 'message' => 'Không gửi được email mã nhận phòng'],
            ], 500);
        }

        $now = current_time('mysql');
        $repo->update($id, ['checkin_code_sent_at' => $now]);

        // Audit log: ghi nhận admin nào đã cấp mã (và mã cũ → mã mới nếu là re-send).
        Container::get(ActivityLogRepository::class)->create([
            'actor_user_id' => (int) get_current_user_id(),
            'entity_type'   => 'order',
            'entity_id'     => $id,
            'action'        => 'checkin_code_sent',
            'before_json'   => $previousCode !== '' ? ['checkin_code' => $previousCode] : null,
            'after_json'    => ['checkin_code' => $code, 'sent_at' => $now],
            'ip'            => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $orderSvc = Container::get(OrderService::class);
        try {
            $detail = $orderSvc->buildDetail($id);
        } catch (OrderNotFoundException $e) {
            $detail = $repo->find($id);
        }
        return ResponseEnvelope::success($detail);
    }

    public static function cancel(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        if ($denied = self::ensureCanAccess($id)) {
            return $denied;
        }
        if ($draftDenied = self::rejectIfDraft($id)) {
            return $draftDenied;
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = [];
        }
        $reason       = trim((string) ($body['reason'] ?? ''));
        $refundAmount = (int) ($body['refund_amount'] ?? 0);

        if ($reason === '') {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'reason', 'message' => 'Vui lòng nhập lý do hủy'],
            ], 422);
        }

        if ($refundAmount < 0) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'refund_amount', 'message' => 'Số tiền hoàn không hợp lệ'],
            ], 422);
        }

        try {
            $orderSvc = Container::get(OrderService::class);
            $result   = $orderSvc->cancel($id, $reason, (int) get_current_user_id(), $refundAmount);
            return ResponseEnvelope::success($result);
        } catch (OrderNotFoundException $e) {
            return ResponseEnvelope::notFound('Đơn hàng');
        } catch (IllegalTransitionException $e) {
            return ResponseEnvelope::error([
                ['code' => 'illegal_transition', 'field' => 'status', 'message' => $e->getMessage()],
            ], 409);
        }
    }
}
