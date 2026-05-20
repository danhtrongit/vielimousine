import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  base: '/wp-content/themes/vielimousine-child/public-app/dist/',
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        hotel: resolve(__dirname, 'src/entries/hotel.ts'),
        success: resolve(__dirname, 'src/entries/success.ts'),
      },
    },
  },
  server: {
    port: 5174,
  },
});
