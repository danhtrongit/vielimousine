import { createRouter, createWebHistory, type RouteLocationNormalized } from 'vue-router';
import { useAuthStore } from '@/stores/auth.store';

const router = createRouter({
  history: createWebHistory('/vie-admin/'),
  routes: [
    {
      path: '/login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { layout: 'auth' },
    },
    {
      path: '/503',
      component: () => import('@/views/errors/ServiceUnavailableView.vue'),
      meta: { layout: 'error' },
    },
    {
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', component: () => import('@/views/dashboard/DashboardView.vue') },
        { path: 'orders',
          component: () => import('@/views/orders/OrderListView.vue'),
          meta: { capAny: ['vie_view_own_orders', 'vie_view_all_orders'] } },
        { path: 'orders/new',
          component: () => import('@/views/orders/OrderCreateView.vue'),
          meta: { cap: 'vie_create_orders' } },
        { path: 'orders/:id',
          component: () => import('@/views/orders/OrderDetailView.vue'),
          meta: { capAny: ['vie_view_own_orders', 'vie_view_all_orders'] } },
        { path: 'customers',
          component: () => import('@/views/customers/CustomerListView.vue'),
          meta: { cap: 'vie_manage_customers' } },
        { path: 'customers/:id',
          component: () => import('@/views/customers/CustomerDetailView.vue'),
          meta: { cap: 'vie_manage_customers' } },
        { path: 'hotels',
          component: () => import('@/views/hotels/HotelListView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'hotels/:id',
          component: () => import('@/views/hotels/HotelDetailView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'rooms',
          component: () => import('@/views/rooms/RoomListView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'rooms/:id',
          component: () => import('@/views/rooms/RoomDetailView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'pricing',
          component: () => import('@/views/pricing/PricingView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'pricing/bulk',
          component: () => import('@/views/pricing/BulkWizardView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
        { path: 'media',
          component: () => import('@/views/media/MediaListView.vue'),
          meta: { cap: 'vie_manage_media' } },
        { path: 'coupons',
          component: () => import('@/views/coupons/CouponListView.vue'),
          meta: { cap: 'vie_manage_coupons' } },
        { path: 'coupons/:id',
          component: () => import('@/views/coupons/CouponDetailView.vue'),
          meta: { cap: 'vie_manage_coupons' } },
        { path: 'payments-ledger',
          component: () => import('@/views/payments/PaymentLedgerView.vue'),
          meta: { capAny: ['vie_manage_payments', 'vie_view_all_orders'] } },
        { path: 'reports',
          component: () => import('@/views/reports/ReportsView.vue'),
          meta: { cap: 'vie_view_reports' } },
        { path: 'activity-log',
          component: () => import('@/views/activity/ActivityLogView.vue'),
          meta: { cap: 'vie_view_audit' } },
        { path: 'setup',
          name: 'setup',
          component: () => import('@/views/setup/SetupView.vue'),
          meta: { cap: 'vie_manage_users' } },
        { path: 'settings',
          component: () => import('@/views/settings/SettingsView.vue'),
          meta: { cap: 'vie_manage_settings' } },
        { path: 'backup',
          component: () => import('@/views/backup/BackupView.vue'),
          meta: { cap: 'vie_manage_backup' } },
        { path: ':pathMatch(.*)*',
          component: () => import('@/views/errors/NotFoundView.vue') },
      ],
    },
  ],
});

router.beforeEach(async (to: RouteLocationNormalized) => {
  const auth = useAuthStore();
  const requiresAuth = to.matched.some((r) => r.meta.requiresAuth);

  if (requiresAuth) {
    if (!auth.isAuthenticated) await auth.tryRefresh();
    if (!auth.isAuthenticated) {
      return { path: '/login', query: { next: to.fullPath } };
    }
  }

  if (to.meta.cap && !auth.can(to.meta.cap as string)) {
    return { path: '/dashboard' };
  }
  if (to.meta.capAny && !auth.canAny(to.meta.capAny as string[])) {
    return { path: '/dashboard' };
  }

  if (to.path === '/login' && auth.isAuthenticated) {
    return { path: '/dashboard' };
  }
});

// Stale-chunk handler: when a new build invalidates cached chunk hashes,
// dynamic imports fail with "Failed to fetch dynamically imported module".
// Force a full reload so the browser fetches the fresh index.html + new chunks.
const CHUNK_LOAD_ERROR_RE = /Failed to fetch dynamically imported module|Importing a module script failed|Loading chunk \d+ failed/i;
const RELOAD_KEY = '__vie_chunk_reload';

function isChunkLoadError(err: unknown): boolean {
  if (!err) return false;
  const msg = (err as Error)?.message ?? String(err);
  return CHUNK_LOAD_ERROR_RE.test(msg);
}

function reloadOnce(targetPath: string): void {
  // Prevent infinite reload loop: only reload once per path within 10s.
  const flag = sessionStorage.getItem(RELOAD_KEY);
  const now = Date.now();
  if (flag) {
    const [path, ts] = flag.split('|');
    if (path === targetPath && now - Number(ts) < 10_000) {
      return; // already tried; let the error bubble so user sees it
    }
  }
  sessionStorage.setItem(RELOAD_KEY, `${targetPath}|${now}`);
  window.location.href = targetPath;
}

router.onError((err, to) => {
  if (isChunkLoadError(err)) {
    reloadOnce(to.fullPath);
  }
});

// Also catch chunk errors raised outside router context (e.g. async data fetches).
window.addEventListener('error', (e) => {
  if (isChunkLoadError(e.error ?? e.message)) {
    reloadOnce(window.location.pathname + window.location.search);
  }
});
window.addEventListener('unhandledrejection', (e) => {
  if (isChunkLoadError(e.reason)) {
    reloadOnce(window.location.pathname + window.location.search);
  }
});

export default router;
