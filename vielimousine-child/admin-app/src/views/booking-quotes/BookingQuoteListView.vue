<script setup lang="ts">
import { ref } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useRouter } from 'vue-router';
import DataTablePanel from '@/components/DataTablePanel.vue';
import FilterBar, { type FilterDef } from '@/components/FilterBar.vue';
import PageHeader from '@/components/PageHeader.vue';
import { bookingQuotesApi } from '@/api/bookingQuotes.api';
import { useNotify } from '@/composables/useNotify';
import { useAuthStore } from '@/stores/auth.store';
import { formatDate, formatVND } from '@/composables/useFormat';
import type { BookingQuote } from '@/types/bookingQuote';
import { effectiveStatusLabels, paymentStatusLabels } from './bookingQuoteForm';

const router = useRouter();
const confirm = useConfirm();
const notify = useNotify();
const auth = useAuthStore();
const refreshKey = ref(0);
const busyId = ref<number | null>(null);

const filterSchema: FilterDef[] = [
  { key: 'q', label: 'Mã / khách / dịch vụ', type: 'string', placeholder: 'Tìm báo giá…' },
  { key: 'status', label: 'Trạng thái', type: 'enum', options: [
    { label: 'Bản nháp', value: 'draft' },
    { label: 'Đã phát hành', value: 'published' },
    { label: 'Hết hạn', value: 'expired' },
    { label: 'Đã thu hồi', value: 'revoked' },
  ] },
];

function statusSeverity(status: string): 'success' | 'warn' | 'danger' | 'secondary' {
  if (status === 'published') return 'success';
  if (status === 'expired') return 'warn';
  if (status === 'revoked') return 'danger';
  return 'secondary';
}
function paymentSeverity(status: string): 'success' | 'info' | 'danger' | 'secondary' {
  if (status === 'paid') return 'success';
  if (status === 'deposit_paid') return 'info';
  if (status === 'review_required') return 'danger';
  return 'secondary';
}
function displayStatus(status: unknown): string {
  return typeof status === 'string' && status in effectiveStatusLabels
    ? effectiveStatusLabels[status as keyof typeof effectiveStatusLabels]
    : String(status ?? '—');
}
function displayPayment(status: unknown): string {
  return typeof status === 'string' && status in paymentStatusLabels
    ? paymentStatusLabels[status as keyof typeof paymentStatusLabels]
    : String(status ?? '—');
}

async function copyLink(item: BookingQuote) {
  if (!item.public_url) return;
  try {
    await navigator.clipboard.writeText(item.public_url);
    notify.success('Đã sao chép link báo giá', 'Chỉ gửi cho đúng khách vì bất kỳ ai có link đều có thể xem báo giá.');
  } catch {
    notify.warn('Không thể tự sao chép', item.public_url);
  }
}

async function duplicate(item: BookingQuote) {
  if (busyId.value !== null) return;
  busyId.value = item.id;
  try {
    const response = await bookingQuotesApi.duplicate(item.id);
    notify.success('Đã tạo bản nháp mới');
    await router.push(`/booking-quotes/${response.data.id}`);
  } catch (error) {
    notify.apiError(error, 'Không nhân bản được báo giá');
  } finally {
    busyId.value = null;
  }
}

function requestRevoke(item: BookingQuote) {
  if (busyId.value !== null) return;
  confirm.require({
    header: 'Thu hồi báo giá?',
    message: `${item.code}: khách sẽ không thể mở link hoặc thanh toán mới.`,
    rejectLabel: 'Giữ báo giá',
    acceptLabel: 'Thu hồi',
    acceptClass: 'p-button-danger',
    accept: async () => {
      busyId.value = item.id;
      try {
        await bookingQuotesApi.revoke(item.id);
        notify.success('Đã thu hồi báo giá');
        refreshKey.value += 1;
      } catch (error) {
        notify.apiError(error, 'Không thu hồi được báo giá');
      } finally {
        busyId.value = null;
      }
    },
  });
}
</script>

<template>
  <div>
    <PageHeader title="Báo giá" subtitle="Tạo, phát hành và theo dõi link báo giá gửi khách" icon="pi pi-file-edit">
      <Button v-if="auth.can('vie_create_booking_quotes')" label="Tạo báo giá" icon="pi pi-plus" @click="router.push('/booking-quotes/new')" />
    </PageHeader>

    <DataTablePanel :key="refreshKey" endpoint="/booking-quotes" :defaults="{ sort: 'created_at', order: 'desc' }" storage-key="booking-quotes.list.v1" table-style="min-width: 1080px">
      <template #filters="{ update }"><FilterBar :schema="filterSchema" @apply="update" /></template>
      <Column field="code" header="Báo giá" sortable>
        <template #body="{ data }">
          <RouterLink :to="`/booking-quotes/${data.id}`" class="link">{{ data.code || 'Chưa có mã' }}</RouterLink>
          <div class="subline">{{ data.customer_name || 'Chưa có khách' }}</div>
        </template>
      </Column>
      <Column field="title" header="Dịch vụ">
        <template #body="{ data }"><span class="truncate" :title="data.title">{{ data.title || '—' }}</span></template>
      </Column>
      <Column field="effective_status" header="Trạng thái">
        <template #body="{ data }"><Tag :value="displayStatus(data.effective_status)" :severity="statusSeverity(data.effective_status)" /></template>
      </Column>
      <Column field="payment_status" header="Thanh toán">
        <template #body="{ data }"><Tag :value="displayPayment(data.payment_status)" :severity="paymentSeverity(data.payment_status)" /></template>
      </Column>
      <Column field="total" header="Tổng" sortable><template #body="{ data }">{{ formatVND(data.total) }}</template></Column>
      <Column field="deposit_amount" header="Cọc" sortable><template #body="{ data }">{{ formatVND(data.deposit_amount) }}</template></Column>
      <Column field="valid_until" header="Hạn" sortable><template #body="{ data }">{{ formatDate(data.valid_until) }}</template></Column>
      <Column header="Thao tác" style="width: 180px">
        <template #body="{ data }">
          <div class="row-actions">
            <Button icon="pi pi-chevron-right" text rounded aria-label="Mở báo giá" v-tooltip.top="'Mở'" @click="router.push(`/booking-quotes/${data.id}`)" />
            <Button v-if="data.status === 'published' && data.public_url" icon="pi pi-copy" text rounded aria-label="Sao chép link" v-tooltip.top="'Sao chép link'" @click="copyLink(data)" />
            <Button v-if="auth.can('vie_create_booking_quotes')" icon="pi pi-clone" text rounded severity="secondary" aria-label="Nhân bản" v-tooltip.top="'Nhân bản để sửa'" :loading="busyId === data.id" :disabled="busyId !== null && busyId !== data.id" @click="duplicate(data)" />
            <Button v-if="auth.can('vie_create_booking_quotes') && data.status === 'published'" icon="pi pi-ban" text rounded severity="danger" aria-label="Thu hồi" v-tooltip.top="'Thu hồi'" :disabled="busyId !== null" @click="requestRevoke(data)" />
          </div>
        </template>
      </Column>
    </DataTablePanel>
  </div>
</template>

<style scoped>
.link { color: var(--p-primary-600); font-weight: 600; text-decoration: none; }
.link:hover { text-decoration: underline; }
.subline { margin-top: 3px; color: var(--p-text-muted-color); font-size: .78rem; }
.truncate { display: inline-block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
.row-actions { display: flex; align-items: center; gap: 2px; }
</style>
