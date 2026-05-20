export interface Envelope<T> {
  success: boolean;
  data: T;
  errors?: Array<{ code: string; field?: string; message: string }>;
}

export class ApiError extends Error {
  errors: Array<{ code: string; field?: string; message: string }>;
  status: number;
  constructor(errors: ApiError['errors'], status: number) {
    super(errors?.[0]?.message || 'Lỗi không xác định');
    this.errors = errors || [];
    this.status = status;
  }
}

function apiRoot(): string {
  return (window.VieRest?.root) || '/wp-json/vie/v1/';
}

function nonce(): string {
  return window.VieRest?.nonce || '';
}

interface FetchOpts {
  method?: string;
  body?: unknown;
  idempotencyKey?: string;
  signal?: AbortSignal;
  query?: Record<string, string | number | (string | number)[] | undefined>;
}

export async function request<T>(path: string, opts: FetchOpts = {}): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': nonce(),
  };
  if (opts.idempotencyKey) headers['X-Idempotency-Key'] = opts.idempotencyKey;

  let url = apiRoot() + path.replace(/^\//, '');
  if (opts.query) {
    const sp = new URLSearchParams();
    for (const [k, v] of Object.entries(opts.query)) {
      if (v === undefined || v === null || v === '') continue;
      if (Array.isArray(v)) v.forEach((x) => sp.append(k + '[]', String(x)));
      else sp.append(k, String(v));
    }
    const qs = sp.toString();
    if (qs) url += '?' + qs;
  }

  const resp = await fetch(url, {
    method: opts.method || 'GET',
    headers,
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
    signal: opts.signal,
  });
  let json: Envelope<T>;
  try {
    json = await resp.json();
  } catch {
    throw new ApiError([{ code: 'invalid_response', message: 'Phản hồi không hợp lệ' }], resp.status);
  }
  if (!json.success) throw new ApiError(json.errors || [], resp.status);
  return json.data;
}

export const api = {
  get: <T>(path: string, query?: FetchOpts['query'], signal?: AbortSignal) =>
    request<T>(path, { method: 'GET', query, signal }),
  post: <T>(path: string, body?: unknown, opts?: { idempotencyKey?: string; signal?: AbortSignal }) =>
    request<T>(path, { method: 'POST', body, idempotencyKey: opts?.idempotencyKey, signal: opts?.signal }),
};

export function uuidv4(): string {
  const b = new Uint8Array(16);
  crypto.getRandomValues(b);
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}
