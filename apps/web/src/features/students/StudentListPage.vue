<script setup lang="ts">
// Placeholder Fase 0 — DataTable + FilterPanel penuh (sync, dsb.) di Fase 1.
import AppShell from '@/features/shared/components/AppShell.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import DataTable, { type Column } from '@/features/shared/components/DataTable.vue'
import { NAV_ITEMS } from '@/router/nav'

interface StudentRow extends Record<string, unknown> {
  nim: string
  name: string
  program_study: string
  status: string
}

const columns: Column<StudentRow>[] = [
  { key: 'nim', label: 'NIM' },
  { key: 'name', label: 'Nama' },
  { key: 'program_study', label: 'Prodi' },
  { key: 'status', label: 'Status' },
]

const rows: StudentRow[] = [
  { nim: '2023010001', name: 'Contoh Mahasiswa', program_study: 'D3 Keperawatan', status: 'Aktif' },
]
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <template #topbar-actions>
      <button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
        Sinkron Sekarang
      </button>
    </template>

    <h1 class="text-lg font-semibold text-slate-900">Data Mahasiswa</h1>
    <p class="mt-1 text-sm text-slate-500">Master data hasil sinkronisasi dari Portal.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">
      <FilterPanel>
        <div class="text-sm text-slate-500">Filter prodi/angkatan/status akan diisi di Fase 1.</div>
      </FilterPanel>

      <DataTable :columns="columns" :rows="rows" :page="1" :per-page="10" :total="rows.length" />
    </div>
  </AppShell>
</template>
