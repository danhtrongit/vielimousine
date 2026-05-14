<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { useAuthStore } from '@/stores/auth.store';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types/envelope';

const username = ref('');
const password = ref('');
const loading = ref(false);
const errorMsg = ref<string | null>(null);

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

async function submit() {
  if (!username.value || !password.value) {
    errorMsg.value = 'Vui lòng nhập username và mật khẩu';
    return;
  }
  loading.value = true;
  errorMsg.value = null;
  try {
    await auth.login(username.value, password.value);
    const next = (route.query.next as string) || '/dashboard';
    router.push(next);
  } catch (e: unknown) {
    const ax = e as AxiosError<{ errors?: ApiError[] }>;
    const apiErr = ax?.response?.data?.errors?.[0];
    errorMsg.value = apiErr?.message ?? 'Đăng nhập thất bại';
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="login-form">
    <div class="field">
      <label>Tên đăng nhập / Email</label>
      <InputText
        v-model="username"
        autocomplete="username"
        autofocus
        :disabled="loading"
      />
    </div>
    <div class="field">
      <label>Mật khẩu</label>
      <Password
        v-model="password"
        :feedback="false"
        toggle-mask
        autocomplete="current-password"
        :disabled="loading"
        fluid
      />
    </div>

    <Message v-if="errorMsg" severity="error" :closable="false">{{ errorMsg }}</Message>

    <Button
      type="submit"
      label="Đăng nhập"
      :loading="loading"
      class="login-button"
    />
  </form>
</template>

<style scoped>
.login-form { display: flex; flex-direction: column; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; color: var(--p-text-color); }
.field :deep(.p-password) { width: 100%; }
.field :deep(.p-password-input) { width: 100%; }
.login-button { margin-top: 0.5rem; }
</style>
