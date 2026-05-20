import { defineStore } from 'pinia';

export interface BreadcrumbItem {
  label: string;
  to?: string;
}

export type ThemeMode = 'light' | 'dark';

const STORAGE_KEY = 'vielimo:ui';

interface PersistedUIState {
  sidebarCollapsed?: boolean;
  theme?: ThemeMode;
}

function loadPersisted(): PersistedUIState {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    return JSON.parse(raw) as PersistedUIState;
  } catch {
    return {};
  }
}

const persisted = loadPersisted();

export const useUIStore = defineStore('ui', {
  state: () => ({
    sidebarCollapsed: persisted.sidebarCollapsed ?? false,
    theme: (persisted.theme ?? 'light') as ThemeMode,
    breadcrumb: [] as BreadcrumbItem[],
  }),
  actions: {
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed;
      this.persist();
    },
    setBreadcrumb(items: BreadcrumbItem[]) {
      this.breadcrumb = items;
    },
    setTheme(mode: ThemeMode) {
      this.theme = mode;
      this.persist();
    },
    toggleTheme() {
      this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
    },
    persist() {
      try {
        const payload: PersistedUIState = {
          sidebarCollapsed: this.sidebarCollapsed,
          theme: this.theme,
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
      } catch {
        /* ignore quota / privacy errors */
      }
    },
  },
});
