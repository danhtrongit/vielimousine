<script setup lang="ts">
import DataTable from 'primevue/datatable';
import Paginator, { type PageState } from 'primevue/paginator';
import ProgressSpinner from 'primevue/progressspinner';
import { useApiList } from '@/composables/useApiList';

const props = defineProps<{
  endpoint: string;
  defaults?: Record<string, unknown>;
}>();

const { data, pagination, loading, error, updateQuery } = useApiList<Record<string, unknown>>(
  props.endpoint,
  props.defaults ?? {}
);

function onPage(e: PageState) {
  updateQuery({ page: String(e.page + 1), per_page: String(e.rows) });
}
function onSort(e: { sortField?: unknown; sortOrder?: number | null }) {
  const field = typeof e.sortField === 'string' ? e.sortField : '';
  if (!field) {
    updateQuery({ sort: '', order: '' });
    return;
  }
  updateQuery({ sort: field, order: e.sortOrder === 1 ? 'asc' : 'desc' });
}
</script>

<template>
  <div class="data-table-panel">
    <slot name="filters" :update="updateQuery" />
    <div v-if="error" class="error-banner">{{ error }}</div>
    <DataTable
      :value="data"
      :loading="loading"
      lazy
      removable-sort
      @sort="onSort"
      data-key="id"
    >
      <template #empty>{{ loading ? 'Đang tải...' : 'Không có dữ liệu' }}</template>
      <slot />
    </DataTable>
    <Paginator
      :rows="pagination.per_page"
      :total-records="pagination.total"
      :rows-per-page-options="[10, 20, 50, 100]"
      :first="(pagination.page - 1) * pagination.per_page"
      @page="onPage"
      template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
      current-page-report-template="Trang {currentPage}/{totalPages} ({totalRecords} bản ghi)"
    />
    <ProgressSpinner v-if="loading" style="position:absolute;top:50%;left:50%;width:32px;height:32px" />
  </div>
</template>

<style scoped>
.data-table-panel { position: relative; }
.error-banner {
  padding: 0.75rem 1rem;
  background: var(--p-red-50);
  color: var(--p-red-700);
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
}
</style>
