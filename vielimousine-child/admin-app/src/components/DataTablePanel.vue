<script setup lang="ts">
import DataTable from 'primevue/datatable';
import Paginator, { type PageState } from 'primevue/paginator';
import { useApiList } from '@/composables/useApiList';
import EmptyState from './EmptyState.vue';
import LoadingState from './LoadingState.vue';

const props = withDefaults(
  defineProps<{
    endpoint: string;
    defaults?: Record<string, unknown>;
    /** 'normal' | 'compact' — controls cell padding density */
    density?: 'normal' | 'compact';
    emptyTitle?: string;
    emptyDescription?: string;
    emptyIcon?: string;
    /** localStorage key to persist filters/pagination across navigation */
    storageKey?: string;
  }>(),
  {
    density: 'normal',
    emptyTitle: 'Không có dữ liệu',
    emptyIcon: 'pi pi-inbox',
  },
);

const { data, pagination, loading, error, updateQuery } = useApiList<Record<string, unknown>>(
  props.endpoint,
  props.defaults ?? {},
  { storageKey: props.storageKey },
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
  <div class="data-table-panel" :class="`density-${density}`">
    <slot name="filters" :update="updateQuery" />
    <slot name="toolbar" :update="updateQuery" />

    <div v-if="error" class="error-banner" role="alert">
      <i class="pi pi-exclamation-triangle" aria-hidden="true" />
      <span>{{ error }}</span>
    </div>

    <div class="table-wrap">
      <DataTable
        :value="data"
        :loading="false"
        lazy
        removable-sort
        @sort="onSort"
        data-key="id"
        striped-rows
        scrollable
        scroll-height="flex"
      >
        <template #empty>
          <LoadingState v-if="loading" variant="skeleton-table" :rows="6" />
          <EmptyState
            v-else
            inset
            :icon="emptyIcon"
            :title="emptyTitle"
            :description="emptyDescription"
          />
        </template>
        <slot />
      </DataTable>
    </div>

    <Paginator
      v-if="pagination.total > 0"
      :rows="pagination.per_page"
      :total-records="pagination.total"
      :rows-per-page-options="[10, 20, 50, 100]"
      :first="(pagination.page - 1) * pagination.per_page"
      @page="onPage"
      template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
      current-page-report-template="Trang {currentPage}/{totalPages} ({totalRecords} bản ghi)"
      class="paginator"
    />
  </div>
</template>

<style scoped>
.data-table-panel {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.table-wrap {
  overflow-x: auto;
  border-radius: var(--radius-md);
}
.error-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  background: var(--app-tint-danger);
  color: var(--app-on-tint-danger);
  border: 1px solid var(--app-tint-danger-border);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
}
.paginator { background: transparent; padding: 0; border: 0; }

.density-compact :deep(.p-datatable .p-datatable-thead > tr > th),
.density-compact :deep(.p-datatable .p-datatable-tbody > tr > td) {
  padding: 0.45rem 0.6rem;
  font-size: 0.875rem;
}
</style>
