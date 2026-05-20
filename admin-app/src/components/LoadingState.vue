<script setup lang="ts">
import ProgressSpinner from 'primevue/progressspinner';

type Variant = 'spinner' | 'skeleton-cards' | 'skeleton-table' | 'skeleton-text';

interface Props {
  variant?: Variant;
  rows?: number;
  label?: string;
  /** Adds min height (used when wrapped in a section). */
  fill?: boolean;
}

withDefaults(defineProps<Props>(), {
  variant: 'spinner',
  rows: 3,
  fill: false,
});
</script>

<template>
  <div class="loading-state" :class="{ fill }" role="status" :aria-busy="true" :aria-label="label ?? 'Đang tải'">
    <template v-if="variant === 'spinner'">
      <ProgressSpinner style="width: 36px; height: 36px;" strokeWidth="4" />
      <span v-if="label" class="loading-label">{{ label }}</span>
    </template>

    <div v-else-if="variant === 'skeleton-cards'" class="sk-cards">
      <div v-for="i in rows" :key="i" class="sk-card">
        <div class="sk-line w-50" />
        <div class="sk-line lg" />
        <div class="sk-line w-30" />
      </div>
    </div>

    <div v-else-if="variant === 'skeleton-table'" class="sk-table">
      <div class="sk-row sk-row-head">
        <div v-for="i in 5" :key="i" class="sk-line w-80" />
      </div>
      <div v-for="r in rows" :key="r" class="sk-row">
        <div v-for="i in 5" :key="i" class="sk-line w-80" />
      </div>
    </div>

    <div v-else-if="variant === 'skeleton-text'" class="sk-text">
      <div v-for="i in rows" :key="i" class="sk-line" :class="{ 'w-80': i % 2, 'w-60': !(i % 2) }" />
    </div>
  </div>
</template>

<style scoped>
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-6);
}
.loading-state.fill { min-height: 240px; }
.loading-label { font-size: 0.875rem; color: var(--p-text-muted-color); }

/* Skeleton primitives */
.sk-line {
  height: 12px;
  border-radius: var(--radius-xs);
  background: linear-gradient(90deg, var(--app-hover-bg), var(--app-card-border), var(--app-hover-bg));
  background-size: 200% 100%;
  animation: pulse 1.4s ease-in-out infinite;
  width: 100%;
}
.sk-line.lg { height: 24px; }
.sk-line.w-80 { width: 80%; }
.sk-line.w-60 { width: 60%; }
.sk-line.w-50 { width: 50%; }
.sk-line.w-30 { width: 30%; }
@keyframes pulse { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Cards layout */
.sk-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-4);
  width: 100%;
}
.sk-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-lg);
}

/* Table layout */
.sk-table { width: 100%; display: flex; flex-direction: column; gap: var(--space-1); }
.sk-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-3); padding: var(--space-2) 0; }
.sk-row-head .sk-line { background-color: var(--app-card-border); }

/* Text layout */
.sk-text { width: 100%; display: flex; flex-direction: column; gap: var(--space-2); }
</style>
