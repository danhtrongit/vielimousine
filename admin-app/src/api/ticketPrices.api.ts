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

export const ticketPricesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<TicketPrice[]>>('/ticket-prices', { params }).then((r) => r.data),
  create: (body: Partial<TicketPrice>) =>
    api.post<Envelope<TicketPrice>>('/ticket-prices', body).then((r) => r.data),
  update: (id: number, body: Partial<TicketPrice>) =>
    api.put<Envelope<TicketPrice>>(`/ticket-prices/${id}`, body).then((r) => r.data),
};
