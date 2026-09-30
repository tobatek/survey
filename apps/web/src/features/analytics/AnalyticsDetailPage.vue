<script setup lang="ts">
// Fase 5 slice 7 -- halaman detail "Hasil Analisa Kuesioner": filter segmen,
// funnel+chart, tombol export, dan hasil per pertanyaan. Judul campaign
// diambil dari daftar (endpoint summary/questions backend tidak
// mengembalikannya) -- lihat store.campaignTitle.
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import AppShell from '@/features/shared/components/AppShell.vue'
import ChartCard from '@/features/shared/components/ChartCard.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'
import { ApiError } from '@/lib/api'
import { useAnalyticsStore } from './store/analytics'
import ExportPanel from './components/ExportPanel.vue'
import QuestionResultCard from './components/QuestionResultCard.vue'

const route = useRoute()
const router = useRouter()
const store = useAnalyticsStore()
const toast = useToast()

const campaignId = computed(() => route.params.campaignId as string)
const title = computed(() => store.campaignTitle(campaignId.value) ?? campaignId.value)

async function loadDetail() {
  try {
    await store.ensureListLoaded()
    await store.loadDetail(campaignId.value)
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memuat hasil analisa.', 'error')
  }
}

async function runExportPng() {
  try {
    await store.requestExport('png')
    toast.push('Export PNG (funnel) diqueue.', 'info')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal membuat export.', 'error')
  }
}

const funnelSeries = computed(() => {
  const funnel = store.summary?.funnel
  if (!funnel) return [{ name: 'Jumlah', data: [] }]
  return [{ name: 'Jumlah', data: Object.values(funnel) }]
})

const funnelCategories = computed(() => {
  const funnel = store.summary?.funnel
  return funnel ? Object.keys(funnel) : []
})

const funnelChartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: funnelCategories.value },
  dataLabels: { enabled: true },
  colors: ['#2563eb'],
}))

watch(campaignId, loadDetail)
onMounted(loadDetail)
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click="router.back()">
      &larr; Kembali
    </button>

    <h1 class="mt-1 text-lg font-semibold text-slate-900">{{ title }}</h1>

    <div v-if="store.detailLoading && !store.summary" class="mt-6 p-10 text-center text-sm text-slate-400">
      Memuat hasil analisa...
    </div>

    <div v-else class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">
      <div class="space-y-4">
        <FilterPanel @apply="store.applyFilter" @reset="store.resetFilter">
          <div>
            <label class="text-xs font-medium text-slate-500">Jenis Responden</label>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
              <label class="flex items-center gap-1.5">
                <input type="checkbox" value="student" v-model="store.filterForm.respondentTypes" />
                Mahasiswa
              </label>
              <label class="flex items-center gap-1.5">
                <input type="checkbox" value="employee" v-model="store.filterForm.respondentTypes" />
                Pegawai
              </label>
            </div>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Fakultas (pisah koma)</label>
            <input v-model="store.filterForm.facultyNames" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Prodi (kode, pisah koma)</label>
            <input v-model="store.filterForm.programStudyCodes" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Angkatan (pisah koma)</label>
            <input v-model="store.filterForm.cohortYears" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Unit Kerja (pisah koma)</label>
            <input v-model="store.filterForm.workUnitNames" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Periode Submit</label>
            <div class="mt-1 flex gap-2">
              <input v-model="store.filterForm.submittedFrom" type="date" class="w-1/2 rounded-lg border border-slate-200 px-2 py-2 text-sm" />
              <input v-model="store.filterForm.submittedTo" type="date" class="w-1/2 rounded-lg border border-slate-200 px-2 py-2 text-sm" />
            </div>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Cari Pertanyaan</label>
            <input v-model="store.filterForm.questionSearch" type="text" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
        </FilterPanel>

        <ExportPanel />
      </div>

      <div class="space-y-4">
        <template v-if="store.summary?.suppressed">
          <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400">
            Data disembunyikan -- jumlah responden di bawah ambang batas minimum_segment_size
            ({{ store.summary.minimum_segment_size }}).
          </div>
        </template>

        <template v-else-if="store.summary">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <StatCard
              label="Response Rate"
              :value="store.summary.response_rate?.value != null ? `${Math.round(store.summary.response_rate.value)}%` : '-'"
              tone="positive"
            />
            <StatCard
              label="Completion Rate"
              :value="store.summary.completion_rate?.value != null ? `${Math.round(store.summary.completion_rate.value)}%` : '-'"
            />
            <StatCard label="Total Responses" :value="store.summary.total_responses ?? 0" />
          </div>

          <ChartCard title="Funnel Respons" :n="store.summary.total_responses ?? 0" @export-png="runExportPng">
            <VueApexCharts type="bar" height="280" :options="funnelChartOptions" :series="funnelSeries" />
          </ChartCard>
        </template>

        <template v-for="section in store.questions?.sections ?? []" :key="section.code">
          <h2 class="pt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ section.title }}</h2>
          <QuestionResultCard v-for="question in section.questions" :key="question.code" :question="question" />
        </template>
      </div>
    </div>
  </AppShell>
</template>
