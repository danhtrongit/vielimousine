<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Card from 'primevue/card';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressSpinner from 'primevue/progressspinner';
import { hotelsApi, roomsApi } from '@/api/hotels.api';
import { useUIStore } from '@/stores/ui.store';
import { useNotify } from '@/composables/useNotify';
import { formatVND } from '@/composables/useFormat';
import type { Hotel, Room, CancellationPolicy } from '@/types/hotel';

const route = useRoute();
const ui = useUIStore();
const notify = useNotify();

const hotel = ref<Hotel | null>(null);
const rooms = ref<Room[]>([]);
const loading = ref(true);
const saving = ref(false);

const id = computed(() => Number(route.params.id));

// Editable cancellation rules
const cancelRules = ref<CancellationPolicy['rules']>([]);

async function load() {
  loading.value = true;
  try {
    const [hResp, rResp] = await Promise.all([
      hotelsApi.get(id.value),
      roomsApi.list({ hotel_id: id.value, per_page: 100 }),
    ]);
    hotel.value = hResp.data;
    rooms.value = rResp.data;
    cancelRules.value = hotel.value.cancellation_policy?.rules ?? [];
    ui.setBreadcrumb([
      { label: 'Khách sạn', to: '/hotels' },
      { label: hotel.value.name },
    ]);
  } catch (e) {
    notify.apiError(e);
  } finally {
    loading.value = false;
  }
}

onMounted(load);

async function saveInfo() {
  if (!hotel.value) return;
  saving.value = true;
  try {
    await hotelsApi.update(id.value, {
      name: hotel.value.name,
      address: hotel.value.address,
      city: hotel.value.city,
      contact_phone: hotel.value.contact_phone,
      contact_email: hotel.value.contact_email,
      star_rating: hotel.value.star_rating,
      default_ticket_price: hotel.value.default_ticket_price,
      ticket_free_children_count: hotel.value.ticket_free_children_count,
      ticket_free_children_max_age: hotel.value.ticket_free_children_max_age,
    });
    notify.success('Đã lưu thông tin');
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

async function savePolicy() {
  if (!hotel.value) return;
  saving.value = true;
  try {
    const sorted = [...cancelRules.value].sort((a, b) => b.hours_before_checkin - a.hours_before_checkin);
    await hotelsApi.update(id.value, {
      cancellation_policy: {
        rules: sorted,
        refund_method: hotel.value.cancellation_policy?.refund_method ?? '',
        notes: hotel.value.cancellation_policy?.notes ?? '',
      },
    });
    notify.success('Đã lưu chính sách hủy');
    await load();
  } catch (e) {
    notify.apiError(e);
  } finally {
    saving.value = false;
  }
}

function addRule() {
  cancelRules.value.push({ hours_before_checkin: 0, penalty_percent: 100, description: '' });
}
function removeRule(idx: number) {
  cancelRules.value.splice(idx, 1);
}
</script>

<template>
  <div v-if="loading" class="loading"><ProgressSpinner /></div>
  <div v-else-if="hotel">
    <h1 class="page-title">{{ hotel.name }}</h1>

    <TabView>
      <TabPanel header="Thông tin" value="info">
        <Card>
          <template #content>
            <div class="grid-2">
              <div class="field">
                <label>Tên</label>
                <InputText v-model="hotel.name" />
              </div>
              <div class="field">
                <label>Thành phố</label>
                <InputText v-model="hotel.city" />
              </div>
              <div class="field grid-span-2">
                <label>Địa chỉ</label>
                <InputText v-model="hotel.address" />
              </div>
              <div class="field">
                <label>SĐT</label>
                <InputText v-model="hotel.contact_phone" />
              </div>
              <div class="field">
                <label>Email</label>
                <InputText v-model="hotel.contact_email" />
              </div>
              <div class="field">
                <label>Hạng sao (1–5)</label>
                <InputNumber v-model="hotel.star_rating" :min="1" :max="5" show-buttons />
              </div>
              <div class="field">
                <label>Giá vé mặc định (VND)</label>
                <InputNumber v-model="hotel.default_ticket_price" :min="0" />
              </div>
              <div class="field">
                <label>Số bé miễn vé / booking</label>
                <InputNumber v-model="hotel.ticket_free_children_count" :min="0" show-buttons />
              </div>
              <div class="field">
                <label>Tuổi tối đa bé miễn vé</label>
                <InputNumber v-model="hotel.ticket_free_children_max_age" :min="0" :max="17" show-buttons />
              </div>
            </div>
          </template>
        </Card>
        <div class="actions-row">
          <Button label="Lưu thông tin" icon="pi pi-save" :loading="saving" @click="saveInfo" />
        </div>
      </TabPanel>

      <TabPanel header="Phòng" value="rooms">
        <DataTable :value="rooms" data-key="id" :empty-message="'Chưa có phòng'">
          <Column field="name" header="Tên" />
          <Column field="included_adults" header="Người lớn gồm" />
          <Column field="max_adults" header="Người lớn tối đa" />
          <Column field="max_children" header="Trẻ em tối đa" />
          <Column field="base_price" header="Giá phòng">
            <template #body="{ data }">{{ formatVND(data.base_price) }}</template>
          </Column>
          <Column field="extra_adult_price" header="Phụ thu người lớn">
            <template #body="{ data }">{{ formatVND(data.extra_adult_price) }}</template>
          </Column>
          <Column field="free_children_count" header="Bé miễn / phòng" />
          <Column field="is_active" header="Trạng thái">
            <template #body="{ data }">{{ data.is_active ? '✓' : '✗' }}</template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel header="Chính sách hủy" value="policy">
        <Card>
          <template #content>
            <p class="muted">Mỗi rule áp dụng khi delta_hours ≥ hours_before_checkin. Sắp xếp tự động giảm dần.</p>
            <DataTable :value="cancelRules" data-key="hours_before_checkin">
              <Column header="Số giờ trước checkin">
                <template #body="{ index }">
                  <InputNumber v-model="cancelRules[index].hours_before_checkin" :min="0" show-buttons />
                </template>
              </Column>
              <Column header="Phạt (%)">
                <template #body="{ index }">
                  <InputNumber v-model="cancelRules[index].penalty_percent" :min="0" :max="100" show-buttons suffix="%" />
                </template>
              </Column>
              <Column header="Mô tả">
                <template #body="{ index }">
                  <InputText v-model="cancelRules[index].description" />
                </template>
              </Column>
              <Column header="" style="width: 60px">
                <template #body="{ index }">
                  <Button icon="pi pi-trash" severity="danger" text rounded @click="removeRule(index)" />
                </template>
              </Column>
            </DataTable>
            <Button label="Thêm rule" icon="pi pi-plus" outlined class="add-rule-btn" @click="addRule" />

            <div class="field">
              <label>Phương thức hoàn tiền</label>
              <InputText v-model="hotel.cancellation_policy!.refund_method" />
            </div>
            <div class="field">
              <label>Ghi chú</label>
              <Textarea v-model="hotel.cancellation_policy!.notes" rows="3" />
            </div>
          </template>
        </Card>
        <div class="actions-row">
          <Button label="Lưu chính sách" icon="pi pi-save" :loading="saving" @click="savePolicy" />
        </div>
      </TabPanel>
    </TabView>
  </div>
</template>

<style scoped>
.loading { display: grid; place-items: center; min-height: 60vh; }
.page-title { margin: 0 0 1.25rem; font-size: 1.5rem; font-weight: 600; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.75rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.actions-row { display: flex; justify-content: flex-end; margin-top: 1rem; }
.add-rule-btn { margin-top: 0.75rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.85rem; margin-bottom: 0.75rem; }
</style>
