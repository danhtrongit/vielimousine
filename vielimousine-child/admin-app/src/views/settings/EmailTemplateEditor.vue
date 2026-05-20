<script setup lang="ts">
import { ref } from 'vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import { useNotify } from '@/composables/useNotify';
import { settingsApi, type EmailTemplateConfig } from '@/api/settings.api';

const props = defineProps<{
  templateKey: string;
  label: string;
  placeholders: string[];
  modelValue: EmailTemplateConfig;
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: EmailTemplateConfig): void;
}>();

const notify = useNotify();
const testing = ref(false);
const testTo = ref('');

function update<K extends keyof EmailTemplateConfig>(key: K, value: EmailTemplateConfig[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value });
}

async function sendTest() {
  testing.value = true;
  try {
    const resp = await settingsApi.testEmail(props.templateKey, testTo.value || undefined);
    notify.success('Đã gửi email thử', `→ ${resp.data.to}`);
  } catch (e) {
    notify.apiError(e);
  } finally {
    testing.value = false;
  }
}
</script>

<template>
  <div class="tpl-editor">
    <div class="tpl-header">
      <div class="tpl-label">{{ label }} <span class="tpl-key">({{ templateKey }})</span></div>
      <div class="tpl-toggle">
        <span class="muted">Bật:</span>
        <ToggleSwitch :model-value="modelValue.enabled" @update:model-value="(v: any) => update('enabled', !!v)" />
      </div>
    </div>

    <div class="tpl-body">
      <div class="field">
        <label>Tiêu đề email</label>
        <InputText :model-value="modelValue.subject" @update:model-value="(v: any) => update('subject', v ?? '')" placeholder="Để trống = dùng mặc định" />
      </div>
      <div class="field">
        <label>Nội dung email (HTML, để trống = dùng template file mặc định)</label>
        <Textarea :model-value="modelValue.body" @update:model-value="(v: any) => update('body', v ?? '')" rows="6" auto-resize />
        <div class="muted">
          Placeholder hỗ trợ:
          <code v-for="p in placeholders" :key="p">{{ '{' + p + '}' }}</code>
        </div>
      </div>

      <div class="tpl-test">
        <InputText v-model="testTo" placeholder="Gửi test tới email... (mặc định admin)" />
        <Button :label="testing ? 'Đang gửi...' : 'Gửi thử'" icon="pi pi-send" outlined :loading="testing" @click="sendTest" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.tpl-editor { border: 1px solid var(--p-surface-200); border-radius: 8px; padding: 1rem; background: #fff; margin-bottom: 0.75rem; }
.tpl-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.tpl-label { font-weight: 600; }
.tpl-key { color: var(--p-text-muted-color); font-size: 0.85rem; font-weight: 400; }
.tpl-toggle { display: flex; gap: 0.5rem; align-items: center; }
.tpl-body { display: flex; flex-direction: column; gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field label { font-size: 0.85rem; font-weight: 500; }
.muted { font-size: 0.8rem; color: var(--p-text-muted-color); }
.muted code { background: var(--p-surface-100); padding: 1px 6px; border-radius: 3px; font-family: monospace; margin-right: 4px; }
.tpl-test { display: flex; gap: 0.5rem; align-items: center; }
.tpl-test .p-inputtext { flex: 1; }
</style>
