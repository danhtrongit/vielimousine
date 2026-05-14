import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { Payment } from '@/types/order';

export const paymentsApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Payment[]>>('/payments', { params }).then(r => r.data),
  create: (body: { order_id: number; type: string; amount: number; method: string; gateway?: string | null; transaction_id?: string | null; note?: string | null }) =>
    api.post<Envelope<unknown>>('/payments', body).then(r => r.data),
};
