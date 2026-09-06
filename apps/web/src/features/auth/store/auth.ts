import { defineStore } from 'pinia'

// Stub Fase 0 — diisi penuh di Fase 1 (integrasi SSO Portal, docs/15).
// Guard di router/index.ts sudah dibangun sekarang supaya struktur
// admin-vs-responden-vs-unauthenticated tidak perlu dirombak nanti.
export type UserRole =
  | 'lpm_admin'
  | 'survey_operator'
  | 'executive_viewer'
  | 'technical_admin'
  | 'student_respondent'
  | 'employee_respondent'
  | 'raw_data_analyst'

interface AuthUser {
  id: string
  name: string
  email: string
  roles: UserRole[]
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    isLoading: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isRespondent: (state) =>
      state.user?.roles.some((r) => r === 'student_respondent' || r === 'employee_respondent') ?? false,
    isAdmin: (state) =>
      state.user?.roles.some((r) => r !== 'student_respondent' && r !== 'employee_respondent') ?? false,
  },
  actions: {
    // TODO Fase 1: panggil GET /api/me setelah redirect kembali dari
    // Portal /sso/authorize, lihat docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md
    setUser(user: AuthUser | null) {
      this.user = user
    },
  },
})
