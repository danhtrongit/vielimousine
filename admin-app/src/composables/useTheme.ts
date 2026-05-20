import { computed, watch, onMounted } from 'vue';
import { useUIStore } from '@/stores/ui.store';

const DARK_CLASS = 'dark-mode';

function applyTheme(mode: 'light' | 'dark') {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (mode === 'dark') root.classList.add(DARK_CLASS);
  else root.classList.remove(DARK_CLASS);
  root.style.colorScheme = mode;
}

let initialized = false;

export function useTheme() {
  const ui = useUIStore();

  if (!initialized) {
    initialized = true;
    applyTheme(ui.theme);
    watch(
      () => ui.theme,
      (mode) => applyTheme(mode),
    );

    onMounted(() => {
      applyTheme(ui.theme);
    });
  }

  const isDark = computed(() => ui.theme === 'dark');
  const toggle = () => ui.toggleTheme();

  return { isDark, toggle, theme: computed(() => ui.theme) };
}
