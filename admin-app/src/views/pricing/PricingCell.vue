<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { formatCompact } from '@/composables/useFormat';

const props = defineProps<{
  modelValue: number;
  placeholder?: number;
  width?: number;
  pending?: boolean;
  error?: boolean;
}>();

const emit = defineEmits<{
  (e: 'change', value: number): void;
}>();

const editing = ref(false);
const draft = ref<string>(String(props.modelValue));

watch(() => props.modelValue, (v) => {
  if (!editing.value) draft.value = String(v);
});

const displayText = computed(() => formatCompact(props.modelValue));

function onFocus(e: FocusEvent) {
  editing.value = true;
  draft.value = props.modelValue === 0 ? '' : String(props.modelValue);
  // Select all to enable quick replace
  requestAnimationFrame(() => (e.target as HTMLInputElement).select());
}

function onInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value;
  // Allow only digits (Vietnamese never uses decimals in prices)
  draft.value = raw.replace(/[^\d]/g, '');
}

function commit() {
  editing.value = false;
  const next = draft.value === '' ? 0 : Number(draft.value);
  if (next !== props.modelValue) emit('change', next);
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
  if (e.key === 'Escape') {
    draft.value = String(props.modelValue);
    (e.target as HTMLInputElement).blur();
  }
}
</script>

<template>
  <div class="cell-input-wrap" :class="{ pending, error, editing }">
    <input
      type="text"
      inputmode="numeric"
      :value="editing ? draft : displayText"
      :placeholder="placeholder !== undefined ? formatCompact(placeholder) : '0'"
      :style="{ width: (width ?? 80) + 'px' }"
      @focus="onFocus"
      @input="onInput"
      @blur="commit"
      @keydown="onKeydown"
    />
    <span v-if="pending" class="ind ind-pending" title="Đang chờ lưu">●</span>
    <span v-else-if="error" class="ind ind-error" title="Lỗi lưu">!</span>
  </div>
</template>

<style scoped>
.cell-input-wrap { position: relative; display: inline-flex; align-items: center; }
.cell-input-wrap input {
  font: inherit; font-size: 0.78rem; padding: 0.2rem 0.35rem; text-align: right;
  border: 1px solid transparent; border-radius: 3px; background: transparent;
  color: inherit; outline: none;
}
.cell-input-wrap input::placeholder { color: var(--p-text-muted-color); opacity: 0.6; }
.cell-input-wrap input:hover { border-color: var(--p-surface-300); background: var(--p-surface-0); }
.cell-input-wrap input:focus { border-color: var(--p-primary-500); background: var(--p-surface-0); box-shadow: 0 0 0 2px var(--p-primary-100); }
.cell-input-wrap.pending input { background: var(--p-yellow-50); border-color: var(--p-yellow-400); }
.cell-input-wrap.error input { background: var(--p-red-50); border-color: var(--p-red-500); }
.ind { position: absolute; right: -10px; top: 50%; transform: translateY(-50%); font-size: 0.6rem; }
.ind-pending { color: var(--p-yellow-600); }
.ind-error { color: var(--p-red-600); font-weight: bold; }
</style>
