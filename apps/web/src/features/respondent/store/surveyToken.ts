// Tukar token undangan (link email, mode anonymous) jadi sesi pengisian.
// Tidak butuh login sama sekali -- lihat StartResponseSessionAction (backend).
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export const useSurveyTokenStore = defineStore('surveyToken', {
  actions: {
    async startByToken(token: string): Promise<string> {
      const response = await apiFetch<{ data: { session_id: string } }>('/my-surveys/start-by-token', {
        method: 'POST',
        body: JSON.stringify({ token }),
      })
      return response.data.session_id
    },
  },
})
