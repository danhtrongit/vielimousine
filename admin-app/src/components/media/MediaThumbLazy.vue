<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import MediaThumb from './MediaThumb.vue';
import { mediaApi } from '@/api/media.api';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
  defineProps<{
    id: number | null | undefined;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  { size: 'sm' }
);

// Module-level cache, shared across all instances
const cache = new Map<number, MediaItem>();
const inflight = new Map<number, Promise<MediaItem | null>>();

const item = ref<MediaItem | null>(null);

async function load(id: number) {
  if (cache.has(id)) {
    item.value = cache.get(id)!;
    return;
  }
  let p = inflight.get(id);
  if (!p) {
    p = mediaApi
      .get(id)
      .then((r) => {
        cache.set(id, r.data);
        return r.data;
      })
      .catch(() => null)
      .finally(() => inflight.delete(id));
    inflight.set(id, p);
  }
  const r = await p;
  if (r) item.value = r;
}

watch(
  () => props.id,
  (id) => {
    item.value = null;
    if (id && id > 0) void load(id);
  },
  { immediate: true }
);

const hasId = computed(() => !!props.id && props.id > 0);
</script>

<template>
  <MediaThumb v-if="hasId" :item="item" :size="size" />
  <span v-else class="empty-cell">—</span>
</template>

<style scoped>
.empty-cell { color: var(--p-text-muted-color); }
</style>
