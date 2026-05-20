<script setup lang="ts">
interface Props {
  title: string;
  subtitle?: string;
  icon?: string;
}
defineProps<Props>();
</script>

<template>
  <div class="page-header">
    <div class="page-header-left">
      <div v-if="icon" class="page-header-icon"><i :class="icon" aria-hidden="true" /></div>
      <div class="page-header-text">
        <h1 class="page-header-title">{{ title }}</h1>
        <p v-if="subtitle" class="page-header-subtitle">{{ subtitle }}</p>
      </div>
    </div>
    <div class="page-header-actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-5);
  flex-wrap: wrap;
}
.page-header-left {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
}
.page-header-icon {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: var(--radius-md);
  background: var(--app-tint-primary);
  color: var(--app-on-tint-primary);
  font-size: 1.05rem;
}
.page-header-text { min-width: 0; }
.page-header-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 600;
  line-height: 1.25;
  color: var(--p-text-color);
  letter-spacing: -0.01em;
}
.page-header-subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}
.page-header-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
  margin-left: auto;
}
@media (max-width: 640px) {
  .page-header-title { font-size: 1.25rem; }
}
</style>
