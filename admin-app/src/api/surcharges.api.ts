import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface Surcharge {
  id: number;
  room_id: number;
  guest_type: string;
  label: string;
  age_from: number;
  age_to: number;
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
};

export const surchargePricesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<SurchargePrice[]>>('/surcharge-prices', { params }).then((r) => r.data),
  create: (body: Partial<SurchargePrice>) =>
    api.post<Envelope<SurchargePrice>>('/surcharge-prices', body).then((r) => r.data),
  update: (id: number, body: Partial<SurchargePrice>) =>
    api.put<Envelope<SurchargePrice>>(`/surcharge-prices/${id}`, body).then((r) => r.data),
};
