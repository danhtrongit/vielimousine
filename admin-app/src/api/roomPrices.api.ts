import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface RoomPrice {
  id: number;
  room_id: number;
  date: string;
  price: number;
  extra_adult_price: number;
  stock: number;
  is_active: boolean;
  source: string;
}

export interface BulkScope {
  room_ids: number[];
  date_from: string;
  date_to: string;
  weekdays?: number[] | null;
}

export interface BulkValues {
  price?: number;
  extra_adult_price?: number;
  stock?: number;
  is_active?: boolean;
  source?: string;
}

export interface BulkResult {
  rows_affected: number;
  dates_count: number;
  rooms_count: number;
  cells_count: number;
}

export const roomPricesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<RoomPrice[]>>('/room-prices', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<RoomPrice>>(`/room-prices/${id}`).then((r) => r.data),
  create: (body: Partial<RoomPrice>) =>
    api.post<Envelope<RoomPrice>>('/room-prices', body).then((r) => r.data),
  update: (id: number, body: Partial<RoomPrice>) =>
    api.put<Envelope<RoomPrice>>(`/room-prices/${id}`, body).then((r) => r.data),
  bulk: (scope: BulkScope, values: BulkValues) =>
    api.post<Envelope<BulkResult>>('/room-prices/bulk', { scope, values }).then((r) => r.data),
};
