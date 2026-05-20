import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface Surcharge {
  id: number;
  room_id: number;
  guest_type: string;
  label: string;
  age_from: number;
  age_to: number;
  child_index_min: number;
  child_index_max: number | null;
  amount: number;
  is_free: boolean;
  sort_order: number;
  is_active: boolean;
}

export interface SurchargePrice {
  id: number;
  surcharge_id: number;
  date: string;
  amount: number;
  is_active: boolean;
}

export const surchargesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Surcharge[]>>('/surcharges', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<Surcharge>>(`/surcharges/${id}`).then((r) => r.data),
  create: (body: Partial<Surcharge>) =>
    api.post<Envelope<Surcharge>>('/surcharges', body).then((r) => r.data),
  update: (id: number, body: Partial<Surcharge>) =>
    api.put<Envelope<Surcharge>>(`/surcharges/${id}`, body).then((r) => r.data),
  delete: (id: number) =>
    api.delete<Envelope<{ ok: boolean }>>(`/surcharges/${id}`).then((r) => r.data),
};

export interface SurchargePriceBulkScope {
  surcharge_ids: number[];
  date_from: string;
  date_to: string;
  weekdays?: number[] | null;
}

export interface SurchargePriceBulkValues {
  amount?: number;
  is_active?: boolean;
}

export interface SurchargePriceBulkResult {
  rows_affected: number;
  dates_count: number;
  surcharges_count: number;
  cells_count: number;
}

export const surchargePricesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<SurchargePrice[]>>('/surcharge-prices', { params }).then((r) => r.data),
  create: (body: Partial<SurchargePrice>) =>
    api.post<Envelope<SurchargePrice>>('/surcharge-prices', body).then((r) => r.data),
  update: (id: number, body: Partial<SurchargePrice>) =>
    api.put<Envelope<SurchargePrice>>(`/surcharge-prices/${id}`, body).then((r) => r.data),
  bulk: (scope: SurchargePriceBulkScope, values: SurchargePriceBulkValues) =>
    api.post<Envelope<SurchargePriceBulkResult>>('/surcharge-prices/bulk', { scope, values }).then((r) => r.data),
};
