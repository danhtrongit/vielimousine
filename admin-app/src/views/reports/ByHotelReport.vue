<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import { useNotify } from '@/composables/useNotify';
import { reportsApi, type ByHotelRow } from '@/api/reports.api';

const props = defineProps<{
  dateFrom: string;
  dateTo: string;
  hotelIds: number[];
  salesUserIds: number[];
  sources: string[];
}>();

const csv = useCsvExport();
const notify = useNotify();

const rows = ref<ByHotelRow[]>([]);
const loading = ref(false);

async function fetchData() {
  loading.value = true;
  try {
    const resp = await reportsApi.byHotel({
      date_from: props.dateFrom,
      date_to: props.dateTo,
      hotel_ids: props.hotelIds.length > 0 ? props.hotelIds : undefined,
      sales_user_ids: props.salesUserIds.length > 0 ? props.salesUserIds : undefined,
      sources: props.sources.length > 0 ? props.sources : undefined,
    });
    rows.value = resp.data;
  } catch (e) {
    notify.apiError(e, 'Không tải được báo cáo theo khách sạn');
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);
watch(() => [props.dateFrom, props.dateTo, props.hotelIds, props.salesUserIds, props.sources], fetchData, { deep: true });

const chartData = (() => ({
  get value() {
    return {
      labels: rows.value.slice(0, 10).map((r) => r.hotel_name),
      datasets: [
        {
          label: 'Doanh thu',
          data: rows.value.slice(0, 10).map((r) => r.revenue),
          backgroundColor: '#fa541c',
        },
        {
          label: 'Lợi nhuận',
          data: rows.value.slice(0, 10).map((r) => r.profit),
          backgroundColor: '#20af48',
        },
      ],
    };
  },
}))();

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: true } },
};

function exportCsv() {
  csv.downloadCsv(
    `vie-by-hotel-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Khách sạn', 'Số đơn', 'Doanh thu', 'Giá vốn', 'Lợi nhuận'],
    rows.value.map((r) => [r.hotel_name, r.orders, r.revenue, r.cost, r.profit]),
  );
}
</script>

<template>
  <div class="report">
    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" :disabled="rows.length === 0" />
    </div>

    <div v-if="loading" class="loading"><ProgressSpinner style="width: 32px; height: 32px" /></div>

    <Card v-else>
      <template #title>Top 10 doanh thu / lợi nhuận theo khách sạn</template>
      <template #content>
        <div class="chart-wrap"><Chart type="bar" :data="chartData.value" :options="chartOptions" /></div>
      </template>
    </Card>

    <Card v-if="!loading">
      <template #title>Chi tiết</template>
      <template #content>
        <DataTable :value="rows" :empty-message="'Không có dữ liệu'">
          <Column field="hotel_name" header="Khách sạn" />
          <Column field="orders" header="Số đơn" sortable />
          <Column header="Doanh thu" sortable>
            <template #body="{ data }">{{ formatVND(data.revenue) }}</template>
          </Column>
          <Column header="Giá vốn">
            <template #body="{ data }">{{ formatVND(data.cost) }}</template>
          </Column>
          <Column header="Lợi nhuận">
            <template #body="{ data }">
              <span :class="{ 'text-negative': data.profit < 0 }">{{ formatVND(data.profit) }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.actions { display: flex; justify-content: flex-end; }
.loading { display: grid; place-items: center; min-height: 200px; }
.chart-wrap { height: 320px; }
.text-negative { color: var(--p-red-600); font-weight: 500; }
</style>
