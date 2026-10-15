<script setup lang="ts">
// Halaman referensi komponen desain sistem (Fase 0 exit criteria: komponen
// ter-render benar di breakpoint mobile/tablet/desktop). Bukan bagian dari
// navigasi produksi — hanya dev tool, diakses via /_style-guide.
import Card from '@/features/shared/components/Card.vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import StatusBadge from '@/features/shared/components/StatusBadge.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import DataTable, { type Column } from '@/features/shared/components/DataTable.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import ChartCard from '@/features/shared/components/ChartCard.vue'
import ToastNotification from '@/features/shared/components/ToastNotification.vue'

interface DemoRow extends Record<string, unknown> {
  code: string
  title: string
  status: string
}

const columns: Column<DemoRow>[] = [
  { key: 'code', label: 'Kode' },
  { key: 'title', label: 'Judul' },
  { key: 'status', label: 'Status' },
]

const rows: DemoRow[] = [
  { code: 'SKM-MHS-01', title: 'Survei Kepuasan Mahasiswa', status: 'Aktif' },
  { code: 'SKM-PEG-01', title: 'Survei Kepuasan Pegawai', status: 'Draft' },
]
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-8 p-4 sm:p-8">
    <h1 class="text-xl font-semibold text-slate-900">Design System &mdash; Style Guide</h1>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Status Badge</h2>
      <div class="flex flex-wrap gap-2">
        <StatusBadge status="draft" />
        <StatusBadge status="ready" />
        <StatusBadge status="active" />
        <StatusBadge status="paused" />
        <StatusBadge status="closed" />
      </div>
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Stat Card</h2>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <StatCard label="Eligible" value="1.240" />
        <StatCard label="Selesai" value="812" tone="positive" />
        <StatCard label="Response Rate" value="65,5%" tone="positive" />
        <StatCard label="Gagal Sync" value="3" tone="danger" />
      </div>
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Card</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <Card clickable>
          <template #header>
            <p class="text-xs text-slate-400">SKM-MHS-01</p>
            <h3 class="text-sm font-semibold text-slate-900">Survei Kepuasan Mahasiswa</h3>
          </template>
          <template #badge><StatusBadge status="active" /></template>
          <template #meta><p class="mt-1 text-xs text-slate-500">1 Sep &ndash; 30 Sep 2026</p></template>
          <template #actions>
            <button class="rounded-md border border-slate-200 px-3 py-1.5 text-xs">Pantau</button>
          </template>
        </Card>
      </div>
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Filter Panel + Data Table</h2>
      <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
        <FilterPanel>
          <div class="text-sm text-slate-500">Contoh isi filter (prodi, angkatan, status).</div>
        </FilterPanel>
        <DataTable :columns="columns" :rows="rows" :page="1" :per-page="10" :total="rows.length" />
      </div>
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Chart Card</h2>
      <ChartCard title="Distribusi Kepuasan" question-code="Q3" :n="812" filter-summary="Semua prodi" refreshed-at="6 Sep 2026 10:00">
        <div class="flex h-40 items-center justify-center rounded-lg bg-slate-50 text-xs text-slate-400">
          Chart ditempatkan di sini (ApexCharts, Fase 5)
        </div>
      </ChartCard>
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Empty State</h2>
      <EmptyState title="Belum ada data" description="Contoh empty state untuk daftar kosong." />
    </section>

    <section class="space-y-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Toast</h2>
      <div class="max-w-sm space-y-2">
        <ToastNotification message="Ekspor selesai, siap diunduh." tone="success" />
        <ToastNotification message="Sinkronisasi gagal sebagian." tone="error" />
      </div>
    </section>
  </div>
</template>
