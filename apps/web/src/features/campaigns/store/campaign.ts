// State satu Campaign (Fase 3) — dipakai CampaignPage.vue & CampaignWizard.vue.
// Beda dari store/builder.ts Kuesioner: campaign TIDAK punya lock_version/
// If-Match (transisi status di backend pakai conditional update, bukan
// optimistic lock — lihat TransitionCampaignStatusAction di apps/api).
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export type CampaignStatus = 'draft' | 'active' | 'paused' | 'closed'

export interface CampaignDetail {
  id: string
  questionnaire_id: string
  status: CampaignStatus
  starts_at: string | null
  ends_at: string | null
  timezone: string
  target_rule_json: Record<string, unknown> | null
  denominator_count: number | null
  minimum_segment_size: number
  activated_at: string | null
  paused_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
}

export interface AudienceFilter {
  faculty_names?: string[]
  program_study_codes?: string[]
  degree_levels?: string[]
  cohort_years?: string[]
  student_statuses?: string[]
  employee_types?: string[]
  work_unit_names?: string[]
  employee_statuses?: string[]
  position_names?: string[]
  include_student_ids?: string[]
  exclude_student_ids?: string[]
  include_employee_ids?: string[]
  exclude_employee_ids?: string[]
}

export interface AudiencePreviewResult {
  total: number
  is_empty: boolean
  students: { count: number; breakdown: Record<string, number> }
  employees: { count: number; breakdown: Record<string, number> }
}

export interface CampaignFunnel {
  eligible: number
  invited: number
  opened: number
  started: number
  completed: number
  expired: number
  excluded: number
}

export interface CampaignBreakdownGroup {
  group: string
  total: number
  completed: number
}

export interface CampaignMonitoring {
  denominator_count: number
  funnel: CampaignFunnel
  response_rate: number
  breakdown: {
    students: CampaignBreakdownGroup[]
    employees: CampaignBreakdownGroup[]
  }
}

export const useCampaignStore = defineStore('campaign', {
  state: () => ({
    campaign: null as CampaignDetail | null,
    loading: false,
    saving: false,
    previewResult: null as AudiencePreviewResult | null,
    monitoring: null as CampaignMonitoring | null,
  }),
  actions: {
    async load(id: string): Promise<void> {
      this.loading = true
      try {
        const response = await apiFetch<{ data: CampaignDetail }>(`/campaigns/${id}`)
        this.campaign = response.data
      } finally {
        this.loading = false
      }
    },

    async reload(): Promise<void> {
      if (this.campaign) await this.load(this.campaign.id)
    },

    /** Bungkus mutasi campaign: reload otomatis setelah sukses, error ditangkap dan dilempar ulang (ditangani di level halaman lewat toast). */
    async mutate<T>(request: () => Promise<T>): Promise<T> {
      this.saving = true
      try {
        const result = await request()
        await this.reload()
        return result
      } finally {
        this.saving = false
      }
    },

    async previewAudience(filter: AudienceFilter): Promise<void> {
      if (!this.campaign) return
      const response = await this.mutate(() =>
        apiFetch<{ data: AudiencePreviewResult }>(`/campaigns/${this.campaign!.id}/audience/preview`, {
          method: 'POST',
          body: JSON.stringify(filter),
        }),
      )
      this.previewResult = response.data
    },

    async updateSchedule(payload: { starts_at?: string | null; ends_at?: string | null; timezone?: string }): Promise<void> {
      if (!this.campaign) return
      await this.mutate(() =>
        apiFetch(`/campaigns/${this.campaign!.id}/schedule`, {
          method: 'PATCH',
          body: JSON.stringify(payload),
        }),
      )
    },

    async activate(): Promise<void> {
      if (!this.campaign) return
      await this.mutate(() => apiFetch(`/campaigns/${this.campaign!.id}/activate`, { method: 'POST' }))
    },

    async loadMonitoring(id: string): Promise<void> {
      const response = await apiFetch<{ data: CampaignMonitoring }>(`/campaigns/${id}/monitoring`)
      this.monitoring = response.data
    },

    async pause(): Promise<void> {
      if (!this.campaign) return
      await this.mutate(() => apiFetch(`/campaigns/${this.campaign!.id}/pause`, { method: 'POST' }))
      await this.loadMonitoring(this.campaign.id)
    },

    async resume(): Promise<void> {
      if (!this.campaign) return
      await this.mutate(() => apiFetch(`/campaigns/${this.campaign!.id}/resume`, { method: 'POST' }))
      await this.loadMonitoring(this.campaign.id)
    },

    async close(): Promise<void> {
      if (!this.campaign) return
      await this.mutate(() => apiFetch(`/campaigns/${this.campaign!.id}/close`, { method: 'POST' }))
      await this.loadMonitoring(this.campaign.id)
    },
  },
})
