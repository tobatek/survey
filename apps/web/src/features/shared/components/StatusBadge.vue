<script setup lang="ts">
// Badge status dipakai lintas modul: Kuesioner (Draft/Siap/Aktif/Dijeda/Ditutup),
// Campaign monitoring, dan Sync (berhasil/gagal/bermasalah).
// Mapping warna mengikuti token `status.*` di tailwind.config.js.

export type SurveyStatus = 'draft' | 'ready' | 'active' | 'paused' | 'closed'

const STATUS_LABEL: Record<SurveyStatus, string> = {
  draft: 'Draft',
  ready: 'Siap Disebarkan',
  active: 'Aktif',
  paused: 'Dijeda',
  closed: 'Ditutup',
}

const STATUS_CLASS: Record<SurveyStatus, string> = {
  draft: 'bg-slate-100 text-slate-600 ring-slate-300',
  ready: 'bg-blue-50 text-blue-700 ring-blue-300',
  active: 'bg-green-50 text-green-700 ring-green-300',
  paused: 'bg-amber-50 text-amber-700 ring-amber-300',
  closed: 'bg-rose-50 text-rose-700 ring-rose-300',
}

defineProps<{
  status: SurveyStatus
}>()
</script>

<template>
  <span
    class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
    :class="STATUS_CLASS[status]"
  >
    {{ STATUS_LABEL[status] }}
  </span>
</template>
