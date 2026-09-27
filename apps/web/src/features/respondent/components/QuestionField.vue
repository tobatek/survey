<script setup lang="ts">
// Renderer satu pertanyaan sesuai tipenya (Fase 4 slice 5). Meniru gaya
// visual App\Domains\Questionnaires (lihat QuestionnairePreviewModal.vue di
// apps/web/src/features/questionnaires/components/), tapi ini SUMBER JAWABAN
// ASLI (bukan simulasi): value yang di-emit memakai option.code, sesuai
// kontrak PUT /api/responses/{session}/draft (backend slice 3), dan dropdown
// dirender sebagai <select> sungguhan (bukan radio seperti preview builder).
import { computed } from 'vue'
import type { FillQuestion } from '../store/surveyFill'

const props = defineProps<{
  question: FillQuestion
  modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const singleValue = computed<string>(() => (typeof props.modelValue === 'string' ? props.modelValue : ''))
const multipleValue = computed<string[]>(() => (Array.isArray(props.modelValue) ? (props.modelValue as string[]) : []))
const matrixValue = computed<Record<string, string>>(() =>
  props.modelValue && typeof props.modelValue === 'object' && !Array.isArray(props.modelValue)
    ? (props.modelValue as Record<string, string>)
    : {},
)

function setSingle(value: string) {
  emit('update:modelValue', value)
}

function toggleMultiple(optionCode: string) {
  const current = multipleValue.value
  emit('update:modelValue', current.includes(optionCode) ? current.filter((c) => c !== optionCode) : [...current, optionCode])
}

function setMatrixCell(rowCode: string, optionCode: string) {
  emit('update:modelValue', { ...matrixValue.value, [rowCode]: optionCode })
}
</script>

<template>
  <div>
    <p class="text-sm font-medium text-slate-800">
      {{ question.text }}
      <span v-if="question.required" class="text-rose-500">*</span>
    </p>

    <div v-if="question.type === 'short_text'" class="mt-2">
      <input
        type="text"
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
        placeholder="Jawaban singkat"
        :value="singleValue"
        @input="setSingle(($event.target as HTMLInputElement).value)"
      />
    </div>

    <div v-else-if="question.type === 'long_text'" class="mt-2">
      <textarea
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
        rows="3"
        placeholder="Jawaban panjang"
        :value="singleValue"
        @input="setSingle(($event.target as HTMLTextAreaElement).value)"
      />
    </div>

    <div v-else-if="question.type === 'number'" class="mt-2">
      <input
        type="number"
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
        :value="singleValue"
        @input="setSingle(($event.target as HTMLInputElement).value)"
      />
    </div>

    <div v-else-if="question.type === 'date'" class="mt-2">
      <input
        type="date"
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
        :value="singleValue"
        @input="setSingle(($event.target as HTMLInputElement).value)"
      />
    </div>

    <div v-else-if="question.type === 'dropdown'" class="mt-2">
      <select
        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
        :value="singleValue"
        @change="setSingle(($event.target as HTMLSelectElement).value)"
      >
        <option value="" disabled>Pilih jawaban</option>
        <option v-for="option in question.options" :key="option.code" :value="option.code">{{ option.label }}</option>
      </select>
    </div>

    <div v-else-if="question.type === 'single_choice' || question.type === 'likert'" class="mt-2 space-y-1.5">
      <label v-for="option in question.options" :key="option.code" class="flex items-center gap-2 text-sm text-slate-700">
        <input
          type="radio"
          :name="question.code"
          :checked="singleValue === option.code"
          @change="setSingle(option.code)"
        />
        {{ option.label }}
      </label>
    </div>

    <div v-else-if="question.type === 'multiple_choice'" class="mt-2 space-y-1.5">
      <label v-for="option in question.options" :key="option.code" class="flex items-center gap-2 text-sm text-slate-700">
        <input
          type="checkbox"
          :checked="multipleValue.includes(option.code)"
          @change="toggleMultiple(option.code)"
        />
        {{ option.label }}
      </label>
    </div>

    <div v-else-if="question.type === 'matrix'" class="mt-2 overflow-x-auto">
      <table class="text-sm">
        <thead>
          <tr>
            <th></th>
            <th v-for="option in question.options" :key="option.code" class="px-2 pb-1 text-xs font-medium text-slate-500">
              {{ option.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in question.matrix_rows" :key="row.code">
            <td class="pr-3 text-xs text-slate-600">{{ row.label }}</td>
            <td v-for="option in question.options" :key="option.code" class="px-2 text-center">
              <input
                type="radio"
                :name="`${question.code}-${row.code}`"
                :checked="matrixValue[row.code] === option.code"
                @change="setMatrixCell(row.code, option.code)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
