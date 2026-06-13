import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { StaffUser, CreateUserPayload, UpdateUserPayload } from '@/types/user';

export interface VieUser {
  id: number;
  display_name: string;
  email: string;
  roles: string[];
}

export const usersApi = {
  list: () => api.get<Envelope<VieUser[]>>('/users').then((r) => r.data),
  show: (id: number) =>
    api.get<Envelope<StaffUser>>(`/users/${id}`).then((r) => r.data),
  create: (body: CreateUserPayload) =>
    api.post<Envelope<StaffUser>>('/users', body).then((r) => r.data),
  update: (id: number, body: UpdateUserPayload) =>
    api.put<Envelope<StaffUser>>(`/users/${id}`, body).then((r) => r.data),
  remove: (id: number) =>
    api.delete<Envelope<null>>(`/users/${id}`).then((r) => r.data),
};
