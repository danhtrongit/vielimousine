<script setup lang="ts">
import { computed } from 'vue';

type Accent = 'primary' | 'success' | 'info' | 'warning' | 'danger' | 'neutral';

interface Trend {
  value: number;
  direction?: 'up' | 'down';
  label?: string;
}

interface Props {
  label: string;
  value: string | number;
  sub?: string;
  icon?: string;
  accent?: Accent;
  loading?: boolean;
  trend?: Trend | null;
  /** When true, applies tabular-nums mono font to value (good for money / counts). */
  mono?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  accent: 'primary',
  loading: false,
  mono: true,
  trend: null,
});

const accentClass = computed(() => `accent-${props.accent}`);
const trendDirection = computed(() => props.trend?.direction ?? (props.trend && props.trend.value < 0 ? 'down' : 'up'));
</script>

<template>
  <div class="stat-card" :class="accentClass">
    <div class="stat-icon" v-if="icon">
      <i :class="icon" aria-hidden="true" />
    </div>
    <div class="stat-body">
      <div class="stat-label">{{ label }}</div>
      <div class="stat-value" :class="{ 'font-mono': mono }">
        <span v-if="loading" class="skeleton" aria-hidden="true" />
        <template v-else>{{ value }}</template>
      </div>
      <div class="stat-foot">
        <span v-if="sub" class="stat-sub">{{ sub }}</span>
        <span v-if="trend" class="stat-trend" :class="`trend-${trendDirection}`">
          <i :class="trendDirection === 'up' ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-right'" aria-hidden="true" />
          {{ Math.abs(trend.value).toFixed(1) }}%
          <span v-if="trend.label" class="trend-label">{{ trend.label }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-card {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-xs);
  transition: box-shadow var(--motion-base) var(--ease-out), background-color var(--motion-base) var(--ease-out);
}
.stat-card:hover { box-shadow: var(--shadow-sm); }
.stat-icon {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: var(--radius-md);
  font-size: 1.15rem;
}
.stat-body { display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1; }
.stat-label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  font-weight: 500;
}
.stat-value {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--p-text-color);
  line-height: 1.1;
  letter-spacing: -0.01em;
  min-height: 1.6em;
}
.stat-foot {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}
.stat-sub { font-size: 0.8rem; color: var(--p-text-muted-color); }
.stat-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: var(--radius-sm);
}
.trend-label { font-weight: 400; opacity: 0.8; margin-left: 2px; }

.skeleton {
  display: inline-block;
  width: 80px;
  height: 1.1em;
  border-radius: var(--radius-xs);
  background: linear-gradient(90deg, var(--app-hover-bg), var(--app-card-border), var(--app-hover-bg));
  background-size: 200% 100%;
  animation: pulse 1.4s ease-in-out infinite;
}
@keyframes pulse { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Accent variants — sử dụng adaptive tint tokens (tự flip theo dark mode) */
.accent-primary .stat-icon { background: var(--app-tint-primary); color: var(--app-on-tint-primary); }
.accent-success .stat-icon { background: var(--app-tint-success); color: var(--app-on-tint-success); }
.accent-info .stat-icon { background: var(--app-tint-info); color: var(--app-on-tint-info); }
.accent-warning .stat-icon { background: var(--app-tint-warning); color: var(--app-on-tint-warning); }
.accent-danger .stat-icon { background: var(--app-tint-danger); color: var(--app-on-tint-danger); }
.accent-neutral .stat-icon { background: var(--app-tint-neutral); color: var(--app-on-tint-neutral); }

.trend-up { color: var(--app-on-tint-success); background: var(--app-tint-success); }
.trend-down { color: var(--app-on-tint-danger); background: var(--app-tint-danger); }
</style>
