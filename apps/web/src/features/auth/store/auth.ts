import { defineStore } from 'pinia'
import { apiFetch, ApiError } from '@/lib/api'

// FSD §5.2. Role independen dari role di Portal (dokumen 15).
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

const RESPONDENT_ROLES: UserRole[] = ['student_respondent', 'employee_respondent']

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    isLoading: false,
    /** true setelah percobaan GET /api/me pertama selesai (sukses atau 401). */
    initialized: false,
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    isRespondent: (state) => state.user?.roles.some((r) => RESPONDENT_ROLES.includes(r)) ?? false,
    isAdmin: (state) => state.user?.roles.some((r) => !RESPONDENT_ROLES.includes(r)) ?? false,
  },
  actions: {
    setUser(user: AuthUser | null) {
      this.user = user
    },

    /**
     * Dipanggil sekali oleh router guard sebelum navigasi pertama. Sesi
     * disimpan sebagai cookie Sanctum oleh apps/api (bukan token di
     * localStorage) — satu-satunya cara tahu status login adalah bertanya
     * ke server.
     */
    async fetchMe(): Promise<void> {
      if (this.initialized) return

      this.isLoading = true
      try {
        const response = await apiFetch<{ data: AuthUser }>('/me')
        this.user = response.data
      } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 401) {
          console.error('Gagal memuat sesi pengguna', error)
        }
        this.user = null
      } finally {
        this.isLoading = false
        this.initialized = true
      }
    },

    /**
     * Redirect penuh browser ke Portal via apps/api (docs/15). Bukan fetch —
     * ini navigasi halaman, sehingga memakai VITE_API_ORIGIN, bukan proxy /api.
     */
    redirectToLogin() {
      const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://127.0.0.1:8000'
      window.location.href = `${apiOrigin}/auth/redirect`
    },

    async logout(): Promise<void> {
      const apiOrigin = import.meta.env.VITE_API_ORIGIN ?? 'http://127.0.0.1:8000'

      // /logout adalah rute web Laravel (bukan /api/*), jadi tidak lewat
      // apiFetch (yang selalu prefix ke API_BASE relatif) — panggil origin
      // penuh langsung, tetap bawa cookie CSRF Sanctum yang sama.
      await fetch(`${apiOrigin}/sanctum/csrf-cookie`, { credentials: 'include' })
      const xsrfToken = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]

      await fetch(`${apiOrigin}/logout`, {
        method: 'POST',
        credentials: 'include',
        headers: xsrfToken ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) } : {},
      }).catch(() => null)

      this.user = null
      window.location.href = '/login'
    },
  },
})
