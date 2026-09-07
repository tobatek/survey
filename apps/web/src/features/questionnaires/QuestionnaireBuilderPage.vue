<script setup lang="ts">
// Builder dasar Fase 2 (slice pertama — lihat plan): CRUD section/pertanyaan
// semua 9 tipe MVP, opsi & baris matrix, validasi, finalisasi. BELUM termasuk:
// drag-drop reorder (pakai tombol naik/turun), preview mode, branch simulator
// — lihat backlog di plan file.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/features/shared/components/AppShell.vue'
import StatusBadge from '@/features/shared/components/StatusBadge.vue'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'
import {
  useQuestionnaireBuilderStore,
  questionNeedsOptions,
  type QuestionType,
  type SectionNode,
} from './store/builder'
import { ApiError } from '@/lib/api'

const QUESTION_TYPE_LABELS: Record<QuestionType, string> = {
  short_text: 'Teks Singkat',
  long_text: 'Teks Panjang',
  single_choice: 'Pilihan Tunggal',
  multiple_choice: 'Pilihan Ganda',
  dropdown: 'Dropdown',
  likert: 'Skala Likert',
  matrix: 'Matrix',
  number: 'Angka',
  date: 'Tanggal',
}

const route = useRoute()
const router = useRouter()
const toast = useToast()
const store = useQuestionnaireBuilderStore()

const selectedSectionId = ref<string | null>(null)
const newSectionTitle = ref('')
const newQuestion = reactive({ code: '', type: 'short_text' as QuestionType, text: '' })

const selectedSection = computed<SectionNode | null>(() => {
  if (!store.questionnaire || !selectedSectionId.value) return null
  return store.questionnaire.sections.find((s) => s.id === selectedSectionId.value) ?? null
})

async function reportError(action: () => Promise<void>, fallback: string) {
  try {
    await action()
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : fallback, 'error')
  }
}

async function loadQuestionnaire() {
  await reportError(async () => {
    await store.load(route.params.id as string)
    if (!selectedSectionId.value && store.questionnaire?.sections.length) {
      selectedSectionId.value = store.questionnaire.sections[0].id
    }
  }, 'Gagal memuat kuesioner.')
}

async function addSection() {
  if (!newSectionTitle.value.trim()) return
  const code = 'S' + String((store.questionnaire?.sections.length ?? 0) + 1).padStart(2, '0')
  await reportError(() => store.addSection({ code, title: newSectionTitle.value.trim() }), 'Gagal menambah section.')
  newSectionTitle.value = ''
}

async function removeSection(sectionId: string) {
  await reportError(() => store.deleteSection(sectionId), 'Gagal menghapus section.')
  if (selectedSectionId.value === sectionId) selectedSectionId.value = null
}

async function moveSection(section: SectionNode, direction: -1 | 1) {
  if (!store.questionnaire) return
  const sections = [...store.questionnaire.sections].sort((a, b) => a.sort_order - b.sort_order)
  const index = sections.findIndex((s) => s.id === section.id)
  const swapWith = sections[index + direction]
  if (!swapWith) return

  await reportError(async () => {
    await store.updateSection(section.id, { sort_order: swapWith.sort_order })
    await store.updateSection(swapWith.id, { sort_order: section.sort_order })
  }, 'Gagal mengubah urutan section.')
}

async function addQuestion() {
  if (!selectedSectionId.value || !newQuestion.code.trim() || !newQuestion.text.trim()) return
  await reportError(
    () => store.addQuestion(selectedSectionId.value!, { ...newQuestion }),
    'Gagal menambah pertanyaan.',
  )
  newQuestion.code = ''
  newQuestion.text = ''
}

async function removeQuestion(questionId: string) {
  await reportError(() => store.deleteQuestion(questionId), 'Gagal menghapus pertanyaan.')
}

async function toggleRequired(questionId: string, current: boolean) {
  await reportError(() => store.updateQuestion(questionId, { required: !current }), 'Gagal mengubah pertanyaan.')
}

const newOptionLabel = reactive<Record<string, string>>({})

async function addOption(questionId: string) {
  const label = newOptionLabel[questionId]?.trim()
  if (!label) return
  const code = 'O' + Date.now().toString(36).slice(-4)
  await reportError(() => store.addOption(questionId, { code, label }), 'Gagal menambah opsi.')
  newOptionLabel[questionId] = ''
}

async function removeOption(optionId: string) {
  await reportError(() => store.deleteOption(optionId), 'Gagal menghapus opsi.')
}

async function setOptionTarget(optionId: string, targetSectionId: string) {
  await reportError(
    () => store.updateOption(optionId, { target_section_id: targetSectionId || null }),
    'Gagal mengatur branching.',
  )
}

const newMatrixRowLabel = reactive<Record<string, string>>({})

async function addMatrixRow(questionId: string) {
  const label = newMatrixRowLabel[questionId]?.trim()
  if (!label) return
  const code = 'R' + Date.now().toString(36).slice(-4)
  await reportError(() => store.addMatrixRow(questionId, { code, label }), 'Gagal menambah baris matrix.')
  newMatrixRowLabel[questionId] = ''
}

async function removeMatrixRow(rowId: string) {
  await reportError(() => store.deleteMatrixRow(rowId), 'Gagal menghapus baris matrix.')
}

async function runValidate() {
  await reportError(() => store.validateStructure(), 'Gagal menjalankan validasi.')
  if (store.validationErrors.length === 0) {
    toast.push('Struktur kuesioner valid, siap difinalisasi.', 'success')
  }
}

async function runFinalize() {
  await reportError(async () => {
    const ok = await store.finalize()
    if (ok) {
      toast.push('Kuesioner berhasil difinalisasi.', 'success')
    } else if (!store.conflict) {
      toast.push('Struktur belum valid, jalankan Validasi untuk detail.', 'error')
    }
  }, 'Gagal memfinalisasi kuesioner.')
}

async function reloadAfterConflict() {
  await loadQuestionnaire()
  store.conflict = false
}

watch(() => route.params.id, loadQuestionnaire)
onMounted(loadQuestionnaire)
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <div v-if="store.loading" class="p-10 text-center text-sm text-slate-400">Memuat kuesioner...</div>

    <template v-else-if="store.questionnaire">
      <div v-if="store.conflict" class="mb-4 flex items-center justify-between rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
        <span>Kuesioner ini sudah diubah oleh sesi lain. Muat ulang untuk melihat versi terbaru.</span>
        <button type="button" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-medium text-white" @click="reloadAfterConflict">
          Muat Ulang
        </button>
      </div>

      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click="router.push({ name: 'questionnaires' })">
            &larr; Kembali ke daftar
          </button>
          <div class="mt-1 flex items-center gap-2">
            <h1 class="text-lg font-semibold text-slate-900">{{ store.questionnaire.title }}</h1>
            <StatusBadge :status="store.questionnaire.status" />
          </div>
          <p class="text-xs text-slate-400">{{ store.questionnaire.code }} &middot; versi {{ store.lockVersion }}</p>
        </div>

        <div class="flex gap-2">
          <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50" @click="runValidate">
            Validasi
          </button>
          <button
            v-if="store.isEditable"
            type="button"
            class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            :disabled="store.saving"
            @click="runFinalize"
          >
            Finalisasi
          </button>
        </div>
      </div>

      <div v-if="store.validationErrors.length || store.validationWarnings.length" class="mt-4 space-y-2">
        <div v-if="store.validationErrors.length" class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 ring-1 ring-rose-200">
          <p class="font-medium">Error (harus diperbaiki sebelum finalisasi):</p>
          <ul class="mt-1 list-disc pl-4">
            <li v-for="e in store.validationErrors" :key="e.code">{{ e.message }}</li>
          </ul>
        </div>
        <div v-if="store.validationWarnings.length" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700 ring-1 ring-amber-200">
          <p class="font-medium">Peringatan:</p>
          <ul class="mt-1 list-disc pl-4">
            <li v-for="w in store.validationWarnings" :key="w.code">{{ w.message }}</li>
          </ul>
        </div>
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-[240px_1fr]">
        <!-- Sidebar section -->
        <div class="rounded-xl border border-slate-200 bg-white p-3">
          <p class="px-1 text-xs font-medium text-slate-500">Section</p>
          <div class="mt-2 space-y-1">
            <div
              v-for="section in [...store.questionnaire.sections].sort((a, b) => a.sort_order - b.sort_order)"
              :key="section.id"
              class="flex items-center gap-1 rounded-lg px-2 py-1.5"
              :class="selectedSectionId === section.id ? 'bg-brand-50' : 'hover:bg-slate-50'"
            >
              <button type="button" class="min-w-0 flex-1 truncate text-left text-sm" :class="selectedSectionId === section.id ? 'font-medium text-brand-700' : 'text-slate-600'" @click="selectedSectionId = section.id">
                {{ section.title }}
              </button>
              <template v-if="store.isEditable">
                <button type="button" class="text-slate-400 hover:text-slate-700" title="Naik" @click="moveSection(section, -1)">&uarr;</button>
                <button type="button" class="text-slate-400 hover:text-slate-700" title="Turun" @click="moveSection(section, 1)">&darr;</button>
                <button type="button" class="text-slate-400 hover:text-rose-600" title="Hapus" @click="removeSection(section.id)">&times;</button>
              </template>
            </div>
          </div>

          <form v-if="store.isEditable" class="mt-3 flex gap-1" @submit.prevent="addSection">
            <input v-model="newSectionTitle" type="text" placeholder="Section baru" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs" />
            <button type="submit" class="rounded-lg bg-brand-600 px-2 py-1.5 text-xs font-medium text-white">+</button>
          </form>
        </div>

        <!-- Canvas pertanyaan -->
        <div>
          <div v-if="!selectedSection" class="rounded-xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-400">
            Pilih atau buat section untuk mulai menambah pertanyaan.
          </div>

          <div v-else class="space-y-4">
            <div v-for="question in [...selectedSection.questions].sort((a, b) => a.sort_order - b.sort_order)" :key="question.id" class="rounded-xl border border-slate-200 bg-white p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs text-slate-400">{{ question.code }} &middot; {{ QUESTION_TYPE_LABELS[question.type] }}</p>
                  <p class="text-sm font-medium text-slate-900">{{ question.text }}</p>
                </div>
                <template v-if="store.isEditable">
                  <label class="flex items-center gap-1 text-xs text-slate-500">
                    <input type="checkbox" :checked="question.required" @change="toggleRequired(question.id, question.required)" />
                    Wajib
                  </label>
                  <button type="button" class="text-slate-400 hover:text-rose-600" @click="removeQuestion(question.id)">&times;</button>
                </template>
              </div>

              <!-- Opsi (choice types) -->
              <div v-if="questionNeedsOptions(question.type) && question.type !== 'matrix'" class="mt-3 space-y-1.5 border-t border-slate-100 pt-3">
                <div v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-xs">
                  <span class="min-w-0 flex-1 truncate text-slate-700">{{ option.label }}</span>
                  <select
                    v-if="store.isEditable"
                    :value="option.target_section_id ?? ''"
                    class="rounded-md border border-slate-200 px-1.5 py-1 text-xs"
                    @change="setOptionTarget(option.id, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">Lanjut default</option>
                    <option v-for="s in store.questionnaire.sections" :key="s.id" :value="s.id">&rarr; {{ s.title }}</option>
                  </select>
                  <button v-if="store.isEditable" type="button" class="text-slate-400 hover:text-rose-600" @click="removeOption(option.id)">&times;</button>
                </div>
                <form v-if="store.isEditable" class="flex gap-1 pt-1" @submit.prevent="addOption(question.id)">
                  <input v-model="newOptionLabel[question.id]" type="text" placeholder="Opsi baru" class="w-full rounded-md border border-slate-200 px-2 py-1 text-xs" />
                  <button type="submit" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">+</button>
                </form>
              </div>

              <!-- Matrix: baris + skala (opsi) -->
              <div v-if="question.type === 'matrix'" class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
                <div>
                  <p class="text-xs font-medium text-slate-500">Baris</p>
                  <div v-for="row in question.matrix_rows" :key="row.id" class="mt-1 flex items-center gap-2 text-xs">
                    <span class="min-w-0 flex-1 truncate text-slate-700">{{ row.label }}</span>
                    <button v-if="store.isEditable" type="button" class="text-slate-400 hover:text-rose-600" @click="removeMatrixRow(row.id)">&times;</button>
                  </div>
                  <form v-if="store.isEditable" class="mt-1 flex gap-1" @submit.prevent="addMatrixRow(question.id)">
                    <input v-model="newMatrixRowLabel[question.id]" type="text" placeholder="Baris baru" class="w-full rounded-md border border-slate-200 px-2 py-1 text-xs" />
                    <button type="submit" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">+</button>
                  </form>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Skala</p>
                  <div v-for="option in question.options" :key="option.id" class="mt-1 flex items-center gap-2 text-xs">
                    <span class="min-w-0 flex-1 truncate text-slate-700">{{ option.label }}</span>
                    <button v-if="store.isEditable" type="button" class="text-slate-400 hover:text-rose-600" @click="removeOption(option.id)">&times;</button>
                  </div>
                  <form v-if="store.isEditable" class="mt-1 flex gap-1" @submit.prevent="addOption(question.id)">
                    <input v-model="newOptionLabel[question.id]" type="text" placeholder="Skala baru" class="w-full rounded-md border border-slate-200 px-2 py-1 text-xs" />
                    <button type="submit" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">+</button>
                  </form>
                </div>
              </div>
            </div>

            <form v-if="store.isEditable" class="rounded-xl border border-dashed border-slate-200 p-4" @submit.prevent="addQuestion">
              <p class="text-xs font-medium text-slate-500">Tambah Pertanyaan</p>
              <div class="mt-2 grid gap-2 sm:grid-cols-[100px_160px_1fr_auto]">
                <input v-model="newQuestion.code" type="text" placeholder="Kode" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                <select v-model="newQuestion.type" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                  <option v-for="(label, type) in QUESTION_TYPE_LABELS" :key="type" :value="type">{{ label }}</option>
                </select>
                <input v-model="newQuestion.text" type="text" placeholder="Teks pertanyaan" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white">Tambah</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </template>
  </AppShell>
</template>
