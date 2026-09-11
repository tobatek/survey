<script setup lang="ts">
// Preview mode builder (FSD §7.5): lebar desktop/mobile + branch simulator.
// Murni simulasi sisi klien di atas struktur yang sudah dimuat builder —
// tidak memanggil API, tidak menyimpan apa pun. Aturan navigasi "Lanjut"
// meniru pola default BranchGraphValidator::buildEdges (server) versi
// ringkas: section dengan opsi branching yang dipilih -> lompat ke target;
// section tanpa itu -> lanjut sort_order berikutnya. Bukan mesin evaluasi
// otoritatif (itu tanggung jawab Fase 4 saat responden sungguhan mengisi).
import { computed, ref } from 'vue'
import { QUESTION_TYPE_LABELS } from '../constants'
import type { QuestionnaireDetail, QuestionNode, SectionNode } from '../store/builder'

const props = defineProps<{ questionnaire: QuestionnaireDetail }>()
const emit = defineEmits<{ close: [] }>()

const viewport = ref<'desktop' | 'mobile'>('desktop')

const orderedSections = computed<SectionNode[]>(() =>
  [...props.questionnaire.sections].sort((a, b) => a.sort_order - b.sort_order),
)

const currentSectionId = ref<string | null>(orderedSections.value[0]?.id ?? null)
const answers = ref<Record<string, string | string[]>>({})
const finished = ref(false)

const currentSection = computed<SectionNode | null>(() =>
  orderedSections.value.find((s) => s.id === currentSectionId.value) ?? null,
)

const currentIndex = computed(() => orderedSections.value.findIndex((s) => s.id === currentSectionId.value))

function orderedQuestions(section: SectionNode): QuestionNode[] {
  return [...section.questions].sort((a, b) => a.sort_order - b.sort_order)
}

function selectSingle(questionId: string, optionId: string) {
  answers.value[questionId] = optionId
}

function toggleMultiple(questionId: string, optionId: string) {
  const current = (answers.value[questionId] as string[] | undefined) ?? []
  answers.value[questionId] = current.includes(optionId)
    ? current.filter((id) => id !== optionId)
    : [...current, optionId]
}

function resolveNextSectionId(section: SectionNode): string | null {
  for (const question of orderedQuestions(section)) {
    const selected = answers.value[question.id]
    if (typeof selected !== 'string') continue
    const option = question.options.find((o) => o.id === selected)
    if (option?.target_section_id) return option.target_section_id
  }

  const next = orderedSections.value[currentIndex.value + 1]
  return next?.id ?? null
}

function goNext() {
  if (!currentSection.value) return
  const nextId = resolveNextSectionId(currentSection.value)
  if (nextId) {
    currentSectionId.value = nextId
  } else {
    finished.value = true
  }
}

function restart() {
  currentSectionId.value = orderedSections.value[0]?.id ?? null
  answers.value = {}
  finished.value = false
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl bg-white shadow-xl">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
        <h2 class="text-sm font-semibold text-slate-900">Preview Kuesioner</h2>
        <div class="flex items-center gap-3">
          <div class="flex rounded-lg border border-slate-200 p-0.5 text-xs">
            <button
              type="button"
              class="rounded-md px-2 py-1"
              :class="viewport === 'desktop' ? 'bg-brand-600 text-white' : 'text-slate-500'"
              @click="viewport = 'desktop'"
            >
              Desktop
            </button>
            <button
              type="button"
              class="rounded-md px-2 py-1"
              :class="viewport === 'mobile' ? 'bg-brand-600 text-white' : 'text-slate-500'"
              @click="viewport = 'mobile'"
            >
              Mobile
            </button>
          </div>
          <button type="button" class="text-slate-400 hover:text-slate-700" @click="emit('close')">&times;</button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto bg-slate-50 p-6">
        <div :class="viewport === 'mobile' ? 'mx-auto max-w-[390px]' : 'max-w-full'">
          <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <template v-if="finished">
              <p class="text-sm font-medium text-slate-900">Simulasi selesai.</p>
              <p class="mt-1 text-xs text-slate-500">Ini akhir alur berdasarkan jawaban simulasi Anda.</p>
            </template>

            <template v-else-if="currentSection">
              <p class="text-xs text-slate-400">Section {{ currentIndex + 1 }} dari {{ orderedSections.length }}</p>
              <h3 class="mt-1 text-base font-semibold text-slate-900">{{ currentSection.title }}</h3>
              <p v-if="currentSection.description" class="mt-1 text-xs text-slate-500">{{ currentSection.description }}</p>

              <div class="mt-4 space-y-5">
                <div v-for="question in orderedQuestions(currentSection)" :key="question.id">
                  <p class="text-sm font-medium text-slate-800">
                    {{ question.text }}
                    <span v-if="question.required" class="text-rose-500">*</span>
                  </p>
                  <p class="text-xs text-slate-400">{{ QUESTION_TYPE_LABELS[question.type] }}</p>

                  <div v-if="question.type === 'short_text'" class="mt-2">
                    <input type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Jawaban singkat" />
                  </div>

                  <div v-else-if="question.type === 'long_text'" class="mt-2">
                    <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" rows="3" placeholder="Jawaban panjang" />
                  </div>

                  <div v-else-if="question.type === 'number'" class="mt-2">
                    <input type="number" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                  </div>

                  <div v-else-if="question.type === 'date'" class="mt-2">
                    <input type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                  </div>

                  <div v-else-if="['single_choice', 'dropdown', 'likert'].includes(question.type)" class="mt-2 space-y-1.5">
                    <label v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-sm text-slate-700">
                      <input
                        type="radio"
                        :name="question.id"
                        :checked="answers[question.id] === option.id"
                        @change="selectSingle(question.id, option.id)"
                      />
                      {{ option.label }}
                      <span v-if="option.target_section_id" class="text-xs text-brand-600">(branch)</span>
                    </label>
                  </div>

                  <div v-else-if="question.type === 'multiple_choice'" class="mt-2 space-y-1.5">
                    <label v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-sm text-slate-700">
                      <input
                        type="checkbox"
                        :checked="((answers[question.id] as string[] | undefined) ?? []).includes(option.id)"
                        @change="toggleMultiple(question.id, option.id)"
                      />
                      {{ option.label }}
                    </label>
                  </div>

                  <div v-else-if="question.type === 'matrix'" class="mt-2 overflow-x-auto">
                    <table class="text-sm">
                      <thead>
                        <tr>
                          <th></th>
                          <th v-for="option in question.options" :key="option.id" class="px-2 pb-1 text-xs font-medium text-slate-500">{{ option.label }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="row in question.matrix_rows" :key="row.id">
                          <td class="pr-3 text-xs text-slate-600">{{ row.label }}</td>
                          <td v-for="option in question.options" :key="option.id" class="px-2 text-center">
                            <input type="radio" :name="`${question.id}-${row.id}`" />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </template>

            <template v-else>
              <p class="text-sm text-slate-400">Kuesioner belum punya section untuk dipreview.</p>
            </template>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600" @click="restart">
          Ulangi dari Awal
        </button>
        <button
          v-if="!finished"
          type="button"
          class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
          @click="goNext"
        >
          Lanjut
        </button>
      </div>
    </div>
  </div>
</template>
