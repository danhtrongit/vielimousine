<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Button from 'primevue/button';
import PriceMatrixView from './PriceMatrixView.vue';
import SurchargeMatrixView from './SurchargeMatrixView.vue';
import TicketMatrixView from './TicketMatrixView.vue';
import { useUIStore } from '@/stores/ui.store';

const router = useRouter();
const ui = useUIStore();
const activeTab = ref('rooms');

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

    <TabView v-model:value="activeTab">
      <TabPanel header="Giá phòng" value="rooms">
        <PriceMatrixView v-if="activeTab === 'rooms'" />
      </TabPanel>
      <TabPanel header="Phụ thu" value="surcharges">
        <SurchargeMatrixView v-if="activeTab === 'surcharges'" />
      </TabPanel>
      <TabPanel header="Vé xe" value="tickets">
        <TicketMatrixView v-if="activeTab === 'tickets'" />
      </TabPanel>
    </TabView>
  </div>
</template>

<style scoped>
.header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { margin: 0; font-size: 1.5rem; font-weight: 600; }
</style>
