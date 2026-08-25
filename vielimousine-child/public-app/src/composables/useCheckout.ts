import type { CheckoutForm } from '@/api/types';
import { fbTrack } from '@/composables/useFbPixel';

/**
 * SePay Cổng thanh toán nhận POST bằng HTML form (không phải GET redirect).
 * Dựng form ẩn từ {action, fields} rồi auto-submit sang trang thanh toán SePay.
 */
export function submitCheckoutForm(checkout: CheckoutForm): void {
  // Meta Pixel: bắt đầu thanh toán → InitiateCheckout (mọi lần chuyển sang SePay:
  // đặt đơn mới hoặc "Thanh toán ngay" cho đơn còn nợ). Dedup 1 lần / mã đơn.
  const f = checkout.fields || {};
  const code = String(f.order_invoice_number || '');
  fbTrack('InitiateCheckout', {
    value: Number(f.order_amount || 0),
    currency: String(f.currency || 'VND'),
    content_ids: code ? [code] : [],
    content_type: 'hotel_booking',
  }, code ? { dedupKey: `vie_fb_ic_${code}` } : {});

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkout.action;
  form.style.display = 'none';
  for (const [name, value] of Object.entries(checkout.fields)) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = String(value ?? '');
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
}
