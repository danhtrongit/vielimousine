<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import Button from 'primevue/button';

const route = useRoute();
const retrying = ref(false);

const reason = ref<string>('');

onMounted(() => {
  const q = route.query.reason;
  if (typeof q === 'string') reason.value = q;
});

async function retry() {
  retrying.value = true;
  // Thử ping /health trước khi reload toàn trang
  try {
    const r = await fetch('/wp-json/vie/v1/health', { cache: 'no-store' });
    if (r.ok) {
      location.href = '/vie-admin/dashboard';
      return;
    }
  } catch { /* still down */ }
  retrying.value = false;
}

function reload() {
  location.reload();
}
</script>

<template>
  <div class="err-wrap">
    <div class="err-card">
      <div class="icon-circle"><i class="pi pi-server" /></div>
      <div class="code">503</div>
      <h1>Hệ thống tạm thời không phản hồi</h1>
      <p class="muted">
        Máy chủ đang bảo trì hoặc gặp sự cố. Vui lòng thử lại sau ít phút.
      </p>
      <p v-if="reason" class="reason"><strong>Chi tiết:</strong> {{ reason }}</p>
      <div class="actions">
        <Button label="Thử lại" icon="pi pi-refresh" :loading="retrying" @click="retry" />
        <Button label="Tải lại trang" icon="pi pi-replay" text @click="reload" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.err-wrap {
  display: grid;
  place-items: center;
  min-height: 100vh;
  padding: 2rem;
  background: var(--p-surface-50);
}
.err-card {
  max-width: 520px;
  text-align: center;
  background: var(--p-surface-0);
  border: 1px solid var(--p-surface-200);
  border-radius: 12px;
  padding: 2.5rem 2rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.icon-circle {
  width: 64px; height: 64px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  background: var(--p-orange-100, #fed7aa);
  color: var(--p-orange-600, #ea580c);
  display: grid; place-items: center;
  font-size: 1.5rem;
}
.code {
  font-size: 3rem;
  font-weight: 700;
  line-height: 1;
  color: var(--p-orange-600, #ea580c);
  margin-bottom: 0.5rem;
}
h1 { margin: 0 0 0.75rem; font-size: 1.25rem; }
.muted { color: var(--p-text-muted-color); font-size: 0.9rem; margin: 0 0 1rem; line-height: 1.5; }
.reason {
  background: var(--p-surface-100);
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8rem;
  margin: 0 0 1.25rem;
  text-align: left;
  word-break: break-word;
}
.actions { display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
</style>
