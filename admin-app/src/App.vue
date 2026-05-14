<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import AuthLayout from '@/layouts/AuthLayout.vue';

const route = useRoute();
const isAuthLayout = computed(() => route.meta.layout === 'auth');
</script>

<template>
  <AuthLayout v-if="isAuthLayout">
    <RouterView />
  </AuthLayout>
  <RouterView v-else />
</template>

<style>
@font-face {
  font-family: 'Tahoma';
  src: url('/wp-content/themes/vielimousine-child/admin-app/dist/fonts/Tahoma.woff2') format('woff2');
  font-weight: 400;
  font-style: normal;
  font-display: swap;
}
@font-face {
  font-family: 'Tahoma';
  src: url('/wp-content/themes/vielimousine-child/admin-app/dist/fonts/Tahoma-Bold.woff2') format('woff2');
  font-weight: 700;
  font-style: normal;
  font-display: swap;
}

:root {
  --p-primary-color: #fa541c;
}

body {
  margin: 0;
  font-family: 'Tahoma', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
</style>
