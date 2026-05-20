import { ref, watch, type Ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '@/api/client';
import type { Envelope, Pagination } from '@/types/envelope';

export interface ApiListResult<T> {
  data: Ref<T[]>;
  pagination: Ref<Pagination>;
  availableSorts: Ref<string[]>;
  filtersApplied: Ref<Record<string, unknown>>;
  loading: Ref<boolean>;
  error: Ref<string | null>;
  fetchData: () => Promise<void>;
  updateQuery: (patch: Record<string, unknown>) => void;
}

export function useApiList<T>(endpoint: string, defaults: Record<string, unknown> = {}): ApiListResult<T> {
  const route = useRoute();
  const router = useRouter();
  const data = ref<T[]>([]) as Ref<T[]>;
  const pagination = ref<Pagination>({
    page: 1, per_page: 20, total: 0, total_pages: 0, has_next: false, has_prev: false,
  });
  const availableSorts = ref<string[]>([]);
  const filtersApplied = ref<Record<string, unknown>>({});
  const loading = ref(false);
  const error = ref<string | null>(null);

  async function fetchData() {
    loading.value = true;
    error.value = null;
    try {
      const params = { ...defaults, ...route.query };
      const resp = await api.get<Envelope<T[]>>(endpoint, { params });
      data.value = resp.data.data;
      if (resp.data.meta.pagination) pagination.value = resp.data.meta.pagination;
      availableSorts.value = resp.data.meta.available_sorts ?? [];
      filtersApplied.value = resp.data.meta.filters_applied ?? {};
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Lỗi tải dữ liệu';
      data.value = [];
    } finally {
      loading.value = false;
    }
  }

  function updateQuery(patch: Record<string, unknown>) {
    const next = { ...route.query, ...patch };
    Object.keys(next).forEach((k) => {
      if (next[k] === '' || next[k] === null || next[k] === undefined) delete next[k];
    });
    router.replace({ query: next as Record<string, string> });
  }

  watch(() => route.query, fetchData, { immediate: true, deep: true });

  return { data, pagination, availableSorts, filtersApplied, loading, error, fetchData, updateQuery };
}
