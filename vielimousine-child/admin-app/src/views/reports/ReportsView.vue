<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import OverviewReport from './OverviewReport.vue';
import ReceivableReport from './ReceivableReport.vue';
import ReceivedCashReport from './ReceivedCashReport.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { useLookupStore, ORDER_SOURCES } from '@/stores/lookup.store';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { reportsApi, type OrderExportRow } from '@/api/reports.api';
import { useCsvExport } from '@/composables/useCsvExport';
import { ymdLocal } from '@/composables/useFormat';
import type { Order, Payment } from '@/types/order';

const ui = useUIStore();
const notify = useNotify();
const lookup = useLookupStore();
const csv = useCsvExport();
const exporting = ref(false);

const today = new Date();
const thirtyAgo = new Date(today.getTime() - 29 * 24 * 3600 * 1000);

const dateFrom = ref<Date>(thirtyAgo);
const dateTo = ref<Date>(today);
const activeTab = ref('overview');
const orders = ref<Order[]>([]);
const payments = ref<Payment[]>([]);
const loading = ref(false);

const selectedHotelIds = ref<number[]>([]);
const selectedSalesUserIds = ref<number[]>([]);
const selectedSources = ref<string[]>([]);

const PRESETS = [
  { label: 'Hôm nay', value: 'today' },
  { label: '7 ngày qua', value: 'last7' },
  { label: '30 ngày qua', value: 'last30' },
  { label: 'Tháng này', value: 'thisMonth' },
  { label: 'Tháng trước', value: 'lastMonth' },
];
const presetValue = ref<string>('last30');

function applyPreset(p: string) {
  const now = new Date();
  switch (p) {
    case 'today':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      dateTo.value = now;
      break;
    case 'last7':
      dateFrom.value = new Date(now.getTime() - 6 * 24 * 3600 * 1000);
      dateTo.value = now;
      break;
    case 'last30':
      dateFrom.value = new Date(now.getTime() - 29 * 24 * 3600 * 1000);
      dateTo.value = now;
      break;
    case 'thisMonth':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1);
      dateTo.value = now;
      break;
    case 'lastMonth':
      dateFrom.value = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      dateTo.value = new Date(now.getFullYear(), now.getMonth(), 0);
      break;
  }
}

function fmt(d: Date): string {
  return ymdLocal(d);
}

async function reload() {
  loading.value = true;
  try {
    const orderParams: Record<string, unknown> = {
      date_from: fmt(dateFrom.value),
      date_to: fmt(dateTo.value),
      per_page: 10000,
    };
    if (selectedSalesUserIds.value.length > 0) {
      orderParams.sales_user_id = selectedSalesUserIds.value.join(',');
    }
    if (selectedSources.value.length > 0) {
      orderParams.source = selectedSources.value.join(',');
    }
    const [oResp, pResp] = await Promise.all([
      ordersApi.list(orderParams),
      paymentsApi.list({
        date_from: fmt(dateFrom.value),
        date_to: fmt(dateTo.value),
        per_page: 10000,
      }),
    ]);
    orders.value = oResp.data;
    payments.value = pResp.data;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

// Client-side fallback filters (backend may not support all)
const filteredOrders = computed<Order[]>(() => {
  let out = orders.value;
  if (selectedSalesUserIds.value.length > 0) {
    const set = new Set(selectedSalesUserIds.value);
    out = out.filter((o) => o.sales_user_id !== null && set.has(o.sales_user_id));
  }
  if (selectedSources.value.length > 0) {
    const set = new Set(selectedSources.value);
    out = out.filter((o) => set.has(o.source));
  }
  return out;
});

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
function scheduleReload() {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(reload, 350);
}

watch([dateFrom, dateTo, selectedSalesUserIds, selectedSources], scheduleReload, { deep: true, immediate: false });

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Báo cáo' }]);
  await lookup.ensureLoaded();
  await reload();
});

// ── Full-column CSV export ──
function fmtVNDate(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}
function fmtVNMonth(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  return `tháng ${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}
function bookingTypeLabel(t: string): string {
  if (t === 'combo') return 'Combo (Phòng + Vé)';
  if (t === 'room')  return 'Phòng';
  return t;
}

const EXPORT_HEADERS = [
  'Tháng', 'Ngày đặt hàng', 'Ngày tạo', 'Mã ĐH', 'Mã KH', 'Tên Khách Hàng',
  'Số Điện Thoại', 'Nguồn khách hàng', 'Mã SP', 'Tên sản phẩm', 'Đơn vị',
  'Nhóm sản phẩm', 'Số lượng', 'Giá bán', 'Phụ thu', 'Thành tiền',
  'Số tiền Thanh Toán', 'Số tiền còn lại', 'CHI PHÍ GIÁ VỐN', 'LỢI NHUẬN',
  'Hình Thức TT', 'Ngày thanh toán', 'Nguồn đơn hàng', 'Ngày đi', 'Ngày về',
  'Tháng đi (sử dụng dịch vụ thực tế)', 'Ghi chú', 'Đối tác', 'Khách sạn ở đâu?',
  'Mã đặt phòng', 'Số Hóa Đơn', 'Người bán', 'Thanh toán đối tác',
  'Thanh toán cọc', 'Phần còn lại phải Thanh toán', 'Mã Voucher',
];

function rowToCells(r: OrderExportRow): unknown[] {
  const note = [r.customer_note, r.internal_note].filter((s) => s && s.trim()).join(' | ');
  const hotelLoc = r.hotel_area || r.hotel_city || r.hotel_name || '';
  const voucher = r.voucher_code || r.coupon_code || '';
  return [
    fmtVNMonth(r.order_created_at),
    fmtVNDate(r.order_created_at),
    fmtVNDate(r.order_created_at),
    r.order_code,
    r.customer_id ? `KH${String(r.customer_id).padStart(6, '0')}` : '',
    r.customer_name,
    r.customer_phone,
    r.order_source,
    r.room_id ? `R${String(r.room_id).padStart(6, '0')}` : '',
    r.item_name,
    r.item_unit || 'đêm',
    bookingTypeLabel(r.booking_type),
    r.item_qty * r.item_nights,
    r.sell_unit_price,
    r.surcharge_total,
    r.item_total,
    r.order_paid,
    r.order_outstanding,
    r.item_cost,
    r.item_profit,
    r.pay_methods,
    fmtVNDate(r.pay_last_at),
    r.order_source,
    fmtVNDate(r.item_checkin),
    fmtVNDate(r.item_checkout),
    fmtVNMonth(r.item_checkin),
    note,
    r.partner_name,
    hotelLoc,
    r.supplier_booking_code,
    r.invoice_number,
    r.sales_name,
    '', // Thanh toán đối tác — chưa được track riêng trong payment_log
    r.pay_deposit_sum,
    r.order_outstanding,
    voucher,
  ];
}

async function exportDetailed() {
  if (exporting.value) return;
  exporting.value = true;
  try {
    const resp = await reportsApi.ordersExport({
      date_from: fmt(dateFrom.value),
      date_to:   fmt(dateTo.value),
      hotel_ids: selectedHotelIds.value.length > 0 ? selectedHotelIds.value : undefined,
      sales_user_ids: selectedSalesUserIds.value.length > 0 ? selectedSalesUserIds.value : undefined,
      sources:   selectedSources.value.length > 0 ? selectedSources.value : undefined,
    });
    const rows = resp.data.map(rowToCells);
    const filename = `vie-orders-${fmt(dateFrom.value)}_${fmt(dateTo.value)}.csv`;
    csv.downloadCsv(filename, EXPORT_HEADERS, rows);
    notify.success('Xuất CSV', `${resp.data.length} dòng`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    exporting.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Báo cáo" subtitle="Phân tích doanh thu" icon="pi pi-chart-bar" />

    <div class="filter-bar">
      <div class="filter-row">
        <div class="field">
          <label>Khoảng nhanh</label>
          <Select
            v-model="presetValue"
            :options="PRESETS"
            option-label="label"
            option-value="value"
            @change="applyPreset(presetValue)"
            style="min-width: 150px"
          />
        </div>
        <div class="field">
          <label>Từ ngày</label>
          <DatePicker v-model="dateFrom" date-format="yy-mm-dd" show-icon />
        </div>
        <div class="field">
          <label>Đến ngày</label>
          <DatePicker v-model="dateTo" date-format="yy-mm-dd" show-icon />
        </div>
        <Button label="Áp dụng" icon="pi pi-refresh" @click="reload" :loading="loading" />
        <Button
          label="Xuất CSV chi tiết"
          icon="pi pi-download"
          severity="secondary"
          outlined
          :loading="exporting"
          @click="exportDetailed"
        />
      </div>
      <div class="filter-row">
        <div class="field grow">
          <label>Khách sạn</label>
          <MultiSelect
            v-model="selectedHotelIds"
            :options="lookup.hotels"
            option-label="name"
            option-value="id"
            placeholder="Tất cả"
            filter
            display="chip"
            :max-selected-labels="3"
          />
        </div>
        <div class="field grow">
          <label>Nhân viên sales</label>
          <MultiSelect
            v-model="selectedSalesUserIds"
            :options="lookup.users"
            option-label="display_name"
            option-value="id"
            placeholder="Tất cả"
            filter
            display="chip"
            :max-selected-labels="3"
          />
        </div>
        <div class="field grow">
          <label>Nguồn</label>
          <MultiSelect
            v-model="selectedSources"
            :options="ORDER_SOURCES"
            option-label="label"
            option-value="value"
            placeholder="Tất cả"
            display="chip"
          />
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-banner">
      <ProgressSpinner style="width: 24px; height: 24px" />
      <span>Đang tổng hợp dữ liệu...</span>
    </div>
    <p v-else class="data-summary">
      Đang xem <strong>{{ filteredOrders.length }}</strong> đơn hàng và
      <strong>{{ payments.length }}</strong> giao dịch trong khoảng đã chọn.
    </p>

    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="overview">Tổng quan</Tab>
        <Tab value="receivable">Công nợ</Tab>
        <Tab value="received-cash">Thực thu</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="overview">
          <OverviewReport
            v-if="activeTab === 'overview'"
            :orders="filteredOrders"
            :date-from="fmt(dateFrom)"
            :date-to="fmt(dateTo)"
            :hotel-ids="selectedHotelIds"
            :sales-user-ids="selectedSalesUserIds"
            :sources="selectedSources"
          />
        </TabPanel>
        <TabPanel value="receivable">
          <ReceivableReport v-if="activeTab === 'receivable'" :orders="filteredOrders" />
        </TabPanel>
        <TabPanel value="received-cash">
          <ReceivedCashReport v-if="activeTab === 'received-cash'" :payments="payments" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.filter-bar { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.85rem; background: var(--p-surface-50); border-radius: 0.5rem; margin-bottom: 1rem; }
.filter-row { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
.field { display: flex; flex-direction: column; gap: 0.25rem; min-width: 140px; }
.field.grow { flex: 1; min-width: 200px; }
.field label { font-size: 0.75rem; color: var(--p-text-muted-color); font-weight: 500; }
.loading-banner { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; margin-bottom: 1rem; }
.data-summary { color: var(--p-text-muted-color); font-size: 0.9rem; margin: 0 0 1rem; }
</style>
