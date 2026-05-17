import { createApp } from 'vue';
import SuccessApp from '@/components/SuccessApp.vue';
import { installPrimeVue } from '@/plugins/primevue';
import '../styles/main.css';

const el = document.querySelector('[data-vie-public-success]');
if (el) {
  const app = createApp(SuccessApp);
  installPrimeVue(app);
  app.mount(el);
}
