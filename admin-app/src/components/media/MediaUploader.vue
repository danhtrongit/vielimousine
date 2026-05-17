<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';
import ProgressBar from 'primevue/progressbar';
import { mediaApi } from '@/api/media.api';
import { useNotify } from '@/composables/useNotify';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
  defineProps<{ accept?: string; maxBytes?: number }>(),
  { accept: 'image/jpeg,image/png,image/webp,image/gif', maxBytes: 10 * 1024 * 1024 }
);

const emit = defineEmits<{
  (e: 'uploaded', items: MediaItem[]): void;
}>();

const notify = useNotify();
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);
const uploading = ref(false);

interface ProgressRow { name: string; percent: number; status: 'pending' | 'done' | 'error'; error?: string }
const queue = ref<ProgressRow[]>([]);

function openPicker() {
  fileInput.value?.click();
}

function onDrop(e: DragEvent) {
  e.preventDefault();
  dragOver.value = false;
  if (!e.dataTransfer?.files?.length) return;
  void uploadAll(Array.from(e.dataTransfer.files));
}

function onChange(e: Event) {
  const input = e.target as HTMLInputElement;
  if (!input.files?.length) return;
  void uploadAll(Array.from(input.files));
  input.value = '';
}

async function uploadAll(files: File[]) {
  const allowed = new Set(props.accept.split(',').map((s) => s.trim()));
  const valid: File[] = [];
  for (const f of files) {
    if (!allowed.has(f.type)) {
      notify.error(`Bỏ qua "${f.name}": định dạng không hỗ trợ.`);
      continue;
    }
    if (f.size > props.maxBytes) {
      notify.error(`Bỏ qua "${f.name}": vượt 10MB.`);
      continue;
    }
    valid.push(f);
  }
  if (valid.length === 0) return;

  uploading.value = true;
  queue.value = valid.map((f) => ({ name: f.name, percent: 0, status: 'pending' }));

  const results = await Promise.all(
    valid.map((file, idx) =>
      mediaApi
        .upload(file, (p) => {
          queue.value[idx].percent = p;
        })
        .then((resp) => {
          queue.value[idx].percent = 100;
          queue.value[idx].status = 'done';
          return resp.data;
        })
        .catch((err: unknown) => {
          queue.value[idx].status = 'error';
          const message =
            (err as { response?: { data?: { errors?: { message?: string }[] } } })?.response?.data?.errors?.[0]
              ?.message ?? 'Lỗi không xác định';
          queue.value[idx].error = message;
          notify.error(`Upload "${file.name}" lỗi: ${message}`);
          return null;
        })
    )
  );

  uploading.value = false;
  const uploaded = results.filter((r): r is MediaItem => r !== null);
  if (uploaded.length > 0) {
    notify.success(`Đã upload ${uploaded.length}/${valid.length} ảnh.`);
    emit('uploaded', uploaded);
  }
  // Clear queue sau 1.5s nếu tất cả done
  if (results.every((r) => r !== null)) {
    setTimeout(() => { queue.value = []; }, 1500);
  }
}
</script>

<template>
  <div
    class="dropzone"
    :class="{ over: dragOver, uploading }"
    @dragover.prevent="dragOver = true"
    @dragleave.prevent="dragOver = false"
    @drop="onDrop"
  >
    <i class="pi pi-cloud-upload icon" />
    <p class="hint">Kéo thả ảnh vào đây hoặc</p>
    <Button label="Chọn file" icon="pi pi-folder-open" outlined @click="openPicker" :disabled="uploading" />
    <p class="muted">jpg / png / webp / gif — tối đa 10MB / file</p>
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      multiple
      hidden
      @change="onChange"
    />

    <ul v-if="queue.length > 0" class="queue">
      <li v-for="(row, idx) in queue" :key="idx" :class="row.status">
        <span class="name">{{ row.name }}</span>
        <ProgressBar v-if="row.status === 'pending'" :value="row.percent" :show-value="false" style="height: 6px" />
        <span v-else-if="row.status === 'done'" class="badge done"><i class="pi pi-check" /> Xong</span>
        <span v-else class="badge err" :title="row.error"><i class="pi pi-times" /> Lỗi</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.dropzone {
  border: 2px dashed var(--p-surface-300);
  border-radius: 10px;
  padding: 1.5rem;
  text-align: center;
  background: var(--p-surface-0);
  transition: background 0.15s, border-color 0.15s;
}
.dropzone.over { border-color: var(--p-primary-color); background: var(--p-primary-50); }
.icon { font-size: 2rem; color: var(--p-text-muted-color); display: block; margin-bottom: 0.5rem; }
.hint { margin: 0 0 0.5rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.8rem; margin: 0.75rem 0 0; }
.queue { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: 0.5rem; text-align: left; }
.queue li { display: grid; grid-template-columns: 1fr 120px; align-items: center; gap: 0.75rem; font-size: 0.85rem; }
.queue .name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.badge { font-size: 0.75rem; font-weight: 500; }
.badge.done { color: var(--p-green-600); }
.badge.err  { color: var(--p-red-600); }
</style>
