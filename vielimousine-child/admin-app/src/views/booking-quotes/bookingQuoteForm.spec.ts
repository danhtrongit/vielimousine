import { describe, expect, it } from 'vitest';
import { createDefaultQuote, lineTotal, quoteAmounts, validateQuote } from './bookingQuoteForm';

describe('booking quote form helpers', () => {
  it('creates useful defaults with a future expiry and starter lines', () => {
    const quote = createDefaultQuote(new Date('2026-09-22T09:00:00'));
    expect(quote.deposit_type).toBe('percent');
    expect(quote.deposit_value).toBe(30);
    expect(quote.lines).toHaveLength(2);
    expect(quote.valid_until).toContain('2026-09-29');
  });

  it('calculates totals and clamps deposit to total', () => {
    const quote = createDefaultQuote();
    quote.lines = [{ label: 'Xe', quantity: 2, unit: 'chuyến', unit_price: 1_000_000 }];
    quote.discount = 250_000;
    quote.deposit_type = 'fixed';
    quote.deposit_value = 9_000_000;
    expect(lineTotal(quote.lines[0])).toBe(2_000_000);
    expect(quoteAmounts(quote)).toEqual({ subtotal: 2_000_000, total: 1_750_000, deposit: 1_750_000, remaining: 0 });
  });

  it('requires a positive priced line only when publishing', () => {
    const quote = createDefaultQuote();
    quote.customer_name = 'Nguyễn Văn A';
    quote.title = 'Xe đi Hạ Long';
    expect(validateQuote(quote)).toEqual([]);
    expect(validateQuote(quote, true)).toContain('Cần ít nhất một dòng có thành tiền lớn hơn 0 để phát hành.');
  });

  it('allows an incomplete draft to be saved for later', () => {
    const quote = createDefaultQuote();
    quote.customer_name = '';
    quote.title = '';
    quote.lines = [];
    quote.deposit_value = 0;
    expect(validateQuote(quote)).toEqual([]);
    expect(validateQuote(quote, true)).toContain('Vui lòng nhập tên khách hàng hoặc tên đoàn.');
  });
});
