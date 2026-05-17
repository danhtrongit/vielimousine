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

export const reportsApi = {
  byHotel: (params: ByHotelParams) =>
    api.get<Envelope<ByHotelRow[]>>('/reports/by-hotel', { params }).then((r) => r.data),
};
