<script setup lang="ts">
// Kartu ringkasan angka untuk dashboard funnel campaign / monitoring sync
// (eligible, invited, completed, response rate, dsb — FSD §13.2/§13.3).
defineProps<{
  label: string
  value: string | number
  hint?: string
  tone?: 'neutral' | 'positive' | 'warning' | 'danger'
}>()

const TONE_CLASS: Record<string, string> = {
  neutral: 'text-slate-900',
  positive: 'text-green-600',
  warning: 'text-amber-600',
  danger: 'text-rose-600',
}
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-4">
    <div class="text-xs font-medium text-slate-500">{{ label }}</div>
    <div class="mt-1 text-2xl font-semibold" :class="TONE_CLASS[tone ?? 'neutral']">{{ value }}</div>
    <div v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</div>
  </div>
</template>
