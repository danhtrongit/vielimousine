import type {
  BookingQuote,
  BookingQuoteLinePayload,
  BookingQuotePayload,
  BookingQuotePaymentStatus,
  BookingQuoteEffectiveStatus,
} from '@/types/bookingQuote';

const MAX_MONEY = 999_999_999_999;

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

export function toLocalDateTime(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

export function createDefaultQuote(now = new Date()): BookingQuotePayload {
  const validUntil = new Date(now);
  validUntil.setDate(validUntil.getDate() + 7);
  validUntil.setHours(23, 59, 0, 0);

  return {
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    title: '',
    image_url: '',
    greeting: 'Cảm ơn Quý khách đã quan tâm đến dịch vụ của Vie Limo. Chúng tôi trân trọng gửi đến Quý khách báo giá sau:',
    trip_start: null,
    trip_end: null,
    description: '',
    inclusions: '',
    exclusions: '',
    terms: 'Báo giá chỉ có hiệu lực trong thời hạn ghi trên báo giá. Sau khi nhận tiền cọc, nhân viên Vie Limo sẽ liên hệ kiểm tra và xác nhận dịch vụ. Thanh toán cọc không tự động xác nhận giữ chỗ.',
    contact_name: '',
    contact_phone: '',
    contact_zalo: '',
    lines: [
      { label: 'Người lớn', quantity: 1, unit: 'khách', unit_price: 0 },
      { label: 'Trẻ em (2–11 tuổi)', quantity: 1, unit: 'khách', unit_price: 0 },
    ],
    discount: 0,
    deposit_type: 'percent',
    deposit_value: 30,
    valid_until: toLocalDateTime(validUntil),
  };
}

export function quoteToPayload(quote: BookingQuote): BookingQuotePayload {
  return {
    customer_name: quote.customer_name ?? '',
    customer_phone: quote.customer_phone ?? '',
    customer_email: quote.customer_email ?? '',
    title: quote.title ?? '',
    image_url: quote.image_url ?? '',
    greeting: quote.greeting ?? '',
    trip_start: quote.trip_start,
    trip_end: quote.trip_end,
    description: quote.description ?? '',
    inclusions: quote.inclusions ?? '',
    exclusions: quote.exclusions ?? '',
    terms: quote.terms ?? '',
    contact_name: quote.contact_name ?? '',
    contact_phone: quote.contact_phone ?? '',
    contact_zalo: quote.contact_zalo ?? '',
    lines: (quote.lines ?? []).map(({ label, quantity, unit, unit_price }) => ({ label, quantity, unit, unit_price })),
    discount: quote.discount ?? 0,
    deposit_type: quote.deposit_type,
    deposit_value: quote.deposit_value,
    valid_until: quote.valid_until ?? '',
  };
}

export function lineTotal(line: BookingQuoteLinePayload): number {
  return Math.min(MAX_MONEY, Math.max(0, Math.round(line.quantity || 0)) * Math.max(0, Math.round(line.unit_price || 0)));
}

export function quoteAmounts(payload: BookingQuotePayload): { subtotal: number; total: number; deposit: number; remaining: number } {
  const subtotal = Math.min(MAX_MONEY, payload.lines.reduce((sum, line) => sum + lineTotal(line), 0));
  const total = Math.max(0, subtotal - Math.max(0, Math.round(payload.discount || 0)));
  const rawDeposit = payload.deposit_type === 'percent'
    ? Math.ceil(total * Math.max(0, payload.deposit_value || 0) / 100)
    : Math.max(0, Math.round(payload.deposit_value || 0));
  const deposit = Math.min(total, rawDeposit);
  return { subtotal, total, deposit, remaining: Math.max(0, total - deposit) };
}

export function validateQuote(payload: BookingQuotePayload, forPublish = false): string[] {
  const errors: string[] = [];
  if (forPublish && !payload.customer_name.trim()) errors.push('Vui lòng nhập tên khách hàng hoặc tên đoàn.');
  if (forPublish && !payload.title.trim()) errors.push('Vui lòng nhập tên dịch vụ hoặc chuyến đi.');
  if (forPublish && !payload.valid_until) errors.push('Vui lòng chọn hạn báo giá.');
  if (forPublish && payload.lines.length < 1) errors.push('Báo giá cần ít nhất một dòng giá.');
  if (payload.lines.length > 50) errors.push('Báo giá chỉ hỗ trợ tối đa 50 dòng giá.');
  payload.lines.forEach((line, index) => {
    if (forPublish && !line.label.trim()) errors.push(`Dòng ${index + 1}: chưa có nội dung.`);
    if (!Number.isInteger(line.quantity) || line.quantity < 1 || line.quantity > 1000) errors.push(`Dòng ${index + 1}: số lượng phải từ 1 đến 1.000.`);
    if (!Number.isInteger(line.unit_price) || line.unit_price < 0 || line.unit_price > MAX_MONEY) errors.push(`Dòng ${index + 1}: đơn giá không hợp lệ.`);
    if (line.quantity > 0 && line.unit_price > Math.floor(MAX_MONEY / line.quantity)) errors.push(`Dòng ${index + 1}: thành tiền vượt giới hạn cho phép.`);
  });
  const rawSubtotal = payload.lines.reduce((sum, line) => sum + Math.max(0, line.quantity || 0) * Math.max(0, line.unit_price || 0), 0);
  if (rawSubtotal > MAX_MONEY) errors.push('Tổng báo giá vượt giới hạn cho phép.');
  if (payload.trip_start && payload.trip_end && payload.trip_end < payload.trip_start) errors.push('Ngày về không được trước ngày đi.');
  const amounts = quoteAmounts(payload);
  if (payload.discount < 0 || payload.discount > amounts.subtotal) errors.push('Giảm giá không được vượt quá tạm tính.');
  if (payload.deposit_value < 0) errors.push('Giá trị tiền cọc không được âm.');
  if (payload.deposit_type === 'percent' && payload.deposit_value > 100) errors.push('Tỷ lệ cọc không được quá 100%.');
  if (forPublish && payload.deposit_type === 'percent' && payload.deposit_value <= 0) errors.push('Tỷ lệ cọc phải lớn hơn 0.');
  if (payload.deposit_type === 'fixed' && payload.deposit_value > amounts.total) errors.push('Tiền cọc không được vượt quá tổng tiền.');
  if (forPublish && payload.deposit_type === 'fixed' && payload.deposit_value <= 0) errors.push('Tiền cọc phải lớn hơn 0.');
  if (forPublish && !payload.lines.some((line) => lineTotal(line) > 0)) errors.push('Cần ít nhất một dòng có thành tiền lớn hơn 0 để phát hành.');
  if (forPublish && (amounts.total <= 0 || amounts.deposit <= 0)) errors.push('Tổng tiền và tiền cọc phải lớn hơn 0 để phát hành.');
  return [...new Set(errors)];
}

export const effectiveStatusLabels: Record<BookingQuoteEffectiveStatus, string> = {
  draft: 'Bản nháp', published: 'Đã phát hành', expired: 'Hết hạn', revoked: 'Đã thu hồi',
};

export const paymentStatusLabels: Record<BookingQuotePaymentStatus, string> = {
  unpaid: 'Chưa thanh toán', deposit_paid: 'Đã thanh toán cọc', paid: 'Đã thanh toán đủ', review_required: 'Cần đối soát',
};
