// Store global (Pinia) untuk "Tur Lengkap Aplikasi": tur step-by-step yang
// mengikuti siklus hidup kuesioner sungguhan (buat -> susun section/
// pertanyaan -> finalisasi -> sebar ke responden -> lihat hasil analisa).
// Beda dari WalkthroughTour.vue (spotlight per-elemen di satu halaman), tur
// ini punya panel sendiri (GuidedTourPanel.vue) yang persist lintas halaman
// karena di-mount sekali di App.vue, bukan di halaman Kuesioner saja.
import { defineStore } from 'pinia'

export interface GuidedTourStep {
  id: string
  title: string
  description: string
}

export const GUIDED_TOUR_STEPS: GuidedTourStep[] = [
  {
    id: 'intro',
    title: 'Selamat datang',
    description:
      'Tur ini mengajak kamu mengikuti satu siklus penuh: buat kuesioner, susun pertanyaannya, sebarkan ke responden, lalu lihat hasil analisanya. Setiap langkah bisa langsung kamu isi dari sini.',
  },
  {
    id: 'create-questionnaire',
    title: 'Langkah 1 — Buat kuesioner',
    description: 'Semua berawal dari sini: kasih kode & judul, lalu tentukan kuesioner ini untuk siapa.',
  },
  {
    id: 'add-section',
    title: 'Langkah 2 — Susun section',
    description: 'Kuesioner dibagi jadi beberapa section (bagian) supaya pertanyaan lebih terorganisir. Buat minimal satu dulu.',
  },
  {
    id: 'add-question',
    title: 'Langkah 3 — Tambah pertanyaan',
    description: 'Isi section tadi dengan pertanyaan. Coba tambah satu pertanyaan ke section pertama.',
  },
  {
    id: 'finalize',
    title: 'Langkah 4 — Validasi & finalisasi',
    description: 'Sebelum disebar, strukturnya dicek dulu (validasi), baru difinalisasi supaya tidak bisa diubah sembarangan lagi.',
  },
  {
    id: 'activate-campaign',
    title: 'Langkah 5 — Sebarkan ke responden',
    description:
      'Kuesioner yang sudah final disebar lewat "Campaign": tentukan target respondennya, jadwalnya, baru diaktifkan. Begitu diaktifkan, undangan asli terkirim — jadi bagian ini kamu isi sendiri langsung di halamannya.',
  },
  {
    id: 'view-results',
    title: 'Langkah 6 — Lihat hasil analisa',
    description: 'Begitu ada responden yang mengisi, hasilnya terkumpul otomatis di sini: funnel, rincian per segmen, sampai export laporan.',
  },
  {
    id: 'done',
    title: 'Tur selesai',
    description: 'Itulah satu siklus penuh myAMETA Survey. Kamu bisa buka tur ini lagi kapan saja lewat tombol "Tur Lengkap".',
  },
]

interface GuidedTourState {
  active: boolean
  minimized: boolean
  stepIndex: number
  questionnaireId: string | null
  questionnaireCode: string | null
  campaignId: string | null
}

const SESSION_KEY = 'myameta.guidedTour.session'

// Supaya progres tidak hilang kalau tab di-refresh di tengah tur (mis.
// setelah pindah halaman ke builder/campaign) — disimpan per tab session,
// bukan permanen, karena ini cuma sesi tur yang sedang berjalan.
function loadPersisted(): Partial<GuidedTourState> {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch {
    return {}
  }
}

export function persistGuidedTourState(state: GuidedTourState) {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(state))
  } catch {
    // sessionStorage bisa diblokir (mode privat) — tur tetap jalan, cuma tidak
    // bertahan setelah refresh.
  }
}

export const useGuidedTourStore = defineStore('guidedTour', {
  state: (): GuidedTourState => ({
    active: false,
    minimized: false,
    stepIndex: 0,
    questionnaireId: null,
    questionnaireCode: null,
    campaignId: null,
    ...loadPersisted(),
  }),
  getters: {
    currentStep: (state): GuidedTourStep => GUIDED_TOUR_STEPS[state.stepIndex],
    isFirst: (state): boolean => state.stepIndex === 0,
    isLast: (state): boolean => state.stepIndex === GUIDED_TOUR_STEPS.length - 1,
    totalSteps: (): number => GUIDED_TOUR_STEPS.length,
  },
  actions: {
    start() {
      this.active = true
      this.minimized = false
      this.stepIndex = 0
    },
    minimize() {
      this.minimized = true
    },
    restore() {
      this.minimized = false
    },
    toggleMinimized() {
      this.minimized = !this.minimized
    },
    next() {
      if (this.stepIndex < GUIDED_TOUR_STEPS.length - 1) this.stepIndex += 1
      else this.active = false
    },
    back() {
      if (this.stepIndex > 0) this.stepIndex -= 1
    },
    close() {
      this.active = false
    },
    setQuestionnaire(id: string, code: string) {
      this.questionnaireId = id
      this.questionnaireCode = code
    },
    setCampaign(id: string) {
      this.campaignId = id
    },
  },
})
