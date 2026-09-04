import type { CouponTemplate } from '@/api/coupons.api';

// Các trần dưới đây PHẢI khớp backend:
// inc/src/Service/Coupon/CouponBulkService.php + CouponCodeGenerator.php
export const MAX_QUANTITY = 500;
export const MIN_RANDOM_LENGTH = 4;
export const MAX_RANDOM_LENGTH = 16;
export const CODE_MAX_LENGTH = 50;

/** Ký tự cho phép ở tiền tố/hậu tố — khớp CouponCodeGenerator::AFFIX_PATTERN. */
const AFFIX_RE = /^[A-Z0-9_-]*$/;

/** Mã lưu dạng in hoa; chuẩn hoá y như CouponCodeGenerator::normalizeAffix(). */
export function normalizeAffix(affix: string | null | undefined): string {
  return (affix ?? '').trim().toUpperCase();
}

export interface BulkFormInput {
  prefix: string;
  suffix: string;
  randomLength: number;
  template: Pick<CouponTemplate, 'type' | 'value' | 'valid_from' | 'valid_to'>;
}

/**
 * Chặn sớm ở client những gì CouponBulkValidation::crossValidate() sẽ từ chối —
 * trả về thông báo lỗi đầu tiên, hoặc null nếu hợp lệ.
 */
export function validateBulkForm(input: BulkFormInput): string | null {
  const prefix = normalizeAffix(input.prefix);
  const suffix = normalizeAffix(input.suffix);

  if (!AFFIX_RE.test(prefix) || !AFFIX_RE.test(suffix)) {
    return 'Tiền tố / hậu tố chỉ được chứa chữ, số, gạch ngang và gạch dưới.';
  }

  const length = prefix.length + Math.max(0, input.randomLength) + suffix.length;
  if (length > CODE_MAX_LENGTH) {
    return `Tổng độ dài mã ${length} ký tự — tối đa ${CODE_MAX_LENGTH}.`;
  }

  const { type, value, valid_from: from, valid_to: to } = input.template;
  if (type === 'percentage' && Number(value ?? 0) > 100) {
    return 'Giảm theo phần trăm không được vượt 100.';
  }

  if (from && to && to < from) {
    return 'Hiệu lực đến phải sau hiệu lực từ.';
  }

  return null;
}
