// State wizard Import Template (Fase 2 slice 2, FSD §15, slice JSON).
// Alur: upload (baca file jadi teks) -> pratinjau (errors/warnings/preview
// dari staging record) -> commit (buat SurveyTemplate baru).
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export interface TemplateImportIssue {
  code: string
  message: string
  path: string
}

export interface TemplateImportPreview {
  template_code: string | null
  sections: number
  questions: number
  options: number
}

export interface TemplateImportRecord {
  id: string
  filename: string | null
  status: 'pending_validation' | 'validated' | 'failed' | 'committed'
  errors: TemplateImportIssue[]
  warnings: TemplateImportIssue[]
  preview: TemplateImportPreview
  checksum: string | null
  expires_at: string | null
  committed_at: string | null
  committed_template_id: string | null
  uploaded_by: string | null
  created_at: string | null
}

interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; per_page: number; total: number }
}

export const useTemplateImportStore = defineStore('templateImport', {
  state: () => ({
    uploading: false,
    committing: false,
    current: null as TemplateImportRecord | null,
    history: [] as TemplateImportRecord[],
    loadingHistory: false,
  }),
  getters: {
    canCommit: (state): boolean =>
      state.current !== null && state.current.status === 'validated' && state.current.committed_at === null,
  },
  actions: {
    async uploadContent(filename: string, content: string): Promise<void> {
      this.uploading = true
      this.current = null
      try {
        const response = await apiFetch<{ data: TemplateImportRecord }>('/settings/template-imports', {
          method: 'POST',
          body: JSON.stringify({ filename, content }),
        })
        this.current = response.data
      } finally {
        this.uploading = false
      }
    },

    async commit(): Promise<TemplateImportRecord | null> {
      if (!this.current) return null
      this.committing = true
      try {
        await apiFetch(`/settings/template-imports/${this.current.id}/commit`, { method: 'POST' })
        const refreshed = await apiFetch<{ data: TemplateImportRecord }>(`/settings/template-imports/${this.current.id}`)
        this.current = refreshed.data
        await this.loadHistory()
        return this.current
      } finally {
        this.committing = false
      }
    },

    async loadHistory(): Promise<void> {
      this.loadingHistory = true
      try {
        const response = await apiFetch<PaginatedResponse<TemplateImportRecord>>('/settings/template-imports?per_page=10')
        this.history = response.data
      } finally {
        this.loadingHistory = false
      }
    },

    reset(): void {
      this.current = null
    },
  },
})
