<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import { useRouter, useRoute } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
// note: Vue templates don't support generic component syntax; use DataTablePanel as plain component
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import StatusTag from '@/components/StatusTag.vue';
import Can from '@/components/Can.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { useCsvExport } from '@/composables/useCsvExport';
import { ordersApi } from '@/api/orders.api';
import { ORDER_STATUSES, PAYMENT_STATUSES, ORDER_SOURCES, labelOrderStatus } from '@/stores/lookup.store';
import { formatVND, formatDate, formatDateTime } from '@/composables/useFormat';

const router = useRouter();
const route = useRoute();
const ui = useUIStore();
const notify = useNotify();
const csv = useCsvExport();
const exporting = ref(false);

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Mã đơn / SĐT / Tên', type: 'string' },
  { key: 'status', label: 'Trạng thái', type: 'enum', options: ORDER_STATUSES },
  { key: 'payment_status', label: 'Thanh toán', type: 'enum', options: PAYMENT_STATUSES },
  { key: 'source', label: 'Nguồn', type: 'enum', options: ORDER_SOURCES },
  { key: 'date_from', label: 'Từ ngày', type: 'date' },
  { key: 'date_to', label: 'Đến ngày', type: 'date' },
];

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Đơn hàng' }]);
});

function rowClick(data: { id: number; status?: string }) {
  if (data.status === 'draft') {
    router.push({ path: '/orders/new', query: { draft: String(data.id) } });
  } else {
    router.push(`/orders/${data.id}`);
  }
}

async function deleteDraft(data: { id: number }) {
  if (!window.confirm('Xóa bản nháp này?')) return;
  try {
    await ordersApi.deleteDraft(data.id);
    notify.success('Đã xóa nháp');
    // Buộc DataTablePanel tải lại: đổi 1 param vô hại trên query (backend bỏ qua key lạ).
    router.replace({ query: { ...route.query, _r: route.query._r === '1' ? '0' : '1' } });
  } catch (e) {
    notify.apiError(e, 'Không xóa được nháp');
  }
}

function remaining(o: { total?: number | string | null; paid_amount?: number | string | null }): number {
  return Math.max(0, Number(o.total ?? 0) - Number(o.paid_amount ?? 0));
}

async function exportAll() {
  exporting.value = true;
  try {
    const resp = await ordersApi.list({ per_page: 5000 });
    // Nháp là đơn chưa hoàn thiện — không đưa vào CSV xuất (đồng nhất với việc loại nháp khỏi báo cáo).
    const rows = resp.data.filter((o) => o.status !== 'draft').map((o) => [
      o.code,
      o.customer_name,
      formatDate(o.checkin),
      formatDate(o.checkout),
      o.hotel_names ?? '',
      o.nights,
      formatVND(o.total),
      formatVND(o.hotel_subtotal),
      formatVND(o.ticket_subtotal),
      formatVND(o.cost_total),
      formatVND(o.profit_total),
      formatVND(o.paid_amount),
      formatVND(remaining(o)),
      labelOrderStatus(o.status),
      formatDateTime(o.created_at),
    ]);
    const today = new Date().toISOString().slice(0, 10);
    csv.downloadCsv(
      `vie-orders-${today}.csv`,
      [
        'Mã đơn', 'Tên khách hàng', 'Check in', 'Check out', 'Tên khách sạn', 'Đêm',
        'Tổng tiền', 'Tổng khách sạn', 'Tổng chi phí vé', 'Tổng giá vốn', 'Lợi nhuận dự kiến',
        'Đã thanh toán', 'Chưa thanh toán', 'Trạng thái', 'Tạo lúc',
      ],
      rows
    );
    notify.success('Đã xuất CSV', `${rows.length} dòng`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    exporting.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Đơn hàng" subtitle="Quản lý booking" icon="pi pi-shopping-cart">
      <Button label="Xuất CSV" icon="pi pi-download" :loading="exporting" outlined @click="exportAll" />
      <Can cap="vie_create_orders">
        <Button label="Tạo đơn mới" icon="pi pi-plus" @click="router.push('/orders/new')" />
      </Can>
    </PageHeader>

    <DataTablePanel
      endpoint="/orders"
      :defaults="{ sort: 'created_at', order: 'desc' }"
      storage-key="orders.list.v1"
      table-style="min-width: 1900px"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="code" header="Mã đơn" sortable>
        <template #body="{ data }">
          <a v-if="data.status === 'draft'" class="link link-draft" @click="rowClick(data)">Nháp #{{ data.id }}</a>
          <RouterLink v-else :to="`/orders/${data.id}`" class="link">{{ data.code }}</RouterLink>
        </template>
      </Column>
      <Column field="customer_name" header="Tên khách hàng" sortable />
      <Column field="checkin" header="Check in" sortable>
        <template #body="{ data }">{{ formatDate(data.checkin) }}</template>
      </Column>
      <Column field="checkout" header="Check out" sortable>
        <template #body="{ data }">{{ formatDate(data.checkout) }}</template>
      </Column>
      <Column field="hotel_names" header="Tên khách sạn">
        <template #body="{ data }">
          <span class="truncate" :title="data.hotel_names || undefined">{{ data.hotel_names || '—' }}</span>
        </template>
      </Column>
      <Column field="nights" header="Đêm" sortable />
      <Column field="total" header="Tổng tiền" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.total) }}</template>
      </Column>
      <Column field="hotel_subtotal" header="Tổng khách sạn" class="col-money">
        <template #body="{ data }">{{ formatVND(data.hotel_subtotal) }}</template>
      </Column>
      <Column field="ticket_subtotal" header="Tổng chi phí vé" class="col-money">
        <template #body="{ data }">{{ formatVND(data.ticket_subtotal) }}</template>
      </Column>
      <Column field="cost_total" header="Tổng giá vốn" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.cost_total) }}</template>
      </Column>
      <Column field="profit_total" header="Lợi nhuận dự kiến" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.profit_total) }}</template>
      </Column>
      <Column field="paid_amount" header="Đã thanh toán" sortable class="col-money">
        <template #body="{ data }">{{ formatVND(data.paid_amount) }}</template>
      </Column>
      <Column header="Chưa thanh toán" class="col-money">
        <template #body="{ data }">{{ formatVND(remaining(data)) }}</template>
      </Column>
      <Column field="status" header="Trạng thái" sortable>
        <template #body="{ data }">
          <StatusTag :value="data.status" />
        </template>
      </Column>
      <Column field="created_at" header="Tạo lúc" sortable>
        <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
      </Column>
      <Column header="" :exportable="false" style="width: 100px">
        <template #body="{ data }">
          <template v-if="data.status === 'draft'">
            <Button icon="pi pi-pencil" text rounded title="Tiếp tục nháp" @click="rowClick(data)" />
            <Button icon="pi pi-trash" severity="danger" text rounded title="Xóa nháp" @click="deleteDraft(data)" />
          </template>
          <Button v-else icon="pi pi-eye" text rounded @click="rowClick(data)" />
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); font-weight: 500; text-decoration: none; }
.link:hover { text-decoration: underline; }
.link-draft { cursor: pointer; }
.truncate { display: inline-block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
:deep(.col-money) { text-align: right; font-variant-numeric: tabular-nums; }
:deep(th.col-money .p-datatable-column-header-content) { justify-content: flex-end; }
:deep(.p-datatable-thead th),
:deep(.p-datatable-tbody td) { white-space: nowrap; }
</style>
