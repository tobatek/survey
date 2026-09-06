<script setup lang="ts">
// Setara StudentListPage — FSD §9.
import { onMounted, reactive, ref, watch } from 'vue'
import AppShell from '@/features/shared/components/AppShell.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import DataTable, { type Column } from '@/features/shared/components/DataTable.vue'
import { apiFetch } from '@/lib/api'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'

interface EmployeeRow extends Record<string, unknown> {
  employee_number: string
  name: string
  employee_type: string | null
  work_unit: string | null
  status: string
  last_synced_at: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; per_page: number; total: number }
}

const columns: Column<EmployeeRow>[] = [
  { key: 'employee_number', label: 'NIP/Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'employee_type', label: 'Jenis' },
  { key: 'work_unit', label: 'Unit' },
  { key: 'status', label: 'Status' },
]

const rows = ref<EmployeeRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 15
const loading = ref(false)
const syncing = ref(false)
const toast = useToast()

const filters = reactive({
  search: '',
  employee_type: '',
})

async function loadEmployees() {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: String(page.value), per_page: String(perPage) })
    if (filters.search) params.set('search', filters.search)
    if (filters.employee_type) params.set('employee_type', filters.employee_type)

    const response = await apiFetch<PaginatedResponse<EmployeeRow>>(`/employees?${params}`)
    rows.value = response.data
    total.value = response.meta.total
  } catch (error) {
    toast.push('Gagal memuat data pegawai.', 'error')
  } finally {
    loading.value = false
  }
}

async function triggerSync() {
  syncing.value = true
  try {
    await apiFetch('/employees/sync', { method: 'POST' })
    toast.push('Sinkronisasi data pegawai dijadwalkan.', 'success')
  } catch (error) {
    toast.push('Gagal memulai sinkronisasi.', 'error')
  } finally {
    syncing.value = false
  }
}

function applyFilters() {
  page.value = 1
  loadEmployees()
}

function resetFilters() {
  filters.search = ''
  filters.employee_type = ''
  applyFilters()
}

watch(page, loadEmployees)
onMounted(loadEmployees)
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

    <h1 class="text-lg font-semibold text-slate-900">Data Pegawai</h1>
    <p class="mt-1 text-sm text-slate-500">Master data dosen &amp; tenaga kependidikan.</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">
      <FilterPanel @apply="applyFilters" @reset="resetFilters">
        <div>
          <label class="text-xs font-medium text-slate-500">Cari NIP/Nama</label>
          <input
            v-model="filters.search"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            @keyup.enter="applyFilters"
          />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500">Jenis Pegawai</label>
          <select v-model="filters.employee_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="">Semua</option>
            <option value="dosen">Dosen</option>
            <option value="tendik">Tenaga Kependidikan</option>
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
