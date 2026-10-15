// State daftar "Survei Saya" (Fase 4 slice 2, FSD §11.1) -- jalur login
// (identified). Jalur token undangan (anonymous) tidak lewat sini, lihat
// store/surveyToken.ts.
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export type RespondentStatus = 'not_started' | 'draft' | 'completed' | 'closed'

export interface MySurveyItem {
  eligibility_id: string
  campaign_id: string
  title: string
  category: string | null
  starts_at: string | null
  ends_at: string | null
  respondent_status: RespondentStatus
}

export const useMySurveysStore = defineStore('mySurveys', {
  state: () => ({
    items: [] as MySurveyItem[],
    loading: false,
    starting: false,
  }),
  actions: {
    async load(): Promise<void> {
      this.loading = true
      try {
        const response = await apiFetch<{ data: MySurveyItem[] }>('/me/surveys')
        this.items = response.data
      } finally {
        this.loading = false
      }
    },

    async start(eligibilityId: string): Promise<string> {
      this.starting = true
      try {
        const response = await apiFetch<{ data: { session_id: string } }>(`/my-surveys/${eligibilityId}/start`, {
          method: 'POST',
        })
        return response.data.session_id
      } finally {
        this.starting = false
      }
    },
  },
})
