import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { BookingQuote, BookingQuoteDetail, BookingQuotePayload } from '@/types/bookingQuote';

function requestBody(body: BookingQuotePayload): Omit<BookingQuotePayload, 'lines'> {
  // Prices and snapshots are server-owned. Keep generated lines in the response
  // model for display, but never let a client submit or override them.
  const { lines: _lines, ...request } = body;
  return request;
}

export const bookingQuotesApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<BookingQuote[]>>('/booking-quotes', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<BookingQuoteDetail>>(`/booking-quotes/${id}`).then((r) => r.data),
  create: (body: BookingQuotePayload) =>
    api.post<Envelope<BookingQuote>>('/booking-quotes', requestBody(body)).then((r) => r.data),
  update: (id: number, body: BookingQuotePayload) =>
    api.put<Envelope<BookingQuote>>(`/booking-quotes/${id}`, requestBody(body)).then((r) => r.data),
  publish: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/publish`).then((r) => r.data),
  revoke: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/revoke`).then((r) => r.data),
  duplicate: (id: number) =>
    api.post<Envelope<BookingQuote>>(`/booking-quotes/${id}/duplicate`).then((r) => r.data),
  orderDraft: (id: number) =>
    api.post<Envelope<{ id: number }>>(`/booking-quotes/${id}/order-draft`).then((r) => r.data),
};
