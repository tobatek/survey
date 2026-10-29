<script setup lang="ts">
// Layout admin: sidebar 5 menu + topbar, dipakai lintas menu admin
// (Kuesioner, Data Mahasiswa, Data Pegawai, Hasil Analisa, Pengaturan).
// Mobile (<lg): off-canvas penuh via hamburger, label selalu tampil.
// Desktop (>=lg): rail ikon 72px, melebar jadi panel 256px saat di-hover
// (atau di-fokus lewat keyboard) — panel overlay di atas konten, tidak
// menggeser layout utama.
import { ref } from 'vue'
import { useAuthStore } from '@/features/auth/store/auth'
import { useBrandingStore } from '@/features/branding/store/branding'
import { useToast } from '@/features/shared/useToast'

export type NavIcon = 'clipboard' | 'academic-cap' | 'briefcase' | 'chart-bar' | 'cog'

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
const auth = useAuthStore()
const branding = useBrandingStore()
const { push } = useToast()

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
}
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
      class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform bg-white ring-1 ring-slate-200 transition-transform duration-200 ease-in-out lg:hidden"
      :class="{ 'translate-x-0': sidebarOpen }"
    >
      <div class="flex h-16 items-center gap-2 border-b border-slate-100 px-5">
        <img v-if="branding.data?.logo_url" :src="branding.data.logo_url" alt="" class="h-8 w-8 rounded-lg object-contain" />
        <div v-else class="h-8 w-8 rounded-lg bg-brand-600" />
        <span class="font-semibold text-slate-900">{{ branding.appName }}</span>
      </div>
      <nav class="flex flex-col gap-1 p-3">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
          active-class="bg-brand-50 text-brand-700"
          @click="sidebarOpen = false"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
    </aside>

    <!-- Desktop: rail 72px berisi ikon saja, melebar jadi panel 256px saat
         logo/ikon di-hover (atau di-fokus via keyboard). Panel overlay
         (absolute) di atas rail supaya konten utama tidak ikut bergeser. -->
    <div class="group/rail relative z-40 hidden shrink-0 lg:block lg:h-screen lg:w-[72px] lg:sticky lg:top-0">
      <div
        class="absolute inset-y-0 left-0 z-40 flex w-[72px] flex-col overflow-hidden bg-white ring-1 ring-slate-200 transition-[width] duration-200 ease-in-out group-hover/rail:w-64 group-hover/rail:shadow-xl group-focus-within/rail:w-64"
      >
        <div class="flex h-16 items-center gap-3 border-b border-slate-100 px-[18px]">
          <img v-if="branding.data?.logo_url" :src="branding.data.logo_url" alt="" class="h-8 w-8 shrink-0 rounded-lg object-contain" />
          <div v-else class="h-8 w-8 shrink-0 rounded-lg bg-brand-600" />
          <span
            class="whitespace-nowrap font-semibold text-slate-900 opacity-0 transition-opacity duration-150 group-hover/rail:opacity-100 group-focus-within/rail:opacity-100"
          >
            {{ branding.appName }}
          </span>
        </div>
        <nav class="flex flex-col gap-1 p-3">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            :title="item.label"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            active-class="bg-brand-50 text-brand-700"
          >
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" :d="NAV_ICON_PATHS[item.icon ?? 'cog']" />
            </svg>
            <span
              class="whitespace-nowrap opacity-0 transition-opacity duration-150 group-hover/rail:opacity-100 group-focus-within/rail:opacity-100"
            >
              {{ item.label }}
            </span>
          </RouterLink>
        </nav>
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
