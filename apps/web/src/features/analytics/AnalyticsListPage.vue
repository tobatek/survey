<script setup lang="ts">
// Fase 5 slice 7 -- daftar campaign berespons (FSD §13.1). DataTable.vue
// tidak dipakai di sini karena butuh baris yang bisa diklik/di-link
// (DataTable hanya render teks per sel) -- kartu grid sederhana sudah cukup
// untuk jumlah kolom yang dibutuhkan (judul, status, response rate, total).
import { onMounted } from 'vue'
import AppShell from '@/features/shared/components/AppShell.vue'
import StatusBadge, { type SurveyStatus } from '@/features/shared/components/StatusBadge.vue'
import { NAV_ITEMS } from '@/router/nav'
import { useAnalyticsStore } from './store/analytics'

const store = useAnalyticsStore()

onMounted(() => store.loadList())

function ratePercent(value: number | null): string {
  return value === null ? '-' : `${Math.round(value)}%`
}
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Hasil Analisa Kuesioner</h1>
    <p class="mt-1 text-sm text-slate-500">
      Daftar campaign yang sudah punya respons -- klik salah satu untuk melihat hasil lengkap.
    </p>

    <div v-if="store.listLoading" class="mt-6 p-10 text-center text-sm text-slate-400">Memuat...</div>

    <p v-else-if="store.campaigns.length === 0" class="mt-6 text-sm text-slate-400">
      Belum ada campaign dengan respons.
    </p>

    <div v-else class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="campaign in store.campaigns"
        :key="campaign.id"
        :to="`/analytics/${campaign.id}`"
        class="block rounded-xl border border-slate-200 bg-white p-4 hover:border-brand-300 hover:shadow-sm sm:p-5"
      >
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-sm font-semibold text-slate-900">{{ campaign.questionnaire_title }}</h3>
          <StatusBadge :status="campaign.status as SurveyStatus" />
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ campaign.questionnaire_code }}</p>

        <div class="mt-4 grid grid-cols-2 gap-3 text-xs">
          <div>
            <div class="text-slate-400">Response Rate</div>
            <div class="mt-0.5 font-semibold text-slate-900">{{ ratePercent(campaign.response_rate.value) }}</div>
          </div>
          <div>
            <div class="text-slate-400">Selesai / Target</div>
            <div class="mt-0.5 font-semibold text-slate-900">
              {{ campaign.completed_count }}/{{ campaign.denominator_count ?? '-' }}
            </div>
          </div>
        </div>

        <p v-if="campaign.last_response_at" class="mt-3 text-[11px] text-slate-400">
          Respons terakhir: {{ new Date(campaign.last_response_at).toLocaleString('id-ID') }}
        </p>
      </RouterLink>
    </div>
  </AppShell>
</template>
