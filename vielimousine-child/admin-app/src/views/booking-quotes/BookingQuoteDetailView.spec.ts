import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  publish: vi.fn(),
  replace: vi.fn(),
  push: vi.fn(),
  copy: vi.fn(),
}));

vi.mock('vue-router', () => ({
  useRoute: () => ({ name: 'booking-quotes-new', params: {} }),
  useRouter: () => ({ replace: mocks.replace, push: mocks.push }),
}));
vi.mock('primevue/useconfirm', () => ({ useConfirm: () => ({ require: vi.fn() }) }));
vi.mock('@/api/bookingQuotes.api', () => ({
  bookingQuotesApi: {
    get: mocks.get, create: mocks.create, update: mocks.update, publish: mocks.publish,
    duplicate: vi.fn(), revoke: vi.fn(),
  },
}));
vi.mock('@/composables/useNotify', () => ({
  useNotify: () => ({ success: vi.fn(), warn: vi.fn(), apiError: vi.fn() }),
}));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));
vi.mock('@/stores/auth.store', () => ({ useAuthStore: () => ({ can: () => true }) }));

import BookingQuoteDetailView from './BookingQuoteDetailView.vue';

const draft = {
  id: 7,
  public_id: 'a'.repeat(32),
  code: 'VQ000007',
  sales_user_id: 3,
  status: 'draft',
  effective_status: 'draft',
  payment_status: 'unpaid',
  customer_name: 'Nguyễn Văn A',
  customer_phone: '0900000000',
  customer_email: '',
  title: 'Xe Hà Nội – Hạ Long',
  image_url: '',
  greeting: 'Xin chào',
  trip_start: null,
  trip_end: null,
  description: '',
  inclusions: '',
  exclusions: '',
  terms: '',
  contact_name: 'Vie Limo',
  contact_phone: '0900000000',
  contact_zalo: '0900000000',
  lines: [{ label: 'Xe riêng', quantity: 1, unit: 'chuyến', unit_price: 2_000_000, line_total: 2_000_000 }],
  discount: 0,
  deposit_type: 'percent',
  deposit_value: 30,
  subtotal: 2_000_000,
  total: 2_000_000,
  deposit_amount: 600_000,
  paid_amount: 0,
  remaining_amount: 2_000_000,
  due_amount: 600_000,
  payment_review: false,
  valid_until: '2026-09-29 23:59:00',
  expires_at: '2026-09-29T23:59:00+07:00',
  can_checkout: false,
  public_url: '',
  brand: null,
  published_at: null,
  created_at: '2026-09-22 10:00:00',
  updated_at: '2026-09-22 10:00:00',
  payments: [],
} as const;

async function mountView() {
  const wrapper = mount(BookingQuoteDetailView, {
    global: {
      directives: { tooltip: () => undefined },
      stubs: {
        PageHeader: { template: '<header><slot /></header>' },
        Button: true, InputText: true, InputNumber: true, Textarea: true,
        DatePicker: true, Select: true, Tag: true, Message: { template: '<div><slot /></div>' },
        ProgressSpinner: true, DataTable: true, Column: true, BookingQuotePreview: true,
      },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('BookingQuoteDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: mocks.copy } });
    mocks.create.mockResolvedValue({ data: { ...draft } });
    mocks.update.mockResolvedValue({ data: { ...draft } });
    mocks.publish.mockResolvedValue({
      data: { ...draft, status: 'published', effective_status: 'published', public_url: 'https://vielimousine.com/booking/' + 'a'.repeat(32) },
    });
    mocks.copy.mockResolvedValue(undefined);
  });

  it('recognizes the named new route when params.id is absent', async () => {
    const wrapper = await mountView();
    expect(wrapper.text()).toContain('Khách hàng');
    expect(wrapper.text()).not.toContain('Không tải được báo giá');
    expect(mocks.get).not.toHaveBeenCalled();
  });

  it('creates a draft using editable fields', async () => {
    const wrapper = await mountView();
    const vm = wrapper.vm as unknown as { form: typeof draft; persistDraft: () => Promise<unknown> };
    vm.form.customer_name = draft.customer_name;
    vm.form.title = draft.title;
    vm.form.lines = draft.lines.map(({ line_total: _lineTotal, ...line }) => line) as typeof vm.form.lines;
    await vm.persistDraft();
    expect(mocks.create).toHaveBeenCalledOnce();
    expect(mocks.create.mock.calls[0][0]).not.toHaveProperty('total');
    expect(mocks.replace).toHaveBeenCalledWith('/booking-quotes/7');
  });

  it('publishes the saved draft and copies the server public URL', async () => {
    const wrapper = await mountView();
    const vm = wrapper.vm as unknown as { form: typeof draft; persistDraft: () => Promise<unknown>; publishQuote: () => Promise<void> };
    vm.form.customer_name = draft.customer_name;
    vm.form.title = draft.title;
    vm.form.lines = draft.lines.map(({ line_total: _lineTotal, ...line }) => line) as typeof vm.form.lines;
    await vm.persistDraft();
    await vm.publishQuote();
    expect(mocks.update).toHaveBeenCalledOnce();
    expect(mocks.publish).toHaveBeenCalledWith(7);
    expect(mocks.copy).toHaveBeenCalledWith('https://vielimousine.com/booking/' + 'a'.repeat(32));
  });
});
