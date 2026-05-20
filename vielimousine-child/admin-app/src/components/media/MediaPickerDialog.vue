<script setup lang="ts">
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import MediaGrid from './MediaGrid.vue';
import MediaUploader from './MediaUploader.vue';
import { mediaApi } from '@/api/media.api';
import { useNotify } from '@/composables/useNotify';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
  defineProps<{
    visible: boolean;
    multiple?: boolean;
    initial?: number[];
  }>(),
  { multiple: false, initial: () => [] }
);

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void;
  (e: 'select', ids: number[]): void;
}>();

const notify = useNotify();

const activeTab = ref<'library' | 'upload'>('library');
const search = ref('');
const items = ref<MediaItem[]>([]);
const selected = ref<number[]>([]);
const loading = ref(false);
const page = ref(1);
const totalPages = ref(1);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
  () => props.visible,
  async (v) => {
    if (v) {
      selected.value = [...props.initial];
      activeTab.value = 'library';
      page.value = 1;
      search.value = '';
      await loadPage(1, true);
    }
  }
);

watch(search, () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    page.value = 1;
    void loadPage(1, true);
  }, 300);
});

async function loadPage(p: number, replace = false) {
  loading.value = true;
  try {
    const resp = await mediaApi.list({ page: p, per_page: 24, q: search.value || undefined });
    if (replace) items.value = resp.data;
    else items.value = [...items.value, ...resp.data];
    page.value = p;
    totalPages.value = resp.meta.pagination?.total_pages ?? 1;
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

function onSelectedUpdate(ids: number[]) {
  if (!props.multiple) selected.value = ids.slice(0, 1);
  else selected.value = ids;
}

function onUploaded(uploaded: MediaItem[]) {
  // Prepend uploaded items + auto-select + switch về Library
  items.value = [...uploaded, ...items.value];
  const uploadedIds = uploaded.map((i) => i.id);
  if (props.multiple) {
    selected.value = Array.from(new Set([...selected.value, ...uploadedIds]));
  } else {
    selected.value = uploadedIds.slice(-1);
  }
  activeTab.value = 'library';
}

function close() {
  emit('update:visible', false);
}

function confirm() {
  emit('select', [...selected.value]);
  close();
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="multiple ? 'Chọn ảnh (nhiều)' : 'Chọn ảnh'"
    :style="{ width: '960px', maxWidth: '95vw' }"
    :dismissable-mask="true"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="library">Thư viện</Tab>
        <Tab value="upload">Tải lên</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="library">
          <div class="search-row">
            <InputText v-model="search" placeholder="Tìm theo tên file…" class="search-input" />
            <span class="muted">{{ items.length }} ảnh</span>
          </div>
          <MediaGrid
            :items="items"
            :loading="loading"
            :selectable="multiple ? 'multi' : 'single'"
            :selected="selected"
            @update:selected="onSelectedUpdate"
          />
          <div v-if="page < totalPages" class="load-more">
            <Button label="Tải thêm" icon="pi pi-angle-down" outlined :loading="loading" @click="loadPage(page + 1)" />
          </div>
        </TabPanel>

        <TabPanel value="upload">
          <MediaUploader @uploaded="onUploaded" />
        </TabPanel>
      </TabPanels>
    </Tabs>

    <template #footer>
      <Button label="Hủy" text @click="close" />
      <Button
        :label="`Chọn (${selected.length})`"
        icon="pi pi-check"
        :disabled="selected.length === 0"
        @click="confirm"
      />
    </template>
  </Dialog>
</template>

<style scoped>
.search-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}
.search-input { flex: 1; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
.load-more { display: flex; justify-content: center; margin-top: 1rem; }
</style>
