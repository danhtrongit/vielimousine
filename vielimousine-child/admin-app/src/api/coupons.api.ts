import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface Coupon {
  id: number;
  code: string;
  description: string | null;
  type: 'percentage' | 'fixed';
  value: number;
  min_order: number;
  max_discount: number | null;
  usage_limit: number | null;
  usage_limit_per_user: number | null;
  used_count: number;
  valid_from: string | null;
  valid_to: string | null;
  hotel_ids: number[] | null;
  room_ids: number[] | null;
  booking_types: string[] | null;
  is_active: boolean;
  sales_only: boolean;
  created_by: number | null;
  created_at: string;
}

export interface CouponUsage {
  id: number;
  coupon_id: number;
  order_id: number;
  user_email: string | null;
  discount: number;
  used_at: string;
}

export const couponsApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Coupon[]>>('/coupons', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<Coupon>>(`/coupons/${id}`).then((r) => r.data),
  create: (body: Partial<Coupon>) =>
    api.post<Envelope<Coupon>>('/coupons', body).then((r) => r.data),
  update: (id: number, body: Partial<Coupon>) =>
    api.put<Envelope<Coupon>>(`/coupons/${id}`, body).then((r) => r.data),
  destroy: (id: number) =>
    api.delete<Envelope<null>>(`/coupons/${id}`).then((r) => r.data),
};

export const couponUsageApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<CouponUsage[]>>('/coupon-usage', { params }).then((r) => r.data),
};
