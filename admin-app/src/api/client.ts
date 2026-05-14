import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from '@/stores/auth.store';

export const api = axios.create({
  baseURL: '/wp-json/vie/v1',
  withCredentials: true,
  headers: { 'Content-Type': 'application/json' },
});

let refreshing: Promise<void> | null = null;

api.interceptors.request.use((config) => {
  const auth = useAuthStore();
  if (auth.accessToken) {
    config.headers.Authorization = `Bearer ${auth.accessToken}`;
  }
  return config;
});

api.interceptors.response.use(
  (resp) => resp,
  async (err: AxiosError) => {
    const config = err.config as (InternalAxiosRequestConfig & { _retry?: boolean }) | undefined;
    const status = err.response?.status;

    if (status === 401 && config && !config._retry && !config.url?.includes('/auth/')) {
      config._retry = true;
      try {
        if (!refreshing) {
          const auth = useAuthStore();
          refreshing = auth.refresh().finally(() => { refreshing = null; });
        }
        await refreshing;
        return api(config);
      } catch {
        const auth = useAuthStore();
        auth.clear();
        if (!location.pathname.endsWith('/login')) {
          location.assign('/vie-admin/login');
        }
      }
    }
    return Promise.reject(err);
  }
);
