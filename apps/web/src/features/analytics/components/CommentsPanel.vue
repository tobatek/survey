<script setup lang="ts">
// Fase 5 slice 7 -- panel komentar esai untuk SATU pertanyaan. Parent
// (QuestionResultCard) bertanggung jawab memastikan ini cuma dirender kalau
// role user memang raw_data_analyst (guard UX di frontend; backend tetap
// jadi otorisasi sesungguhnya -- lihat CampaignPolicy::viewComments).
import { onMounted, ref } from 'vue'
import DataTable, { type Column } from '@/features/shared/components/DataTable.vue'
import { useToast } from '@/features/shared/useToast'
import { ApiError } from '@/lib/api'
import { useAnalyticsStore, type CommentRow } from '../store/analytics'

const props = defineProps<{ questionCode: string }>()

const store = useAnalyticsStore()
const toast = useToast()
const searchInput = ref('')

const columns: Column<CommentRow>[] = [
  { key: 'text', label: 'Komentar' },
  {
    key: 'segment',
    label: 'Segmen',
    render: (row) =>
      Object.entries(row.segment)
        .map(([k, v]) => `${k}=${v}`)
        .join(', ') || '-',
  },
  {
    key: 'submitted_at',
    label: 'Waktu',
    render: (row) => (row.submitted_at ? new Date(row.submitted_at).toLocaleString('id-ID') : '-'),
  },
]

const entry = () => store.commentsByQuestion[props.questionCode]

async function load(page = 1) {
  try {
    await store.loadComments(props.questionCode, { page, search: searchInput.value })
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memuat komentar.', 'error')
  }
}

onMounted(() => load(1))
</script>

<template>
  <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3">
    <div class="flex flex-wrap gap-2">
      <input
        v-model="searchInput"
        type="text"
        placeholder="Cari isi komentar..."
        class="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm"
        @keyup.enter="load(1)"
      />
      <button
        type="button"
        class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-white"
        @click="load(1)"
      >
        Cari
      </button>
    </div>

    <div v-if="entry()?.loading" class="mt-3 text-center text-xs text-slate-400">Memuat komentar...</div>

    <p v-else-if="entry()?.result?.suppressed" class="mt-3 text-xs text-slate-400">
      Data disembunyikan -- jumlah komentar di bawah ambang batas minimum_segment_size.
    </p>

    <DataTable
      v-else-if="entry()?.result?.data"
      class="mt-3"
      :columns="columns"
      :rows="entry()!.result!.data!"
      :page="entry()!.result!.meta!.current_page"
      :per-page="entry()!.result!.meta!.per_page"
      :total="entry()!.result!.meta!.total"
      @update:page="load"
    />
  </div>
</template>
