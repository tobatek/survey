<script setup lang="ts">
// My Surveys — halaman responden (mahasiswa/pegawai), tanpa sidebar admin.
// Ini yang paling wajib mobile-first (dipakai dari HP) — FSD §11.1.
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Card from '@/features/shared/components/Card.vue'
import StatusBadge, { type SurveyStatus } from '@/features/shared/components/StatusBadge.vue'
import { useToast } from '@/features/shared/useToast'
import { ApiError } from '@/lib/api'
import { useMySurveysStore, type MySurveyItem, type RespondentStatus } from './store/mySurveys'

const store = useMySurveysStore()
const router = useRouter()
const toast = useToast()

const STATUS_BADGE: Record<RespondentStatus, SurveyStatus> = {
  not_started: 'ready',
  draft: 'active',
  completed: 'closed',
  closed: 'closed',
}

const BUTTON_LABEL: Record<RespondentStatus, string> = {
  not_started: 'Mulai Mengisi',
  draft: 'Lanjutkan Mengisi',
  completed: 'Selesai',
  closed: 'Ditutup',
}

const items = computed(() => store.items)

async function handleStart(item: MySurveyItem) {
  if (item.respondent_status === 'completed' || item.respondent_status === 'closed') return

  try {
    const sessionId = await store.start(item.eligibility_id)
    router.push({ name: 'survey.fill', params: { sessionId } })
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memulai survei.', 'error')
  }
}

onMounted(async () => {
  try {
    await store.load()
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memuat daftar survei.', 'error')
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="bg-[#1c1f3d] px-4 py-4 text-white sm:px-6">
      <div class="mx-auto flex max-w-2xl items-center gap-2">
        <div class="h-8 w-8 rounded-lg bg-white/15" />
        <span class="font-semibold uppercase tracking-wide">myAMETA Survey</span>
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6 sm:px-6">
      <h1 class="text-lg font-semibold text-slate-900">Survei Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Daftar survei yang ditujukan untuk Anda.</p>

      <div v-if="store.loading" class="mt-6 text-sm text-slate-400">Memuat...</div>

      <div v-else-if="!items.length" class="mt-6 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400">
        Belum ada survei yang ditujukan untuk Anda.
      </div>

      <div v-else class="mt-6 space-y-3">
        <Card v-for="item in items" :key="item.eligibility_id" clickable>
          <template #header>
            <p v-if="item.category" class="text-xs text-slate-400">{{ item.category }}</p>
            <h2 class="text-sm font-semibold text-slate-900">{{ item.title }}</h2>
          </template>
          <template #badge>
            <StatusBadge :status="STATUS_BADGE[item.respondent_status]" />
          </template>
          <template #actions>
            <button
              type="button"
              class="w-full rounded-full bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:brightness-110 disabled:opacity-50 sm:w-auto"
              :disabled="store.starting || item.respondent_status === 'completed' || item.respondent_status === 'closed'"
              @click="handleStart(item)"
            >
              {{ BUTTON_LABEL[item.respondent_status] }}
            </button>
          </template>
        </Card>
      </div>
    </main>
  </div>
</template>
