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
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { useLookupStore, ORDER_SOURCES } from '@/stores/lookup.store';
import { ordersApi } from '@/api/orders.api';
import { paymentsApi } from '@/api/payments.api';
import { ymdLocal } from '@/composables/useFormat';
import type { Order, Payment } from '@/types/order';

const ui = useUIStore();
const notify = useNotify();
const lookup = useLookupStore();

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
      per_page: 5000,
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
        per_page: 5000,
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
</script>

<template>
  <div>
    <div class="header">
      <h1 class="page-title">Báo cáo</h1>
    </div>

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
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
.filter-bar { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.85rem; background: var(--p-surface-50); border-radius: 0.5rem; margin-bottom: 1rem; }
.filter-row { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
.field { display: flex; flex-direction: column; gap: 0.25rem; min-width: 140px; }
.field.grow { flex: 1; min-width: 200px; }
.field label { font-size: 0.75rem; color: var(--p-text-muted-color); font-weight: 500; }
.loading-banner { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--p-surface-50); border-radius: 0.5rem; margin-bottom: 1rem; }
.data-summary { color: var(--p-text-muted-color); font-size: 0.9rem; margin: 0 0 1rem; }
</style>
