import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { BookingQuote, BookingQuoteDetail, BookingQuotePayload } from '@/types/bookingQuote';

export const bookingQuotesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<BookingQuote[]>>('/booking-quotes', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<BookingQuoteDetail>>(`/booking-quotes/${id}`).then((r) => r.data),
  create: (body: BookingQuotePayload) =>
    api.post<Envelope<BookingQuote>>('/booking-quotes', body).then((r) => r.data),
  update: (id: number, body: BookingQuotePayload) =>
    api.put<Envelope<BookingQuote>>(`/booking-quotes/${id}`, body).then((r) => r.data),
  publish: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/publish`).then((r) => r.data),
  revoke: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/revoke`).then((r) => r.data),
  duplicate: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/duplicate`).then((r) => r.data),
};
