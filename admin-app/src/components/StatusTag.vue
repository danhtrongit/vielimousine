<script setup lang="ts">
import { computed } from 'vue';
import Tag from 'primevue/tag';
import { ORDER_STATUSES, PAYMENT_STATUSES } from '@/stores/lookup.store';

const props = defineProps<{ value: string; kind?: 'order' | 'payment' }>();

const opts = computed(() => (props.kind === 'payment' ? PAYMENT_STATUSES : ORDER_STATUSES));
const matched = computed(() => opts.value.find((o) => o.value === props.value));
</script>

<template>
  <Tag
    :value="matched?.label ?? value"
    :severity="(matched?.severity as any) ?? 'secondary'"
  />
</template>
