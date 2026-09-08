import type { CheckoutForm } from '@/api/types';
import { afterTrackingFlush, fbTrack } from '@/composables/useFbPixel';

/** Cho phép fire lại InitiateCheckout cho cùng mã đơn sau 30 phút (khách bỏ dở rồi trả lại). */
const IC_DEDUP_TTL_MS = 30 * 60 * 1000;

/**
 * SePay Cổng thanh toán nhận POST bằng HTML form (không phải GET redirect).
 * Dựng form ẩn từ {action, fields} rồi auto-submit sang trang thanh toán SePay.
 *
 * @param alreadyTracked caller vừa bắn event khác (vd dataLayer) → vẫn chờ flush dù pixel không fire.
 */
export function submitCheckoutForm(checkout: CheckoutForm, alreadyTracked = false): void {
  // Meta Pixel: bắt đầu thanh toán → InitiateCheckout (mọi lần chuyển sang SePay:
  // đặt đơn mới hoặc "Thanh toán ngay" cho đơn còn nợ).
  const f = checkout.fields || {};
  const code = String(f.order_invoice_number || '');
  const tracked = fbTrack('InitiateCheckout', {
    value: Number(f.order_amount || 0),
    currency: String(f.currency || 'VND'),
    content_ids: code ? [code] : [],
    content_type: 'hotel_booking',
  }, code ? { dedupKey: `vie_fb_ic_${code}`, ttlMs: IC_DEDUP_TTL_MS } : {});

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

  afterTrackingFlush(tracked || alreadyTracked, () => form.submit());
}
