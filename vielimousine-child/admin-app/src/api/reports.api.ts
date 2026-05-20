import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface ByHotelRow {
  hotel_id: number;
  hotel_name: string;
  orders: number;
  revenue: number;
  cost: number;
  profit: number;
}

export interface ByHotelParams {
  date_from: string;
  date_to: string;
  hotel_ids?: number[];
  sales_user_ids?: number[];
  sources?: string[];
}

export interface OrderExportRow {
  order_id: number;
  order_code: string;
  order_created_at: string;
  order_confirmed_at: string | null;
  customer_id: number;
  customer_name: string;
  customer_phone: string;
  order_source: string;
  invoice_number: string;
  voucher_code: string;
  coupon_code: string;
  order_total: number;
  order_paid: number;
  order_outstanding: number;
  order_discount: number;
  customer_note: string;
  internal_note: string;
  sales_user_id: number | null;
  sales_name: string;
  item_id: number;
  item_name: string;
  item_unit: string;
  booking_type: string;
  item_qty: number;
  item_nights: number;
  item_checkin: string;
  item_checkout: string;
  sell_unit_price: number;
  surcharge_total: number;
  item_total: number;
  item_cost: number;
  item_profit: number;
  room_id: number;
  hotel_id: number;
  hotel_name: string;
  hotel_city: string;
  partner_name: string;
  hotel_area: string;
  supplier_booking_code: string;
  pay_methods: string;
  pay_last_at: string | null;
  pay_deposit_sum: number;
  pay_payment_sum: number;
}

export const reportsApi = {
  byHotel: (params: ByHotelParams) =>
    api.get<Envelope<ByHotelRow[]>>('/reports/by-hotel', { params }).then((r) => r.data),
  ordersExport: (params: ByHotelParams) =>
    api.get<Envelope<OrderExportRow[]>>('/reports/orders-export', { params }).then((r) => r.data),
};
