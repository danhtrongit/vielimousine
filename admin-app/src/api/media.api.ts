import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { MediaItem } from '@/types/media';

export interface MediaListParams {
  page?: number;
  per_page?: number;
  q?: string;
}

export const mediaApi = {
  list: (params: MediaListParams = {}) =>
    api.get<Envelope<MediaItem[]>>('/media', { params }).then((r) => r.data),

  get: (id: number) =>
    api.get<Envelope<MediaItem>>(`/media/${id}`).then((r) => r.data),

  upload: (file: File, onProgress?: (percent: number) => void) => {
    const fd = new FormData();
    fd.append('file', file);
    return api
      .post<Envelope<MediaItem>>('/media', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (onProgress && e.total) {
            onProgress(Math.round((e.loaded / e.total) * 100));
          }
        },
      })
      .then((r) => r.data);
  },

  update: (id: number, body: Partial<Pick<MediaItem, 'title' | 'alt' | 'caption'>>) =>
    api.put<Envelope<MediaItem>>(`/media/${id}`, body).then((r) => r.data),

  delete: (id: number) => api.delete(`/media/${id}`),
};
