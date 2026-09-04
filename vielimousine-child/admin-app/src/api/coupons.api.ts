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

/** Cấu hình dùng chung cho mọi mã trong lô sinh hàng loạt. */
export type CouponTemplate = Pick<
  Coupon,
  | 'description'
  | 'type'
  | 'value'
  | 'min_order'
  | 'max_discount'
  | 'usage_limit'
  | 'usage_limit_per_user'
  | 'valid_from'
  | 'valid_to'
  | 'hotel_ids'
  | 'room_ids'
  | 'booking_types'
  | 'is_active'
  | 'sales_only'
>;

export interface CouponBulkGenerateRequest {
  quantity: number;
  prefix?: string;
  suffix?: string;
  random_length?: number;
  template: CouponTemplate;
}

export interface CouponBulkGenerateResult {
  created_count: number;
  requested_count: number;
  coupons: Coupon[];
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
  bulkGenerate: (body: CouponBulkGenerateRequest) =>
    api
      .post<Envelope<CouponBulkGenerateResult>>('/coupons/bulk-generate', body)
      .then((r) => r.data),
};

export const couponUsageApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<CouponUsage[]>>('/coupon-usage', { params }).then((r) => r.data),
};
