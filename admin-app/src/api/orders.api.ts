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
  cancel: (id: number, reason: string) =>
    api.post<Envelope<OrderDetail>>(`/orders/${id}/cancel`, { reason }).then(r => r.data),
};
