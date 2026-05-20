<script setup lang="ts">
import { computed } from 'vue';
import { ORDER_STATUSES, PAYMENT_STATUSES } from '@/stores/lookup.store';

type Severity = 'success' | 'info' | 'warning' | 'danger' | 'secondary' | 'contrast';

interface Props {
  value: string;
  kind?: 'order' | 'payment';
  /** Override label / severity if used outside order/payment context */
  label?: string;
  severity?: Severity;
  size?: 'sm' | 'md';
  /** Render leading dot indicator instead of solid pill */
  dot?: boolean;
}

const props = withDefaults(defineProps<Props>(), { size: 'md', dot: false });

const opts = computed(() => (props.kind === 'payment' ? PAYMENT_STATUSES : ORDER_STATUSES));
const matched = computed(() => opts.value.find((o) => o.value === props.value));
const displayLabel = computed(() => props.label ?? matched.value?.label ?? props.value ?? '—');
const severity = computed<Severity>(() => (props.severity ?? (matched.value?.severity as Severity) ?? 'secondary'));
</script>

<template>
  <span class="status-tag" :class="[`sev-${severity}`, `size-${size}`, { 'with-dot': dot }]">
    <span v-if="dot" class="dot" aria-hidden="true" />
    {{ displayLabel }}
  </span>
</template>

<style scoped>
.status-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
  line-height: 1;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  white-space: nowrap;
}
.size-sm { font-size: 0.72rem; padding: 3px 6px; }
.size-md { font-size: 0.78rem; padding: 4px 8px; }
.dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: currentColor;
  flex-shrink: 0;
}

/* Severity variants — adaptive tint tokens (tự flip light/dark) */
.sev-success { background: var(--app-tint-success); color: var(--app-on-tint-success); border-color: var(--app-tint-success-border); }
.sev-info { background: var(--app-tint-info); color: var(--app-on-tint-info); border-color: var(--app-tint-info-border); }
.sev-warning { background: var(--app-tint-warning); color: var(--app-on-tint-warning); border-color: var(--app-tint-warning-border); }
.sev-danger { background: var(--app-tint-danger); color: var(--app-on-tint-danger); border-color: var(--app-tint-danger-border); }
.sev-secondary { background: var(--app-tint-neutral); color: var(--app-on-tint-neutral); border-color: var(--app-tint-neutral-border); }
.sev-contrast { background: var(--app-text-strong); color: var(--app-bg-elevated); border-color: var(--app-text-strong); }
</style>
