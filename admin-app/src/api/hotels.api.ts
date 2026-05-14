import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { Hotel } from '@/types/hotel';

export const hotelsApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Hotel[]>>('/hotels', { params }).then(r => r.data),
  get: (id: number) =>
    api.get<Envelope<Hotel>>(`/hotels/${id}`).then(r => r.data),
  update: (id: number, body: Partial<Hotel>) =>
    api.put<Envelope<Hotel>>(`/hotels/${id}`, body).then(r => r.data),
};

// Re-export roomsApi for backward compat (lookup.store imports it from here)
export { roomsApi } from './rooms.api';
