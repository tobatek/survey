<script setup lang="ts">
// Fase 5 slice 7 -- 4 tombol format (FSD §14). Status job dipoll oleh store
// (pollExportJob), komponen ini cuma menampilkan hasilnya + toast di titik
// penting (diqueue/sukses/gagal). `download_url` adalah signed URL absolut
// dari backend -- navigasi langsung, BUKAN lewat apiFetch (itu file binary).
import { watch } from 'vue'
import { useToast } from '@/features/shared/useToast'
import { ApiError } from '@/lib/api'
import { useAnalyticsStore, type ExportFormat, type ExportJob } from '../store/analytics'

const store = useAnalyticsStore()
const toast = useToast()

const FORMAT_LABEL: Record<ExportFormat, string> = { xlsx: 'XLSX', csv: 'CSV', pdf: 'PDF', png: 'PNG' }
const STATUS_LABEL: Record<ExportJob['status'], string> = {
  queued: 'Menunggu',
  processing: 'Diproses',
  succeeded: 'Selesai',
  failed: 'Gagal',
}
const STATUS_CLASS: Record<ExportJob['status'], string> = {
  queued: 'text-slate-500',
  processing: 'text-amber-600',
  succeeded: 'text-green-600',
  failed: 'text-rose-600',
}

async function runExport(format: ExportFormat) {
  try {
    await store.requestExport(format)
    toast.push(`Export ${FORMAT_LABEL[format]} diqueue.`, 'info')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal membuat export.', 'error')
  }
}

// Toast begitu satu job mencapai status terminal (sukses/gagal) -- dipantau
// dari perubahan array exportJobs yang di-update in-place oleh polling.
const notified = new Set<string>()
watch(
  () => store.exportJobs.map((j) => `${j.id}:${j.status}`).join(','),
  () => {
    for (const job of store.exportJobs) {
      if (notified.has(job.id)) continue
      if (job.status === 'succeeded') {
        toast.push(`Export ${FORMAT_LABEL[job.format]} siap diunduh.`, 'success')
        notified.add(job.id)
      } else if (job.status === 'failed') {
        toast.push(`Export ${FORMAT_LABEL[job.format]} gagal${job.error_code ? ` (${job.error_code})` : ''}.`, 'error')
        notified.add(job.id)
      }
    }
  },
)
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <h3 class="text-sm font-semibold text-slate-900">Export</h3>
    <div class="mt-3 flex flex-wrap gap-2">
      <button
        v-for="format in (['xlsx', 'csv', 'pdf', 'png'] as ExportFormat[])"
        :key="format"
        type="button"
        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        @click="runExport(format)"
      >
        {{ FORMAT_LABEL[format] }}
      </button>
    </div>

    <ul v-if="store.exportJobs.length" class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
      <li v-for="job in store.exportJobs" :key="job.id" class="flex items-center justify-between gap-3 py-2 text-sm">
        <span class="text-slate-600">{{ FORMAT_LABEL[job.format] }}</span>
        <span class="font-medium" :class="STATUS_CLASS[job.status]">{{ STATUS_LABEL[job.status] }}</span>
        <a
          v-if="job.download_url"
          :href="job.download_url"
          class="rounded-md border border-brand-300 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50"
        >
          Unduh
        </a>
      </li>
    </ul>
  </div>
</template>
