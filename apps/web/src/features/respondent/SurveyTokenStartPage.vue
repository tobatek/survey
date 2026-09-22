<script setup lang="ts">
// Jalur anonymous -- klik link undangan email (?token=...), tanpa login sama
// sekali. Langsung tukar token jadi sesi lalu redirect ke halaman pengisian.
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/lib/api'
import { useSurveyTokenStore } from './store/surveyToken'

const route = useRoute()
const router = useRouter()
const store = useSurveyTokenStore()

const errorMessage = ref<string | null>(null)

const ERROR_MESSAGES: Record<string, string> = {
  SURVEY_NOT_STARTED: 'Survei ini belum dimulai.',
  SURVEY_PAUSED: 'Survei ini sedang dijeda oleh pengelola.',
  SURVEY_CLOSED: 'Survei ini sudah ditutup.',
  NOT_ELIGIBLE: 'Tautan undangan ini tidak valid atau sudah tidak berlaku.',
  ALREADY_COMPLETED: 'Anda sudah menyelesaikan survei ini sebelumnya.',
}

onMounted(async () => {
  const token = route.query.token
  if (typeof token !== 'string' || !token) {
    errorMessage.value = 'Tautan undangan tidak lengkap.'
    return
  }

  try {
    const sessionId = await store.startByToken(token)
    router.replace({ name: 'survey.fill', params: { sessionId } })
  } catch (error) {
    if (error instanceof ApiError) {
      errorMessage.value = (error.code && ERROR_MESSAGES[error.code]) ?? error.message
    } else {
      errorMessage.value = 'Gagal membuka survei. Coba beberapa saat lagi.'
    }
  }
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
      <template v-if="errorMessage">
        <h1 class="text-sm font-semibold text-slate-900">Tidak bisa membuka survei</h1>
        <p class="mt-2 text-sm text-slate-500">{{ errorMessage }}</p>
      </template>
      <template v-else>
        <p class="text-sm text-slate-500">Menyiapkan survei Anda...</p>
      </template>
    </div>
  </div>
</template>
