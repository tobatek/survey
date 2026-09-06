<script setup lang="ts">
// Wrapper chart wajib menampilkan judul, question code, `n`, filter aktif,
// dan timestamp agregasi (FSD §13.5) — tidak ada chart yang berdiri sendiri
// tanpa konteks ini. Chart itu sendiri disuntik lewat slot supaya ChartCard
// tidak terikat ke satu chart library (ApexCharts dipasang Fase 5).
defineProps<{
  title: string
  questionCode?: string
  n: number
  filterSummary?: string
  refreshedAt?: string
}>()

const emit = defineEmits<{ exportPng: [] }>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
          <span v-if="questionCode" class="text-xs text-slate-400">{{ questionCode }}</span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
          n = {{ n }}<span v-if="filterSummary"> &middot; {{ filterSummary }}</span>
        </p>
      </div>
      <button
        type="button"
        class="shrink-0 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
        @click="emit('exportPng')"
      >
        Unduh PNG
      </button>
    </div>

    <div class="mt-4 w-full overflow-x-auto">
      <slot />
    </div>

    <p v-if="refreshedAt" class="mt-3 text-right text-[11px] text-slate-400">
      Diperbarui {{ refreshedAt }}
    </p>
  </div>
</template>
