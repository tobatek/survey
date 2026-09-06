<script setup lang="ts">
// FSD §8.1 — daftar mahasiswa: search NIM/nama, filter, pagination
// server-side, status sinkronisasi. Sinkron dipicu async (POST /students/sync)
// lalu berjalan di queue worker — UI hanya menunjukkan bahwa job dijadwalkan.
import { onMounted, reactive, ref, watch } from 'vue'
import AppShell from '@/features/shared/components/AppShell.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import DataTable, { type Column } from '@/features/shared/components/DataTable.vue'
import { apiFetch } from '@/lib/api'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'

interface StudentRow extends Record<string, unknown> {
  nim: string
  name: string
  program_study: string | null
  status: string
  last_synced_at: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; per_page: number; total: number }
}

const columns: Column<StudentRow>[] = [
  { key: 'nim', label: 'NIM' },
  { key: 'name', label: 'Nama' },
  { key: 'program_study', label: 'Prodi' },
  { key: 'status', label: 'Status' },
  {
    key: 'last_synced_at',
    label: 'Sinkron Terakhir',
    render: (row) => (row.last_synced_at ? new Date(row.last_synced_at as string).toLocaleString('id-ID') : '-'),
  },
]

const rows = ref<StudentRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)
const syncing = ref(false)
const toast = useToast()

const filters = reactive({
  search: '',
  status: '',
})

async function loadStudents() {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      per_page: String(perPage),
    })
    if (filters.search) params.set('search', filters.search)
    if (filters.status) params.set('status', filters.status)

    const response = await apiFetch<PaginatedResponse<StudentRow>>(`/students?${params}`)
    rows.value = response.data
    total.value = response.meta.total
  } catch (error) {
    toast.push('Gagal memuat data mahasiswa.', 'error')
  } finally {
    loading.value = false
  }
}

async function triggerSync() {
  syncing.value = true
  try {
    await apiFetch('/students/sync', { method: 'POST' })
    toast.push('Sinkronisasi data mahasiswa dijadwalkan.', 'success')
  } catch (error) {
    toast.push('Gagal memulai sinkronisasi.', 'error')
  } finally {
    syncing.value = false
  }
}

function applyFilters() {
  page.value = 1
  loadStudents()
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  applyFilters()
}

watch(page, loadStudents)
onMounted(loadStudents)
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <template #topbar-actions>
      <button
        type="button"
        class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        :disabled="syncing"
        @click="triggerSync"
      >
        {{ syncing ? 'Menjadwalkan...' : 'Sinkron Sekarang' }}
      </button>
    </template>

    <h1 class="text-lg font-semibold text-slate-900">Data Mahasiswa</h1>
    <p class="mt-1 text-sm text-slate-500">Master data hasil sinkronisasi dari Portal.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">
      <FilterPanel @apply="applyFilters" @reset="resetFilters">
        <div>
          <label class="text-xs font-medium text-slate-500">Cari NIM/Nama</label>
          <input
            v-model="filters.search"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            placeholder="mis. 2023010001"
            @keyup.enter="applyFilters"
          />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500">Status</label>
          <select v-model="filters.status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Semua</option>
            <option value="active">Aktif</option>
            <option value="inactive">Tidak Aktif</option>
          </select>
        </div>
      </FilterPanel>

      <DataTable
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :page="page"
        :per-page="perPage"
        :total="total"
        @update:page="(p) => (page = p)"
      />
    </div>
  </AppShell>
</template>
