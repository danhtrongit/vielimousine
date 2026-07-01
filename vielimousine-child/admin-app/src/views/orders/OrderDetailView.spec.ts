import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const caps = vi.hoisted(() => ({ value: [] as string[] }));

const { orderDetail } = vi.hoisted(() => ({
  orderDetail: {
    id: 1, code: 'VIE0001', status: 'pending', payment_status: 'pending',
    subtotal: 100, discount: 0, total: 100, paid_amount: 0,
    cost_total: 60, profit_total: 40,
    customer_name: 'A', customer_phone: '0900000000',
    items: [], payments: [], customer: null,
  },
}));

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    can: (c: string) => caps.value.includes(c),
    canAny: (cs: string[]) => cs.some((c) => caps.value.includes(c)),
  }),
}));
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '1' } }) }));
vi.mock('@/api/orders.api', () => ({
  ordersApi: { get: vi.fn().mockResolvedValue({ data: orderDetail }) },
}));
vi.mock('@/api/payments.api', () => ({ paymentsApi: {} }));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));
vi.mock('@/stores/lookup.store', () => ({
  labelBookingType: (v: string) => v, labelPaymentMethod: (v: string) => v,
  labelPaymentType: (v: string) => v, labelGateway: (v: string) => v,
}));

import OrderDetailView from './OrderDetailView.vue';

async function mountView() {
  const wrapper = mount(OrderDetailView, {
    global: {
      stubs: {
        Card: { template: '<div><slot name="title" /><slot name="content" /></div>' },
        Tabs: { template: '<div><slot /></div>' }, TabList: true, Tab: true,
        TabPanels: true, TabPanel: true, DataTable: true, Column: true,
        Button: true, Dialog: true, Textarea: true, InputNumber: true,
        InputText: true, Select: true, ProgressSpinner: true, StatusTag: true,
        Can: { template: '<div><slot /></div>' },
        InvoiceDialog: true,
        PageHeader: { template: '<div><slot /></div>' },
      },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('OrderDetailView cost/profit card', () => {
  beforeEach(() => { caps.value = []; });

  it('hides the "Giá vốn & Lợi nhuận" card when lacking vie_manage_pricing', async () => {
    // hotel_manager: xem báo cáo nhưng không thấy giá vốn/lợi nhuận
    caps.value = ['vie_view_reports', 'vie_manage_inventory'];
    const html = (await mountView()).html();
    expect(html).not.toContain('Lợi nhuận');
  });

  it('shows the card when holding vie_manage_pricing (admin)', async () => {
    caps.value = ['vie_manage_pricing'];
    const html = (await mountView()).html();
    expect(html).toContain('Lợi nhuận');
  });
});
