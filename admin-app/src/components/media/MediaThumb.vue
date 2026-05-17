<script setup lang="ts">
import { computed } from 'vue';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
  defineProps<{
    item: MediaItem | null | undefined;
    size?: 'sm' | 'md' | 'lg';
    selected?: boolean;
    removable?: boolean;
    clickable?: boolean;
  }>(),
  { size: 'sm', selected: false, removable: false, clickable: false }
);

const emit = defineEmits<{
  (e: 'remove'): void;
  (e: 'click'): void;
}>();

const src = computed(() => {
  if (!props.item) return '';
  return props.item.sizes?.thumbnail?.url ?? props.item.sizes?.medium?.url ?? props.item.url;
});

const sizePx = computed(() => ({ sm: 96, md: 160, lg: 240 }[props.size] ?? 96));
</script>

<template>
  <div
    class="thumb"
    :class="{ selected, clickable, missing: !item }"
    :style="{ width: sizePx + 'px', height: sizePx + 'px' }"
    @click.stop="clickable && emit('click')"
  >
    <img v-if="item" :src="src" :alt="item.alt || item.title" loading="lazy" />
    <div v-else class="placeholder"><i class="pi pi-image" /></div>

    <div v-if="selected" class="check"><i class="pi pi-check" /></div>
    <button
      v-if="removable"
      type="button"
      class="remove"
      title="Bỏ chọn"
      @click.stop="emit('remove')"
    >
      <i class="pi pi-times" />
    </button>
  </div>
</template>

<style scoped>
.thumb {
  position: relative;
  border: 1px solid var(--p-surface-300);
  border-radius: 6px;
  overflow: hidden;
  background: var(--p-surface-100);
  box-sizing: border-box;
  flex-shrink: 0;
}
.thumb.clickable { cursor: pointer; }
.thumb.clickable:hover { border-color: var(--p-primary-color); }
.thumb.selected { border-color: var(--p-primary-color); box-shadow: 0 0 0 2px var(--p-primary-200); }
.thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.placeholder {
  width: 100%; height: 100%;
  display: grid; place-items: center;
  color: var(--p-text-muted-color);
  font-size: 1.5rem;
}
.check,
.remove {
  position: absolute;
  top: 4px;
  width: 24px;
  height: 24px;
  padding: 0;
  margin: 0;
  border: none;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  box-sizing: border-box;
  color: #fff;
}
.check {
  left: 4px;
  background: var(--p-primary-color);
  font-size: 0.8rem;
  pointer-events: none;
}
.remove {
  right: 4px;
  background: rgba(0, 0, 0, 0.7);
  cursor: pointer;
  font-size: 0.75rem;
  -webkit-appearance: none;
}
.remove i,
.check i { line-height: 1; display: block; }
.remove:hover { background: var(--p-red-500); }
</style>
