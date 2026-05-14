import { useToast } from 'primevue/usetoast';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types/envelope';

export function useNotify() {
  const toast = useToast();

  return {
    success: (summary: string, detail?: string) =>
      toast.add({ severity: 'success', summary, detail, life: 3000 }),
    info: (summary: string, detail?: string) =>
      toast.add({ severity: 'info', summary, detail, life: 3000 }),
    warn: (summary: string, detail?: string) =>
      toast.add({ severity: 'warn', summary, detail, life: 5000 }),
    error: (summary: string, detail?: string) =>
      toast.add({ severity: 'error', summary, detail, life: 6000 }),
    apiError: (err: unknown, fallback = 'Có lỗi xảy ra') => {
      const axiosErr = err as AxiosError<{ errors?: ApiError[] }>;
      const errors = axiosErr?.response?.data?.errors;
      if (Array.isArray(errors) && errors.length > 0) {
        errors.forEach((e) =>
          toast.add({ severity: 'error', summary: e.code, detail: e.message, life: 6000 })
        );
      } else {
        toast.add({ severity: 'error', summary: fallback, detail: axiosErr?.message, life: 6000 });
      }
    },
  };
}
