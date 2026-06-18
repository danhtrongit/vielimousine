import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('@/api/backup.api', () => ({
  backupApi: {
    tables: vi.fn().mockResolvedValue({ data: [
      { name: 'wpte_vie_order', rows: 2121, size_mb: 2.61 },
      { name: 'wpte_vie_coupon', rows: 3, size_mb: 0.11 },
    ] }),
    export: vi.fn(),
    restore: vi.fn(),
  },
}));
vi.mock('@/composables/useNotify', () => ({ useNotify: () => ({ success: vi.fn(), apiError: vi.fn() }) }));
vi.mock('@/stores/ui.store', () => ({ useUIStore: () => ({ setBreadcrumb: vi.fn() }) }));

import BackupView from './BackupView.vue';

function mountView() {
  return mount(BackupView, {
    global: {
      stubs: {
        PageHeader: { template: '<div><slot /></div>' },
        DataTable: { props: ['value'], template: '<div class="dt"><span v-for="r in value" :key="r.name" class="row">{{ r.name }}</span></div>' },
        Column: true, Checkbox: true, Button: true, InputText: true, FileUpload: true, Card: { template: '<div><slot name="title"/><slot name="content"/></div>' },
        Message: { template: '<div><slot /></div>' },
      },
    },
  });
}

describe('BackupView', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('loads and renders the table list', async () => {
    const w = mountView();
    await flushPromises();
    expect(w.html()).toContain('wpte_vie_order');
    expect(w.html()).toContain('wpte_vie_coupon');
  });

  it('restore is blocked until the confirm text is exactly RESTORE', async () => {
    const w = mountView();
    await flushPromises();
    const vm = w.vm as unknown as { confirmText: string; canRestore: boolean; restoreSql: string };
    vm.restoreSql = 'DROP TABLE x;';
    vm.confirmText = 'restore';
    expect((w.vm as unknown as { canRestore: boolean }).canRestore).toBe(false);
    vm.confirmText = 'RESTORE';
    expect((w.vm as unknown as { canRestore: boolean }).canRestore).toBe(true);
  });
});
