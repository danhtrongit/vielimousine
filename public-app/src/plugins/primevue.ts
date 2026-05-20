import type { App } from 'vue';
import PrimeVue from 'primevue/config';
import VieLimoPreset from '@/styles/preset';
import 'primeicons/primeicons.css';

export function installPrimeVue(app: App): void {
  app.use(PrimeVue, {
    theme: {
      preset: VieLimoPreset,
      options: {
        prefix: 'p',
        darkModeSelector: '.dark-mode',
        cssLayer: false,
      },
    },
    ripple: true,
  });
}
