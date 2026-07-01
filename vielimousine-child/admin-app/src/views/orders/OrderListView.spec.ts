import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const caps = vi.hoisted(() => ({ value: [] as string[] }));

vi.mock('@/stores/auth.store', () => ({
  useAuthStore: () => ({
    can: (c: string) => caps.value.includes(c),
    canAny: (cs: string[]) => cs.some((c) => caps.value.includes(c)),
  }),
}));
vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  useRoute: () => ({ query: {} }),
}));
vi.mock('@/api/orders.api', () => ({
  ordersApi: { list: vi.fn().mockResolvedValue({ data: [] }), deleteDraft: vi.fn() },
}));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/composables/useCsvExport', () => ({ useCsvExport: () => ({ downloadCsv: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));
vi.mock('@/stores/lookup.store', () => ({
  ORDER_STATUSES: [], PAYMENT_STATUSES: [], ORDER_SOURCES: [],
  labelOrderStatus: (v: string) => v,
}));

import OrderListView from './OrderListView.vue';

function mountView() {
  return mount(OrderListView, {
    global: {
      stubs: {
        DataTablePanel: { template: '<div><slot /></div>' },
        Column: { props: ['header'], template: '<div class="col">{{ header }}</div>' },
        FilterBar: true,
        StatusTag: true,
        PageHeader: { template: '<div><slot /></div>' },
        Can: { template: '<div><slot /></div>' },
        Button: true,
        RouterLink: true,
      },
    },
  });
}

describe('OrderListView cost/profit columns', () => {
  beforeEach(() => { caps.value = []; });

  it('hides the cost & profit columns when lacking vie_manage_pricing', () => {
    // hotel_manager: xem đơn/báo cáo nhưng không thấy giá vốn/lợi nhuận
    caps.value = ['vie_view_reports', 'vie_view_all_orders'];
    const html = mountView().html();
    expect(html).not.toContain('Tổng giá vốn');
    expect(html).not.toContain('Lợi nhuận dự kiến');
  });

  it('shows the cost & profit columns when holding vie_manage_pricing (admin)', () => {
    caps.value = ['vie_manage_pricing', 'vie_view_all_orders'];
    const html = mountView().html();
    expect(html).toContain('Tổng giá vốn');
    expect(html).toContain('Lợi nhuận dự kiến');
  });
});
