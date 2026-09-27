// State form pengisian survei (Fase 4 slice 5) -- mengonsumsi endpoint draft
// & submit yang sudah selesai di slice 3-4 (lihat App\Http\Controllers\Api\
// ResponseController backend). Pola mutate()/ifMatchHeaders() meniru
// features/questionnaires/store/builder.ts, satu-satunya store lain di
// proyek ini yang sudah menyelesaikan siklus If-Match/409.
import { defineStore } from 'pinia'
import { apiFetch, ApiError } from '@/lib/api'
import type { QuestionType } from '@/features/questionnaires/store/builder'

export interface FillOption {
  id: number
  code: string
  label: string
  numeric_score: number | string | null
  target_section_id: number | null
}

export interface FillMatrixRow {
  id: number
  code: string
  label: string
}

export interface FillQuestion {
  id: number
  code: string
  type: QuestionType
  text: string
  required: boolean
  allow_not_applicable: boolean
  validation: Record<string, unknown> | null
  options: FillOption[]
  matrix_rows: FillMatrixRow[]
}

export interface FillSection {
  id: number
  code: string
  title: string
  sort_order: number
  questions: FillQuestion[]
}

export interface FillStructure {
  sections: FillSection[]
}

export type DraftAnswers = Record<string, unknown>

export interface FillSessionData {
  session_id: string
  status: 'draft' | 'submitting' | 'completed' | 'expired'
  mode: 'identified' | 'anonymous'
  lock_version: number
  expires_at: string | null
  structure: FillStructure
  draft_answers: DraftAnswers
  reachable_section_ids: number[]
}

export const useSurveyFillStore = defineStore('surveyFill', {
  state: () => ({
    session: null as FillSessionData | null,
    token: null as string | null,
    loading: false,
    saving: false,
    submitting: false,
    conflict: false,
    loadError: null as { code?: string; message: string } | null,
    idempotencyKey: null as string | null,
  }),
  getters: {
    lockVersion: (state): number => state.session?.lock_version ?? 0,
  },
  actions: {
    setToken(token: string | null) {
      this.token = token
    },

    async load(sessionId: string): Promise<void> {
      this.loading = true
      this.loadError = null
      try {
        const response = await apiFetch<{ data: FillSessionData }>(`/responses/${sessionId}`)
        this.session = response.data
      } catch (error) {
        this.loadError = error instanceof ApiError
          ? { code: error.code, message: error.message }
          : { message: 'Gagal memuat survei. Coba beberapa saat lagi.' }
      } finally {
        this.loading = false
      }
    },

    ifMatchHeaders(): Record<string, string> {
      return { 'If-Match': String(this.lockVersion) }
    },

    /** Simpan draft (full replace) lalu muat ulang supaya lock_version &
     * reachable_section_ids selalu sinkron dengan server -- lihat keputusan
     * desain di plan slice 5 soal kenapa tidak cukup pakai respons PUT saja. */
    async saveDraft(answers: DraftAnswers): Promise<boolean> {
      if (!this.session) return false
      this.saving = true
      try {
        await apiFetch(`/responses/${this.session.session_id}/draft`, {
          method: 'PUT',
          headers: this.ifMatchHeaders(),
          body: JSON.stringify({ answers }),
        })
        await this.load(this.session.session_id)
        return true
      } catch (error) {
        if (error instanceof ApiError && error.status === 409) {
          this.conflict = true
          await this.load(this.session.session_id)
          return false
        }
        throw error
      } finally {
        this.saving = false
      }
    },

    ensureIdempotencyKey(): string {
      if (!this.idempotencyKey) {
        this.idempotencyKey = crypto.randomUUID()
      }
      return this.idempotencyKey
    },

    async submit(): Promise<{ responseId: string; submittedAt: string }> {
      if (!this.session) throw new Error('Sesi belum dimuat.')
      this.submitting = true
      try {
        const body = this.session.mode === 'anonymous' ? { token: this.token } : {}
        const response = await apiFetch<{ data: { response_id: string; submitted_at: string } }>(
          `/responses/${this.session.session_id}/submit`,
          {
            method: 'POST',
            headers: { 'Idempotency-Key': this.ensureIdempotencyKey() },
            body: JSON.stringify(body),
          },
        )
        return { responseId: response.data.response_id, submittedAt: response.data.submitted_at }
      } finally {
        this.submitting = false
      }
    },
  },
})
