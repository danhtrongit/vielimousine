import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth.store';
import type { AuthUser } from '@/types/auth';
import { useCostVisibility } from './useCostVisibility';

function setCaps(caps: string[]): void {
  useAuthStore().user = { caps } as unknown as AuthUser;
}

describe('useCostVisibility', () => {
  beforeEach(() => setActivePinia(createPinia()));

  it('is true when the user has vie_view_reports', () => {
    setCaps(['vie_view_reports', 'vie_view_own_orders']);
    expect(useCostVisibility().canViewCost.value).toBe(true);
  });

  it('is false for a sales user lacking vie_view_reports', () => {
    setCaps(['vie_view_own_orders', 'vie_create_orders']);
    expect(useCostVisibility().canViewCost.value).toBe(false);
  });

  it('is false when there is no user', () => {
    expect(useCostVisibility().canViewCost.value).toBe(false);
  });
});
