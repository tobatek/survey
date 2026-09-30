// State halaman "Hasil Analisa Kuesioner" (Fase 5 slice 7) — list campaign
// berespons, detail (summary+questions) satu campaign, filter segmen (pola
// sama FilterPanel: apply-on-click, bukan reactive per keystroke), riwayat
// job export (polling status), dan komentar esai per pertanyaan (role-gated
// raw_data_analyst di backend — store ini cuma memanggil endpointnya, tidak
// menduplikasi otorisasi).
import { defineStore } from 'pinia'
import { apiFetch } from '@/lib/api'

export interface AnalyticsCampaignRow {
  id: string
  questionnaire_code: string
  questionnaire_title: string
  status: string
  starts_at: string | null
  ends_at: string | null
  denominator_count: number | null
  completed_count: number
  response_rate: RateValue
  last_response_at: string | null
  last_aggregated_at: string | null
}

export interface RateValue {
  numerator: number
  denominator: number
  value: number | null
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

export interface AnalyticsSummary {
  suppressed?: boolean
  minimum_segment_size?: number
  funnel?: CampaignFunnel
  response_rate?: RateValue
  completion_rate?: RateValue
  total_responses?: number
  last_response_at?: string | null
}

export interface ChoiceOption {
  option_code: string
  label: string
  result: RateValue
}

export interface MatrixRowResult {
  row_code: string
  label: string
  suppressed?: boolean
  n: number
  mean?: number | null
  median?: number | null
  positive_rate?: RateValue | null
  options?: ChoiceOption[]
}

export interface DateBucket {
  date: string
  count: number
}

export interface QuestionResult {
  suppressed?: boolean
  n?: number
  options?: ChoiceOption[]
  mean?: number | null
  median?: number | null
  positive_rate?: RateValue | null
  min?: number | null
  max?: number | null
  rows?: MatrixRowResult[]
  buckets?: DateBucket[]
}

export interface Question {
  code: string
  text: string
  type: string
  result: QuestionResult
}

export interface QuestionSection {
  code: string
  title: string
  questions: Question[]
}

export interface AnalyticsQuestions {
  sections: QuestionSection[]
}

export interface AnalyticsFilterForm {
  respondentTypes: string[]
  facultyNames: string
  programStudyCodes: string
  degreeLevels: string
  cohortYears: string
  workUnitNames: string
  employeeTypes: string
  submittedFrom: string
  submittedTo: string
  questionSearch: string
}

export interface CommentRow extends Record<string, unknown> {
  id: string
  question_code: string
  text: string
  submitted_at: string | null
  segment: Record<string, unknown>
}

export interface CommentsResult {
  suppressed?: boolean
  minimum_segment_size?: number
  data?: CommentRow[]
  meta?: { current_page: number; per_page: number; total: number; last_page: number }
}

export type ExportFormat = 'xlsx' | 'csv' | 'pdf' | 'png'
export type ExportStatus = 'queued' | 'processing' | 'succeeded' | 'failed'

export interface ExportJob {
  id: string
  status: ExportStatus
  format: ExportFormat
  error_code: string | null
  download_url: string | null
}

function emptyFilterForm(): AnalyticsFilterForm {
  return {
    respondentTypes: [],
    facultyNames: '',
    programStudyCodes: '',
    degreeLevels: '',
    cohortYears: '',
    workUnitNames: '',
    employeeTypes: '',
    submittedFrom: '',
    submittedTo: '',
    questionSearch: '',
  }
}

function splitCsv(value: string): string[] | undefined {
  const items = value
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
  return items.length ? items : undefined
}

/** Query params dari form filter yang SUDAH diterapkan (bukan draft form). */
function buildFilterParams(filter: AnalyticsFilterForm): URLSearchParams {
  const params = new URLSearchParams()
  filter.respondentTypes.forEach((v) => params.append('respondent_types[]', v))
  splitCsv(filter.facultyNames)?.forEach((v) => params.append('faculty_names[]', v))
  splitCsv(filter.programStudyCodes)?.forEach((v) => params.append('program_study_codes[]', v))
  splitCsv(filter.degreeLevels)?.forEach((v) => params.append('degree_levels[]', v))
  splitCsv(filter.cohortYears)?.forEach((v) => params.append('cohort_years[]', v))
  splitCsv(filter.workUnitNames)?.forEach((v) => params.append('work_unit_names[]', v))
  splitCsv(filter.employeeTypes)?.forEach((v) => params.append('employee_types[]', v))
  if (filter.submittedFrom) params.set('submitted_from', filter.submittedFrom)
  if (filter.submittedTo) params.set('submitted_to', filter.submittedTo)
  if (filter.questionSearch) params.set('question_search', filter.questionSearch)
  return params
}

const EXPORT_POLL_INTERVAL_MS = 3000
const EXPORT_POLL_MAX_ATTEMPTS = 60

export const useAnalyticsStore = defineStore('analytics', {
  state: () => ({
    campaigns: [] as AnalyticsCampaignRow[],
    listLoading: false,
    campaignId: null as string | null,
    summary: null as AnalyticsSummary | null,
    questions: null as AnalyticsQuestions | null,
    detailLoading: false,
    /** Form draft (diedit user) — terpisah dari appliedFilter (pola FilterPanel). */
    filterForm: emptyFilterForm(),
    appliedFilter: emptyFilterForm(),
    exportJobs: [] as ExportJob[],
    commentsByQuestion: {} as Record<string, { result: CommentsResult | null; loading: boolean; search: string; page: number }>,
  }),
  getters: {
    campaignTitle(state): (id: string) => string | null {
      return (id: string) => state.campaigns.find((c) => c.id === id)?.questionnaire_title ?? null
    },
  },
  actions: {
    async loadList(): Promise<void> {
      this.listLoading = true
      try {
        const response = await apiFetch<{ data: AnalyticsCampaignRow[] }>('/analytics/campaigns')
        this.campaigns = response.data
      } finally {
        this.listLoading = false
      }
    },

    async ensureListLoaded(): Promise<void> {
      if (this.campaigns.length === 0 && !this.listLoading) {
        await this.loadList()
      }
    },

    async loadDetail(campaignId: string): Promise<void> {
      this.campaignId = campaignId
      this.filterForm = emptyFilterForm()
      this.appliedFilter = emptyFilterForm()
      this.commentsByQuestion = {}
      await this.refreshDetail()
    },

    async refreshDetail(): Promise<void> {
      if (!this.campaignId) return
      this.detailLoading = true
      try {
        const params = buildFilterParams(this.appliedFilter)
        const query = params.toString()
        const suffix = query ? `?${query}` : ''

        const [summaryRes, questionsRes] = await Promise.all([
          apiFetch<{ data: AnalyticsSummary }>(`/analytics/campaigns/${this.campaignId}/summary${suffix}`),
          apiFetch<{ data: AnalyticsQuestions }>(`/analytics/campaigns/${this.campaignId}/questions${suffix}`),
        ])
        this.summary = summaryRes.data
        this.questions = questionsRes.data
      } finally {
        this.detailLoading = false
      }
    },

    applyFilter(): void {
      this.appliedFilter = { ...this.filterForm }
      this.refreshDetail()
    },

    resetFilter(): void {
      this.filterForm = emptyFilterForm()
      this.appliedFilter = emptyFilterForm()
      this.refreshDetail()
    },

    async requestExport(format: ExportFormat): Promise<ExportJob> {
      if (!this.campaignId) throw new Error('Campaign belum dipilih.')

      const filter = this.appliedFilter
      const payload: Record<string, unknown> = { campaign_id: this.campaignId, format }
      if (filter.respondentTypes.length) payload.respondent_types = filter.respondentTypes
      const facultyNames = splitCsv(filter.facultyNames)
      if (facultyNames) payload.faculty_names = facultyNames
      const programStudyCodes = splitCsv(filter.programStudyCodes)
      if (programStudyCodes) payload.program_study_codes = programStudyCodes
      const degreeLevels = splitCsv(filter.degreeLevels)
      if (degreeLevels) payload.degree_levels = degreeLevels
      const cohortYears = splitCsv(filter.cohortYears)
      if (cohortYears) payload.cohort_years = cohortYears
      const workUnitNames = splitCsv(filter.workUnitNames)
      if (workUnitNames) payload.work_unit_names = workUnitNames
      const employeeTypes = splitCsv(filter.employeeTypes)
      if (employeeTypes) payload.employee_types = employeeTypes
      if (filter.submittedFrom) payload.submitted_from = filter.submittedFrom
      if (filter.submittedTo) payload.submitted_to = filter.submittedTo

      const response = await apiFetch<{ data: ExportJob }>('/exports', {
        method: 'POST',
        body: JSON.stringify(payload),
      })

      const job = response.data
      this.exportJobs = [job, ...this.exportJobs]
      this.pollExportJob(job.id)
      return job
    },

    pollExportJob(jobId: string): void {
      let attempts = 0
      const interval = setInterval(async () => {
        attempts++
        try {
          const response = await apiFetch<{ data: ExportJob }>(`/exports/${jobId}`)
          const index = this.exportJobs.findIndex((j) => j.id === jobId)
          if (index !== -1) this.exportJobs[index] = response.data

          const isTerminal = response.data.status === 'succeeded' || response.data.status === 'failed'
          if (isTerminal || attempts >= EXPORT_POLL_MAX_ATTEMPTS) {
            clearInterval(interval)
          }
        } catch {
          // Biarkan interval jalan lagi di attempt berikutnya -- kegagalan
          // polling sesaat (mis. jaringan) tidak boleh menghentikan polling
          // sepenuhnya sebelum batas attempts habis.
          if (attempts >= EXPORT_POLL_MAX_ATTEMPTS) clearInterval(interval)
        }
      }, EXPORT_POLL_INTERVAL_MS)
    },

    async loadComments(questionCode: string, options: { search?: string; page?: number } = {}): Promise<void> {
      if (!this.campaignId) return

      const page = options.page ?? 1
      const search = options.search ?? this.commentsByQuestion[questionCode]?.search ?? ''

      this.commentsByQuestion[questionCode] = {
        result: this.commentsByQuestion[questionCode]?.result ?? null,
        loading: true,
        search,
        page,
      }

      try {
        const params = buildFilterParams(this.appliedFilter)
        params.set('question_code', questionCode)
        params.set('page', String(page))
        if (search) params.set('search', search)

        const response = await apiFetch<{ data: CommentsResult }>(
          `/analytics/campaigns/${this.campaignId}/comments?${params.toString()}`,
        )
        this.commentsByQuestion[questionCode] = { result: response.data, loading: false, search, page }
      } catch (error) {
        this.commentsByQuestion[questionCode] = { result: null, loading: false, search, page }
        throw error
      }
    },
  },
})
