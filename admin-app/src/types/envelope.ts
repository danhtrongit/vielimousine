export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  has_next: boolean;
  has_prev: boolean;
}

export interface ApiError {
  code: string;
  field: string | null;
  message: string;
  meta?: Record<string, unknown>;
}

export interface EnvelopeMeta {
  request_id: string;
  timestamp: string;
  pagination?: Pagination;
  sort?: { field: string; order: 'asc' | 'desc' };
  filters_applied?: Record<string, unknown>;
  available_sorts?: string[];
}

export interface Envelope<T> {
  success: boolean;
  data: T;
  meta: EnvelopeMeta;
  errors: ApiError[] | null;
}
