// State builder Kuesioner (Fase 2) — dipakai bersama oleh sidebar section,
// canvas pertanyaan, dan panel properti di QuestionnaireBuilderPage.vue.
// Setiap mutasi mengirim header `If-Match: lock_version` (FSD §7.7); server
// membalas 409 kalau ada editor lain yang sudah menang duluan — di sini
// direpresentasikan lewat flag `conflict`, UI menawarkan reload penuh.
import { defineStore } from 'pinia'
import { apiFetch, ApiError } from '@/lib/api'

export interface QuestionOption {
  id: string
  code: string
  label: string
  numeric_score: string | number | null
  is_positive: boolean
  is_not_applicable: boolean
  target_section_id: string | null
  sort_order: number
}

export interface MatrixRow {
  id: string
  code: string
  label: string
  sort_order: number
}

export type QuestionType =
  | 'short_text'
  | 'long_text'
  | 'single_choice'
  | 'multiple_choice'
  | 'dropdown'
  | 'likert'
  | 'matrix'
  | 'number'
  | 'date'

export interface QuestionNode {
  id: string
  section_id: string
  code: string
  type: QuestionType
  text: string
  help_text: string | null
  required: boolean
  allow_not_applicable: boolean
  validation: Record<string, unknown> | null
  sort_order: number
  options: QuestionOption[]
  matrix_rows: MatrixRow[]
}

export interface SectionNode {
  id: string
  code: string
  title: string
  description: string | null
  sort_order: number
  questions: QuestionNode[]
}

export interface QuestionnaireDetail {
  id: string
  code: string
  title: string
  description: string | null
  category: string | null
  audience_type: string
  identity_mode: string
  status: 'draft' | 'ready' | 'active' | 'paused' | 'closed'
  allow_draft_save: boolean
  privacy_notice: string | null
  confirmation_text: string | null
  structure_version: number
  lock_version: number
  snapshot_checksum: string | null
  is_editable: boolean
  finalized_at: string | null
  sections: SectionNode[]
}

export interface StructureIssue {
  code: string
  message: string
}

const QUESTION_TYPES_NEEDING_OPTIONS: QuestionType[] = ['single_choice', 'multiple_choice', 'dropdown', 'likert', 'matrix']

export function questionNeedsOptions(type: QuestionType): boolean {
  return QUESTION_TYPES_NEEDING_OPTIONS.includes(type)
}

export const useQuestionnaireBuilderStore = defineStore('questionnaireBuilder', {
  state: () => ({
    questionnaire: null as QuestionnaireDetail | null,
    loading: false,
    saving: false,
    conflict: false,
    validationErrors: [] as StructureIssue[],
    validationWarnings: [] as StructureIssue[],
  }),
  getters: {
    lockVersion: (state): number => state.questionnaire?.lock_version ?? 0,
    isEditable: (state): boolean => state.questionnaire?.is_editable ?? false,
  },
  actions: {
    async load(id: string): Promise<void> {
      this.loading = true
      this.conflict = false
      try {
        const response = await apiFetch<{ data: QuestionnaireDetail }>(`/questionnaires/${id}`)
        this.questionnaire = response.data
      } finally {
        this.loading = false
      }
    },

    async reload(): Promise<void> {
      if (this.questionnaire) await this.load(this.questionnaire.id)
    },

    /** Membungkus satu mutasi builder: kirim If-Match, tandai `conflict` pada 409, reload otomatis. */
    async mutate<T>(request: () => Promise<T>): Promise<T | null> {
      this.saving = true
      try {
        const result = await request()
        await this.reload()
        return result
      } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
          this.conflict = true
          return null
        }
        throw error
      } finally {
        this.saving = false
      }
    },

    ifMatchHeaders(): Record<string, string> {
      return { 'If-Match': String(this.lockVersion) }
    },

    async updateMetadata(data: Partial<Pick<QuestionnaireDetail, 'title' | 'description' | 'category' | 'audience_type' | 'identity_mode' | 'privacy_notice' | 'confirmation_text'>>) {
      if (!this.questionnaire) return
      await this.mutate(() =>
        apiFetch(`/questionnaires/${this.questionnaire!.id}`, {
          method: 'PATCH',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async addSection(data: { code: string; title: string }) {
      if (!this.questionnaire) return
      await this.mutate(() =>
        apiFetch(`/questionnaires/${this.questionnaire!.id}/sections`, {
          method: 'POST',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async updateSection(sectionId: string, data: Partial<Pick<SectionNode, 'code' | 'title' | 'description' | 'sort_order'>>) {
      await this.mutate(() =>
        apiFetch(`/sections/${sectionId}`, {
          method: 'PATCH',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async deleteSection(sectionId: string) {
      await this.mutate(() =>
        apiFetch(`/sections/${sectionId}`, { method: 'DELETE', headers: this.ifMatchHeaders() }),
      )
    },

    async addQuestion(sectionId: string, data: { code: string; type: QuestionType; text: string }) {
      await this.mutate(() =>
        apiFetch(`/sections/${sectionId}/questions`, {
          method: 'POST',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async updateQuestion(questionId: string, data: Partial<Pick<QuestionNode, 'code' | 'type' | 'text' | 'help_text' | 'required' | 'allow_not_applicable' | 'sort_order'>>) {
      await this.mutate(() =>
        apiFetch(`/questions/${questionId}`, {
          method: 'PATCH',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async deleteQuestion(questionId: string) {
      await this.mutate(() =>
        apiFetch(`/questions/${questionId}`, { method: 'DELETE', headers: this.ifMatchHeaders() }),
      )
    },

    async addOption(questionId: string, data: { code: string; label: string; target_section_id?: string | null }) {
      await this.mutate(() =>
        apiFetch(`/questions/${questionId}/options`, {
          method: 'POST',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async updateOption(optionId: string, data: Partial<Pick<QuestionOption, 'code' | 'label' | 'target_section_id'>>) {
      await this.mutate(() =>
        apiFetch(`/options/${optionId}`, {
          method: 'PATCH',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async deleteOption(optionId: string) {
      await this.mutate(() =>
        apiFetch(`/options/${optionId}`, { method: 'DELETE', headers: this.ifMatchHeaders() }),
      )
    },

    async addMatrixRow(questionId: string, data: { code: string; label: string }) {
      await this.mutate(() =>
        apiFetch(`/questions/${questionId}/matrix-rows`, {
          method: 'POST',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify(data),
        }),
      )
    },

    async deleteMatrixRow(rowId: string) {
      await this.mutate(() =>
        apiFetch(`/matrix-rows/${rowId}`, { method: 'DELETE', headers: this.ifMatchHeaders() }),
      )
    },

    async validateStructure(): Promise<void> {
      if (!this.questionnaire) return
      const response = await apiFetch<{ data: { errors: StructureIssue[]; warnings: StructureIssue[] } }>(
        `/questionnaires/${this.questionnaire.id}/validate`,
      )
      this.validationErrors = response.data.errors
      this.validationWarnings = response.data.warnings
    },

    async finalize(): Promise<boolean> {
      if (!this.questionnaire) return false
      const result = await this.mutate(() =>
        apiFetch(`/questionnaires/${this.questionnaire!.id}/finalize`, {
          method: 'POST',
          headers: this.ifMatchHeaders(),
        }),
      )
      return result !== null
    },
  },
})
