<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import { useRoute } from 'vue-router';
import { ymdLocal } from '@/composables/useFormat';

export interface FilterDef {
  key: string;
  label: string;
  type: 'string' | 'enum' | 'date';
  options?: Array<{ label: string; value: string }>;
  placeholder?: string;
}

const props = defineProps<{ schema: FilterDef[] }>();
const emit = defineEmits<{ (e: 'apply', payload: Record<string, string>): void }>();

const route = useRoute();
const state = reactive<Record<string, string>>({});

function initFromRoute() {
  props.schema.forEach((f) => {
    state[f.key] = (route.query[f.key] as string) ?? '';
  });
}
initFromRoute();
watch(() => route.query, initFromRoute);

const activeFilters = computed(() => {
  return props.schema
    .map((f) => {
      const v = state[f.key];
      if (v === '' || v === null || v === undefined) return null;
      let display = v;
      if (f.type === 'enum') {
        display = f.options?.find((o) => o.value === v)?.label ?? v;
      }
      return { key: f.key, label: f.label, value: display };
    })
    .filter((x): x is { key: string; label: string; value: string } => x !== null);
});

const hasActive = computed(() => activeFilters.value.length > 0);

function apply() {
  const out: Record<string, string> = { page: '1' };
  Object.entries(state).forEach(([k, v]) => {
    out[k] = v !== '' && v !== null && v !== undefined ? String(v) : '';
  });
  emit('apply', out);
}

function clearOne(key: string) {
  state[key] = '';
  apply();
}

function reset() {
  props.schema.forEach((f) => { state[f.key] = ''; });
  apply();
}
</script>

<template>
  <div class="filter-bar">
    <div class="filter-grid">
      <div v-for="f in schema" :key="f.key" class="filter-item">
        <label :for="`filter-${f.key}`">{{ f.label }}</label>
        <InputText
          v-if="f.type === 'string'"
          :id="`filter-${f.key}`"
          v-model="state[f.key]"
          :placeholder="f.placeholder ?? f.label"
          @keydown.enter="apply"
        />
        <Select
          v-else-if="f.type === 'enum'"
          :input-id="`filter-${f.key}`"
          v-model="state[f.key]"
          :options="f.options ?? []"
          option-label="label"
          option-value="value"
          :placeholder="f.placeholder ?? `Tất cả ${f.label.toLowerCase()}`"
          :filter="(f.options?.length ?? 0) > 6"
          filter-placeholder="Tìm…"
          show-clear
        />
        <DatePicker
          v-else-if="f.type === 'date'"
          :input-id="`filter-${f.key}`"
          :model-value="state[f.key] ? new Date(state[f.key]) : null"
          @update:model-value="(v: any) => state[f.key] = v instanceof Date ? ymdLocal(v) : ''"
          date-format="yy-mm-dd"
          show-icon
          :placeholder="f.placeholder"
        />
      </div>
      <div class="filter-actions">
        <Button label="Lọc" icon="pi pi-filter" @click="apply" />
        <Button
          label="Đặt lại"
          icon="pi pi-refresh"
          severity="secondary"
          outlined
          @click="reset"
          :disabled="!hasActive"
        />
      </div>
    </div>

    <div v-if="hasActive" class="active-chips" aria-label="Bộ lọc đang áp dụng">
      <Chip
        v-for="f in activeFilters"
        :key="f.key"
        :label="`${f.label}: ${f.value}`"
        removable
        @remove="clearOne(f.key)"
        class="active-chip"
      />
    </div>
  </div>
</template>

<style scoped>
.filter-bar {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-4);
}
.filter-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-3);
  align-items: end;
}
.filter-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}
.filter-item label {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  font-weight: 500;
  letter-spacing: 0.02em;
}
.filter-actions {
  display: flex;
  gap: var(--space-2);
  align-items: end;
  flex-wrap: wrap;
}
.active-chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  padding-top: var(--space-2);
  border-top: 1px dashed var(--app-card-border);
}
.active-chip :deep(.p-chip) {
  background: var(--app-tint-primary);
  color: var(--app-on-tint-primary);
}
@media (max-width: 768px) {
  .filter-actions { grid-column: 1 / -1; }
}
</style>
