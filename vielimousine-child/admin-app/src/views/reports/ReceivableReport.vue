<script setup lang="ts">
import { computed } from 'vue';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import StatusTag from '@/components/StatusTag.vue';
import { useCsvExport } from '@/composables/useCsvExport';
import { formatVND, formatDate } from '@/composables/useFormat';
import type { Order } from '@/types/order';

const props = defineProps<{ orders: Order[] }>();
const csv = useCsvExport();

const receivables = computed(() => {
  return props.orders
    .filter((o) =>
      ['cancelled', 'no_show'].indexOf(o.status) === -1 &&
      (o.paid_amount ?? 0) < (o.total ?? 0)
    )
    .map((o) => ({
      ...o,
      remaining: (o.total ?? 0) - (o.paid_amount ?? 0),
      is_overdue: new Date(o.checkin) < new Date(),
    }))
    .sort((a, b) => b.remaining - a.remaining);
});

const totalOutstanding = computed(() =>
  receivables.value.reduce((s, o) => s + o.remaining, 0)
);
const overdueCount = computed(() => receivables.value.filter((o) => o.is_overdue).length);

function exportCsv() {
  csv.downloadCsv(
    `vie-receivable-${new Date().toISOString().slice(0, 10)}.csv`,
    ['Mã đơn', 'Khách hàng', 'SĐT', 'Check-in', 'Tổng', 'Đã trả', 'Còn phải thu', 'Trạng thái', 'Quá hạn'],
    receivables.value.map((o) => [
      o.code,
      o.customer_name,
      o.customer_phone,
      o.checkin,
      o.total,
      o.paid_amount,
      o.remaining,
      o.status,
      o.is_overdue ? '1' : '0',
    ])
  );
}
</script>

<template>
  <div class="report">
    <div class="cards">
      <Card class="metric">
        <template #title>Tổng công nợ</template>
        <template #content>
          <div class="value warn">{{ formatVND(totalOutstanding) }}</div>
          <div class="sub">{{ receivables.length }} đơn</div>
        </template>
      </Card>
      <Card class="metric">
        <template #title>Đã quá hạn (đã checkin)</template>
        <template #content>
          <div class="value danger">{{ overdueCount }}</div>
          <div class="sub">đơn cần thu gấp</div>
        </template>
      </Card>
    </div>

    <div class="actions">
      <Button label="Xuất CSV" icon="pi pi-download" outlined @click="exportCsv" />
    </div>

    <Card>
      <template #title>Chi tiết công nợ</template>
      <template #content>
        <DataTable :value="receivables" :empty-message="'Không có công nợ trong khoảng đã chọn'">
          <Column field="code" header="Mã đơn">
            <template #body="{ data }">
              <RouterLink :to="`/orders/${data.id}`" class="link">{{ data.code }}</RouterLink>
            </template>
          </Column>
          <Column header="Khách hàng">
            <template #body="{ data }">
              {{ data.customer_name }} ({{ data.customer_phone }})
            </template>
          </Column>
          <Column field="checkin" header="Check-in" sortable>
            <template #body="{ data }">
              <span :class="{ 'overdue': data.is_overdue }">{{ formatDate(data.checkin) }}</span>
            </template>
          </Column>
          <Column field="total" header="Tổng" sortable>
            <template #body="{ data }">{{ formatVND(data.total) }}</template>
          </Column>
          <Column field="paid_amount" header="Đã trả">
            <template #body="{ data }">{{ formatVND(data.paid_amount) }}</template>
          </Column>
          <Column field="remaining" header="Còn phải thu" sortable>
            <template #body="{ data }">
              <strong class="warn">{{ formatVND(data.remaining) }}</strong>
            </template>
          </Column>
          <Column field="status" header="Trạng thái">
            <template #body="{ data }"><StatusTag :value="data.status" /></template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.report { display: flex; flex-direction: column; gap: 1rem; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; }
.value { font-size: 1.5rem; font-weight: 700; }
.value.warn { color: var(--p-orange-600); }
.value.danger { color: var(--p-red-600); }
.sub { font-size: 0.85rem; color: var(--p-text-muted-color); margin-top: 0.25rem; }
.actions { display: flex; justify-content: flex-end; }
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.overdue { color: var(--p-red-600); font-weight: 500; }
.warn { color: var(--p-orange-600); }
</style>
