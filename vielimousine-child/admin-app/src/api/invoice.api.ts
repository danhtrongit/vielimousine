import { api } from './client';
import type { Envelope } from '@/types/envelope';

export type InvoiceTemplate = 'receipt' | 'tax_invoice';

export interface InvoiceData {
  order: Record<string, any>;
  items: Array<Record<string, any>>;
  settings: Record<string, any>;
  amount_in_words: string;
  template: InvoiceTemplate;
}

export const invoiceApi = {
  /** Fetch JSON data for Vue-rendered invoice. Assigns invoice_number server-side. */
  fetchData: (orderId: number, template: InvoiceTemplate) =>
    api
      .get<Envelope<InvoiceData>>(`/orders/${orderId}/invoice/data`, { params: { template } })
      .then((r) => r.data.data),
};
