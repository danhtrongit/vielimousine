import type { Order } from '@/types/order';
import { formatVND, formatDate, formatDateTime } from '@/composables/useFormat';
import { labelOrderStatus } from '@/stores/lookup.store';

/** CSV column headers; cost/profit columns only when the viewer is authorized. */
export function ordersCsvHeaders(canViewCost: boolean): string[] {
  return [
    'Mã đơn', 'Tên khách hàng', 'Check in', 'Check out', 'Tên khách sạn', 'Đêm',
    'Tổng tiền', 'Tổng khách sạn', 'Tổng chi phí vé',
    ...(canViewCost ? ['Tổng giá vốn', 'Lợi nhuận dự kiến'] : []),
    'Đã thanh toán', 'Chưa thanh toán', 'Trạng thái', 'Tạo lúc',
  ];
}

/** One CSV row for an order; cost/profit cells only when authorized (must match headers). */
export function orderToCsvRow(o: Order, canViewCost: boolean): unknown[] {
  const remaining = Math.max(0, Number(o.total ?? 0) - Number(o.paid_amount ?? 0));
  return [
    o.code,
    o.customer_name,
    formatDate(o.checkin),
    formatDate(o.checkout),
    o.hotel_names ?? '',
    o.nights,
    formatVND(o.total),
    formatVND(o.hotel_subtotal),
    formatVND(o.ticket_subtotal),
    ...(canViewCost ? [formatVND(o.cost_total), formatVND(o.profit_total)] : []),
    formatVND(o.paid_amount),
    formatVND(remaining),
    labelOrderStatus(o.status),
    formatDateTime(o.created_at),
  ];
}
