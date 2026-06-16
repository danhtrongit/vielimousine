<?php
declare(strict_types=1);

namespace Vie\Service\Order;

use Vie\Repository\ActivityLogRepository;
use Vie\Repository\OrderRepository;

final class OrderDraftService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    /**
     * Lưu một đơn nháp mới (status='draft'). Không trừ kho, không sinh mã,
     * không ghi coupon, không gửi email — chỉ persist state + cột preview.
     */
    public function save(array $data, int $userId): array
    {
        $row = $this->buildRow($data, $userId);
        $row['status']     = 'draft';
        $row['created_by'] = $userId ?: null;

        $order = $this->orderRepo->create($row);

        $this->activityRepo->create([
            'actor_user_id' => $userId,
            'entity_type'   => 'order_draft',
            'entity_id'     => (int) $order['id'],
            'action'        => 'draft_save',
            'before_json'   => null,
            'after_json'    => ['status' => 'draft'],
            'ip'            => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return $order;
    }

    /** Cập nhật nháp đang có; chỉ cho phép khi status vẫn là 'draft'. */
    public function update(int $id, array $data, int $userId): array
    {
        $this->assertDraft($id);
        $this->orderRepo->update($id, $this->buildRow($data, $userId));
        return $this->orderRepo->findOrFail($id);
    }

    /** Lấy nháp để nạp lại wizard (kèm draft_payload đã decode). */
    public function get(int $id, int $userId): array
    {
        return $this->assertDraft($id);
    }

    /** Xóa cứng nháp — nháp chưa có side-effect (kho/coupon) nên xóa thẳng. */
    public function delete(int $id): void
    {
        $this->assertDraft($id);
        $this->orderRepo->delete($id);
    }

    private function assertDraft(int $id): array
    {
        $existing = $this->orderRepo->find($id);
        if ($existing === null || ($existing['status'] ?? '') !== 'draft') {
            throw new OrderNotFoundException('Không tìm thấy đơn nháp');
        }
        return $existing;
    }

    /** Map payload từ wizard sang các cột preview + draft_payload JSON. */
    private function buildRow(array $data, int $userId): array
    {
        return [
            'customer_phone' => (string) ($data['customer_phone'] ?? ''),
            'customer_name'  => (string) ($data['customer_name'] ?? ''),
            'customer_email' => $data['customer_email'] ?? null,
            'source'         => (string) ($data['source'] ?? 'admin'),
            'customer_note'  => $data['customer_note'] ?? null,
            'coupon_code'    => $data['coupon_code'] ?? null,
            'checkin'        => $data['checkin']  ?? null,
            'checkout'       => $data['checkout'] ?? null,
            'nights'         => isset($data['nights'])   ? (int) $data['nights']   : null,
            'adults'         => isset($data['adults'])   ? (int) $data['adults']   : 0,
            'children'       => isset($data['children']) ? (int) $data['children'] : 0,
            'child_ages'     => $data['child_ages'] ?? null,
            'subtotal'       => isset($data['subtotal']) ? (int) $data['subtotal'] : 0,
            'discount'       => isset($data['discount']) ? (int) $data['discount'] : 0,
            'total'          => isset($data['total'])    ? (int) $data['total']    : 0,
            'sales_user_id'  => $userId ?: null,
            'draft_payload'  => $data['draft_payload'] ?? null,
        ];
    }
}
