<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import type { Order } from '@/types/order';

const props = defineProps<{ orders: Order[] }>();
const csv = useCsvExport();

const aggregated = computed(() => {
  const map = new Map<number, { hotel_id: number; hotel_name: string; orders: number; revenue: number; paid: number; outstanding: number }>();
  for (const o of props.orders) {
    if (o.status === 'cancelled') continue;
    // Aggregate by primary hotel of order's first item (approx - items not in list response)
    // Use 'hotel_id' if available, else 0
    const hid = 0;  // No hotel_id in Order list response — items aren't included
    // Fallback: group all into "Tất cả" since order list doesn't include items
    if (!map.has(hid)) {
      map.set(hid, { hotel_id: hid, hotel_name: 'Tất cả khách sạn', orders: 0, revenue: 0, paid: 0, outstanding: 0 });
    }
    const row = map.get(hid)!;
    row.orders++;
    row.revenue += o.total ?? 0;
    row.paid += o.paid_amount ?? 0;
    row.outstanding += Math.max(0, (o.total ?? 0) - (o.paid_amount ?? 0));
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue);
});

const chartData = computed(() => ({
  labels: aggregated.value.slice(0, 10).map((r) => r.hotel_name),
  datasets: [
    {
      label: 'Doanh thu',
      data: aggregated.value.slice(0, 10).map((r) => r.revenue),
      backgroundColor: '#fa541c',
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y' as const,
  plugins: { legend: { display: false } },
};

function exportCsv() {
  csv.downloadCsv(
    `vie-by-hotel-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Khách sạn', 'Số đơn', 'Doanh thu', 'Đã thu', 'Còn phải thu'],
    aggregated.value.map((r) => [r.hotel_name, r.orders, r.revenue, r.paid, r.outstanding])
  );
}
</script>

<template>
  <div class="report">
    <p class="muted">
      <i class="pi pi-info-circle" />
      Phân tích theo khách sạn yêu cầu fetch chi tiết từng đơn — tạm thời gộp toàn bộ.
      Để phân tích chính xác, mở từng order detail hoặc dùng query backend (Phase sau).
    </p>

    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <Card>
      <template #title>Top 10 doanh thu theo khách sạn</template>
      <template #content>
        <div class="chart-wrap"><Chart type="bar" :data="chartData" :options="chartOptions" /></div>
      </template>
    </Card>

    <Card>
      <template #title>Chi tiết</template>
      <template #content>
        <DataTable :value="aggregated" :empty-message="'Không có dữ liệu'">
          <Column field="hotel_name" header="Khách sạn" />
          <Column field="orders" header="Số đơn" sortable />
          <Column header="Doanh thu" sortable>
            <template #body="{ data }">{{ formatVND(data.revenue) }}</template>
          </Column>
          <Column header="Đã thu">
            <template #body="{ data }">{{ formatVND(data.paid) }}</template>
          </Column>
          <Column header="Còn phải thu">
            <template #body="{ data }">{{ formatVND(data.outstanding) }}</template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; padding: 0.5rem 0.75rem; background: var(--p-surface-50); border-radius: 0.375rem; }
.actions { display: flex; justify-content: flex-end; }
.chart-wrap { height: 320px; }
</style>
