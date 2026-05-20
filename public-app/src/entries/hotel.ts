import { createApp } from 'vue';
import HotelDetailApp from '@/components/HotelDetailApp.vue';
import BookingWidget from '@/components/BookingWidget.vue';
import { installPrimeVue } from '@/plugins/primevue';
import '@/styles/tokens.css';
import '@/styles/main.css';

// Mount main booking app (search + rooms + inline checkout)
const mainMounts = document.querySelectorAll('[data-vie-public-hotel]');
let rooms: any[] = [];
let hotelId = 0;

mainMounts.forEach((el) => {
  hotelId = parseInt(el.getAttribute('data-hotel-id') || '0', 10);
  const dataScript = el.querySelector('script[type="application/json"]');
  if (dataScript) {
    try {
      rooms = JSON.parse(dataScript.textContent || '[]');
    } catch {
      rooms = [];
    }
  }
  const app = createApp(HotelDetailApp, { hotelId, rooms });
  installPrimeVue(app);
  app.mount(el);
});

// Mount sidebar booking widget (lives in parent theme's <aside>)
const widgetMounts = document.querySelectorAll('[data-vie-public-widget]');
widgetMounts.forEach((el) => {
  const app = createApp(BookingWidget, { rooms });
  installPrimeVue(app);
  app.mount(el);
});
