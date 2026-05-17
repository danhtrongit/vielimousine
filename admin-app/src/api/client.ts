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

    // Network error (no response) hoặc 5xx server error → đẩy sang /503
    // Bỏ qua nếu đang ở /503 rồi (tránh loop) hoặc retry call.
    const networkDown = !err.response && (err.code === 'ERR_NETWORK' || err.code === 'ECONNABORTED');
    const serverDown  = typeof status === 'number' && status >= 500 && status !== 501;
    const onErrorPage = location.pathname.endsWith('/503');

    if ((networkDown || serverDown) && !onErrorPage) {
      const reason = networkDown
        ? 'Không kết nối được tới máy chủ.'
        : `Máy chủ trả về lỗi ${status}.`;
      location.assign(`/vie-admin/503?reason=${encodeURIComponent(reason)}`);
    }

    return Promise.reject(err);
  }
);
