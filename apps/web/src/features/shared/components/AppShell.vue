<script setup lang="ts">
// Layout admin: sidebar 5 menu + topbar, dipakai lintas menu admin
// (Kuesioner, Data Mahasiswa, Data Pegawai, Hasil Analisa, Pengaturan).
// Mobile (<lg): off-canvas penuh via hamburger, label selalu tampil.
// Desktop (>=lg): sidebar tetap (fix) sebagai bagian dari layout — bukan
// overlay hover — lebar 256px berlabel, bisa diciutkan ke rail 72px
// ikon-saja lewat tombol minimize. Status minimize disimpan di
// localStorage supaya tidak reset tiap buka halaman.
import { ref, watch } from 'vue'
import { useAuthStore } from '@/features/auth/store/auth'
import { useBrandingStore } from '@/features/branding/store/branding'
import { useToast } from '@/features/shared/useToast'

const SIDEBAR_COLLAPSED_KEY = 'myameta.sidebar.collapsed'

export type NavIcon = 'clipboard' | 'academic-cap' | 'briefcase' | 'chart-bar' | 'cog' | 'download'

export interface NavItem {
  label: string
  to: string
  icon?: NavIcon
}

defineProps<{
  navItems: NavItem[]
  institutionName?: string
  activeRoleLabel?: string
}>()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1')
const auth = useAuthStore()
const branding = useBrandingStore()
const { push } = useToast()

watch(sidebarCollapsed, (value) => {
  localStorage.setItem(SIDEBAR_COLLAPSED_KEY, value ? '1' : '0')
})

function toggleSidebarCollapsed() {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

async function onLogout() {
  try {
    await auth.logout()
  } catch (e) {
    push(e instanceof Error ? e.message : 'Gagal keluar.', 'error')
  }
}

// Path SVG outline per ikon menu (gaya konsisten dengan ikon lain di app:
// stroke-width 2, viewBox 24). Dipakai di rail sidebar desktop (collapsed).
const NAV_ICON_PATHS: Record<NavIcon, string> = {
  clipboard: 'M9 12h6m-6 4h6M9 8h6M5 6a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6z',
  'academic-cap':
    'M12 3l9 4.5-9 4.5-9-4.5L12 3zM6.5 9.75V15c0 1.66 2.46 3 5.5 3s5.5-1.34 5.5-3V9.75',
  briefcase:
    'M9 6V4a2 2 0 012-2h2a2 2 0 012 2v2h3a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h3zm2-2v2h2V4h-2z',
  'chart-bar': 'M4 20V10m6 10V4m6 16v-7',
  cog: 'M12 15a3 3 0 100-6 3 3 0 000 6zM4.5 12a7.5 7.5 0 01.2-1.72l-1.6-1.24 1.5-2.6 1.9.62a7.6 7.6 0 011.5-.86L8.4 4h3l.4 2.2c.54.21 1.04.5 1.5.86l1.9-.62 1.5 2.6-1.6 1.24c.13.55.2 1.13.2 1.72s-.07 1.17-.2 1.72l1.6 1.24-1.5 2.6-1.9-.62c-.46.36-.96.65-1.5.86L15.4 20h-3l-.4-2.2a7.6 7.6 0 01-1.5-.86l-1.9.62-1.5-2.6 1.6-1.24c-.13-.55-.2-1.13-.2-1.72z',
  download: 'M12 4v12m0 0l-4-4m4 4l4-4M4 20h16',
}

// Chevron ganda untuk tombol minimize/expand sidebar.
const COLLAPSE_ICON_PATH = 'M11 5l-7 7 7 7M18 5l-7 7 7 7'
const EXPAND_ICON_PATH = 'M13 5l7 7-7 7M6 5l7 7-7 7'
</script>

<template>
  <div class="min-h-screen bg-slate-50 lg:flex">
    <!-- Overlay mobile -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Mobile: off-canvas penuh (hamburger), label selalu tampil -->
    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform overflow-hidden bg-[#1c1f3d] text-white transition-transform duration-200 ease-in-out lg:hidden"
      :class="{ 'translate-x-0': sidebarOpen }"
    >
      <!-- Dekorasi latar senada halaman login -->
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-20 top-1/2 h-72 w-72 rounded-full bg-brand-700/40 blur-3xl" />
        <div class="absolute -top-16 right-[-4rem] h-48 w-48 rounded-full bg-brand-400/25 blur-2xl" />
      </div>

      <div class="relative flex h-16 items-center gap-2 border-b border-white/10 px-5">
        <img v-if="branding.data?.logo_url" :src="branding.data.logo_url" alt="" class="h-8 w-8 rounded-lg object-contain" />
        <div v-else class="h-8 w-8 rounded-lg bg-white/15" />
        <span class="font-semibold uppercase tracking-wide">{{ branding.appName }}</span>
      </div>
      <nav class="relative flex flex-col gap-1 p-3">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="rounded-full px-4 py-2 text-sm font-medium text-indigo-100/80 transition hover:bg-white/10 hover:text-white"
          active-class="!bg-gradient-to-r !from-brand-500 !to-fuchsia-500 !text-white shadow-sm"
          @click="sidebarOpen = false"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
    </aside>

    <!-- Desktop: sidebar tetap (bagian dari layout, bukan overlay) — lebar
         256px berlabel secara default, bisa diciutkan ke rail 72px
         ikon-saja lewat tombol minimize di bawah. -->
    <div
      class="relative z-40 hidden shrink-0 flex-col overflow-hidden bg-[#1c1f3d] text-white transition-[width] duration-200 ease-in-out lg:sticky lg:top-0 lg:flex lg:h-screen"
      :class="sidebarCollapsed ? 'lg:w-[72px]' : 'lg:w-64'"
    >
      <!-- Dekorasi latar senada halaman login -->
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-20 top-1/2 h-72 w-72 rounded-full bg-brand-700/40 blur-3xl" />
        <div class="absolute -top-16 right-[-4rem] h-48 w-48 rounded-full bg-brand-400/25 blur-2xl" />
      </div>

      <div class="relative flex h-16 items-center gap-3 border-b border-white/10 px-[18px]">
        <img v-if="branding.data?.logo_url" :src="branding.data.logo_url" alt="" class="h-8 w-8 shrink-0 rounded-lg object-contain" />
        <div v-else class="h-8 w-8 shrink-0 rounded-lg bg-white/15" />
        <span v-if="!sidebarCollapsed" class="whitespace-nowrap font-semibold uppercase tracking-wide">
          {{ branding.appName }}
        </span>
      </div>
      <nav class="relative flex flex-1 flex-col gap-1 p-3">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :title="item.label"
          class="flex items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-indigo-100/80 transition hover:bg-white/10 hover:text-white"
          :class="sidebarCollapsed ? 'justify-center' : ''"
          active-class="!bg-gradient-to-r !from-brand-500 !to-fuchsia-500 !text-white shadow-sm"
        >
          <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="NAV_ICON_PATHS[item.icon ?? 'cog']" />
          </svg>
          <span v-if="!sidebarCollapsed" class="whitespace-nowrap">{{ item.label }}</span>
        </RouterLink>
      </nav>

      <!-- Tombol minimize/expand sidebar -->
      <div class="relative border-t border-white/10 p-3">
        <button
          type="button"
          class="flex w-full items-center gap-3 rounded-full px-3 py-2 text-sm font-medium text-indigo-100/80 transition hover:bg-white/10 hover:text-white"
          :class="sidebarCollapsed ? 'justify-center' : ''"
          :aria-label="sidebarCollapsed ? 'Perbesar sidebar' : 'Ciutkan sidebar'"
          :title="sidebarCollapsed ? 'Perbesar sidebar' : 'Ciutkan sidebar'"
          @click="toggleSidebarCollapsed"
        >
          <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              :d="sidebarCollapsed ? EXPAND_ICON_PATH : COLLAPSE_ICON_PATH"
            />
          </svg>
          <span v-if="!sidebarCollapsed" class="whitespace-nowrap">Ciutkan</span>
        </button>
      </div>
    </div>

    <div class="flex min-h-screen flex-1 flex-col">
      <header
        class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6"
      >
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
            aria-label="Buka menu navigasi"
            @click="sidebarOpen = true"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div class="hidden text-sm text-slate-500 sm:block">{{ institutionName ?? branding.data?.institution_name ?? '' }}</div>
        </div>
        <div class="flex items-center gap-3">
          <span v-if="auth.user" class="hidden text-xs text-slate-500 sm:inline">{{ auth.user.name }}</span>
          <span v-else-if="activeRoleLabel" class="hidden text-xs text-slate-500 sm:inline">{{ activeRoleLabel }}</span>
          <slot name="topbar-actions" />
          <button
            type="button"
            class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
            @click="onLogout()"
          >
            Keluar
          </button>
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
