# 06 — Admin SPA (Vue 3 + PrimeVue)

> Admin build hoàn toàn **tách biệt** với WP admin UI. SPA static, gọi REST `vie/v1/*` qua JWT.

## 6.1. Stack

| Lớp | Lựa chọn |
|---|---|
| Framework | **Vue 3** (`<script setup>` + Composition API, TypeScript) |
| UI Kit | **PrimeVue** latest (v4+ với "Aura" / "Lara" theme) + `primeicons` + `primeflex` |
| Build | **Vite 5** |
| Router | **Vue Router 4** |
| State | **Pinia** |
| HTTP | **Axios** với interceptor (auth + envelope unwrap) |
| Form/validate | **VeeValidate** + **Zod** |
| Date | **dayjs** |
| Charts | **Chart.js** (PrimeVue có `<Chart>` wrapper) |
| i18n | **vue-i18n** (vi default, mở rộng sau) |
| Lint | ESLint + Prettier + `eslint-plugin-vue` |

## 6.2. Mount URL

SPA serve qua 2 cách:

1. **Cùng domain** (khuyến nghị): rewrite rule `/vie-admin/{any}` → load `index.html` từ `admin-app/dist/`. Lớp loader: `Frontend\AdminAppLoader::register()`.
2. **Sub-domain** (mở rộng): `admin.vielimousine.com` trỏ tới `admin-app/dist/`; cấu hình CORS trong settings.

`AdminAppLoader` thêm vào `parse_request`/`template_redirect`:

```php
add_action('init', function () {
    add_rewrite_rule('^vie-admin(/.*)?$', 'index.php?vie_admin_app=1', 'top');
});
add_filter('query_vars', fn($v) => array_merge($v, ['vie_admin_app']));
add_action('template_redirect', function () {
    if (!get_query_var('vie_admin_app')) return;
    $index = VIE_CHILD_PATH . '/admin-app/dist/index.html';
    if (!is_file($index)) wp_die('SPA chưa build');
    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    readfile($index);
    exit;
});
```

Asset (JS/CSS từ Vite build, hash file): expose qua URL `{theme}/admin-app/dist/assets/*`.

## 6.3. Cấu trúc dự án

```
admin-app/
├── package.json
├── vite.config.ts
├── tsconfig.json
├── env.d.ts
├── index.html
└── src/
    ├── main.ts                   ← createApp, install PrimeVue, router, pinia, i18n
    ├── App.vue
    ├── router.ts
    │
    ├── api/
    │   ├── client.ts             ← axios instance + interceptor
    │   ├── envelope.ts           ← unwrap success/data/meta
    │   ├── auth.api.ts
    │   ├── hotels.api.ts
    │   ├── rooms.api.ts
    │   ├── roomPrices.api.ts
    │   ├── surcharges.api.ts
    │   ├── ticketPrices.api.ts
    │   ├── productCodes.api.ts
    │   ├── customers.api.ts
    │   ├── orders.api.ts
    │   ├── payments.api.ts
    │   ├── coupons.api.ts
    │   ├── reports.api.ts
    │   ├── activityLog.api.ts
    │   ├── settings.api.ts
    │   └── lookup.api.ts
    │
    ├── stores/
    │   ├── auth.store.ts         ← user, token, capabilities
    │   ├── ui.store.ts           ← sidebar, theme, breadcrumb
    │   ├── lookup.store.ts       ← cached lookups (sources, statuses, sales users)
    │   └── notify.store.ts       ← toast queue
    │
    ├── composables/
    │   ├── useApi.ts             ← typed query hook (page/filter/sort)
    │   ├── usePagination.ts
    │   ├── useFilters.ts
    │   ├── useSort.ts
    │   ├── useCan.ts             ← can('vie_manage_orders')
    │   ├── useToast.ts
    │   └── useConfirm.ts
    │
    ├── types/
    │   ├── envelope.ts
    │   ├── pagination.ts
    │   ├── hotel.ts
    │   ├── room.ts
    │   ├── order.ts
    │   └── ...
    │
    ├── layouts/
    │   ├── DefaultLayout.vue     ← sidebar + topbar + <RouterView>
    │   ├── AuthLayout.vue        ← màn login
    │   └── BlankLayout.vue       ← in / print
    │
    ├── components/
    │   ├── DataTablePanel.vue    ← wrapper PrimeVue DataTable + filter + pagination
    │   ├── FilterBar.vue         ← render filter động từ available_filters
    │   ├── SortHeader.vue
    │   ├── MoneyField.vue
    │   ├── PhoneField.vue
    │   ├── ChildAgesInput.vue
    │   ├── DateRangePicker.vue
    │   ├── HotelPicker.vue
    │   ├── RoomPicker.vue
    │   ├── CustomerPicker.vue
    │   ├── StatusTag.vue
    │   ├── PaymentStatusTag.vue
    │   ├── EmptyState.vue
    │   ├── PriceBreakdownPanel.vue
    │   ├── PaymentLedgerTable.vue
    │   └── PolicyEditor.vue
    │
    ├── views/
    │   ├── auth/
    │   │   └── LoginView.vue
    │   ├── dashboard/
    │   │   └── DashboardView.vue
    │   ├── orders/
    │   │   ├── OrderListView.vue
    │   │   ├── OrderDetailView.vue
    │   │   ├── OrderCreateView.vue
    │   │   └── OrderPrintView.vue
    │   ├── customers/
    │   │   ├── CustomerListView.vue
    │   │   └── CustomerDetailView.vue
    │   ├── hotels/
    │   │   ├── HotelListView.vue
    │   │   ├── HotelDetailView.vue
    │   │   └── HotelPolicyView.vue
    │   ├── rooms/
    │   │   ├── RoomListView.vue
    │   │   └── RoomDetailView.vue
    │   ├── pricing/
    │   │   ├── PriceMatrixView.vue          ← grid lớn
    │   │   ├── BulkUpdateView.vue
    │   │   ├── SurchargeMatrixView.vue
    │   │   └── TicketPriceMatrixView.vue
    │   ├── products/
    │   │   └── ProductCodeListView.vue
    │   ├── coupons/
    │   │   ├── CouponListView.vue
    │   │   └── CouponDetailView.vue
    │   ├── payments/
    │   │   └── PaymentLedgerView.vue
    │   ├── reports/
    │   │   ├── RevenueView.vue
    │   │   ├── ByHotelView.vue
    │   │   ├── BySourceView.vue
    │   │   ├── BySalesView.vue
    │   │   ├── ReceivableView.vue
    │   │   └── ReceivedCashView.vue
    │   ├── activity/
    │   │   └── ActivityLogView.vue
    │   ├── tools/
    │   │   ├── PriceCheckView.vue           ← quote tool (sales)
    │   │   └── ExportImportView.vue
    │   ├── users/
    │   │   ├── SalesUserListView.vue
    │   │   └── HotelManagerListView.vue
    │   └── settings/
    │       ├── GeneralSettingsView.vue
    │       ├── EmailSettingsView.vue
    │       └── SepaySettingsView.vue
    │
    ├── assets/
    │   ├── theme.css
    │   └── primevue-overrides.css
    │
    └── locales/
        ├── vi.json
        └── en.json
```

## 6.4. Routing

```ts
// src/router.ts
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  { path: '/login',    component: () => import('@/views/auth/LoginView.vue'), meta: { layout: 'auth' } },
  {
    path: '/',
    component: () => import('@/layouts/DefaultLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '',            redirect: '/dashboard' },
      { path: 'dashboard',   component: () => import('@/views/dashboard/DashboardView.vue') },

      { path: 'orders',          component: () => import('@/views/orders/OrderListView.vue'),    meta: { capAny: ['vie_manage_orders','vie_view_all_orders','vie_view_orders_own_hotel','vie_view_own_orders'] } },
      { path: 'orders/new',      component: () => import('@/views/orders/OrderCreateView.vue'),  meta: { cap: 'vie_create_orders' } },
      { path: 'orders/:id',      component: () => import('@/views/orders/OrderDetailView.vue'),  meta: { capAny: ['vie_manage_orders','vie_view_all_orders','vie_view_orders_own_hotel','vie_view_own_orders'] } },
      { path: 'orders/:id/print',component: () => import('@/views/orders/OrderPrintView.vue'),   meta: { layout: 'blank', cap: 'vie_print_order' } },

      { path: 'customers',       component: () => import('@/views/customers/CustomerListView.vue') },
      { path: 'customers/:id',   component: () => import('@/views/customers/CustomerDetailView.vue') },

      { path: 'hotels',                component: () => import('@/views/hotels/HotelListView.vue') },
      { path: 'hotels/:id',            component: () => import('@/views/hotels/HotelDetailView.vue') },
      { path: 'hotels/:id/policy',     component: () => import('@/views/hotels/HotelPolicyView.vue') },

      { path: 'rooms',           component: () => import('@/views/rooms/RoomListView.vue') },
      { path: 'rooms/:id',       component: () => import('@/views/rooms/RoomDetailView.vue') },

      { path: 'pricing/matrix',  component: () => import('@/views/pricing/PriceMatrixView.vue') },
      { path: 'pricing/bulk',    component: () => import('@/views/pricing/BulkUpdateView.vue') },
      { path: 'pricing/surcharges', component: () => import('@/views/pricing/SurchargeMatrixView.vue') },
      { path: 'pricing/tickets', component: () => import('@/views/pricing/TicketPriceMatrixView.vue') },

      { path: 'products',        component: () => import('@/views/products/ProductCodeListView.vue') },

      { path: 'coupons',         component: () => import('@/views/coupons/CouponListView.vue') },
      { path: 'coupons/:id',     component: () => import('@/views/coupons/CouponDetailView.vue') },

      { path: 'payments',        component: () => import('@/views/payments/PaymentLedgerView.vue'), meta: { cap: 'vie_manage_payments' } },

      { path: 'reports/revenue', component: () => import('@/views/reports/RevenueView.vue'),    meta: { capAny: ['vie_view_reports','vie_view_reports_own_hotel'] } },
      { path: 'reports/by-hotel',component: () => import('@/views/reports/ByHotelView.vue'),    meta: { capAny: ['vie_view_reports','vie_view_reports_own_hotel'] } },
      { path: 'reports/by-source', component: () => import('@/views/reports/BySourceView.vue'), meta: { cap: 'vie_view_reports' } },
      { path: 'reports/by-sales',  component: () => import('@/views/reports/BySalesView.vue'),  meta: { cap: 'vie_view_reports' } },
      { path: 'reports/receivable',component: () => import('@/views/reports/ReceivableView.vue'),meta: { capAny: ['vie_view_reports','vie_view_reports_own_hotel'] } },
      { path: 'reports/received-cash', component: () => import('@/views/reports/ReceivedCashView.vue'), meta: { cap: 'vie_view_reports' } },

      { path: 'activity',        component: () => import('@/views/activity/ActivityLogView.vue'), meta: { cap: 'vie_view_audit' } },

      { path: 'tools/price-check', component: () => import('@/views/tools/PriceCheckView.vue'),    meta: { cap: 'vie_use_price_check' } },
      { path: 'tools/export-import', component: () => import('@/views/tools/ExportImportView.vue') },

      { path: 'users/sales',     component: () => import('@/views/users/SalesUserListView.vue'),   meta: { cap: 'manage_options' } },
      { path: 'users/hotel-managers', component: () => import('@/views/users/HotelManagerListView.vue'), meta: { cap: 'manage_options' } },

      { path: 'settings/general',component: () => import('@/views/settings/GeneralSettingsView.vue'), meta: { cap: 'manage_options' } },
      { path: 'settings/email',  component: () => import('@/views/settings/EmailSettingsView.vue'),   meta: { cap: 'manage_options' } },
      { path: 'settings/sepay',  component: () => import('@/views/settings/SepaySettingsView.vue'),   meta: { cap: 'manage_options' } },
    ],
  },
];

const router = createRouter({ history: createWebHistory('/vie-admin/'), routes });

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    await auth.tryRefresh();
    if (!auth.isAuthenticated) return { path: '/login', query: { next: to.fullPath } };
  }
  // `cap` = phải có cap này (AND); `capAny` = chỉ cần 1 trong list (OR)
  if (to.meta.cap && !auth.can(to.meta.cap as string)) return { path: '/' };
  if (to.meta.capAny && !(to.meta.capAny as string[]).some(c => auth.can(c))) return { path: '/' };
});

export default router;
```

## 6.5. Axios client + envelope

```ts
// src/api/client.ts
import axios, { AxiosError } from 'axios';
import router from '@/router';
import { useAuthStore } from '@/stores/auth.store';
import { useNotifyStore } from '@/stores/notify.store';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/wp-json/vie/v1',
  withCredentials: true,  // gửi cookie refresh
  timeout: 30000,
});

api.interceptors.request.use((cfg) => {
  const t = useAuthStore().accessToken;
  if (t) cfg.headers.Authorization = `Bearer ${t}`;
  return cfg;
});

let refreshing: Promise<void> | null = null;
api.interceptors.response.use(
  (res) => res,
  async (err: AxiosError) => {
    const auth = useAuthStore();
    const notify = useNotifyStore();
    const status = err.response?.status;

    if (status === 401 && !err.config?._retry) {
      err.config!._retry = true;
      refreshing ??= auth.refresh().finally(() => { refreshing = null; });
      try { await refreshing; return api(err.config!); }
      catch { router.push('/login'); }
    }

    const errs = (err.response?.data as any)?.errors;
    if (Array.isArray(errs)) {
      errs.forEach((e: any) => notify.error(e.message));
    } else if (status && status >= 500) {
      notify.error('Lỗi máy chủ, vui lòng thử lại');
    }
    return Promise.reject(err);
  }
);
```

```ts
// src/api/envelope.ts
export interface Envelope<T> {
  success: boolean;
  data: T;
  meta: {
    request_id: string;
    timestamp: string;
    pagination?: Pagination;
    sort?: { field: string; order: 'asc' | 'desc' };
    filters_applied?: Record<string, any>;
    available_filters?: FilterMeta[];
    available_sorts?: string[];
    links?: Record<string, string>;
  };
  errors: ApiError[] | null;
}

export function unwrap<T>(p: Promise<{ data: Envelope<T> }>): Promise<T> {
  return p.then(r => r.data.data);
}

export function unwrapWithMeta<T>(p: Promise<{ data: Envelope<T> }>): Promise<{ data: T; meta: Envelope<T>['meta'] }> {
  return p.then(r => ({ data: r.data.data, meta: r.data.meta }));
}
```

## 6.6. Composable `useApi` cho list page

Tự đồng bộ filter/sort/page từ URL query → REST → response meta → UI.

```ts
// src/composables/useApi.ts
export function useApiList<T>(endpoint: string, defaults: Partial<ListParams> = {}) {
  const route = useRoute();
  const router = useRouter();

  const params = reactive<ListParams>({
    page: Number(route.query.page) || 1,
    per_page: Number(route.query.per_page) || 20,
    sort: route.query.sort?.toString() || defaults.sort || 'created_at',
    order: (route.query.order?.toString() as any) || 'desc',
    filters: {}, // parse từ route.query
  });

  const data = ref<T[]>([]);
  const meta = ref<EnvelopeMeta | null>(null);
  const loading = ref(false);

  async function load() {
    loading.value = true;
    try {
      const res = await api.get(endpoint, { params: flatten(params) });
      data.value = res.data.data;
      meta.value = res.data.meta;
    } finally { loading.value = false; }
  }

  watch(params, () => {
    router.replace({ query: flatten(params) });
    load();
  }, { deep: true });

  onMounted(load);
  return { params, data, meta, loading, reload: load };
}
```

## 6.7. Component: `DataTablePanel`

Wrap PrimeVue `<DataTable>` + `<FilterBar>` + `<Paginator>`. Đồng bộ meta:

```vue
<!-- src/components/DataTablePanel.vue -->
<template>
  <div class="vie-table-panel">
    <FilterBar v-if="meta?.available_filters"
               :schema="meta.available_filters"
               v-model:value="params.filters" />

    <DataTable :value="data" :loading="loading"
               lazy paginator :rows="params.per_page"
               :totalRecords="meta?.pagination?.total ?? 0"
               :first="(params.page - 1) * params.per_page"
               @page="onPage"
               sortMode="multiple" removableSort
               @sort="onSort"
               dataKey="id"
               responsiveLayout="scroll">
      <slot />
    </DataTable>
  </div>
</template>
```

`OrderListView.vue` sử dụng:

```vue
<DataTablePanel endpoint="/orders" :defaults="{ sort:'created_at' }">
  <Column field="code" header="Mã ĐH" sortable>
    <template #body="{ data }">
      <RouterLink :to="`/orders/${data.id}`">{{ data.code }}</RouterLink>
    </template>
  </Column>
  <Column field="customer.name" header="Khách hàng" />
  <Column field="customer.phone" header="SĐT" />
  <Column field="checkin" header="Checkin" sortable />
  <Column field="total" header="Tổng" sortable>
    <template #body="{ data }">{{ vnd(data.total) }}</template>
  </Column>
  <Column field="payment_status" header="Thanh toán">
    <template #body="{ data }"><PaymentStatusTag :value="data.payment_status" :paid="data.paid_amount" :total="data.total" /></template>
  </Column>
  <Column field="status" header="Trạng thái">
    <template #body="{ data }"><StatusTag :value="data.status" /></template>
  </Column>
  <Column header="Hành động">
    <template #body="{ data }">
      <Button icon="pi pi-eye" text @click="$router.push(`/orders/${data.id}`)" />
      <Button icon="pi pi-print" text @click="$router.push(`/orders/${data.id}/print`)" />
    </template>
  </Column>
</DataTablePanel>
```

## 6.8. Component: `FilterBar`

Render UI từ `meta.available_filters[]`:

| `type` | UI |
|---|---|
| `enum` | `<MultiSelect>` (multi) hoặc `<Dropdown>` (single) |
| `string` | `<InputText>` |
| `int` / `decimal` | range `<InputNumber>` × 2 |
| `bool` | `<TriStateCheckbox>` |
| `date` | `<Calendar>` |
| `daterange` | `<Calendar selectionMode="range">` |
| `ref` | `<AutoComplete>` gọi `/lookup/...` |

Toolbar phía trên có: nút `Apply`, `Reset`, `Save view`, `Export`. State filter sync vào URL — share link được.

## 6.9. Trang `OrderDetailView`

Layout 2 cột:

| Bên trái (8/12) | Bên phải (4/12) |
|---|---|
| Header: mã đơn, tag status / payment | Customer card (tên, SDT, email, VAT) |
| Tabs: **Items / Payments / Description / Activity / Email / Print** | Pricing summary: subtotal, discount, total, paid, remaining |
| Items table: từng item kèm `Số chỗ ngồi`, `partner`, `supplier_booking_code`, action Hủy item | Buttons: Confirm / Cancel / Complete / Resend email / Recompute cost |
| Payments tab: bảng ledger (xem `PaymentLedgerTable`) + nút Thu / Hoàn / Void | |

`PaymentLedgerTable`:

| # | created_at | type | method | amount | balance after | by | note |

Action buttons mở `Dialog` (PrimeVue) chứa form thu / hoàn / void.

`Description` tab hiện chuỗi sinh bởi `OrderDescription`, copy được.

## 6.10. Trang `PriceMatrixView`

- Grid: cột = `nights` trong range chọn (mặc định 30 ngày), dòng = `room` (filter theo hotel).
- Cell editable inline: hiển thị `price/stock`, edit qua dialog popup hoặc inline.
- Toolbar trên cùng:
    - `HotelPicker` + `DateRangePicker`.
    - Nút **"Bulk update"** mở dialog: chọn weekday pattern, nhập values, áp ngay.
    - Nút **"Holiday override"** dialog: chọn dải ngày cụ thể, set giá ngày lễ.
- Heat map color: stock = 0 đỏ, low <= 3 vàng, ngày chưa cấu hình xám.
- Save: batch optimistic update + `POST /room-prices/bulk`.

## 6.11. Trang `BulkUpdateView`

Form 3 bước (PrimeVue `<Steps>`):

1. **Scope**: chọn hotel(s), room(s), date range, weekday pattern.
2. **Values**: nhập `price`, `extra_adult_price`, `stock`.
3. **Preview & Confirm**: list dòng sẽ insert/update; nút Apply.

Sau khi Apply → toast + redirect tới `PriceMatrixView`.

## 6.12. Trang `OrderCreateView`

Form wizard (Steps):

1. **Khách hàng**: AutoComplete phone → autofill hoặc tạo mới.
2. **Phòng / Combo**: thêm nhiều items, mỗi item có sub-form (room picker, date range, adults, child ages chips, quantity, booking_type). Mỗi item gọi `/quote` realtime hiển thị breakdown.
3. **Coupon / Voucher**: nhập, validate.
4. **Thanh toán & Ghi chú**: chọn method, nhập sales_user, source, customer_note, pickup/dropoff.
5. **Xác nhận**: hiển thị tổng kết → submit `POST /orders`.

Cho phép "Save as draft" (lưu localStorage) — không persist tới BE đến khi submit.

## 6.13. Trang `HotelPolicyView`

3 cards trên 1 trang:

1. **Chính sách giá** (form chỉnh `pricing_policy` JSON):
    - `child_note` textarea
    - `extra_bed_note`
    - `ticket_note`
    - `general_notes[]` (chip input)
2. **Chính sách hoàn huỷ** (`cancellation_policy`):
    - Table `rules[]` editable: hours_before_checkin (number), penalty_percent (number), description (text). Add/Remove rows.
    - `refund_method` textarea
    - `notes` textarea
    - **Preview block** hiển thị HTML render giống frontend single-hotel.
3. **Quy tắc miễn phí**:
    - `ticket_free_children_count` (1)
    - `ticket_free_children_max_age` (5)

PUT `/hotels/{id}` với payload diff.

## 6.14. Trang `PaymentLedgerView` (global)

DataTable ledger toàn hệ thống. Filter: date_from/to, method, gateway, type, order code, amount range. Cho phép drill-down vào `/orders/{id}/payments`.

## 6.15. Reports views

Mỗi view = 3 phần:

- **Filter bar** (date range, granularity, các segment filter)
- **Summary cards** (PrimeVue `<Card>`): tổng đơn, doanh thu, thu, công nợ, giá vốn, lợi nhuận.
- **Chart** (PrimeVue `<Chart type="line|bar">`) + **Table** chi tiết.
- Nút **Export CSV/XLSX** trên top right.

## 6.16. Toast & Confirm

- Toast: PrimeVue `<Toast>` mount global; `useToast()` API trong composable.
- Confirm Dialog: PrimeVue `<ConfirmDialog>`; `useConfirm()` cho destructive actions (hủy đơn, void payment, delete coupon).

## 6.17. Theme & i18n

- PrimeVue v4 dùng style themes API (Aura preset). Override màu chủ đạo qua `definePreset()`.
- Brand colors: primary `#fa541c` (orange), secondary `#20af48`.
- i18n: vi default; tất cả label / message bọc `t('order.list.title')`.

## 6.18. Build & Deploy

```bash
cd admin-app
npm i
npm run dev       # vite dev, proxy /wp-json → site
npm run build     # ra admin-app/dist/
```

`vite.config.ts` sub-base: `base: '/wp-content/themes/vielimousine-child/admin-app/dist/'` để asset URL đúng.

`index.html` trong dist được serve qua `AdminAppLoader` (xem §6.2) khi user mở `/vie-admin/*`.

## 6.19. Capability gate trong UI

```ts
// useCan.ts
export function useCan() {
  const auth = useAuthStore();
  return (cap: string | string[]) => {
    const caps = Array.isArray(cap) ? cap : [cap];
    return caps.every(c => auth.user?.caps?.includes(c));
  };
}
```

Component `<Can :cap="'vie_manage_orders'"><slot /></Can>` ẩn/hiện UI element.

## 6.20. Lookup cache

`stores/lookup.store.ts`:

```ts
export const useLookupStore = defineStore('lookup', {
  state: () => ({
    sources: [] as Lookup[],
    statuses: [] as Lookup[],
    paymentMethods: [] as Lookup[],
    salesUsers: [] as User[],
    cities: [] as string[],
    weekdayPatterns: [] as string[],
    loaded: false,
  }),
  actions: {
    async ensureLoaded() {
      if (this.loaded) return;
      const [s, st, pm, su, c, wp] = await Promise.all([
        LookupApi.sources(),
        LookupApi.statuses(),
        LookupApi.paymentMethods(),
        LookupApi.salesUsers(),
        LookupApi.cities(),
        LookupApi.weekdayPatterns(),
      ]);
      Object.assign(this, { sources: s, statuses: st, paymentMethods: pm, salesUsers: su, cities: c, weekdayPatterns: wp, loaded: true });
    }
  }
});
```

Load khi vào layout chính.

## 6.21. UX patterns

- **Optimistic update**: bulk price edit, cell inline edit → show ngay, rollback nếu lỗi.
- **Skeleton loading**: PrimeVue `<Skeleton>` cho card / table khi load lần đầu.
- **Infinite scroll** không dùng — luôn paginate (đếm chính xác hơn cho admin).
- **Keyboard**: `Ctrl+K` mở Command Palette (search nhanh order code / customer phone).
- **Sticky header & filter bar** trên list views.
- **Save view**: user lưu lại bộ filter dạng preset (localStorage hoặc `wp_user_meta`).
