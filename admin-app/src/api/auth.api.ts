import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { AuthTokens, AuthUser } from '@/types/auth';

export const authApi = {
  login: (body: { username: string; password: string; remember?: boolean }) =>
    api.post<Envelope<AuthTokens>>('/auth/login', body).then(r => r.data),
  refresh: () =>
    api.post<Envelope<AuthTokens>>('/auth/refresh').then(r => r.data),
  logout: () =>
    api.post<Envelope<{ logged_out: boolean }>>('/auth/logout').then(r => r.data),
  me: () =>
    api.get<Envelope<AuthUser>>('/auth/me').then(r => r.data),
};
