import { describe, it, expect } from 'vitest';
import {
  CODE_MAX_LENGTH,
  normalizeAffix,
  validateBulkForm,
  type BulkFormInput,
} from './couponBulkForm';

const base: BulkFormInput = {
  prefix: 'VIE',
  suffix: '',
  randomLength: 8,
  template: {
    type: 'percentage',
    value: 10,
    valid_from: '2026-01-01 00:00:00',
    valid_to: '2026-12-31 23:59:59',
  },
};

describe('normalizeAffix', () => {
  it('trims and uppercases like the PHP generator', () => {
    expect(normalizeAffix('  vie-26 ')).toBe('VIE-26');
    expect(normalizeAffix(null)).toBe('');
  });
});

describe('validateBulkForm', () => {
  it('accepts a valid configuration', () => {
    expect(validateBulkForm(base)).toBeNull();
  });

  it('rejects affixes with characters outside [A-Z0-9_-]', () => {
    expect(validateBulkForm({ ...base, prefix: 'VIE HÈ!' })).toMatch(/Tiền tố/);
    expect(validateBulkForm({ ...base, suffix: 'HÈ@' })).toMatch(/Tiền tố/);
  });

  it('rejects codes longer than the coupon column', () => {
    const err = validateBulkForm({
      ...base,
      prefix: 'A'.repeat(20),
      suffix: 'B'.repeat(20),
      randomLength: 16,
    });
    expect(err).toBe(`Tổng độ dài mã 56 ký tự — tối đa ${CODE_MAX_LENGTH}.`);
  });

  it('caps percentage discounts at 100 but leaves fixed amounts alone', () => {
    expect(validateBulkForm({ ...base, template: { ...base.template, value: 120 } })).toMatch(/phần trăm/);
    expect(
      validateBulkForm({ ...base, template: { ...base.template, type: 'fixed', value: 500000 } }),
    ).toBeNull();
  });

  it('rejects a validity window that ends before it starts', () => {
    const err = validateBulkForm({
      ...base,
      template: { ...base.template, valid_to: '2025-01-01 00:00:00' },
    });
    expect(err).toMatch(/Hiệu lực đến/);
  });

  it('allows an open-ended validity window', () => {
    expect(
      validateBulkForm({ ...base, template: { ...base.template, valid_from: null, valid_to: null } }),
    ).toBeNull();
  });
});
