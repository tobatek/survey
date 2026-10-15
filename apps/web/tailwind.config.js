/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', '"Inter"', 'system-ui', 'sans-serif'],
      },
      colors: {
        // Palet netral + satu aksen brand, terinspirasi karirlink.id
        // (functional minimalism: abu-abu netral untuk struktur, aksen untuk CTA).
        brand: {
          50: '#eef4ff',
          100: '#d9e6ff',
          200: '#b8d1ff',
          300: '#8bb3ff',
          400: '#5c8dff',
          500: '#3466f6',
          600: '#254bdb',
          700: '#1f3bb0',
          800: '#20348c',
          900: '#1e2f6f',
        },
        // Mapping warna badge status kuesioner/campaign — konsisten lintas modul
        // (FSD §7.6 state machine): draft/ready/active/paused/closed.
        status: {
          draft: '#6b7280',
          ready: '#2563eb',
          active: '#16a34a',
          paused: '#d97706',
          closed: '#9f1239',
        },
      },
    },
  },
  plugins: [],
}
