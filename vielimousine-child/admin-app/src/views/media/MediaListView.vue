<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import MediaGrid from '@/components/media/MediaGrid.vue';
import MediaUploader from '@/components/media/MediaUploader.vue';
import PageHeader from '@/components/PageHeader.vue';
import { mediaApi } from '@/api/media.api';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import type { MediaItem } from '@/types/media';

const ui = useUIStore();
const notify = useNotify();
const confirm = useConfirm();

const items = ref<MediaItem[]>([]);
const loading = ref(false);
const search = ref('');
const page = ref(1);
const totalPages = ref(1);

const uploadVisible = ref(false);
const detail = ref<MediaItem | null>(null);
const detailLoading = ref(false);
const savingDetail = ref(false);
const editForm = ref({ title: '', alt: '', caption: '' });

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Thư viện ảnh' }]);
  await loadPage(1, true);
});

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

async function openDetail(id: number) {
  detailLoading.value = true;
  try {
    const resp = await mediaApi.get(id);
    detail.value = resp.data;
    editForm.value = {
      title: resp.data.title,
      alt: resp.data.alt,
      caption: resp.data.caption,
    };
  } catch (e) {
    notify.apiError(e);
  } finally {
    detailLoading.value = false;
  }
}

function closeDetail() {
  detail.value = null;
}

async function saveDetail() {
  if (!detail.value) return;
  savingDetail.value = true;
  try {
    const resp = await mediaApi.update(detail.value.id, editForm.value);
    detail.value = { ...detail.value, ...resp.data };
    const idx = items.value.findIndex((i) => i.id === resp.data.id);
    if (idx >= 0) items.value[idx] = { ...items.value[idx], ...resp.data };
    notify.success('Đã lưu metadata');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingDetail.value = false;
  }
}

function confirmDelete() {
  if (!detail.value) return;
  const id = detail.value.id;
  confirm.require({
    message: `Xóa vĩnh viễn ảnh "${detail.value.title}"?`,
    header: 'Xác nhận',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Xóa',
    rejectLabel: 'Hủy',
    accept: () => {
      void doDelete(id);
    },
  });
}

async function doDelete(id: number) {
  try {
    await mediaApi.delete(id);
    items.value = items.value.filter((i) => i.id !== id);
    detail.value = null;
    notify.success('Đã xóa ảnh.');
  } catch (e) {
    notify.apiError(e);
  }
}

function onUploaded(uploaded: MediaItem[]) {
  items.value = [...uploaded, ...items.value];
  uploadVisible.value = false;
}

function copyUrl(url: string) {
  navigator.clipboard.writeText(url)
    .then(() => notify.success('Đã copy URL.'))
    .catch(() => notify.error('Không copy được URL.'));
}

function formatBytes(n: number): string {
  if (n <= 0) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let v = n;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
  return `${v.toFixed(v >= 10 ? 0 : 1)} ${units[i]}`;
}
</script>

<template>
  <div>
    <PageHeader title="Thư viện ảnh" subtitle="Quản lý media" icon="pi pi-images">
      <Button label="Tải lên" icon="pi pi-upload" @click="uploadVisible = true" />
    </PageHeader>

    <div class="toolbar">
      <InputText v-model="search" placeholder="Tìm theo tên file…" class="search-input" />
      <span class="muted">{{ items.length }} ảnh</span>
    </div>

    <MediaGrid :items="items" :loading="loading" @open="openDetail" />

    <div v-if="page < totalPages" class="load-more">
      <Button label="Tải thêm" icon="pi pi-angle-down" outlined :loading="loading" @click="loadPage(page + 1)" />
    </div>

    <Dialog
      :visible="uploadVisible"
      modal
      header="Tải lên ảnh mới"
      :style="{ width: '640px', maxWidth: '95vw' }"
      @update:visible="(v) => (uploadVisible = v)"
    >
      <MediaUploader @uploaded="onUploaded" />
    </Dialog>

    <Dialog
      :visible="!!detail"
      modal
      :header="detail?.title || 'Chi tiết ảnh'"
      :style="{ width: '720px', maxWidth: '95vw' }"
      @update:visible="closeDetail"
    >
      <div v-if="detailLoading" class="loading-detail"><ProgressSpinner /></div>
      <div v-else-if="detail" class="detail">
        <div class="preview">
          <img :src="detail.sizes.large?.url ?? detail.sizes.medium?.url ?? detail.url" :alt="detail.alt" />
        </div>
        <div class="meta-list">
          <div><strong>MIME:</strong> {{ detail.mime }}</div>
          <div><strong>Kích thước:</strong> {{ detail.width }} × {{ detail.height }} px</div>
          <div><strong>Dung lượng:</strong> {{ formatBytes(detail.filesize) }}</div>
          <div class="url-row">
            <strong>URL:</strong>
            <a :href="detail.url" target="_blank" rel="noopener">{{ detail.url }}</a>
            <Button icon="pi pi-copy" text rounded size="small" @click="copyUrl(detail.url)" />
          </div>
        </div>

        <div class="form">
          <div class="field">
            <label>Tiêu đề</label>
            <InputText v-model="editForm.title" />
          </div>
          <div class="field">
            <label>Alt text (SEO + accessibility)</label>
            <InputText v-model="editForm.alt" />
          </div>
          <div class="field">
            <label>Chú thích</label>
            <Textarea v-model="editForm.caption" rows="2" auto-resize />
          </div>
        </div>

        <div v-if="(detail.used_in?.length ?? 0) > 0" class="used-in">
          <strong>Đang dùng ở:</strong>
          <ul>
            <li v-for="(u, i) in detail.used_in" :key="i">
              {{ u.type === 'hotel' ? 'Khách sạn' : 'Phòng' }} #{{ u.id }} — {{ u.name }}
            </li>
          </ul>
        </div>
      </div>

      <template #footer>
        <Button
          label="Xóa"
          icon="pi pi-trash"
          severity="danger"
          text
          :disabled="!detail || (detail.used_in?.length ?? 0) > 0"
          @click="confirmDelete"
        />
        <span style="flex: 1" />
        <Button label="Đóng" text @click="closeDetail" />
        <Button label="Lưu" icon="pi pi-save" :loading="savingDetail" @click="saveDetail" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.search-input { flex: 1; max-width: 360px; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
.load-more { display: flex; justify-content: center; margin-top: 1.25rem; }
.loading-detail { display: grid; place-items: center; min-height: 200px; }
.detail { display: grid; gap: 1rem; }
.preview {
  background: var(--p-surface-100);
  border-radius: 8px;
  padding: 0.5rem;
  display: grid;
  place-items: center;
}
.preview img { max-width: 100%; max-height: 320px; object-fit: contain; display: block; }
.meta-list { display: grid; gap: 0.35rem; font-size: 0.85rem; color: var(--p-text-color); }
.url-row { display: flex; align-items: center; gap: 0.5rem; overflow: hidden; }
.url-row a { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
.form { display: grid; gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.used-in { padding: 0.75rem; background: var(--p-surface-100); border-radius: 6px; font-size: 0.85rem; }
.used-in ul { margin: 0.35rem 0 0 1rem; padding: 0; }
</style>
