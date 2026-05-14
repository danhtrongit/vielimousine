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
      path: '/',
      component: () => import('@/layouts/DefaultLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', component: () => import('@/views/dashboard/DashboardView.vue') },
        { path: 'orders',
          component: () => import('@/views/orders/OrderListView.vue'),
          meta: { capAny: ['vie_view_own_orders', 'vie_view_orders_own_hotel', 'vie_view_all_orders'] } },
        { path: 'orders/new',
          component: () => import('@/views/orders/OrderCreateView.vue'),
          meta: { cap: 'vie_create_orders' } },
        { path: 'orders/:id',
          component: () => import('@/views/orders/OrderDetailView.vue'),
          meta: { capAny: ['vie_view_own_orders', 'vie_view_orders_own_hotel', 'vie_view_all_orders'] } },
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
        { path: 'product-codes',
          component: () => import('@/views/product-codes/ProductCodeListView.vue'),
          meta: { cap: 'vie_manage_inventory' } },
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
          meta: { capAny: ['vie_view_reports', 'vie_view_reports_own_hotel'] } },
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

export default router;
