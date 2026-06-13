<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useAuthStore } from '@/stores/auth.store';
import { useLookupStore } from '@/stores/lookup.store';
import { useUIStore } from '@/stores/ui.store';
import { useTheme } from '@/composables/useTheme';

interface NavItem {
  label: string;
  icon: string;
  to: string;
  show: boolean;
  group: string;
}

const auth = useAuthStore();
const lookup = useLookupStore();
const ui = useUIStore();
const route = useRoute();
const router = useRouter();
const { isDark, toggle: toggleTheme } = useTheme();

const navItems = computed<NavItem[]>(() => [
  { label: 'Tổng quan', icon: 'pi pi-home', to: '/dashboard', show: true, group: 'main' },
  { label: 'Đơn hàng', icon: 'pi pi-shopping-cart', to: '/orders',
    show: auth.canAny(['vie_view_own_orders', 'vie_view_all_orders']), group: 'main' },
  { label: 'Khách hàng', icon: 'pi pi-users', to: '/customers',
    show: auth.can('vie_manage_customers'), group: 'main' },
  { label: 'Khách sạn', icon: 'pi pi-building', to: '/hotels',
    show: auth.can('vie_manage_inventory'), group: 'inventory' },
  { label: 'Phòng', icon: 'pi pi-th-large', to: '/rooms',
    show: auth.can('vie_manage_inventory'), group: 'inventory' },
  { label: 'Bảng giá', icon: 'pi pi-dollar', to: '/pricing',
    show: auth.can('vie_manage_inventory'), group: 'inventory' },
  { label: 'Thư viện ảnh', icon: 'pi pi-images', to: '/media',
    show: auth.can('vie_manage_media'), group: 'inventory' },
  { label: 'Mã giảm giá', icon: 'pi pi-ticket', to: '/coupons',
    show: auth.can('vie_manage_coupons'), group: 'inventory' },
  { label: 'Sổ thanh toán', icon: 'pi pi-wallet', to: '/payments-ledger',
    show: auth.canAny(['vie_manage_payments', 'vie_view_all_orders']), group: 'finance' },
  { label: 'Báo cáo', icon: 'pi pi-chart-bar', to: '/reports',
    show: auth.can('vie_view_reports'), group: 'finance' },
  { label: 'Nhật ký', icon: 'pi pi-history', to: '/activity-log',
    show: auth.can('vie_view_audit'), group: 'system' },
  { label: 'Thiết lập', icon: 'pi pi-sliders-h', to: '/setup',
    show: auth.can('vie_manage_users'), group: 'system' },
  { label: 'Cài đặt', icon: 'pi pi-cog', to: '/settings',
    show: auth.can('vie_manage_settings'), group: 'system' },
].filter((m) => m.show));

interface NavGroup { key: string; label: string; items: NavItem[] }

const groupedNav = computed<NavGroup[]>(() => {
  const map: Record<string, NavGroup> = {
    main: { key: 'main', label: 'Vận hành', items: [] },
    inventory: { key: 'inventory', label: 'Quản lý dữ liệu', items: [] },
    finance: { key: 'finance', label: 'Tài chính', items: [] },
    system: { key: 'system', label: 'Hệ thống', items: [] },
  };
  for (const item of navItems.value) {
    map[item.group]?.items.push(item);
  }
  return Object.values(map).filter((g) => g.items.length > 0);
});

const userMenu = ref();
function toggleUserMenu(e: Event) { userMenu.value?.toggle(e); }

const userInitials = computed(() => {
  const name = auth.user?.display_name ?? '';
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(-2)
    .map((p) => p.charAt(0).toUpperCase())
    .join('') || 'U';
});

const userMenuItems = computed(() => [
  {
    label: auth.user?.display_name ?? 'Người dùng',
    items: [
      { label: 'Đăng xuất', icon: 'pi pi-sign-out', command: doLogout },
    ],
  },
]);

async function doLogout() {
  await auth.logout();
  router.push('/login');
}

const breadcrumbHome = computed(() => ({ icon: 'pi pi-home', to: '/dashboard' }));

const appVersion = (import.meta.env.VITE_APP_VERSION as string | undefined) ?? '2.0';

function isActive(path: string): boolean {
  return route.path === path || route.path.startsWith(path + '/');
}

onMounted(() => { lookup.ensureLoaded(); });
</script>

<template>
  <div class="layout">
    <aside class="sidebar" :class="{ collapsed: ui.sidebarCollapsed }" aria-label="Điều hướng chính">
      <div class="sidebar-brand">
        <RouterLink to="/dashboard" class="brand-link" :aria-label="ui.sidebarCollapsed ? 'Vielimousine' : ''">
          <span class="brand-icon"><i class="pi pi-car" /></span>
          <span v-if="!ui.sidebarCollapsed" class="brand-text">
            <span class="brand-name">Vielimousine</span>
            <span class="brand-sub">Admin Console</span>
          </span>
        </RouterLink>
        <Button
          :icon="ui.sidebarCollapsed ? 'pi pi-chevron-right' : 'pi pi-chevron-left'"
          text rounded size="small"
          :aria-label="ui.sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
          @click="ui.toggleSidebar()"
          class="sidebar-toggle"
          v-tooltip.right="ui.sidebarCollapsed ? 'Mở rộng' : 'Thu gọn'"
        />
      </div>

      <nav class="sidebar-nav">
        <template v-for="group in groupedNav" :key="group.key">
          <div v-if="!ui.sidebarCollapsed" class="nav-group-label">{{ group.label }}</div>
          <div v-else class="nav-group-divider" role="separator" />
          <RouterLink
            v-for="m in group.items"
            :key="m.to"
            :to="m.to"
            class="nav-link"
            :class="{ active: isActive(m.to) }"
            :aria-current="isActive(m.to) ? 'page' : undefined"
            v-tooltip.right="ui.sidebarCollapsed ? m.label : ''"
          >
            <i :class="m.icon" aria-hidden="true" />
            <span v-if="!ui.sidebarCollapsed">{{ m.label }}</span>
          </RouterLink>
        </template>
      </nav>

      <div class="sidebar-footer" v-if="!ui.sidebarCollapsed">
        <span>v{{ appVersion }}</span>
        <span class="dot">·</span>
        <span>© {{ new Date().getFullYear() }} Vielimousine</span>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="topbar-left">
          <Breadcrumb :home="breadcrumbHome" :model="ui.breadcrumb" class="bc" />
        </div>
        <div class="topbar-right">
          <Button
            :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
            text rounded
            :aria-label="isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
            v-tooltip.bottom="isDark ? 'Chuyển sáng' : 'Chuyển tối'"
            @click="toggleTheme"
            class="icon-btn"
          />
          <Button
            icon="pi pi-bell"
            text rounded
            aria-label="Thông báo"
            v-tooltip.bottom="'Thông báo'"
            class="icon-btn"
          />
          <button
            class="user-chip"
            @click="toggleUserMenu"
            type="button"
            :aria-label="'Tài khoản: ' + (auth.user?.display_name ?? '')"
          >
            <span class="avatar">{{ userInitials }}</span>
            <span class="user-name">{{ auth.user?.display_name }}</span>
            <i class="pi pi-angle-down" aria-hidden="true" />
          </button>
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
  background: var(--app-bg);
  color: var(--p-text-color);
}

/* ===== Sidebar ===== */
.sidebar {
  background: var(--app-sidebar-bg);
  border-right: 1px solid var(--app-card-border);
  width: var(--sidebar-width);
  padding: var(--space-3) var(--space-2);
  position: relative;
  transition: width var(--motion-base) var(--ease-in-out), background-color var(--motion-base) var(--ease-out);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.sidebar.collapsed { width: var(--sidebar-width-collapsed); }

.sidebar-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-2);
  border-radius: var(--radius-lg);
  background: var(--app-sidebar-brand-bg);
}
.sidebar.collapsed .sidebar-brand {
  flex-direction: column;
  background: transparent;
  padding: var(--space-1);
}
.brand-link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
  text-decoration: none;
  color: var(--p-text-color);
  flex: 1;
}
.brand-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border-radius: var(--radius-md);
  background: var(--p-primary-500);
  color: #fff;
  box-shadow: var(--shadow-sm);
  font-size: 1.05rem;
}
.brand-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.brand-name { font-weight: 700; font-size: 0.98rem; color: var(--p-text-color); }
.brand-sub { font-size: 0.72rem; color: var(--p-text-muted-color); letter-spacing: 0.04em; text-transform: uppercase; }
.sidebar-toggle { flex-shrink: 0; }

/* ===== Sidebar nav ===== */
.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  overflow-y: auto;
  padding: 0 var(--space-1);
  margin: 0 calc(-1 * var(--space-1));
}
.nav-group-label {
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
  padding: var(--space-3) var(--space-3) var(--space-1);
  font-weight: 600;
}
.nav-group-divider {
  height: 1px;
  background: var(--app-divider);
  margin: var(--space-2) var(--space-1);
}
.sidebar.collapsed .nav-group-divider:first-of-type { display: none; }
.nav-link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0.55rem 0.75rem;
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--p-text-color);
  font-size: 0.9rem;
  transition: background var(--motion-fast) var(--ease-out), color var(--motion-fast) var(--ease-out);
  cursor: pointer;
  position: relative;
}
.sidebar.collapsed .nav-link { justify-content: center; padding: 0.6rem 0; }
.nav-link:hover {
  background: var(--app-hover-bg);
  color: var(--p-text-color);
}
.nav-link.active {
  background: var(--app-sidebar-active-bg);
  color: var(--app-sidebar-active-color);
  font-weight: 600;
}
.nav-link.active::before {
  content: '';
  position: absolute;
  left: -8px;
  top: 25%;
  bottom: 25%;
  width: 3px;
  border-radius: 2px;
  background: var(--p-primary-500);
}
.sidebar.collapsed .nav-link.active::before { display: none; }
.nav-link i { font-size: 1.05rem; min-width: 18px; text-align: center; }

.sidebar-footer {
  font-size: 0.72rem;
  color: var(--p-text-muted-color);
  padding: var(--space-2) var(--space-3);
  display: flex;
  align-items: center;
  gap: var(--space-2);
  border-top: 1px solid var(--app-divider);
}
.sidebar-footer .dot { opacity: 0.5; }

/* ===== Main ===== */
.main {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  overflow: hidden;
  min-width: 0;
}

/* ===== Topbar ===== */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) var(--space-6);
  background: var(--app-topbar-bg);
  border-bottom: 1px solid var(--app-card-border);
  min-height: var(--topbar-height);
  gap: var(--space-3);
  transition: background-color var(--motion-base) var(--ease-out);
}
.topbar-left { min-width: 0; flex: 1; }
.bc :deep(.p-breadcrumb) { background: transparent; padding: 0; border: 0; }
.topbar-right {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.icon-btn:hover { background: var(--app-hover-bg); }

.user-chip {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.35rem 0.6rem 0.35rem 0.4rem;
  background: transparent;
  border: 1px solid var(--app-card-border);
  border-radius: var(--radius-full);
  cursor: pointer;
  transition: background var(--motion-fast) var(--ease-out), border-color var(--motion-fast) var(--ease-out);
  color: var(--p-text-color);
  font-size: 0.875rem;
  font-family: inherit;
}
.user-chip:hover { background: var(--app-hover-bg); }
.avatar {
  width: 30px;
  height: 30px;
  border-radius: var(--radius-full);
  background: var(--p-primary-100);
  color: var(--p-primary-700);
  display: grid;
  place-items: center;
  font-weight: 600;
  font-size: 0.78rem;
}
:global(.dark-mode) .avatar {
  background: color-mix(in srgb, var(--p-primary-500), transparent 70%);
  color: var(--p-primary-200);
}
.user-name {
  font-weight: 500;
  white-space: nowrap;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ===== Content ===== */
.content {
  padding: var(--space-6);
  flex: 1;
  overflow: auto;
}

/* ===== Responsive ===== */
@media (max-width: 1024px) {
  .sidebar { width: var(--sidebar-width-collapsed); }
  .sidebar:not(.collapsed) .brand-text,
  .sidebar:not(.collapsed) .nav-link span,
  .sidebar:not(.collapsed) .nav-group-label,
  .sidebar:not(.collapsed) .sidebar-footer { display: none; }
}
@media (max-width: 768px) {
  .content { padding: var(--space-4); }
  .topbar { padding: var(--space-2) var(--space-4); }
  .user-name { display: none; }
}

</style>
