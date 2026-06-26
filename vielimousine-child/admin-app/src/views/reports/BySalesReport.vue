<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND } from '@/composables/useFormat';
import { useNotify } from '@/composables/useNotify';
import { useLookupStore } from '@/stores/lookup.store';
import type { Order } from '@/types/order';

const props = defineProps<{ orders: Order[] }>();
const csv = useCsvExport();
const notify = useNotify();
const lookup = useLookupStore();

const aggregated = computed(() => {
  const map = new Map<number, { sales_user_id: number; orders: number; revenue: number; paid: number }>();
  for (const o of props.orders) {
    if (o.status === 'cancelled' || o.sales_user_id === null) continue;
    const uid = o.sales_user_id;
    if (!map.has(uid)) {
      map.set(uid, { sales_user_id: uid, orders: 0, revenue: 0, paid: 0 });
    }
    const row = map.get(uid)!;
    row.orders++;
    row.revenue += o.total ?? 0;
    row.paid += o.paid_amount ?? 0;
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue);
});

const chartData = computed(() => ({
  labels: aggregated.value.slice(0, 10).map((r) => lookup.displayUser(r.sales_user_id)),
  datasets: [
    {
      label: 'Doanh thu',
      data: aggregated.value.slice(0, 10).map((r) => r.revenue),
      backgroundColor: '#20af48',
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
};

function exportCsv() {
  try {
    csv.downloadCsv(
      `vie-by-sales-${new Date().toISOString().slice(0, 10)}.csv`,
      ['Nhân viên', 'Email', 'Số đơn', 'Doanh thu', 'Đã thu'],
      aggregated.value.map((r) => {
        const u = lookup.userById(r.sales_user_id);
        return [u?.display_name ?? `User #${r.sales_user_id}`, u?.email ?? '', r.orders, r.revenue, r.paid];
      })
    );
    notify.success('Đã xuất CSV');
  } catch (e) {
    notify.apiError(e, 'Không xuất được CSV');
  }
}
</script>

<template>
  <div class="report">
    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <Card>
      <template #title>Top 10 sales theo doanh thu</template>
      <template #content>
        <div class="chart-wrap"><Chart type="bar" :data="chartData" :options="chartOptions" /></div>
      </template>
    </Card>

    <Card>
      <template #title>Chi tiết</template>
      <template #content>
        <DataTable :value="aggregated" :empty-message="'Chưa có đơn nào của sales user'">
          <Column header="Nhân viên">
            <template #body="{ data }">
              <div class="user-cell">
                <strong>{{ lookup.displayUser(data.sales_user_id) }}</strong>
                <small v-if="lookup.userById(data.sales_user_id)?.email">{{ lookup.userById(data.sales_user_id)?.email }}</small>
              </div>
            </template>
          </Column>
          <Column field="orders" header="Số đơn" sortable />
          <Column header="Doanh thu" sortable>
            <template #body="{ data }">{{ formatVND(data.revenue) }}</template>
          </Column>
          <Column header="Đã thu">
            <template #body="{ data }">{{ formatVND(data.paid) }}</template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.actions { display: flex; justify-content: flex-end; }
.chart-wrap { height: 320px; }
.user-cell { display: flex; flex-direction: column; }
.user-cell small { color: var(--p-text-muted-color); font-size: 0.75rem; }
</style>
