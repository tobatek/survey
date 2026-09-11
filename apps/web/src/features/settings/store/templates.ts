// Daftar & status SurveyTemplate (FSD §17.7 — aktifkan/nonaktifkan template
// pasca-import). Terpisah dari templateImport.ts karena mengelola resource
// beda (SurveyTemplate yang sudah di-commit, bukan staging TemplateImport).
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export interface TemplateRow {
  id: string
  code: string
  title: string
  category: string | null
  audience_type: string
  identity_mode: string
  language: string
  status: 'active' | 'inactive'
  checksum: string | null
  created_at: string | null
}

export const useTemplatesStore = defineStore('templates', {
  state: () => ({
    list: [] as TemplateRow[],
    loading: false,
    updatingId: null as string | null,
  }),
  actions: {
    async loadAll(): Promise<void> {
      this.loading = true
      try {
        const response = await apiFetch<{ data: TemplateRow[] }>('/templates?status=all')
        this.list = response.data
      } finally {
        this.loading = false
      }
    },

    async toggleStatus(template: TemplateRow): Promise<void> {
      this.updatingId = template.id
      const nextStatus = template.status === 'active' ? 'inactive' : 'active'
      try {
        await apiFetch(`/templates/${template.id}/status`, {
          method: 'PATCH',
          body: JSON.stringify({ status: nextStatus }),
        })
        await this.loadAll()
      } finally {
        this.updatingId = null
      }
    },
  },
})
