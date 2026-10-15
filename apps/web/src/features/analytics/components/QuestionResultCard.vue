<script setup lang="ts">
// Fase 5 slice 7 -- satu kartu per pertanyaan, dispatch render sesuai tipe
// (FSD §13.5, dipersempit -- lihat plan): chart+tabel untuk tipe pilihan/
// likert/date, tabel saja untuk matrix (tanpa heatmap, di luar scope),
// stat saja untuk number, n+tombol komentar untuk teks (role-gated
// raw_data_analyst, guard di sini pakai auth store).
import { computed, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import ChartCard from '@/features/shared/components/ChartCard.vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import { useAuthStore } from '@/features/auth/store/auth'
import type { Question } from '../store/analytics'
import CommentsPanel from './CommentsPanel.vue'

const props = defineProps<{ question: Question }>()

const auth = useAuthStore()
const canViewComments = computed(() => auth.user?.roles.includes('raw_data_analyst') ?? false)
const showComments = ref(false)

const CHART_TYPES = ['single_choice', 'dropdown', 'likert', 'multiple_choice', 'date']

const chartSeries = computed(() => {
  const result = props.question.result
  if (props.question.type === 'date') {
    return [{ name: 'Jumlah', data: (result.buckets ?? []).map((b) => b.count) }]
  }
  return [{ name: 'Persentase', data: (result.options ?? []).map((o) => o.result.value ?? 0) }]
})

const chartCategories = computed(() => {
  const result = props.question.result
  if (props.question.type === 'date') {
    return (result.buckets ?? []).map((b) => b.date)
  }
  return (result.options ?? []).map((o) => o.label)
})

const chartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: chartCategories.value },
  dataLabels: { enabled: true },
  colors: ['#2563eb'],
}))
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex items-center gap-2">
      <h3 class="text-sm font-semibold text-slate-900">{{ question.text }}</h3>
      <span class="text-xs text-slate-400">{{ question.code }}</span>
    </div>

    <p v-if="question.result.suppressed" class="mt-3 text-xs text-slate-400">
      Data disembunyikan (n={{ question.result.n }}, di bawah ambang batas segmen).
    </p>

    <template v-else>
      <!-- Chart + tabel: single_choice/dropdown/likert/multiple_choice/date -->
      <template v-if="CHART_TYPES.includes(question.type)">
        <ChartCard :title="question.text" :question-code="question.code" :n="question.result.n ?? 0" hide-png-export class="mt-3">
          <VueApexCharts type="bar" height="260" :options="chartOptions" :series="chartSeries" />
        </ChartCard>

        <div v-if="question.type !== 'date'" class="mt-3 grid grid-cols-3 gap-2 text-xs sm:grid-cols-4">
          <StatCard v-if="question.result.mean != null" label="Mean" :value="question.result.mean.toFixed(2)" />
          <StatCard v-if="question.result.median != null" label="Median" :value="question.result.median.toFixed(2)" />
          <StatCard
            v-if="question.result.positive_rate?.value != null"
            label="Positive Rate"
            :value="`${Math.round(question.result.positive_rate.value)}%`"
            tone="positive"
          />
        </div>

        <table class="mt-3 w-full text-left text-xs">
          <thead class="text-slate-400"><tr><th class="py-1">Opsi</th><th class="py-1">n</th><th class="py-1">%</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="option in question.result.options ?? []" :key="option.option_code">
              <td class="py-1.5 text-slate-700">{{ option.label }}</td>
              <td class="py-1.5 text-slate-500">{{ option.result.numerator }}</td>
              <td class="py-1.5 text-slate-500">{{ option.result.value?.toFixed(1) ?? '-' }}%</td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Matrix: tabel baris x opsi, tanpa chart (di luar scope slice ini) -->
      <table v-else-if="question.type === 'matrix'" class="mt-3 w-full text-left text-xs">
        <tbody class="divide-y divide-slate-100">
          <template v-for="row in question.result.rows ?? []" :key="row.row_code">
            <tr v-if="row.suppressed" class="text-slate-400">
              <td class="py-1.5" colspan="3">{{ row.label }} -- disembunyikan (n={{ row.n }})</td>
            </tr>
            <tr v-for="option in row.options ?? []" v-else :key="`${row.row_code}-${option.option_code}`">
              <td class="py-1.5 text-slate-500">{{ row.label }}</td>
              <td class="py-1.5 text-slate-700">{{ option.label }}</td>
              <td class="py-1.5 text-slate-500">{{ option.result.value?.toFixed(1) ?? '-' }}%</td>
            </tr>
          </template>
        </tbody>
      </table>

      <!-- Number: stat saja, tidak ada distribusi dari backend untuk chart -->
      <div v-else-if="question.type === 'number'" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <StatCard label="n" :value="question.result.n ?? 0" />
        <StatCard label="Mean" :value="question.result.mean?.toFixed(2) ?? '-'" />
        <StatCard label="Median" :value="question.result.median?.toFixed(2) ?? '-'" />
        <StatCard label="Min/Max" :value="`${question.result.min ?? '-'} / ${question.result.max ?? '-'}`" />
      </div>

      <!-- Teks: n + tombol komentar (role-gated raw_data_analyst) -->
      <div v-else>
        <StatCard label="n (jawaban valid)" :value="question.result.n ?? 0" />
        <button
          v-if="canViewComments"
          type="button"
          class="mt-3 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="showComments = !showComments"
        >
          {{ showComments ? 'Sembunyikan Komentar' : 'Lihat Komentar' }}
        </button>
        <CommentsPanel v-if="showComments" :question-code="question.code" />
      </div>
    </template>
  </div>
</template>
