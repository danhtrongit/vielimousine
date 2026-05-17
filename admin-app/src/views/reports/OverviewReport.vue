<script setup lang="ts">
import RevenueReport from './RevenueReport.vue';
import ByHotelReport from './ByHotelReport.vue';
import BySourceReport from './BySourceReport.vue';
import BySalesReport from './BySalesReport.vue';
import type { Order } from '@/types/order';

defineProps<{
  orders: Order[];
  dateFrom: string;
  dateTo: string;
  hotelIds: number[];
  salesUserIds: number[];
  sources: string[];
}>();
</script>

<template>
  <div class="overview">
    <section>
      <RevenueReport :orders="orders" />
    </section>

    <section>
      <h2 class="section-title"><i class="pi pi-building" /> Theo khách sạn</h2>
      <ByHotelReport
        :date-from="dateFrom"
        :date-to="dateTo"
        :hotel-ids="hotelIds"
        :sales-user-ids="salesUserIds"
        :sources="sources"
      />
    </section>

    <section>
      <h2 class="section-title"><i class="pi pi-tag" /> Theo nguồn</h2>
      <BySourceReport :orders="orders" />
    </section>

    <section>
      <h2 class="section-title"><i class="pi pi-users" /> Theo nhân viên sales</h2>
      <BySalesReport :orders="orders" />
    </section>
  </div>
</template>

<style scoped>
.overview { display: flex; flex-direction: column; gap: 2rem; }
.section-title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1.1rem; font-weight: 600;
  margin: 0 0 0.75rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid var(--p-primary-200);
  color: var(--p-primary-700);
}
.section-title .pi { font-size: 1.1rem; }
</style>
