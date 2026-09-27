<script setup lang="ts">
// Form pengisian survei (Fase 4 slice 5) -- stepper per section mengikuti
// reachable_section_ids dari server (bukan hitung ulang branching di
// client, lihat store/surveyFill.ts), autosave dengan debounce, layar
// review sebelum kirim, submit idempoten. Menggantikan stub sebelumnya.
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import QuestionField from './components/QuestionField.vue'
import { useToast } from '@/features/shared/useToast'
import { ApiError } from '@/lib/api'
import { useSurveyFillStore, type DraftAnswers, type FillQuestion, type FillSection } from './store/surveyFill'

const route = useRoute()
const store = useSurveyFillStore()
const toast = useToast()

const AUTOSAVE_DEBOUNCE_MS = 1200

const ERROR_MESSAGES: Record<string, string> = {
  SURVEY_NOT_STARTED: 'Survei ini belum dimulai.',
  SURVEY_PAUSED: 'Survei ini sedang dijeda oleh pengelola.',
  SURVEY_CLOSED: 'Survei ini sudah ditutup.',
  NOT_ELIGIBLE: 'Anda tidak bisa mengakses survei ini.',
  ALREADY_COMPLETED: 'Anda sudah menyelesaikan survei ini sebelumnya.',
  SESSION_EXPIRED: 'Waktu pengisian survei ini sudah habis.',
  STALE_DRAFT: 'Draft sudah diperbarui dari perangkat/tab lain.',
  SUBMISSION_IN_PROGRESS: 'Pengiriman jawaban sedang diproses, coba lagi sesaat lagi.',
  VALIDATION_FAILED: 'Ada jawaban yang belum lengkap atau belum sesuai.',
}

type Screen = 'loading' | 'error' | 'fill' | 'review' | 'done'

const screen = ref<Screen>('loading')
const currentIndex = ref(0)
const answers = reactive<DraftAnswers>({})
const submitFieldErrors = ref<{ field: string; message: string }[]>([])
let autosaveTimer: ReturnType<typeof setTimeout> | null = null

const structure = computed(() => store.session?.structure ?? { sections: [] })

const reachableSections = computed<FillSection[]>(() => {
  const ids = store.session?.reachable_section_ids ?? []
  return ids
    .map((id) => structure.value.sections.find((section) => section.id === id))
    .filter((section): section is FillSection => section !== undefined)
})

const currentSection = computed<FillSection | null>(() => reachableSections.value[currentIndex.value] ?? null)

const questionSectionIndex = computed<Record<string, number>>(() => {
  const map: Record<string, number> = {}
  reachableSections.value.forEach((section, index) => {
    section.questions.forEach((question) => {
      map[question.code] = index
    })
  })
  return map
})

function syncAnswersFromServer() {
  const draft = store.session?.draft_answers ?? {}
  Object.keys(answers).forEach((key) => delete answers[key])
  Object.assign(answers, draft)
}

function cancelPendingAutosave() {
  if (autosaveTimer) {
    clearTimeout(autosaveTimer)
    autosaveTimer = null
  }
}

async function flushSave(): Promise<void> {
  cancelPendingAutosave()
  try {
    const ok = await store.saveDraft({ ...answers })
    syncAnswersFromServer()
    if (!ok) {
      toast.push('Draft dimuat ulang karena diperbarui dari sesi lain.', 'info')
    }
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menyimpan draft.', 'error')
  }
}

function scheduleAutosave() {
  cancelPendingAutosave()
  autosaveTimer = setTimeout(() => {
    flushSave()
  }, AUTOSAVE_DEBOUNCE_MS)
}

// Dipicu langsung dari perubahan input (bukan `watch(answers, ..., {deep})`)
// supaya sinkronisasi programatik dari server (syncAnswersFromServer, dipanggil
// habis load/save) tidak ikut memicu autosave lagi -- itu akan jadi loop tak
// berhenti (sync -> watch -> save -> sync -> watch -> ...).
function setAnswer(code: string, value: unknown) {
  answers[code] = value
  scheduleAutosave()
}

async function goNext() {
  await flushSave()
  if (currentIndex.value < reachableSections.value.length - 1) {
    currentIndex.value += 1
  } else {
    screen.value = 'review'
  }
}

function goBack() {
  if (screen.value === 'review') {
    screen.value = 'fill'
    return
  }
  if (currentIndex.value > 0) currentIndex.value -= 1
}

function editSection(index: number) {
  currentIndex.value = index
  screen.value = 'fill'
}

function optionLabel(question: FillQuestion, code: string): string {
  return question.options.find((option) => option.code === code)?.label ?? code
}

function formatAnswer(question: FillQuestion, value: unknown): string {
  if (value === undefined || value === null || value === '') return '(Belum diisi)'

  if (question.type === 'single_choice' || question.type === 'dropdown' || question.type === 'likert') {
    return optionLabel(question, value as string)
  }
  if (question.type === 'multiple_choice' && Array.isArray(value)) {
    return value.length ? value.map((code) => optionLabel(question, code as string)).join(', ') : '(Belum diisi)'
  }
  if (question.type === 'matrix' && value && typeof value === 'object') {
    const rows = question.matrix_rows
      .map((row) => {
        const code = (value as Record<string, string>)[row.code]
        return code ? `${row.label}: ${optionLabel(question, code)}` : null
      })
      .filter((line): line is string => line !== null)
    return rows.length ? rows.join('; ') : '(Belum diisi)'
  }
  return String(value)
}

async function handleSubmit() {
  submitFieldErrors.value = []
  await flushSave()
  try {
    await store.submit()
    screen.value = 'done'
  } catch (error) {
    if (error instanceof ApiError && error.code === 'VALIDATION_FAILED' && Array.isArray(error.fields)) {
      submitFieldErrors.value = (error.fields as { field: string; message: string }[]).map((f) => ({
        field: f.field,
        message: f.message,
      }))
      toast.push(ERROR_MESSAGES.VALIDATION_FAILED, 'error')
    } else if (error instanceof ApiError) {
      toast.push((error.code && ERROR_MESSAGES[error.code]) ?? error.message, 'error')
    } else {
      toast.push('Gagal mengirim jawaban. Coba lagi.', 'error')
    }
  }
}

function jumpToField(field: string) {
  const index = questionSectionIndex.value[field]
  if (index !== undefined) editSection(index)
}

onMounted(async () => {
  const sessionId = route.params.sessionId as string
  const token = typeof route.query.token === 'string' ? route.query.token : null
  store.setToken(token)

  await store.load(sessionId)

  if (store.loadError) {
    screen.value = 'error'
    return
  }

  syncAnswersFromServer()
  screen.value = 'fill'
})

onBeforeUnmount(() => {
  cancelPendingAutosave()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-2xl items-center gap-2">
        <div class="h-8 w-8 rounded-lg bg-brand-600" />
        <span class="font-semibold text-slate-900">myAMETA Survey</span>
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6 sm:px-6">
      <div v-if="screen === 'loading'" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400">
        Memuat survei...
      </div>

      <div v-else-if="screen === 'error'" class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <h1 class="text-sm font-semibold text-slate-900">Tidak bisa mengisi survei</h1>
        <p class="mt-2 text-sm text-slate-500">
          {{ (store.loadError?.code && ERROR_MESSAGES[store.loadError.code]) ?? store.loadError?.message }}
        </p>
      </div>

      <div v-else-if="screen === 'done'" class="rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <h1 class="text-sm font-semibold text-slate-900">Terima kasih!</h1>
        <p class="mt-2 text-sm text-slate-500">Jawaban Anda sudah berhasil dikirim.</p>
      </div>

      <template v-else-if="screen === 'fill' && currentSection">
        <p class="text-xs text-slate-400">Bagian {{ currentIndex + 1 }} dari {{ reachableSections.length }}</p>
        <h1 class="mt-1 text-base font-semibold text-slate-900">{{ currentSection.title }}</h1>

        <div class="mt-5 space-y-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <QuestionField
            v-for="question in currentSection.questions"
            :key="question.code"
            :question="question"
            :model-value="answers[question.code]"
            @update:model-value="(value) => setAnswer(question.code, value)"
          />
        </div>

        <p v-if="store.saving" class="mt-2 text-xs text-slate-400">Menyimpan...</p>

        <div class="mt-5 flex justify-between gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 disabled:opacity-40"
            :disabled="currentIndex === 0"
            @click="goBack"
          >
            Kembali
          </button>
          <button
            type="button"
            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white"
            @click="goNext"
          >
            {{ currentIndex < reachableSections.length - 1 ? 'Lanjut' : 'Tinjau Jawaban' }}
          </button>
        </div>
      </template>

      <template v-else-if="screen === 'review'">
        <h1 class="text-base font-semibold text-slate-900">Tinjau Jawaban</h1>
        <p class="mt-1 text-sm text-slate-500">Periksa jawaban Anda sebelum mengirim. Jawaban tidak bisa diubah lagi setelah dikirim.</p>

        <div class="mt-5 space-y-4">
          <div v-for="(section, index) in reachableSections" :key="section.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-slate-900">{{ section.title }}</h2>
              <button type="button" class="text-xs font-medium text-brand-600" @click="editSection(index)">Ubah</button>
            </div>
            <dl class="mt-2 space-y-2">
              <div v-for="question in section.questions" :key="question.code">
                <dt class="text-xs text-slate-500">{{ question.text }}</dt>
                <dd
                  class="text-sm"
                  :class="submitFieldErrors.some((e) => e.field === question.code) ? 'text-rose-600' : 'text-slate-800'"
                >
                  {{ formatAnswer(question, answers[question.code]) }}
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <div v-if="submitFieldErrors.length" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
          <p class="font-medium">Beberapa jawaban perlu dilengkapi:</p>
          <ul class="mt-1 list-inside list-disc">
            <li v-for="err in submitFieldErrors" :key="err.field">
              {{ err.message }}
              <button type="button" class="ml-1 underline" @click="jumpToField(err.field)">Perbaiki</button>
            </li>
          </ul>
        </div>

        <div class="mt-5 flex justify-between gap-2">
          <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600" @click="goBack">
            Kembali
          </button>
          <button
            type="button"
            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            :disabled="store.submitting"
            @click="handleSubmit"
          >
            {{ store.submitting ? 'Mengirim...' : 'Kirim Jawaban' }}
          </button>
        </div>
      </template>
    </main>
  </div>
</template>
