<script setup lang="ts" generic="T extends Record<string, unknown>">
// Tabel data generik: search + sort (via slot header) + pagination server-side.
// Di mobile (<sm), baris ditampilkan sebagai stacked card (bukan tabel yang
// di-scroll horizontal) — pola ini dipakai ulang di Data Mahasiswa/Pegawai,
// Kuesioner, dan Hasil Analisa (FSD §6.1, §8.1, §9).
export interface Column<Row> {
  key: string
  label: string
  render?: (row: Row) => string
}

const props = defineProps<{
  columns: Column<T>[]
  rows: T[]
  loading?: boolean
  page: number
  perPage: number
  total: number
}>()

const emit = defineEmits<{ 'update:page': [page: number] }>()

function cell(row: T, col: Column<T>): string {
  if (col.render) return col.render(row)
  const value = row[col.key]
  return value == null ? '-' : String(value)
}

function lastPage(): number {
  return Math.max(1, Math.ceil(props.total / props.perPage))
}
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div v-if="loading" class="p-6 text-center text-sm text-slate-400">Memuat data...</div>

    <template v-else-if="rows.length === 0">
      <slot name="empty">
        <div class="p-10 text-center text-sm text-slate-400">Tidak ada data yang sesuai.</div>
      </slot>
    </template>

    <template v-else>
      <!-- Desktop/tablet: tabel biasa -->
      <table class="hidden w-full text-left text-sm sm:table">
        <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
          <tr>
            <th v-for="col in columns" :key="col.key" class="px-4 py-3">{{ col.label }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(row, i) in rows" :key="i" class="hover:bg-slate-50">
            <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-slate-700">
              {{ cell(row, col) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Mobile: stacked card per baris -->
      <ul class="divide-y divide-slate-100 sm:hidden">
        <li v-for="(row, i) in rows" :key="i" class="space-y-1 p-4">
          <div v-for="col in columns" :key="col.key" class="flex justify-between gap-3 text-sm">
            <span class="text-slate-400">{{ col.label }}</span>
            <span class="text-right text-slate-700">{{ cell(row, col) }}</span>
          </div>
        </li>
      </ul>
    </template>

    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-slate-500">
      <span>
        Menampilkan {{ rows.length === 0 ? 0 : (page - 1) * perPage + 1 }}–{{
          Math.min(page * perPage, total)
        }}
        dari total {{ total }} data
      </span>
      <div class="flex gap-1">
        <button
          type="button"
          class="rounded-md border border-slate-200 px-2 py-1 disabled:opacity-40"
          :disabled="page <= 1"
          @click="emit('update:page', page - 1)"
        >
          Sebelumnya
        </button>
        <button
          type="button"
          class="rounded-md border border-slate-200 px-2 py-1 disabled:opacity-40"
          :disabled="page >= lastPage()"
          @click="emit('update:page', page + 1)"
        >
          Berikutnya
        </button>
      </div>
    </div>
  </div>
</template>
