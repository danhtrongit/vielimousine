<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useAuthStore } from '@/stores/auth.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useUIStore } from '@/stores/ui.store';

const auth = useAuthStore();
const lookup = useLookupStore();
const ui = useUIStore();
const route = useRoute();
const router = useRouter();

const menuItems = computed(() => [
  { label: 'Dashboard', icon: 'pi pi-home', to: '/dashboard', show: true },
  { label: 'Đơn hàng', icon: 'pi pi-shopping-cart', to: '/orders',
    show: auth.canAny(['vie_view_own_orders', 'vie_view_orders_own_hotel', 'vie_view_all_orders']) },
  { label: 'Khách hàng', icon: 'pi pi-users', to: '/customers',
    show: auth.can('vie_manage_customers') },
  { label: 'Khách sạn', icon: 'pi pi-building', to: '/hotels',
    show: auth.can('vie_manage_inventory') },
  { label: 'Phòng', icon: 'pi pi-th-large', to: '/rooms',
    show: auth.can('vie_manage_inventory') },
  { label: 'Bảng giá', icon: 'pi pi-dollar', to: '/pricing',
    show: auth.can('vie_manage_inventory') },
  { label: 'Mã sản phẩm', icon: 'pi pi-tag', to: '/product-codes',
    show: auth.can('vie_manage_inventory') },
  { label: 'Mã giảm giá', icon: 'pi pi-ticket', to: '/coupons',
    show: auth.can('vie_manage_coupons') },
  { label: 'Sổ thanh toán', icon: 'pi pi-wallet', to: '/payments-ledger',
    show: auth.canAny(['vie_manage_payments', 'vie_view_all_orders']) },
  { label: 'Báo cáo', icon: 'pi pi-chart-bar', to: '/reports',
    show: auth.canAny(['vie_view_reports', 'vie_view_reports_own_hotel']) },
].filter((m) => m.show));

const userMenuItems = computed(() => [
  { label: auth.user?.display_name ?? '', disabled: true },
  { separator: true },
  { label: 'Đăng xuất', icon: 'pi pi-sign-out', command: doLogout },
]);

const userMenu = ref();
function toggleUserMenu(e: Event) { userMenu.value?.toggle(e); }
import { ref } from 'vue';

async function doLogout() {
  await auth.logout();
  router.push('/login');
}

const breadcrumbHome = computed(() => ({ icon: 'pi pi-home', to: '/dashboard' }));

onMounted(() => { lookup.ensureLoaded(); });
</script>

<template>
  <div class="layout">
    <aside class="sidebar" :class="{ collapsed: ui.sidebarCollapsed }">
      <div class="sidebar-brand">
        <div class="brand-logo">
          <i class="pi pi-car" style="font-size: 1.5rem; color: var(--p-primary-color)" />
          <span v-if="!ui.sidebarCollapsed">Vielimousine</span>
        </div>
        <Button
          :icon="ui.sidebarCollapsed ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"
          text rounded size="small"
          @click="ui.toggleSidebar()"
          class="sidebar-toggle"
          v-tooltip.right="ui.sidebarCollapsed ? 'Mở rộng' : 'Thu gọn'"
        />
      </div>
      <nav>
        <RouterLink
          v-for="m in menuItems"
          :key="m.to"
          :to="m.to"
          class="nav-link"
          :class="{ active: route.path.startsWith(m.to) && m.to !== '/' }"
          v-tooltip.right="ui.sidebarCollapsed ? m.label : ''"
        >
          <i :class="m.icon" />
          <span v-if="!ui.sidebarCollapsed">{{ m.label }}</span>
        </RouterLink>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <Breadcrumb :home="breadcrumbHome" :model="ui.breadcrumb" />
        <div class="topbar-right">
          <Button
            :label="auth.user?.display_name"
            icon="pi pi-user"
            text
            @click="toggleUserMenu"
          />
          <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
        </div>
      </header>
      <section class="content">
        <RouterView />
      </section>
    </main>

    <Toast position="top-right" />
    <ConfirmDialog />
  </div>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: auto 1fr;
  min-height: 100vh;
  background: var(--p-surface-50);
}
.sidebar {
  background: var(--p-surface-0);
  border-right: 1px solid var(--p-surface-200);
  width: 240px;
  padding: 1rem 0.5rem;
  position: relative;
  transition: width 0.2s;
  display: flex;
  flex-direction: column;
}
.sidebar.collapsed { width: 68px; }
.sidebar-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0.5rem 1.25rem;
  font-weight: 700;
  font-size: 1.05rem;
  border-bottom: 1px solid var(--p-surface-100);
  margin-bottom: 0.75rem;
}
.sidebar.collapsed .sidebar-brand {
  flex-direction: column;
  gap: 0.5rem;
  padding-left: 0;
  padding-right: 0;
}
.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.sidebar-toggle { flex-shrink: 0; }
.sidebar nav { display: flex; flex-direction: column; gap: 0.25rem; }
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.85rem;
  border-radius: 0.5rem;
  text-decoration: none;
  color: var(--p-text-color);
  transition: background 0.15s;
}
.nav-link:hover { background: var(--p-surface-100); }
.nav-link.active {
  background: var(--p-primary-50);
  color: var(--p-primary-700);
  font-weight: 500;
}
.nav-link i { font-size: 1.05rem; min-width: 18px; }

.main {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  overflow: hidden;
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.5rem;
  background: var(--p-surface-0);
  border-bottom: 1px solid var(--p-surface-200);
}
.content { padding: 1.5rem; flex: 1; overflow: auto; }
</style>
