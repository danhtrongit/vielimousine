<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { useTheme } from '@/composables/useTheme';

const route = useRoute();
const isAuthLayout = computed(() => route.meta.layout === 'auth');

useTheme();
</script>

<template>
  <AuthLayout v-if="isAuthLayout">
    <RouterView />
  </AuthLayout>
  <RouterView v-else />
</template>

<style>
*, *::before, *::after { box-sizing: border-box; }

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
}

body {
  font-family: var(--font-sans);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: var(--p-text-color);
  background: var(--app-bg);
  line-height: 1.5;
  transition: background-color var(--motion-base) var(--ease-out);
}

a { color: inherit; }

.font-mono {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum';
}

.required-mark { color: var(--p-red-500); font-weight: 600; }

/* Focus ring đồng nhất toàn app */
:focus-visible {
  outline: 2px solid var(--p-primary-500);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

/* Scrollbar */
::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb {
  background-color: var(--p-surface-300);
  border-radius: var(--radius-full);
  border: 2px solid transparent;
  background-clip: content-box;
}
::-webkit-scrollbar-thumb:hover { background-color: var(--p-surface-400); background-clip: content-box; }
.dark-mode ::-webkit-scrollbar-thumb { background-color: var(--p-surface-700); background-clip: content-box; }
.dark-mode ::-webkit-scrollbar-thumb:hover { background-color: var(--p-surface-600); background-clip: content-box; }

/* Selection */
::selection {
  background: var(--p-primary-100);
  color: var(--p-primary-900);
}
.dark-mode ::selection {
  background: color-mix(in srgb, var(--p-primary-400), transparent 60%);
  color: var(--p-primary-100);
}

/* Print: ép light theme cho hoá đơn */
@media print {
  body { background: #ffffff; color: #0f172a; }
  .no-print { display: none !important; }
}
</style>
