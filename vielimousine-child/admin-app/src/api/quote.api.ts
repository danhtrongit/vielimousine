import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface QuoteRequest {
  room_id: number;
  checkin: string;
  checkout: string;
  adults: number;
  child_ages: number[];
  user_rooms: number;
  booking_type: 'room' | 'combo';
  coupon_code?: string | null;
}

export interface PriceBreakdown {
  num_rooms: number;
  nights: number;
  effective_adults: number;
  effective_children: number;
  extra_adult_beds: number;
  seat_count: number;
  billable_seats: number;
  free_child_seats: number;
  nightly: Array<{ date: string; price: number; extra_adult_price: number; ticket_price: number; child_surcharges: Array<{ label: string; age: number; amount: number }> }>;
  child_assessments: Array<{ age: number; is_free: boolean; treated_as_adult: boolean }>;
  room_subtotal: number;
  extra_adult_subtotal: number;
  child_surcharge_total: number;
  ticket_subtotal: number;
  subtotal: number;
  discount: number;
  total: number;
  // cost_total: bỏ — /quote public không trả giá vốn. Giá vốn order lấy từ endpoint order (gated CostVisibility).
  requires_quote: boolean;
  messages: string[];
  unavailable_date: string | null;
}

export const quoteApi = {
  quote: (body: QuoteRequest) =>
    api.post<Envelope<PriceBreakdown>>('/quote', body).then(r => r.data),
};

export const couponsApi = {
  validate: (body: { code: string; order_subtotal: number; hotel_id?: number; room_id?: number; booking_type?: string; user_email?: string }) =>
    api.post<Envelope<{ valid: boolean; discount: number; messages: string[]; coupon: unknown }>>('/coupons/validate', body).then(r => r.data),
};
