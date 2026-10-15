import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// SPA terpisah dari Laravel API (apps/api) — lihat docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md.
// Dev server default port 5173 harus sinkron dengan SPA_URL / SANCTUM_STATEFUL_DOMAINS di apps/api/.env.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        // 127.0.0.1 eksplisit, BUKAN 'localhost' — Node me-resolve
        // 'localhost' ke ::1 (IPv6) lebih dulu di banyak setup, sedangkan
        // `php artisan serve` default hanya listen di IPv4 127.0.0.1,
        // menyebabkan ECONNREFUSED intermiten pada proxy dev.
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/sanctum': {
        // 127.0.0.1 eksplisit, BUKAN 'localhost' — Node me-resolve
        // 'localhost' ke ::1 (IPv6) lebih dulu di banyak setup, sedangkan
        // `php artisan serve` default hanya listen di IPv4 127.0.0.1,
        // menyebabkan ECONNREFUSED intermiten pada proxy dev.
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
