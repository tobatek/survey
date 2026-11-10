<script setup lang="ts">
// Panel "Tur Lengkap Aplikasi": di-mount sekali di App.vue supaya tetap ada
// walau pindah halaman (beda dari WalkthroughTour.vue yang spotlight dan
// cuma hidup di satu halaman). Docked di kanan, isinya interaktif betulan —
// tiap langkah manggil store/endpoint asli yang sama dengan yang dipakai
// halaman aslinya, bukan form tiruan.
//
// Langkah dengan efek nyata & terlihat pihak lain (mengaktifkan campaign =
// kirim undangan asli) SENGAJA tidak diotomatiskan dari sini — panel cuma
// mengarahkan ke halaman aslinya dan "mendengarkan" perubahan status lewat
// store yang sama (lihat watcher campaignStore di bawah), baru lanjut
// otomatis begitu admin benar-benar menekan Aktifkan di halaman itu sendiri.
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGuidedTourStore, persistGuidedTourState } from '@/features/shared/guidedTour'
import { useToast } from '@/features/shared/useToast'
import { apiFetch, ApiError } from '@/lib/api'
import { useQuestionnaireBuilderStore, type QuestionType } from '@/features/questionnaires/store/builder'
import { QUESTION_TYPE_LABELS } from '@/features/questionnaires/constants'
import { useCampaignStore } from '@/features/campaigns/store/campaign'

const tour = useGuidedTourStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const builder = useQuestionnaireBuilderStore()
const campaignStore = useCampaignStore()

const stepId = computed(() => tour.currentStep.id)

tour.$subscribe((_mutation, state) => persistGuidedTourState(state))

// ---------- Navigasi: tiap langkah butuh halaman tertentu ----------
interface RouteTarget {
  name: string
  params?: { id: string } | { campaignId: string }
  label: string
}

const routeTarget = computed<RouteTarget | null>(() => {
  switch (stepId.value) {
    case 'create-questionnaire':
      return { name: 'questionnaires', label: 'Daftar Kuesioner' }
    case 'add-section':
    case 'add-question':
    case 'finalize':
      return tour.questionnaireId
        ? { name: 'questionnaires.builder', params: { id: tour.questionnaireId }, label: 'Builder Kuesioner' }
        : { name: 'questionnaires', label: 'Daftar Kuesioner' }
    case 'activate-campaign':
      return tour.campaignId
        ? { name: 'campaigns.detail', params: { campaignId: tour.campaignId }, label: 'Halaman Campaign' }
        : null
    case 'view-results':
      return tour.campaignId
        ? { name: 'analytics.detail', params: { campaignId: tour.campaignId }, label: 'Hasil Analisa' }
        : { name: 'analytics', label: 'Hasil Analisa' }
    default:
      return null
  }
})

const onTargetRoute = computed(() => {
  const target = routeTarget.value
  if (!target) return true
  if (route.name !== target.name) return false
  if (!target.params) return true
  return Object.entries(target.params).every(([key, value]) => String(route.params[key] ?? '') === value)
})

function goToTargetRoute() {
  if (routeTarget.value) router.push({ name: routeTarget.value.name, params: routeTarget.value.params })
}

// Begitu masuk ke step builder dan sudah di halamannya, pastikan store
// builder memuat kuesioner yang sama dipakai tur (bukan sisa kuesioner lain
// yang mungkin masih tersimpan di store dari sesi sebelumnya).
watch(
  () => [stepId.value, onTargetRoute.value, tour.questionnaireId] as const,
  async ([id, onTarget, qid]) => {
    if (['add-section', 'add-question', 'finalize'].includes(id) && onTarget && qid && builder.questionnaire?.id !== qid) {
      try {
        await builder.load(qid)
      } catch {
        toast.push('Gagal memuat kuesioner untuk tur.', 'error')
      }
    }
  },
  { immediate: true },
)

const sortedSections = computed(() =>
  builder.questionnaire ? [...builder.questionnaire.sections].sort((a, b) => a.sort_order - b.sort_order) : [],
)
const firstSection = computed(() => sortedSections.value[0] ?? null)

// ---------- Langkah 1: buat kuesioner ----------
const createForm = reactive({ code: '', title: '', audience_type: 'student', identity_mode: 'anonymous' })
const creatingQuestionnaire = ref(false)
const createError = ref<string | null>(null)

async function submitCreateQuestionnaire() {
  if (!createForm.code.trim() || !createForm.title.trim()) return
  creatingQuestionnaire.value = true
  createError.value = null
  try {
    const response = await apiFetch<{ data: { id: string; code: string } }>('/questionnaires', {
      method: 'POST',
      body: JSON.stringify(createForm),
    })
    tour.setQuestionnaire(response.data.id, response.data.code)
    await router.push({ name: 'questionnaires.builder', params: { id: response.data.id } })
    tour.next()
  } catch (error) {
    createError.value = error instanceof ApiError ? error.message : 'Gagal membuat kuesioner.'
  } finally {
    creatingQuestionnaire.value = false
  }
}

// ---------- Langkah 2: tambah section ----------
const sectionTitle = ref('')
const addingSection = ref(false)

async function submitAddSection() {
  if (!sectionTitle.value.trim() || !builder.questionnaire) return
  addingSection.value = true
  try {
    const code = 'S' + String(builder.questionnaire.sections.length + 1).padStart(2, '0')
    await builder.addSection({ code, title: sectionTitle.value.trim() })
    sectionTitle.value = ''
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menambah section.', 'error')
  } finally {
    addingSection.value = false
  }
}

// ---------- Langkah 3: tambah pertanyaan ----------
const questionForm = reactive({ code: '', type: 'short_text' as QuestionType, text: '' })
const addingQuestion = ref(false)

async function submitAddQuestion() {
  if (!firstSection.value || !questionForm.code.trim() || !questionForm.text.trim()) return
  addingQuestion.value = true
  try {
    await builder.addQuestion(firstSection.value.id, { ...questionForm })
    questionForm.code = ''
    questionForm.text = ''
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menambah pertanyaan.', 'error')
  } finally {
    addingQuestion.value = false
  }
}

// ---------- Langkah 4: validasi & finalisasi ----------
const validating = ref(false)
const finalizing = ref(false)

async function runValidate() {
  validating.value = true
  try {
    await builder.validateStructure()
    if (builder.validationErrors.length === 0) toast.push('Struktur valid, siap difinalisasi.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menjalankan validasi.', 'error')
  } finally {
    validating.value = false
  }
}

async function runFinalize() {
  finalizing.value = true
  try {
    const ok = await builder.finalize()
    if (ok) {
      toast.push('Kuesioner berhasil difinalisasi.', 'success')
      tour.next()
    } else if (!builder.conflict) {
      toast.push('Struktur belum valid, jalankan Validasi untuk lihat detail.', 'error')
    }
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memfinalisasi kuesioner.', 'error')
  } finally {
    finalizing.value = false
  }
}

// ---------- Langkah 5: buka/buat campaign (aktivasi dilakukan manual di halamannya) ----------
const openingCampaign = ref(false)

async function openOrCreateCampaign() {
  if (!tour.questionnaireId) return
  openingCampaign.value = true
  try {
    if (!tour.campaignId) {
      const existing = await apiFetch<{ data: { id: string }[] }>(`/questionnaires/${tour.questionnaireId}/campaigns`)
      if (existing.data[0]) {
        tour.setCampaign(existing.data[0].id)
      } else {
        const created = await apiFetch<{ data: { id: string } }>(`/questionnaires/${tour.questionnaireId}/campaigns`, {
          method: 'POST',
        })
        tour.setCampaign(created.data.id)
      }
    }
    await router.push({ name: 'campaigns.detail', params: { campaignId: tour.campaignId! } })
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal membuka campaign.', 'error')
  } finally {
    openingCampaign.value = false
  }
}

// Dengarkan store campaign asli (dipakai juga oleh CampaignPage.vue) — begitu
// admin menekan "Aktifkan Campaign" di halamannya sendiri, tur ikut maju.
watch(
  () => campaignStore.campaign?.status,
  (status, previous) => {
    if (stepId.value === 'activate-campaign' && status === 'active' && previous !== 'active') {
      toast.push('Campaign aktif — undangan sedang dikirim ke responden.', 'success')
      tour.next()
    }
  },
)

// ---------- Langkah 6: hasil analisa ----------
function openResults() {
  if (routeTarget.value) router.push({ name: routeTarget.value.name, params: routeTarget.value.params })
}

function closeTour() {
  tour.close()
}

function skipTour() {
  tour.close()
}

// "Lanjut" manual disembunyikan di step yang punya aksi wajib dulu (buat
// kuesioner, finalisasi) SELAMA aksinya belum sukses — begitu sukses,
// langkah tersebut otomatis maju lewat tour.next() di handler-nya masing-
// masing, atau (kalau user sempat balik ke step itu lagi) tombol ini muncul
// lagi karena syaratnya sudah terpenuhi.
const canShowManualNext = computed(() => {
  if (tour.isLast) return true
  if (stepId.value === 'create-questionnaire') return Boolean(tour.questionnaireId)
  if (stepId.value === 'finalize') return builder.questionnaire?.status !== 'draft'
  return true
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-250 ease-out"
      enter-from-class="opacity-0 translate-x-6"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-6"
    >
      <div v-if="tour.active && !tour.minimized" class="fixed inset-y-0 right-0 z-[90] flex w-full max-w-[400px] items-stretch p-3 sm:p-4">
        <div class="relative w-full">
          <!-- Efek "tertumpuk": dua kartu samar di belakang, melambangkan langkah lanjutan yang masih antre. -->
          <div
            v-if="!tour.isLast"
            class="pointer-events-none absolute inset-x-3 -top-2 h-full rounded-2xl bg-white/70 shadow-md"
            style="transform: scale(0.97) translateY(-6px)"
          />
          <div
            v-if="tour.stepIndex < tour.totalSteps - 2"
            class="pointer-events-none absolute inset-x-6 -top-4 h-full rounded-2xl bg-white/50 shadow-sm"
            style="transform: scale(0.94) translateY(-12px)"
          />

          <!-- Kartu utama -->
          <div class="relative flex h-full max-h-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
            <div class="h-1.5 w-full shrink-0 bg-gradient-to-r from-brand-500 to-fuchsia-500" />

            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
              <div class="flex items-center gap-2">
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
                  Tur Lengkap
                </span>
                <span class="text-xs text-slate-400">Langkah {{ tour.stepIndex + 1 }}/{{ tour.totalSteps }}</span>
              </div>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  class="rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                  aria-label="Kecilkan tur, lihat halaman di baliknya"
                  title="Kecilkan"
                  @click="tour.minimize()"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12" />
                  </svg>
                </button>
                <button type="button" class="rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup tur" @click="closeTour">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Progress dots -->
            <div class="flex items-center gap-1.5 px-5 pt-3">
              <span
                v-for="(_, i) in tour.totalSteps"
                :key="i"
                class="h-1.5 flex-1 rounded-full transition-all"
                :class="i <= tour.stepIndex ? 'bg-gradient-to-r from-brand-500 to-fuchsia-500' : 'bg-slate-100'"
              />
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
              <h2 class="text-base font-semibold text-slate-900">{{ tour.currentStep.title }}</h2>
              <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ tour.currentStep.description }}</p>

              <!-- Prompt pindah halaman kalau belum di halaman yang dibutuhkan -->
              <div v-if="routeTarget && !onTargetRoute" class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">
                <p>Langkah ini perlu dikerjakan di halaman <strong>{{ routeTarget.label }}</strong>.</p>
                <button type="button" class="mt-2 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700" @click="goToTargetRoute">
                  Buka {{ routeTarget.label }}
                </button>
              </div>

              <template v-else>
                <!-- intro -->
                <div v-if="stepId === 'intro'" class="mt-4 space-y-2 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                  <p class="flex items-center gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white">1</span> Buat kuesioner</p>
                  <p class="flex items-center gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white">2</span> Susun section & pertanyaan</p>
                  <p class="flex items-center gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white">3</span> Finalisasi & sebar ke responden</p>
                  <p class="flex items-center gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white">4</span> Lihat hasil analisa</p>
                </div>

                <!-- create-questionnaire -->
                <form v-else-if="stepId === 'create-questionnaire' && !tour.questionnaireId" class="mt-4 space-y-3" @submit.prevent="submitCreateQuestionnaire">
                  <div>
                    <label class="text-xs font-medium text-slate-500">Kode</label>
                    <input v-model="createForm.code" type="text" required placeholder="mis. SKM-2026" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                  </div>
                  <div>
                    <label class="text-xs font-medium text-slate-500">Judul</label>
                    <input v-model="createForm.title" type="text" required placeholder="mis. Survei Kepuasan Mahasiswa 2026" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <div>
                      <label class="text-xs font-medium text-slate-500">Target</label>
                      <select v-model="createForm.audience_type" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        <option value="student">Mahasiswa</option>
                        <option value="employee">Pegawai</option>
                      </select>
                    </div>
                    <div>
                      <label class="text-xs font-medium text-slate-500">Identitas</label>
                      <select v-model="createForm.identity_mode" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        <option value="anonymous">Anonim</option>
                        <option value="identified">Teridentifikasi</option>
                      </select>
                    </div>
                  </div>
                  <p v-if="createError" class="text-xs text-rose-600">{{ createError }}</p>
                  <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-brand-500 to-fuchsia-500 px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50" :disabled="creatingQuestionnaire">
                    {{ creatingQuestionnaire ? 'Menyimpan...' : 'Buat & Lanjut ke Builder' }}
                  </button>
                </form>
                <div v-else-if="stepId === 'create-questionnaire'" class="mt-4 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-700 ring-1 ring-emerald-200">
                  Kuesioner <strong>{{ tour.questionnaireCode }}</strong> sudah dibuat. Lanjut ke langkah berikutnya.
                </div>

                <!-- add-section -->
                <div v-else-if="stepId === 'add-section'" class="mt-4 space-y-3">
                  <div v-if="sortedSections.length" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                    Section saat ini: {{ sortedSections.map((s) => s.title).join(', ') }}
                  </div>
                  <form class="flex gap-1.5" @submit.prevent="submitAddSection">
                    <input v-model="sectionTitle" type="text" placeholder="Judul section, mis. Data Diri" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <button type="submit" class="shrink-0 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="addingSection">+</button>
                  </form>
                </div>

                <!-- add-question -->
                <div v-else-if="stepId === 'add-question'" class="mt-4 space-y-3">
                  <div v-if="!firstSection" class="rounded-xl bg-amber-50 p-3 text-xs text-amber-700 ring-1 ring-amber-200">
                    Belum ada section. Kembali ke langkah sebelumnya dulu.
                  </div>
                  <template v-else>
                    <div v-if="firstSection.questions.length" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                      Pertanyaan di "{{ firstSection.title }}": {{ firstSection.questions.length }}
                    </div>
                    <form class="space-y-2 rounded-xl border border-dashed border-slate-200 p-3" @submit.prevent="submitAddQuestion">
                      <input v-model="questionForm.code" type="text" placeholder="Kode, mis. Q01" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                      <select v-model="questionForm.type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option v-for="(label, type) in QUESTION_TYPE_LABELS" :key="type" :value="type">{{ label }}</option>
                      </select>
                      <input v-model="questionForm.text" type="text" placeholder="Teks pertanyaan" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                      <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="addingQuestion">
                        Tambah Pertanyaan
                      </button>
                    </form>
                  </template>
                </div>

                <!-- finalize -->
                <div v-else-if="stepId === 'finalize'" class="mt-4 space-y-3">
                  <div v-if="builder.validationErrors.length" class="rounded-xl bg-rose-50 p-3 text-xs text-rose-700 ring-1 ring-rose-200">
                    <p class="font-medium">Perlu diperbaiki:</p>
                    <ul class="mt-1 list-disc pl-4">
                      <li v-for="e in builder.validationErrors" :key="e.code">{{ e.message }}</li>
                    </ul>
                  </div>
                  <div class="flex gap-2">
                    <button type="button" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50" :disabled="validating" @click="runValidate">
                      {{ validating ? 'Memeriksa...' : 'Validasi' }}
                    </button>
                    <button type="button" class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50" :disabled="finalizing" @click="runFinalize">
                      {{ finalizing ? 'Memproses...' : 'Finalisasi' }}
                    </button>
                  </div>
                </div>

                <!-- activate-campaign -->
                <div v-else-if="stepId === 'activate-campaign'" class="mt-4 space-y-3">
                  <button
                    v-if="!tour.campaignId"
                    type="button"
                    class="w-full rounded-lg bg-gradient-to-r from-brand-500 to-fuchsia-500 px-3 py-2 text-sm font-semibold text-white shadow-sm disabled:opacity-50"
                    :disabled="openingCampaign"
                    @click="openOrCreateCampaign"
                  >
                    {{ openingCampaign ? 'Membuka...' : 'Buat / Buka Campaign' }}
                  </button>
                  <div v-else class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                    Isi target responden, jadwal, lalu klik <strong>Aktifkan Campaign</strong> di halaman ini — tur akan otomatis lanjut begitu statusnya aktif.
                  </div>
                </div>

                <!-- view-results -->
                <div v-else-if="stepId === 'view-results'" class="mt-4">
                  <button type="button" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="openResults">
                    Buka Hasil Analisa
                  </button>
                </div>

                <!-- done -->
                <div v-else-if="stepId === 'done'" class="mt-4 rounded-xl bg-emerald-50 p-3 text-xs text-emerald-700 ring-1 ring-emerald-200">
                  Mantap, satu siklus penuh sudah kamu coba. Tombol "Tur Lengkap" di halaman Kuesioner bisa dipakai lagi kapan saja.
                </div>
              </template>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
              <button v-if="!tour.isFirst" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100" @click="tour.back()">
                Kembali
              </button>
              <span v-else />

              <div class="flex items-center gap-2">
                <button type="button" class="text-xs font-medium text-slate-400 transition hover:text-slate-600" @click="skipTour">
                  Lewati tur
                </button>
                <button
                  v-if="canShowManualNext"
                  type="button"
                  class="rounded-lg bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:brightness-110"
                  @click="tour.isLast ? closeTour() : tour.next()"
                >
                  {{ tour.isLast ? 'Tutup' : 'Lanjut' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Bentuk dikecilkan: bubble mengambang, supaya tampilan asli halaman di
         baliknya kelihatan penuh. Klik untuk membuka panel lagi. -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-90"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-90"
    >
      <div v-if="tour.active && tour.minimized" class="fixed bottom-5 right-5 z-[90]">
        <button
          type="button"
          class="group flex items-center gap-3 rounded-full bg-white py-2 pl-2 pr-4 shadow-2xl ring-1 ring-black/5 transition hover:shadow-xl"
          @click="tour.restore()"
        >
          <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-brand-500 to-fuchsia-500 text-white">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" />
            </svg>
          </span>
          <span class="text-left">
            <span class="block text-xs font-semibold text-slate-900">Tur Lengkap</span>
            <span class="block text-[11px] text-slate-400">Langkah {{ tour.stepIndex + 1 }}/{{ tour.totalSteps }} &middot; lanjutkan</span>
          </span>
        </button>
      </div>
    </Transition>
  </Teleport>
</template>
