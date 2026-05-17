import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { Order, OrderDetail } from '@/types/order';

export const ordersApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Order[]>>('/orders', { params }).then(r => r.data),
  get: (id: number) =>
    api.get<Envelope<OrderDetail>>(`/orders/${id}`).then(r => r.data),
  create: (body: unknown, idempotencyKey?: string) =>
    api.post<Envelope<OrderDetail>>('/orders', body, {
      headers: idempotencyKey ? { 'X-Idempotency-Key': idempotencyKey } : undefined,
    }).then(r => r.data),
  update: (id: number, body: Partial<Order>) =>
    api.put<Envelope<OrderDetail>>(`/orders/${id}`, body).then(r => r.data),
  cancel: (id: number, reason: string, refundAmount = 0) =>
    api.post<Envelope<OrderDetail>>(`/orders/${id}/cancel`, { reason, refund_amount: refundAmount }).then(r => r.data),
  transition: (id: number, status: 'confirmed' | 'completed' | 'no_show') =>
    api.post<Envelope<OrderDetail>>(`/orders/${id}/transition`, { status }).then(r => r.data),
};
