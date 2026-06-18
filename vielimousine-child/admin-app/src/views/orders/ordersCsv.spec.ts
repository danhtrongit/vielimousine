import { describe, it, expect } from 'vitest';
import type { Order } from '@/types/order';
import { ordersCsvHeaders, orderToCsvRow } from './ordersCsv';

const order = {
  code: 'VIE0001', customer_name: 'A', checkin: '2026-01-01', checkout: '2026-01-02',
  hotel_names: 'H', nights: 1, total: 100, hotel_subtotal: 80, ticket_subtotal: 20,
  cost_total: 60, profit_total: 40, paid_amount: 50, status: 'pending',
  created_at: '2026-01-01T00:00:00Z',
} as unknown as Order;

describe('ordersCsv', () => {
  it('omits cost/profit headers when canViewCost=false', () => {
    const h = ordersCsvHeaders(false);
    expect(h).not.toContain('Tổng giá vốn');
    expect(h).not.toContain('Lợi nhuận dự kiến');
  });

  it('includes cost/profit headers when canViewCost=true', () => {
    const h = ordersCsvHeaders(true);
    expect(h).toContain('Tổng giá vốn');
    expect(h).toContain('Lợi nhuận dự kiến');
  });

  it('row length always matches header length', () => {
    expect(orderToCsvRow(order, false).length).toBe(ordersCsvHeaders(false).length);
    expect(orderToCsvRow(order, true).length).toBe(ordersCsvHeaders(true).length);
  });
});
