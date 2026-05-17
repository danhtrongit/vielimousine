<script setup lang="ts">
import { computed } from 'vue';
import MediaThumb from './MediaThumb.vue';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
  defineProps<{
    items: MediaItem[];
    loading?: boolean;
    selectable?: 'none' | 'single' | 'multi';
    selected?: number[];
  }>(),
  { loading: false, selectable: 'none', selected: () => [] }
);

const emit = defineEmits<{
  (e: 'update:selected', ids: number[]): void;
  (e: 'open', id: number): void;
}>();

const selectedSet = computed(() => new Set(props.selected));

function onClick(item: MediaItem) {
  if (props.selectable === 'none') {
    emit('open', item.id);
    return;
  }
  if (props.selectable === 'single') {
    emit('update:selected', selectedSet.value.has(item.id) ? [] : [item.id]);
    return;
  }
  // multi
  const set = new Set(selectedSet.value);
  if (set.has(item.id)) set.delete(item.id);
  else set.add(item.id);
  emit('update:selected', Array.from(set));
}
</script>

<template>
  <div class="grid">
    <div v-if="loading && items.length === 0" class="empty">Đang tải…</div>
    <div v-else-if="items.length === 0" class="empty">Chưa có ảnh nào.</div>
    <MediaThumb
      v-for="item in items"
      :key="item.id"
      :item="item"
      size="md"
      clickable
      :selected="selectedSet.has(item.id)"
      @click="onClick(item)"
    />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
  align-items: start;
}
.grid > .thumb { width: 100% !important; aspect-ratio: 1 / 1; height: auto !important; }
.empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 2rem;
  color: var(--p-text-muted-color);
}
</style>
