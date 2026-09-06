<script setup lang="ts">
// Layout admin: sidebar 5 menu + topbar, dipakai lintas menu admin
// (Kuesioner, Data Mahasiswa, Data Pegawai, Hasil Analisa, Pengaturan).
// Mobile-first: sidebar jadi off-canvas (hamburger) di bawah breakpoint `lg`,
// menempel di kiri mulai `lg` — pola ini terinspirasi sidebar filter
// collapsible karirlink.id, diadaptasi jadi sidebar navigasi.
import { ref } from 'vue'

export interface NavItem {
  label: string
  to: string
  icon?: string
}

defineProps<{
  navItems: NavItem[]
  institutionName?: string
  activeRoleLabel?: string
}>()

const sidebarOpen = ref(false)
</script>

<template>
  <div class="min-h-screen bg-slate-50 lg:flex">
    <!-- Overlay mobile -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full transform bg-white ring-1 ring-slate-200 transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
      :class="{ 'translate-x-0': sidebarOpen }"
    >
      <div class="flex h-16 items-center gap-2 border-b border-slate-100 px-5">
        <div class="h-8 w-8 rounded-lg bg-brand-600" />
        <span class="font-semibold text-slate-900">myAMETA Survey</span>
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
          <div class="hidden text-sm text-slate-500 sm:block">{{ institutionName ?? 'STIKES Advaita Medika' }}</div>
        </div>
        <div class="flex items-center gap-3">
          <span v-if="activeRoleLabel" class="hidden text-xs text-slate-500 sm:inline">{{ activeRoleLabel }}</span>
          <slot name="topbar-actions" />
        </div>
      </header>

      <main class="flex-1 p-4 sm:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
