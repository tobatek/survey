// Branding institusi (logo, nama, warna utama, favicon, teks login) dari
// GET /api/branding (publik). Diterapkan ke seluruh app: judul tab, favicon,
// dan variabel CSS --brand-* yang dipakai palet `brand` di tailwind.config.js.
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export interface Branding {
  institution_name: string | null
  primary_color: string | null
  login_title: string | null
  login_description: string | null
  footer_text: string | null
  logo_url: string | null
  favicon_url: string | null
}

export const DEFAULT_APP_NAME = 'myAMETA Survey'

const SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900] as const

// Campuran warna dasar dengan putih (terang) atau hitam (gelap); 600 = warna
// yang dipilih admin, supaya tombol utama persis warna institusi.
const MIX: Record<(typeof SHADES)[number], { with: 'white' | 'black'; amount: number }> = {
  50: { with: 'white', amount: 0.92 },
  100: { with: 'white', amount: 0.82 },
  200: { with: 'white', amount: 0.64 },
  300: { with: 'white', amount: 0.44 },
  400: { with: 'white', amount: 0.22 },
  500: { with: 'white', amount: 0.1 },
  600: { with: 'white', amount: 0 },
  700: { with: 'black', amount: 0.18 },
  800: { with: 'black', amount: 0.34 },
  900: { with: 'black', amount: 0.5 },
}

function hexToRgb(hex: string): [number, number, number] {
  const n = parseInt(hex.slice(1), 16)
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

export function brandPalette(hex: string): Record<number, string> {
  const [r, g, b] = hexToRgb(hex)
  const palette: Record<number, string> = {}

  for (const shade of SHADES) {
    const { with: target, amount } = MIX[shade]
    const t = target === 'white' ? 255 : 0
    const mix = (c: number) => Math.round(c + (t - c) * amount)
    palette[shade] = `${mix(r)} ${mix(g)} ${mix(b)}`
  }

  return palette
}

function setFavicon(url: string): void {
  let link = document.querySelector<HTMLLinkElement>('link[rel~="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.href = url
}

export const useBrandingStore = defineStore('branding', {
  state: () => ({
    loaded: false,
    data: null as Branding | null,
  }),
  getters: {
    appName: (state): string => state.data?.institution_name || DEFAULT_APP_NAME,
  },
  actions: {
    async load(): Promise<void> {
      try {
        const res = await apiFetch<{ data: Branding }>('/branding')
        this.data = res.data
      } catch {
        // Branding opsional -- gagal muat jangan memblokir app, pakai tampilan bawaan.
        this.data = null
      } finally {
        this.loaded = true
        this.apply()
      }
    },

    set(data: Branding): void {
      this.data = data
      this.apply()
    },

    apply(): void {
      const root = document.documentElement
      const color = this.data?.primary_color

      if (color && /^#[0-9a-fA-F]{6}$/.test(color)) {
        for (const [shade, rgb] of Object.entries(brandPalette(color))) {
          root.style.setProperty(`--brand-${shade}`, rgb)
        }
      } else {
        for (const shade of SHADES) root.style.removeProperty(`--brand-${shade}`)
      }

      document.title = this.appName

      if (this.data?.favicon_url) setFavicon(this.data.favicon_url)
    },
  },
})
