<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth.store';

const props = defineProps<{ cap?: string; capAny?: string[] }>();
const auth = useAuthStore();

const allowed = computed(() => {
  if (props.cap) return auth.can(props.cap);
  if (props.capAny) return auth.canAny(props.capAny);
  return true;
});
</script>

<template>
  <slot v-if="allowed" />
</template>
