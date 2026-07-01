import { computed, type ComputedRef } from 'vue';
import { useAuthStore } from '@/stores/auth.store';

/**
 * Single source of truth for who may see financial fields (giá vốn / lợi nhuận).
 * Gated by the vie_manage_pricing capability (admin only). "Quản lý khách sạn" xem
 * được báo cáo doanh thu nhưng KHÔNG thấy giá vốn / lợi nhuận. Khớp với backend
 * CostVisibility::canView().
 */
export function useCostVisibility(): { canViewCost: ComputedRef<boolean> } {
  const auth = useAuthStore();
  const canViewCost = computed(() => auth.can('vie_manage_pricing'));
  return { canViewCost };
}
