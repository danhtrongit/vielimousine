import { computed, type ComputedRef } from 'vue';
import { useAuthStore } from '@/stores/auth.store';

/**
 * Single source of truth for who may see financial fields (giá vốn / lợi nhuận).
 * Gated by the vie_view_reports capability (sales role lacks it).
 */
export function useCostVisibility(): { canViewCost: ComputedRef<boolean> } {
  const auth = useAuthStore();
  const canViewCost = computed(() => auth.can('vie_view_reports'));
  return { canViewCost };
}
