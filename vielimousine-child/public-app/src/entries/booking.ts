import { createApp } from 'vue';
import BookingQuoteApp from '@/components/BookingQuoteApp.vue';
import { installPrimeVue } from '@/plugins/primevue';
import '@/styles/tokens.css';
import '@/styles/booking-quote.css';

document.querySelectorAll<HTMLElement>('[data-vie-public-booking]').forEach((el) => {
  const publicId = el.dataset.publicId?.trim() || '';
  const app = createApp(BookingQuoteApp, { publicId });
  installPrimeVue(app);
  app.mount(el);
});
