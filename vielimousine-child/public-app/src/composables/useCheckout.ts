import type { CheckoutForm } from '@/api/types';
import { fbTrack } from '@/composables/useFbPixel';

/**
 * fbevents đẩy beacon `facebook.com/tr` sau ~20 ms kể từ lúc gọi `fbq('track')`
 * (đo trên production). Điều hướng ngay trong cùng tick huỷ request → mất
 * InitiateCheckout. Chờ ngắn trước khi POST sang SePay, chỉ khi event thật sự fire.
 */
const PIXEL_FLUSH_MS = 350;

/** Cho phép fire lại InitiateCheckout cho cùng mã đơn sau 30 phút (khách bỏ dở rồi trả lại). */
const IC_DEDUP_TTL_MS = 30 * 60 * 1000;

/**
 * SePay Cổng thanh toán nhận POST bằng HTML form (không phải GET redirect).
 * Dựng form ẩn từ {action, fields} rồi auto-submit sang trang thanh toán SePay.
 */
export function submitCheckoutForm(checkout: CheckoutForm): void {
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

  if (tracked) setTimeout(() => form.submit(), PIXEL_FLUSH_MS);
  else form.submit();
}
