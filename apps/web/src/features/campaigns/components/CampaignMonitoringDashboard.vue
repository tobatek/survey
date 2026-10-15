<script setup lang="ts">
// Dashboard pemantauan Campaign (slice 7b): funnel status, response rate,
// breakdown per prodi/unit (backend: CampaignMonitoringAction) + tombol
// pause/resume/close. Visual funnel & breakdown pakai bar CSS custom (bukan
// library chart) -- proyek ini belum pasang chart library apa pun, lihat
// komentar di ChartCard.vue.
import { computed, onMounted, ref } from 'vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import { useToast } from '@/features/shared/useToast'
import { useAuthStore } from '@/features/auth/store/auth'
import { ApiError } from '@/lib/api'
import { useCampaignStore, type CampaignFunnel } from '../store/campaign'
import CloseCampaignModal from './CloseCampaignModal.vue'

const store = useCampaignStore()
const auth = useAuthStore()
const toast = useToast()

const showCloseModal = ref(false)

const canManage = computed(
  () => auth.user?.roles.some((r) => r === 'lpm_admin' || r === 'survey_operator') ?? false,
)

const FUNNEL_STAGES: Array<{ key: keyof CampaignFunnel; label: string; tone: 'neutral' | 'positive' | 'warning' | 'danger' }> = [
  { key: 'eligible', label: 'Memenuhi Syarat', tone: 'neutral' },
  { key: 'invited', label: 'Diundang', tone: 'neutral' },
  { key: 'opened', label: 'Dibuka', tone: 'neutral' },
  { key: 'started', label: 'Dimulai', tone: 'neutral' },
  { key: 'completed', label: 'Selesai', tone: 'positive' },
]

const SIDE_STAGES: Array<{ key: keyof CampaignFunnel; label: string; tone: 'warning' | 'danger' }> = [
  { key: 'expired', label: 'Kedaluwarsa', tone: 'warning' },
  { key: 'excluded', label: 'Dikecualikan', tone: 'danger' },
]

const BAR_COLOR: Record<string, string> = {
  neutral: 'bg-slate-400',
  positive: 'bg-green-500',
  warning: 'bg-amber-500',
  danger: 'bg-rose-500',
}

const funnelBase = computed(() => {
  const funnel = store.monitoring?.funnel
  if (!funnel) return 1
  return Math.max(funnel.eligible, store.monitoring?.denominator_count ?? 0, 1)
})

function barWidth(value: number, base: number): string {
  if (base <= 0) return '0%'
  return `${Math.min(100, Math.round((value / base) * 100))}%`
}

async function loadMonitoring() {
  if (!store.campaign) return
  try {
    await store.loadMonitoring(store.campaign.id)
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memuat data pemantauan.', 'error')
  }
}

async function runPause() {
  try {
    await store.pause()
    toast.push('Campaign dijeda.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menjeda campaign.', 'error')
  }
}

async function runResume() {
  try {
    await store.resume()
    toast.push('Campaign diaktifkan kembali.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal melanjutkan campaign.', 'error')
  }
}

async function runClose() {
  showCloseModal.value = false
  try {
    await store.close()
    toast.push('Campaign ditutup.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menutup campaign.', 'error')
  }
}

onMounted(loadMonitoring)
</script>

<template>
  <div class="mt-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-4">
      <div class="flex flex-wrap gap-2">
        <template v-if="canManage">
          <button
            v-if="store.campaign?.status === 'active'"
            type="button"
            class="rounded-lg border border-amber-300 px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-50"
            :disabled="store.saving"
            @click="runPause"
          >
            Jeda
          </button>
          <button
            v-if="store.campaign?.status === 'paused'"
            type="button"
            class="rounded-lg border border-green-300 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50 disabled:opacity-50"
            :disabled="store.saving"
            @click="runResume"
          >
            Lanjutkan
          </button>
          <button
            v-if="store.campaign?.status === 'active' || store.campaign?.status === 'paused'"
            type="button"
            class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-50"
            :disabled="store.saving"
            @click="showCloseModal = true"
          >
            Tutup Campaign
          </button>
        </template>
      </div>
      <button
        type="button"
        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
        @click="loadMonitoring"
      >
        Muat Ulang
      </button>
    </div>

    <div v-if="store.monitoring" class="space-y-4">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <StatCard label="Total Target" :value="store.monitoring.denominator_count" />
        <StatCard label="Selesai Mengisi" :value="store.monitoring.funnel.completed" tone="positive" />
        <StatCard
          label="Tingkat Respons"
          :value="`${Math.round(store.monitoring.response_rate * 100)}%`"
          tone="positive"
        />
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Corong Status</h2>
        <div class="mt-4 space-y-3">
          <div v-for="stage in FUNNEL_STAGES" :key="stage.key">
            <div class="flex items-center justify-between text-xs text-slate-500">
              <span>{{ stage.label }}</span>
              <span class="font-medium text-slate-700">{{ store.monitoring.funnel[stage.key] }}</span>
            </div>
            <div class="mt-1 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full"
                :class="BAR_COLOR[stage.tone]"
                :style="{ width: barWidth(store.monitoring.funnel[stage.key], funnelBase) }"
              />
            </div>
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-3">
          <div v-for="stage in SIDE_STAGES" :key="stage.key" class="text-xs text-slate-500">
            {{ stage.label }}:
            <span class="font-medium" :class="stage.tone === 'danger' ? 'text-rose-600' : 'text-amber-600'">
              {{ store.monitoring.funnel[stage.key] }}
            </span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
          <h2 class="text-sm font-semibold text-slate-900">Rincian per Program Studi</h2>
          <p v-if="!store.monitoring.breakdown.students.length" class="mt-2 text-xs text-slate-400">
            Tidak ada target mahasiswa.
          </p>
          <div v-else class="mt-3 space-y-3">
            <div v-for="row in store.monitoring.breakdown.students" :key="row.group">
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>{{ row.group }}</span>
                <span class="font-medium text-slate-700">{{ row.completed }}/{{ row.total }}</span>
              </div>
              <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-500" :style="{ width: barWidth(row.completed, row.total) }" />
              </div>
              <p v-if="row.group === 'lainnya'" class="mt-0.5 text-[11px] text-slate-400">
                Kelompok kecil digabung untuk menjaga privasi.
              </p>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
          <h2 class="text-sm font-semibold text-slate-900">Rincian per Unit Kerja</h2>
          <p v-if="!store.monitoring.breakdown.employees.length" class="mt-2 text-xs text-slate-400">
            Tidak ada target pegawai.
          </p>
          <div v-else class="mt-3 space-y-3">
            <div v-for="row in store.monitoring.breakdown.employees" :key="row.group">
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>{{ row.group }}</span>
                <span class="font-medium text-slate-700">{{ row.completed }}/{{ row.total }}</span>
              </div>
              <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-500" :style="{ width: barWidth(row.completed, row.total) }" />
              </div>
              <p v-if="row.group === 'lainnya'" class="mt-0.5 text-[11px] text-slate-400">
                Kelompok kecil digabung untuk menjaga privasi.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400">
      Memuat data pemantauan...
    </div>

    <CloseCampaignModal v-if="showCloseModal" @close="showCloseModal = false" @confirm="runClose" />
  </div>
</template>
