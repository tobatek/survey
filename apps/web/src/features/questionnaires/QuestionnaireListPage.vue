<script setup lang="ts">
// FSD §17.3 / plan Fase 2 — daftar Kuesioner nyata (ganti placeholder Fase 0):
// tab status, search+kategori via FilterPanel, card ala karirlink.id.
// Builder (section/pertanyaan/finalize) ada di QuestionnaireBuilderPage.vue.
import { onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/features/shared/components/AppShell.vue'
import Card from '@/features/shared/components/Card.vue'
import StatusBadge, { type SurveyStatus } from '@/features/shared/components/StatusBadge.vue'
import FilterPanel from '@/features/shared/components/FilterPanel.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import { apiFetch, ApiError } from '@/lib/api'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'

interface QuestionnaireRow {
  id: string
  code: string
  title: string
  category: string | null
  audience_type: string
  status: SurveyStatus
  updated_at: string | null
}

interface TemplateOption {
  id: string
  code: string
  title: string
  category: string | null
  audience_type: string
  identity_mode: string
}

interface PaginatedResponse<T> {
  data: T[]
  meta: { current_page: number; per_page: number; total: number }
}

const STATUS_TABS: { value: SurveyStatus | ''; label: string }[] = [
  { value: '', label: 'Semua' },
  { value: 'draft', label: 'Draft' },
  { value: 'ready', label: 'Siap' },
  { value: 'active', label: 'Aktif' },
  { value: 'paused', label: 'Dijeda' },
  { value: 'closed', label: 'Ditutup' },
]

const router = useRouter()
const toast = useToast()

const rows = ref<QuestionnaireRow[]>([])
const total = ref(0)
const page = ref(1)
const perPage = 12
const loading = ref(false)
const activeTab = ref<SurveyStatus | ''>('')

const filters = reactive({ search: '', category: '' })

const showCreateForm = ref(false)
const creating = ref(false)
const createError = ref<string | null>(null)
const createSource = ref<'blank' | 'template'>('blank')
const templates = ref<TemplateOption[]>([])
const selectedTemplateId = ref('')
const newQuestionnaire = reactive({
  code: '',
  title: '',
  audience_type: 'student',
  identity_mode: 'anonymous',
})

async function loadTemplates() {
  try {
    const response = await apiFetch<{ data: TemplateOption[] }>('/templates')
    templates.value = response.data
  } catch (error) {
    toast.push('Gagal memuat daftar template.', 'error')
  }
}

function onTemplateSelected() {
  const template = templates.value.find((t) => t.id === selectedTemplateId.value)
  if (!template) return
  newQuestionnaire.audience_type = template.audience_type
  newQuestionnaire.identity_mode = template.identity_mode
}

function openCreateForm() {
  showCreateForm.value = true
  createSource.value = 'blank'
  selectedTemplateId.value = ''
  loadTemplates()
}

async function loadQuestionnaires() {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: String(page.value), per_page: String(perPage) })
    if (filters.search) params.set('search', filters.search)
    if (filters.category) params.set('category', filters.category)
    if (activeTab.value) params.set('status', activeTab.value)

    const response = await apiFetch<PaginatedResponse<QuestionnaireRow>>(`/questionnaires?${params}`)
    rows.value = response.data
    total.value = response.meta.total
  } catch (error) {
    toast.push('Gagal memuat daftar kuesioner.', 'error')
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  page.value = 1
  loadQuestionnaires()
}

function resetFilters() {
  filters.search = ''
  filters.category = ''
  applyFilters()
}

function selectTab(tab: SurveyStatus | '') {
  activeTab.value = tab
  applyFilters()
}

async function submitCreate() {
  creating.value = true
  createError.value = null
  try {
    const payload =
      createSource.value === 'template' && selectedTemplateId.value
        ? { code: newQuestionnaire.code, title: newQuestionnaire.title, template_id: selectedTemplateId.value }
        : newQuestionnaire

    const response = await apiFetch<{ data: QuestionnaireRow }>('/questionnaires', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
    showCreateForm.value = false
    router.push({ name: 'questionnaires.builder', params: { id: response.data.id } })
  } catch (error) {
    createError.value = error instanceof ApiError ? error.message : 'Gagal membuat kuesioner.'
  } finally {
    creating.value = false
  }
}

watch(page, loadQuestionnaires)
onMounted(loadQuestionnaires)
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <template #topbar-actions>
      <button
        type="button"
        class="rounded-full bg-gradient-to-r from-brand-500 to-fuchsia-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-110"
        @click="openCreateForm"
      >
        Buat Kuesioner
      </button>
    </template>

    <h1 class="text-lg font-semibold text-slate-900">Kuesioner</h1>
    <p class="mt-1 text-sm text-slate-500">Kelola kuesioner dari draft hingga hasil analisa.</p>

    <div class="mt-4 flex gap-1 overflow-x-auto border-b border-slate-200">
      <button
        v-for="tab in STATUS_TABS"
        :key="tab.value"
        type="button"
        class="whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium"
        :class="
          activeTab === tab.value
            ? 'border-brand-600 text-brand-700'
            : 'border-transparent text-slate-500 hover:text-slate-700'
        "
        @click="selectTab(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[280px_1fr]">
      <FilterPanel @apply="applyFilters" @reset="resetFilters">
        <div>
          <label class="text-xs font-medium text-slate-500">Cari Kode/Judul</label>
          <input
            v-model="filters.search"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            placeholder="mis. SKM-2026"
            @keyup.enter="applyFilters"
          />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500">Kategori</label>
          <input
            v-model="filters.category"
            type="text"
            class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
            placeholder="mis. kepuasan"
            @keyup.enter="applyFilters"
          />
        </div>
      </FilterPanel>

      <div>
        <div v-if="loading" class="rounded-xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-400">
          Memuat...
        </div>

        <EmptyState
          v-else-if="rows.length === 0"
          title="Belum ada kuesioner"
          description="Buat kuesioner baru atau ubah filter pencarian."
        />

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <Card v-for="row in rows" :key="row.id" clickable @click="router.push({ name: 'questionnaires.builder', params: { id: row.id } })">
            <template #header>
              <p class="text-xs text-slate-400">{{ row.code }}</p>
              <h2 class="truncate text-sm font-semibold text-slate-900">{{ row.title }}</h2>
            </template>
            <template #badge>
              <StatusBadge :status="row.status" />
            </template>
            <template #meta>
              <p class="mt-1 text-xs text-slate-500">
                {{ row.category ?? 'Tanpa kategori' }} &middot; Target: {{ row.audience_type }}
              </p>
            </template>
          </Card>
        </div>

        <p v-if="!loading && total > 0" class="mt-4 text-xs text-slate-400">
          Menampilkan {{ rows.length }} dari total {{ total }} data
        </p>
      </div>
    </div>

    <div v-if="showCreateForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
      <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
        <h2 class="text-sm font-semibold text-slate-900">Buat Kuesioner Baru</h2>

        <form class="mt-4 space-y-3" @submit.prevent="submitCreate">
          <div>
            <label class="text-xs font-medium text-slate-500">Kode</label>
            <input
              v-model="newQuestionnaire.code"
              type="text"
              required
              class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              placeholder="mis. SKM-2026"
            />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500">Judul</label>
            <input
              v-model="newQuestionnaire.title"
              type="text"
              required
              class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              placeholder="mis. Survei Kepuasan Mahasiswa 2026"
            />
          </div>

          <div>
            <label class="text-xs font-medium text-slate-500">Mulai Dari</label>
            <div class="mt-1 flex gap-4 text-sm text-slate-700">
              <label class="flex items-center gap-1.5">
                <input v-model="createSource" type="radio" value="blank" />
                Kosong
              </label>
              <label class="flex items-center gap-1.5">
                <input v-model="createSource" type="radio" value="template" />
                Dari Template
              </label>
            </div>
          </div>

          <div v-if="createSource === 'template'">
            <label class="text-xs font-medium text-slate-500">Template</label>
            <select
              v-model="selectedTemplateId"
              required
              class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              @change="onTemplateSelected"
            >
              <option value="" disabled>Pilih template...</option>
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.code }} &middot; {{ tpl.title }}</option>
            </select>
            <p v-if="templates.length === 0" class="mt-1 text-xs text-slate-400">Belum ada template aktif. Impor lewat Pengaturan.</p>
          </div>

          <div v-if="createSource === 'blank'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-medium text-slate-500">Target</label>
              <select v-model="newQuestionnaire.audience_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="student">Mahasiswa</option>
                <option value="employee">Pegawai</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500">Mode Identitas</label>
              <select v-model="newQuestionnaire.identity_mode" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="anonymous">Anonim</option>
                <option value="identified">Teridentifikasi</option>
              </select>
            </div>
          </div>

          <p v-if="createError" class="text-xs text-rose-600">{{ createError }}</p>

          <div class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600"
              @click="showCreateForm = false"
            >
              Batal
            </button>
            <button
              type="submit"
              class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
              :disabled="creating"
            >
              {{ creating ? 'Menyimpan...' : 'Buat & Buka Builder' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppShell>
</template>
