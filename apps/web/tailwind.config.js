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
        // Dibaca dari variabel CSS --brand-* (default di style.css) supaya warna
        // utama bisa diganti admin lewat Pengaturan > Tampilan tanpa rebuild.
        brand: {
          50: 'rgb(var(--brand-50) / <alpha-value>)',
          100: 'rgb(var(--brand-100) / <alpha-value>)',
          200: 'rgb(var(--brand-200) / <alpha-value>)',
          300: 'rgb(var(--brand-300) / <alpha-value>)',
          400: 'rgb(var(--brand-400) / <alpha-value>)',
          500: 'rgb(var(--brand-500) / <alpha-value>)',
          600: 'rgb(var(--brand-600) / <alpha-value>)',
          700: 'rgb(var(--brand-700) / <alpha-value>)',
          800: 'rgb(var(--brand-800) / <alpha-value>)',
          900: 'rgb(var(--brand-900) / <alpha-value>)',
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
