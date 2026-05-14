<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import { labelSource } from '@/stores/lookup.store';
import type { Order } from '@/types/order';

const props = defineProps<{ orders: Order[] }>();
const csv = useCsvExport();

const aggregated = computed(() => {
  const map = new Map<string, { source: string; orders: number; revenue: number; paid: number }>();
  for (const o of props.orders) {
    if (o.status === 'cancelled') continue;
    const src = o.source || 'unknown';
    if (!map.has(src)) {
      map.set(src, { source: src, orders: 0, revenue: 0, paid: 0 });
    }
    const row = map.get(src)!;
    row.orders++;
    row.revenue += o.total ?? 0;
    row.paid += o.paid_amount ?? 0;
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue);
});

const COLORS = ['#fa541c', '#20af48', '#3b82f6', '#a855f7', '#f59e0b', '#ec4899', '#10b981', '#6366f1'];

const chartData = computed(() => ({
  labels: aggregated.value.map((r) => labelSource(r.source)),
  datasets: [
    {
      data: aggregated.value.map((r) => r.revenue),
      backgroundColor: aggregated.value.map((_, i) => COLORS[i % COLORS.length]),
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'right' as const } },
};

function exportCsv() {
  csv.downloadCsv(
    `vie-by-source-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Nguồn', 'Số đơn', 'Doanh thu', 'Đã thu', 'AOV'],
    aggregated.value.map((r) => [
      labelSource(r.source),
      r.orders,
      r.revenue,
      r.paid,
      Math.round(r.orders > 0 ? r.revenue / r.orders : 0),
    ])
  );
}
</script>

<template>
  <div class="report">
    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <div class="grid-2">
      <Card>
        <template #title>Phân bổ doanh thu theo nguồn</template>
        <template #content>
          <div class="chart-wrap"><Chart type="pie" :data="chartData" :options="chartOptions" /></div>
        </template>
      </Card>

      <Card>
        <template #title>Chi tiết</template>
        <template #content>
          <DataTable :value="aggregated" :empty-message="'Không có dữ liệu'">
            <Column field="source" header="Nguồn">
              <template #body="{ data }">{{ labelSource(data.source) }}</template>
            </Column>
            <Column field="orders" header="Số đơn" sortable />
            <Column header="Doanh thu" sortable>
              <template #body="{ data }">{{ formatVND(data.revenue) }}</template>
            </Column>
            <Column header="AOV (TB/đơn)">
              <template #body="{ data }">
                {{ formatVND(data.orders > 0 ? Math.round(data.revenue / data.orders) : 0) }}
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.actions { display: flex; justify-content: flex-end; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.chart-wrap { height: 320px; }
@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
