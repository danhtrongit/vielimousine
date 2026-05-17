<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Button from 'primevue/button';
import PricingConfigView from './PricingConfigView.vue';
import UnifiedMatrixView from './UnifiedMatrixView.vue';
import { useUIStore } from '@/stores/ui.store';

const router = useRouter();
const ui = useUIStore();
const activeTab = ref('config');

onMounted(() => {
  ui.setBreadcrumb([{ label: 'Bảng giá' }]);
});
</script>

<template>
  <div>
    <div class="header">
      <h1 class="page-title">Bảng giá</h1>
      <Button label="Cập nhật hàng loạt" icon="pi pi-bolt" severity="warn" @click="router.push('/pricing/bulk')" />
    </div>

    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="config">Cấu hình</Tab>
        <Tab value="matrix">Theo ngày</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="config">
          <PricingConfigView v-if="activeTab === 'config'" />
        </TabPanel>
        <TabPanel value="matrix">
          <UnifiedMatrixView v-if="activeTab === 'matrix'" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
</style>
