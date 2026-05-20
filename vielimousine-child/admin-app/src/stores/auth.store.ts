import { defineStore } from 'pinia';
import { authApi } from '@/api/auth.api';
import type { AuthUser } from '@/types/auth';

const broadcastChannel: BroadcastChannel | null =
  typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('vie_auth') : null;

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    accessToken: null as string | null,
    expiresAt: 0,
  }),
  getters: {
    isAuthenticated: (s): boolean => s.user !== null && s.accessToken !== null,
    can: (s) => (cap: string): boolean => s.user?.caps.includes(cap) ?? false,
    canAny: (s) => (caps: string[]): boolean => caps.some((c) => s.user?.caps.includes(c) ?? false),
  },
  actions: {
    async login(username: string, password: string) {
      const resp = await authApi.login({ username, password });
      this.setTokens(resp.data.access_token, resp.data.expires_in, resp.data.user);
      broadcastChannel?.postMessage({ type: 'login' });
    },
    async refresh() {
      const resp = await authApi.refresh();
      this.setTokens(resp.data.access_token, resp.data.expires_in, resp.data.user);
    },
    async logout() {
      try {
        await authApi.logout();
      } catch {
        /* ignore */
      }
      this.clear();
      broadcastChannel?.postMessage({ type: 'logout' });
    },
    async tryRefresh() {
      try {
        await this.refresh();
        return true;
      } catch {
        this.clear();
        return false;
      }
    },
    setTokens(accessToken: string, expiresIn: number, user: AuthUser) {
      this.accessToken = accessToken;
      this.expiresAt = Date.now() + expiresIn * 1000;
      this.user = user;
    },
    clear() {
      this.user = null;
      this.accessToken = null;
      this.expiresAt = 0;
    },
  },
});

broadcastChannel?.addEventListener('message', (e) => {
  const data = e.data as { type?: string } | null;
  if (data?.type === 'logout') {
    const store = useAuthStore();
    store.clear();
    if (!location.pathname.endsWith('/login')) {
      location.assign('/vie-admin/login');
    }
  }
});
