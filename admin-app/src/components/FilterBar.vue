<script setup lang="ts">
import { reactive, watch } from 'vue';
import InputText from 'primevue/inputtext';
import Calendar from 'primevue/calendar';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import { useRoute } from 'vue-router';

export interface FilterDef {
  key: string;
  label: string;
  type: 'string' | 'enum' | 'date';
  options?: Array<{ label: string; value: string }>;
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

function apply() {
  const out: Record<string, string> = { page: '1' };
  Object.entries(state).forEach(([k, v]) => {
    out[k] = v !== '' && v !== null && v !== undefined ? String(v) : '';
  });
  emit('apply', out);
}

function reset() {
  props.schema.forEach((f) => { state[f.key] = ''; });
  apply();
}
</script>

<template>
  <div class="filter-bar">
    <div v-for="f in schema" :key="f.key" class="filter-item">
      <label>{{ f.label }}</label>
      <InputText v-if="f.type === 'string'" v-model="state[f.key]" :placeholder="f.label" />
      <Dropdown
        v-else-if="f.type === 'enum'"
        v-model="state[f.key]"
        :options="f.options ?? []"
        option-label="label"
        option-value="value"
        :placeholder="`Tất cả ${f.label.toLowerCase()}`"
        show-clear
      />
      <Calendar
        v-else-if="f.type === 'date'"
        :model-value="state[f.key] ? new Date(state[f.key]) : null"
        @update:model-value="(v: any) => state[f.key] = v instanceof Date ? v.toISOString().slice(0,10) : ''"
        date-format="yy-mm-dd"
        show-icon
      />
    </div>
    <div class="filter-actions">
      <Button label="Lọc" icon="pi pi-filter" @click="apply" />
      <Button label="Đặt lại" icon="pi pi-refresh" severity="secondary" outlined @click="reset" />
    </div>
  </div>
</template>

<style scoped>
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  padding: 0.75rem 1rem;
  background: var(--p-surface-50);
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}
.filter-item { display: flex; flex-direction: column; gap: 0.25rem; min-width: 160px; }
.filter-item label { font-size: 0.75rem; color: var(--p-text-muted-color); font-weight: 500; }
.filter-actions { display: flex; gap: 0.5rem; align-items: flex-end; }
</style>
