import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface TicketPrice {
  id: number;
  hotel_id: number;
  route_id: number;
  date: string;
  ticket_price: number;
  is_active: boolean;
}

export interface TicketPriceBulkScope {
  hotel_ids: number[];
  route_id?: number;
  date_from: string;
  date_to: string;
  weekdays?: number[] | null;
}

export interface TicketPriceBulkValues {
  ticket_price?: number;
  is_active?: boolean;
}

export interface TicketPriceBulkResult {
  rows_affected: number;
  dates_count: number;
  hotels_count: number;
  cells_count: number;
}

export const ticketPricesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<TicketPrice[]>>('/ticket-prices', { params }).then((r) => r.data),
  create: (body: Partial<TicketPrice>) =>
    api.post<Envelope<TicketPrice>>('/ticket-prices', body).then((r) => r.data),
  update: (id: number, body: Partial<TicketPrice>) =>
    api.put<Envelope<TicketPrice>>(`/ticket-prices/${id}`, body).then((r) => r.data),
  bulk: (scope: TicketPriceBulkScope, values: TicketPriceBulkValues) =>
    api.post<Envelope<TicketPriceBulkResult>>('/ticket-prices/bulk', { scope, values }).then((r) => r.data),
};
