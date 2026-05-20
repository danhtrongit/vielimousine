import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { Room } from '@/types/hotel';

export const roomsApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<Room[]>>('/rooms', { params }).then((r) => r.data),
  get: (id: number) =>
    api.get<Envelope<Room>>(`/rooms/${id}`).then((r) => r.data),
  create: (body: Partial<Room>) =>
    api.post<Envelope<Room>>('/rooms', body).then((r) => r.data),
  update: (id: number, body: Partial<Room>) =>
    api.put<Envelope<Room>>(`/rooms/${id}`, body).then((r) => r.data),
};
