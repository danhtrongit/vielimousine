import { api } from './client';
import type { Envelope } from '@/types/envelope';
import type { CustomerListItem } from '@/types/customer';

export const customersApi = {
  list: (params: Record<string, unknown> = {}) =>
    api.get<Envelope<CustomerListItem[]>>('/customers', { params }).then(r => r.data),
  get: (id: number) =>
    api.get<Envelope<CustomerListItem>>(`/customers/${id}`).then(r => r.data),
};
