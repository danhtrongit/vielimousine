import type { CheckoutForm } from '@/api/types';

/**
 * SePay Cổng thanh toán nhận POST bằng HTML form (không phải GET redirect).
 * Dựng form ẩn từ {action, fields} rồi auto-submit sang trang thanh toán SePay.
 */
export function submitCheckoutForm(checkout: CheckoutForm): void {
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
