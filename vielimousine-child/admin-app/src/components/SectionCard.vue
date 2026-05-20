<script setup lang="ts">
interface Props {
  title?: string;
  subtitle?: string;
  icon?: string;
  /** Removes inner body padding (useful when wrapping a DataTable). */
  flush?: boolean;
}
withDefaults(defineProps<Props>(), { flush: false });
</script>

<template>
  <section class="section-card">
    <header v-if="title || $slots.actions || $slots.header" class="section-head">
      <div class="section-head-left">
        <span v-if="icon" class="section-icon"><i :class="icon" aria-hidden="true" /></span>
        <div class="section-head-text">
          <h2 v-if="title" class="section-title">{{ title }}</h2>
          <p v-if="subtitle" class="section-subtitle">{{ subtitle }}</p>
        </div>
        <slot name="header" />
      </div>
      <div class="section-actions">
        <slot name="actions" />
      </div>
    </header>
    <div class="section-body" :class="{ flush }">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="section-foot">
      <slot name="footer" />
    </footer>
  </section>
</template>

<style scoped>
.section-card {
  background: var(--app-card-bg);
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xs);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: background-color var(--motion-base) var(--ease-out);
}
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) var(--space-5);
  border-bottom: 1px solid var(--app-card-border);
  gap: var(--space-3);
  flex-wrap: wrap;
}
.section-head-left { display: flex; align-items: center; gap: var(--space-3); min-width: 0; flex: 1; }
.section-icon {
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border-radius: var(--radius-md);
  background: var(--app-tint-primary);
  color: var(--app-on-tint-primary);
  font-size: 0.9rem;
  flex-shrink: 0;
}
.section-head-text { min-width: 0; }
.section-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--p-text-color);
  letter-spacing: -0.005em;
}
.section-subtitle {
  margin: 0;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}
.section-actions { display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.section-body { padding: var(--space-5); flex: 1; }
.section-body.flush { padding: 0; }
.section-foot {
  padding: var(--space-3) var(--space-5);
  border-top: 1px solid var(--app-card-border);
}
</style>
