<script setup lang="ts">
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Button from 'primevue/button';
import { useUIStore } from '@/stores/ui.store';

const route = useRoute();
const router = useRouter();
const ui = useUIStore();

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Trang không tồn tại' }]);
});
</script>

<template>
  <div class="err-wrap">
    <div class="err-card">
      <div class="code">404</div>
      <h1>Trang không tồn tại</h1>
      <p class="muted">
        Đường dẫn <code>{{ route.fullPath }}</code> không khớp với bất kỳ trang nào.
        Có thể link đã đổi hoặc trang đã bị xóa.
      </p>
      <div class="actions">
        <Button label="Quay lại Tổng quan" icon="pi pi-home" @click="router.push('/dashboard')" />
        <Button label="Trang trước" icon="pi pi-arrow-left" text @click="router.back()" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.err-wrap { display: grid; place-items: center; min-height: 60vh; padding: 2rem; }
.err-card {
  max-width: 480px;
  text-align: center;
  background: var(--p-surface-0);
  border: 1px solid var(--p-surface-200);
  border-radius: 12px;
  padding: 2.5rem 2rem;
}
.code {
  font-size: 4.5rem;
  font-weight: 700;
  line-height: 1;
  color: var(--p-primary-color);
  letter-spacing: -2px;
  margin-bottom: 0.5rem;
}
h1 { margin: 0 0 0.75rem; font-size: 1.25rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.9rem; margin: 0 0 1.5rem; line-height: 1.5; }
.muted code { background: var(--p-surface-100); padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
.actions { display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
</style>
