<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { formatDateTime } from '@/composables/useFormat';
import type { ActivityLog } from '@/api/activityLog.api';

const ui = useUIStore();

const filterSchema: FilterDef[] = [
  { key: 'entity_type', label: 'Loại đối tượng', type: 'enum', options: [
    { label: 'Đơn hàng',  value: 'order' },
    { label: 'Khách hàng', value: 'customer' },
    { label: 'Thanh toán', value: 'payment' },
    { label: 'Mã giảm giá', value: 'coupon' },
    { label: 'Đăng nhập', value: 'auth' },
    { label: 'Email',     value: 'mail' },
    { label: 'Phiên token', value: 'token' },
  ]},
  { key: 'action', label: 'Hành động', type: 'string' },
  { key: 'entity_id', label: 'ID đối tượng', type: 'string' },
  { key: 'actor_user_id', label: 'User ID thực hiện', type: 'string' },
  { key: 'date_from', label: 'Từ ngày', type: 'date' },
  { key: 'date_to', label: 'Đến ngày', type: 'date' },
];

const detail = ref<ActivityLog | null>(null);
const dialog = ref(false);

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Nhật ký hệ thống' }]);
});

function openDetail(row: ActivityLog) {
  detail.value = row;
  dialog.value = true;
}

function actionSeverity(action: string): string {
  if (action === 'login_failed' || action.includes('fail')) return 'danger';
  if (action.includes('cancel') || action.includes('reuse')) return 'warn';
  if (action === 'sent' || action.includes('created') || action.includes('paid')) return 'success';
  return 'info';
}

function prettyJson(value: unknown): string {
  if (value == null) return '—';
  try { return JSON.stringify(value, null, 2); } catch { return String(value); }
}

function isOrderLink(row: ActivityLog): boolean {
  return row.entity_type === 'order' && row.entity_id > 0;
}
</script>

<template>
  <div>
    <PageHeader title="Nhật ký hệ thống" subtitle="Lịch sử thao tác" icon="pi pi-history" />

    <DataTablePanel
      endpoint="/activity-log"
      :defaults="{ sort: 'created_at', order: 'desc' }"
      storage-key="activity.list.v1"
    >
      <template #filters="{ update }">
        <FilterBar :schema="filterSchema" @apply="update" />
      </template>

      <Column field="created_at" header="Thời gian" sortable>
        <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
      </Column>
      <Column field="actor_user_id" header="User" />
      <Column field="entity_type" header="Loại" />
      <Column field="entity_id" header="ID">
        <template #body="{ data }">
          <RouterLink v-if="isOrderLink(data)" :to="`/orders/${data.entity_id}`" class="link">#{{ data.entity_id }}</RouterLink>
          <span v-else>{{ data.entity_id || '—' }}</span>
        </template>
      </Column>
      <Column field="action" header="Hành động">
        <template #body="{ data }">
          <Tag :severity="actionSeverity(data.action) as any" :value="data.action" />
        </template>
      </Column>
      <Column field="ip" header="IP">
        <template #body="{ data }">{{ data.ip || '—' }}</template>
      </Column>
      <Column header="" style="width: 110px">
        <template #body="{ data }">
          <button class="btn-link" @click="openDetail(data)">Chi tiết</button>
        </template>
      </Column>
    </DataTablePanel>

    <Dialog v-model:visible="dialog" :modal="true" :style="{ width: '720px' }" :header="`Nhật ký #${detail?.id ?? ''}`">
      <div v-if="detail" class="detail">
        <div class="grid-2">
          <div><strong>Thời gian:</strong> {{ formatDateTime(detail.created_at) }}</div>
          <div><strong>User:</strong> {{ detail.actor_user_id || '—' }}</div>
          <div><strong>Loại:</strong> {{ detail.entity_type }}</div>
          <div><strong>ID:</strong> {{ detail.entity_id || '—' }}</div>
          <div><strong>Hành động:</strong> {{ detail.action }}</div>
          <div><strong>IP:</strong> {{ detail.ip || '—' }}</div>
        </div>
        <div class="ua" v-if="detail.user_agent"><strong>User Agent:</strong> {{ detail.user_agent }}</div>
        <div class="json-row">
          <h4>Before</h4>
          <pre>{{ prettyJson(detail.before_json) }}</pre>
        </div>
        <div class="json-row">
          <h4>After</h4>
          <pre>{{ prettyJson(detail.after_json) }}</pre>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); text-decoration: none; font-weight: 500; }
.link:hover { text-decoration: underline; }
.btn-link { background: none; border: none; color: var(--p-primary-600); cursor: pointer; padding: 0; font-weight: 500; }
.btn-link:hover { text-decoration: underline; }
.detail { display: flex; flex-direction: column; gap: 0.85rem; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1rem; }
.ua { font-size: 0.85rem; color: var(--p-text-muted-color); word-break: break-all; }
.json-row h4 { margin: 0 0 0.35rem; font-size: 0.9rem; font-weight: 600; }
.json-row pre { background: var(--p-surface-50); border: 1px solid var(--p-surface-200); border-radius: 6px; padding: 0.75rem; font-family: monospace; font-size: 0.8rem; max-height: 220px; overflow: auto; margin: 0; }
</style>
