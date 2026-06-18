<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Card from 'primevue/card';
import Message from 'primevue/message';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { backupApi, type BackupTable } from '@/api/backup.api';

const ui = useUIStore();
const notify = useNotify();

const tables = ref<BackupTable[]>([]);
const selected = ref<string[]>([]);
const loading = ref(true);
const exporting = ref(false);

const restoreSql = ref('');
const restoreFileName = ref('');
const confirmText = ref('');
const restoring = ref(false);
const canRestore = computed(() => confirmText.value === 'RESTORE' && restoreSql.value.trim() !== '');

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Sao lưu & Phục hồi' }]);
  try {
    const resp = await backupApi.tables();
    tables.value = resp.data;
    // mặc định chọn tất cả TRỪ vie_token (chứa refresh token)
    selected.value = resp.data.map((t) => t.name).filter((n) => !n.endsWith('vie_token'));
  } catch (e) {
    notify.apiError(e, 'Không tải được danh sách bảng');
  } finally {
    loading.value = false;
  }
});

function downloadFile(filename: string, content: string): void {
  const blob = new Blob([content], { type: 'application/sql;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function doBackup(): Promise<void> {
  if (selected.value.length === 0) { notify.apiError(null, 'Chọn ít nhất 1 bảng'); return; }
  exporting.value = true;
  try {
    const resp = await backupApi.export(selected.value);
    downloadFile(resp.data.filename, resp.data.sql);
    notify.success('Đã tạo backup', `${resp.data.tables.length} bảng · ${(resp.data.bytes / 1024 / 1024).toFixed(2)} MB`);
  } catch (e) {
    notify.apiError(e, 'Backup thất bại');
  } finally {
    exporting.value = false;
  }
}

function onFile(e: Event): void {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  restoreFileName.value = file.name;
  const reader = new FileReader();
  reader.onload = () => { restoreSql.value = String(reader.result ?? ''); };
  reader.readAsText(file);
}

async function doRestore(): Promise<void> {
  if (!canRestore.value) return;
  if (!window.confirm('Phục hồi sẽ GHI ĐÈ dữ liệu các bảng trong file. Tiếp tục?')) return;
  restoring.value = true;
  try {
    const resp = await backupApi.restore(restoreSql.value, confirmText.value);
    const errs = resp.data.errors ?? [];
    if (errs.length > 0) {
      notify.apiError(null, `Phục hồi có lỗi (đã có snapshot ${resp.data.snapshot_file}): ${errs.join('; ')}`);
    } else {
      notify.success('Đã phục hồi', `${resp.data.tables_restored.length} bảng · snapshot: ${resp.data.snapshot_file}`);
    }
    confirmText.value = '';
  } catch (e) {
    notify.apiError(e, 'Phục hồi thất bại');
  } finally {
    restoring.value = false;
  }
}
</script>

<template>
  <div>
    <PageHeader title="Sao lưu & Phục hồi" subtitle="Xuất/nhập dữ liệu vie_*" icon="pi pi-database" />

    <Card class="mb">
      <template #title>Sao lưu</template>
      <template #content>
        <DataTable :value="tables" :loading="loading" dataKey="name" class="mb">
          <Column header="" style="width:3rem">
            <template #body="{ data }">
              <Checkbox v-model="selected" :value="data.name" :inputId="data.name" />
            </template>
          </Column>
          <Column field="name" header="Bảng" />
          <Column field="rows" header="Số dòng" />
          <Column field="size_mb" header="Dung lượng (MB)" />
        </DataTable>
        <Button label="Sao lưu (tải .sql)" icon="pi pi-download" :loading="exporting" @click="doBackup" :disabled="selected.length === 0" />
      </template>
    </Card>

    <Card>
      <template #title>Phục hồi</template>
      <template #content>
        <Message severity="warn" :closable="false">Phục hồi sẽ GHI ĐÈ dữ liệu các bảng có trong file. Hệ thống tự lưu snapshot trước khi ghi đè.</Message>
        <div class="field">
          <label>Chọn file .sql</label>
          <input type="file" accept=".sql" @change="onFile" />
          <span v-if="restoreFileName" class="muted">{{ restoreFileName }}</span>
        </div>
        <div class="field">
          <label>Gõ <strong>RESTORE</strong> để xác nhận</label>
          <InputText v-model="confirmText" placeholder="RESTORE" />
        </div>
        <Button label="Phục hồi" icon="pi pi-upload" severity="danger" :loading="restoring" :disabled="!canRestore" @click="doRestore" />
      </template>
    </Card>
  </div>
</template>

<style scoped>
.mb { margin-bottom: var(--space-5); }
.field { margin: var(--space-4) 0; display: flex; flex-direction: column; gap: var(--space-2); }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; }
</style>
