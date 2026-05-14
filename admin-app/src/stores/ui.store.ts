import { defineStore } from 'pinia';

export interface BreadcrumbItem {
  label: string;
  to?: string;
}

export const useUIStore = defineStore('ui', {
  state: () => ({
    sidebarCollapsed: false,
    breadcrumb: [] as BreadcrumbItem[],
  }),
  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed;
    },
    setBreadcrumb(items: BreadcrumbItem[]) {
      this.breadcrumb = items;
    },
  },
});
