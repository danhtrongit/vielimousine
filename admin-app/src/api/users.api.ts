import { api } from './client';
import type { Envelope } from '@/types/envelope';

export interface VieUser {
  id: number;
  display_name: string;
  email: string;
  roles: string[];
}

export const usersApi = {
  list: () => api.get<Envelope<VieUser[]>>('/users').then((r) => r.data),
};
