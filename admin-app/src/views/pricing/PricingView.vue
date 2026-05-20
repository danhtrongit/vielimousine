<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import UnifiedMatrixView from './UnifiedMatrixView.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useUIStore } from '@/stores/ui.store';

const router = useRouter();
const ui = useUIStore();

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Bảng giá' }]);
});
</script>

<template>
  <div>
    <PageHeader title="Bảng giá" subtitle="Giá phòng theo ngày" icon="pi pi-dollar">
      <Button label="Cập nhật hàng loạt" icon="pi pi-bolt" severity="warn" @click="router.push('/pricing/bulk')" />
    </PageHeader>

    <UnifiedMatrixView />
  </div>
</template>
