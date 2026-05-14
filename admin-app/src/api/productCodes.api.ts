import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface ProductCode {
  id: number;
  code: string;
  hotel_id: number;
  room_id: number;
  booking_type: string;
  weekday_pattern: string | null;
  display_name: string;
  unit_label: string;
  is_active: boolean;
}

export const productCodesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<ProductCode[]>>('/product-codes', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<ProductCode>>(`/product-codes/${id}`).then((r) => r.data),
  create: (body: Partial<ProductCode>) =>
    api.post<Envelope<ProductCode>>('/product-codes', body).then((r) => r.data),
  update: (id: number, body: Partial<ProductCode>) =>
    api.put<Envelope<ProductCode>>(`/product-codes/${id}`, body).then((r) => r.data),
  destroy: (id: number) =>
    api.delete<Envelope<null>>(`/product-codes/${id}`).then((r) => r.data),
};
