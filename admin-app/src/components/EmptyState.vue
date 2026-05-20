<script setup lang="ts">
interface Props {
  icon?: string;
  title?: string;
  description?: string;
  /** When inset, removes outer padding so it can sit inside a card. */
  inset?: boolean;
}
withDefaults(defineProps<Props>(), {
  icon: 'pi pi-inbox',
  title: 'Không có dữ liệu',
  inset: false,
});
</script>

<template>
  <div class="empty-state" :class="{ inset }">
    <div class="empty-icon"><i :class="icon" aria-hidden="true" /></div>
    <div class="empty-title">{{ title }}</div>
    <p v-if="description" class="empty-desc">{{ description }}</p>
    <div class="empty-actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: var(--space-9) var(--space-6);
  color: var(--p-text-muted-color);
  gap: var(--space-2);
}
.empty-state.inset { padding: var(--space-6) var(--space-4); }
.empty-icon {
  width: 56px;
  height: 56px;
  display: grid;
  place-items: center;
  border-radius: var(--radius-full);
  background: var(--app-tint-neutral);
  color: var(--app-on-tint-neutral);
  font-size: 1.5rem;
  margin-bottom: var(--space-2);
}
.empty-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-text-color);
}
.empty-desc {
  margin: 0;
  font-size: 0.875rem;
  max-width: 360px;
}
.empty-actions {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-3);
  flex-wrap: wrap;
  justify-content: center;
}
</style>
