<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import ProgressSpinner from 'primevue/progressspinner';
import { useConfirm } from 'primevue/useconfirm';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { decodeEntities } from '@/composables/useFormat';
import { usersApi } from '@/api/users.api';
import { settingsApi, type OrderSourceItem } from '@/api/settings.api';
import type { StaffRole, StaffUser, UpdateUserPayload } from '@/types/user';
import { loadOrderSources, ORDER_SOURCES, useLookupStore } from '@/stores/lookup.store';

const ui = useUIStore();
const notify = useNotify();
const confirm = useConfirm();
const lookup = useLookupStore();

const ROLE_OPTIONS: Array<{ label: string; value: StaffRole }> = [
  { label: 'Sales', value: 'vie_sales' },
  { label: 'Quản lý khách sạn', value: 'vie_hotel_manager' },
];
const STAFF_ROLE_VALUES: string[] = ROLE_OPTIONS.map((r) => r.value);

function labelRole(role: string): string {
  return ROLE_OPTIONS.find((r) => r.value === role)?.label ?? role;
}
function roleSeverity(role: string): string {
  return role === 'vie_sales' ? 'info' : 'warn';
}

// ===== Tab Nhân viên =====
const staffLoading = ref(false);
const staff = ref<StaffUser[]>([]);

async function loadStaff() {
  staffLoading.value = true;
  try {
    const resp = await usersApi.list();
    const staffOnly = (resp.data ?? []).filter((u) =>
      u.roles.some((r) => STAFF_ROLE_VALUES.includes(r)),
    );
    const rows = await Promise.all(
      staffOnly.map((u) =>
        usersApi.show(u.id).then(
          (r) => r.data,
          (): StaffUser => ({
            id: u.id,
            user_login: '',
            email: u.email,
            display_name: u.display_name,
            role: u.roles.find((r) => STAFF_ROLE_VALUES.includes(r)) ?? '',
            created: '',
          }),
        ),
      ),
    );
    staff.value = rows.map((u) => ({ ...u, display_name: decodeEntities(u.display_name) }));
  } catch (e) {
    notify.apiError(e);
  } finally {
    staffLoading.value = false;
  }
}

const staffDialog = ref(false);
const savingStaff = ref(false);
const editingId = ref<number | null>(null);

const blankStaffForm = () => ({
  display_name: '',
  user_login: '',
  email: '',
  role: 'vie_sales' as StaffRole,
  password: '',
});
const staffForm = ref(blankStaffForm());

function openCreateStaff() {
  editingId.value = null;
  staffForm.value = blankStaffForm();
  staffDialog.value = true;
}

function openEditStaff(row: StaffUser) {
  editingId.value = row.id;
  staffForm.value = {
    display_name: row.display_name,
    user_login: row.user_login,
    email: row.email,
    role: (STAFF_ROLE_VALUES.includes(row.role) ? row.role : 'vie_sales') as StaffRole,
    password: '',
  };
  staffDialog.value = true;
}

async function submitStaff() {
  const f = staffForm.value;
  if (!f.display_name.trim()) { notify.warn('Vui lòng nhập tên hiển thị'); return; }
  if (editingId.value === null && !f.user_login.trim()) { notify.warn('Vui lòng nhập username'); return; }
  if (!f.email.trim()) { notify.warn('Vui lòng nhập email'); return; }
  if (editingId.value === null && !f.password) { notify.warn('Vui lòng nhập mật khẩu'); return; }
  if (f.password && f.password.length < 8) { notify.warn('Mật khẩu tối thiểu 8 ký tự'); return; }

  savingStaff.value = true;
  try {
    if (editingId.value === null) {
      await usersApi.create({
        user_login: f.user_login.trim(),
        email: f.email.trim(),
        password: f.password,
        display_name: f.display_name.trim(),
        role: f.role,
      });
      notify.success('Đã tạo nhân viên');
    } else {
      const body: UpdateUserPayload = {
        email: f.email.trim(),
        display_name: f.display_name.trim(),
        role: f.role,
      };
      if (f.password) body.password = f.password;
      await usersApi.update(editingId.value, body);
      notify.success('Đã cập nhật nhân viên');
    }
    staffDialog.value = false;
    await Promise.all([loadStaff(), lookup.refresh()]);
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingStaff.value = false;
  }
}

function confirmDeleteStaff(row: StaffUser) {
  confirm.require({
    message: `Xóa tài khoản "${row.display_name}"?`,
    header: 'Xác nhận',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Xóa',
    rejectLabel: 'Hủy',
    accept: () => {
      void deleteStaff(row.id);
    },
  });
}

async function deleteStaff(id: number) {
  try {
    await usersApi.remove(id);
    notify.success('Đã xóa nhân viên');
    await Promise.all([loadStaff(), lookup.refresh()]);
  } catch (e) {
    notify.apiError(e);
  }
}

// ===== Tab Nguồn đơn =====
const sourcesLoading = ref(false);
const savingSources = ref(false);
const sources = ref<OrderSourceItem[]>([]);

async function loadSources() {
  sourcesLoading.value = true;
  try {
    const resp = await settingsApi.getOrderSources();
    sources.value = (resp.data?.sources ?? []).map((s) => ({ ...s }));
  } catch (e) {
    notify.apiError(e);
    sources.value = ORDER_SOURCES.map((s) => ({ ...s }));
  } finally {
    sourcesLoading.value = false;
  }
}

function addSource() {
  sources.value.push({ value: '', label: '' });
}
function removeSource(index: number) {
  sources.value.splice(index, 1);
}

async function saveSources() {
  const cleaned = sources.value.map((s) => ({ value: s.value.trim(), label: s.label.trim() }));
  if (cleaned.length === 0) { notify.warn('Cần ít nhất một nguồn đơn'); return; }
  if (cleaned.some((s) => !s.value)) { notify.warn('Giá trị (value) không được để trống'); return; }
  if (cleaned.some((s) => !s.label)) { notify.warn('Tên hiển thị không được để trống'); return; }
  const values = cleaned.map((s) => s.value);
  const dup = values.find((v, i) => values.indexOf(v) !== i);
  if (dup) { notify.warn(`Giá trị "${dup}" bị trùng lặp`); return; }

  savingSources.value = true;
  try {
    const resp = await settingsApi.updateOrderSources(cleaned);
    sources.value = (resp.data?.sources ?? cleaned).map((s) => ({ ...s }));
    await loadOrderSources(true);
    notify.success('Đã cập nhật nguồn đơn');
  } catch (e) {
    notify.apiError(e);
  } finally {
    savingSources.value = false;
  }
}

onMounted(async () => {
  ui.setBreadcrumb([{ label: 'Thiết lập' }]);
  await Promise.all([loadStaff(), loadSources()]);
});
</script>

<template>
  <div>
    <PageHeader title="Thiết lập" subtitle="Tài khoản nhân viên & nguồn đơn" icon="pi pi-sliders-h" />

    <Tabs value="staff">
      <TabList>
        <Tab value="staff">Nhân viên</Tab>
        <Tab value="sources">Nguồn đơn</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="staff">
          <div class="toolbar">
            <Button label="Thêm nhân viên" icon="pi pi-plus" @click="openCreateStaff" />
          </div>
          <DataTable :value="staff" :loading="staffLoading" data-key="id">
            <template #empty>Chưa có nhân viên nào.</template>
            <Column field="display_name" header="Tên hiển thị" />
            <Column header="Username">
              <template #body="{ data }">{{ data.user_login || '—' }}</template>
            </Column>
            <Column field="email" header="Email" />
            <Column header="Vai trò" style="width: 180px">
              <template #body="{ data }">
                <Tag :value="labelRole(data.role)" :severity="roleSeverity(data.role)" />
              </template>
            </Column>
            <Column header="" style="width: 100px">
              <template #body="{ data }">
                <Button icon="pi pi-pencil" text rounded @click="openEditStaff(data)" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDeleteStaff(data)" />
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <TabPanel value="sources">
          <p class="muted">
            Danh sách nguồn đơn dùng cho form tạo đơn và bộ lọc báo cáo.
            Giá trị (value) được lưu vào đơn hàng — không nên đổi value đã có đơn sử dụng.
          </p>
          <div v-if="sourcesLoading" class="loading">
            <ProgressSpinner style="width: 32px; height: 32px" />
          </div>
          <div v-else class="source-rows">
            <div class="source-row source-row-head">
              <span>Giá trị (value)</span>
              <span>Tên hiển thị</span>
              <span />
            </div>
            <div v-for="(s, i) in sources" :key="i" class="source-row">
              <InputText v-model="s.value" placeholder="VD: zalo" />
              <InputText v-model="s.label" placeholder="VD: Zalo" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="removeSource(i)" />
            </div>
            <div>
              <Button label="Thêm nguồn" icon="pi pi-plus" text @click="addSource" />
            </div>
          </div>
          <div class="actions">
            <Button label="Cập nhật" icon="pi pi-save" :loading="savingSources" @click="saveSources" />
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <Dialog
      v-model:visible="staffDialog"
      :header="editingId === null ? 'Thêm nhân viên' : 'Sửa nhân viên'"
      :modal="true"
      :style="{ width: '480px' }"
    >
      <div class="form-grid">
        <div class="field">
          <label>Tên hiển thị <span class="req">*</span></label>
          <InputText v-model="staffForm.display_name" placeholder="VD: Nguyễn Văn A" />
        </div>
        <div v-if="editingId === null" class="field">
          <label>Username <span class="req">*</span></label>
          <InputText v-model="staffForm.user_login" placeholder="VD: nguyenvana" />
        </div>
        <div v-else class="field">
          <label>Username</label>
          <InputText :model-value="staffForm.user_login || '—'" disabled />
        </div>
        <div class="field">
          <label>Email <span class="req">*</span></label>
          <InputText v-model="staffForm.email" placeholder="email@example.com" />
        </div>
        <div class="field">
          <label>Vai trò <span class="req">*</span></label>
          <Select
            v-model="staffForm.role"
            :options="ROLE_OPTIONS"
            option-label="label"
            option-value="value"
            placeholder="Chọn vai trò"
          />
        </div>
        <div class="field">
          <label>
            {{ editingId === null ? 'Mật khẩu' : 'Mật khẩu mới (bỏ trống nếu không đổi)' }}
            <span v-if="editingId === null" class="req">*</span>
          </label>
          <Password v-model="staffForm.password" toggle-mask :feedback="false" />
        </div>
      </div>
      <template #footer>
        <Button label="Hủy" severity="secondary" text @click="staffDialog = false" />
        <Button
          :label="editingId === null ? 'Tạo nhân viên' : 'Cập nhật'"
          icon="pi pi-check"
          :loading="savingStaff"
          @click="submitStaff"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 120px; }
.toolbar { display: flex; justify-content: flex-end; margin-bottom: 0.75rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin: 0 0 0.75rem; }
.source-rows { display: flex; flex-direction: column; gap: 0.5rem; max-width: 560px; }
.source-row { display: grid; grid-template-columns: 1fr 1fr 44px; gap: 0.5rem; align-items: center; }
.source-row-head { font-size: 0.8rem; font-weight: 600; color: var(--p-text-muted-color); }
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--p-surface-200); }
.form-grid { display: grid; grid-template-columns: 1fr; gap: 0.85rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; }
.field label { font-size: 0.82rem; font-weight: 500; }
.req { color: var(--p-red-500); }
.field :deep(.p-password) { width: 100%; }
.field :deep(.p-password-input) { width: 100%; }
</style>
