<script setup lang="ts">
// Notifikasi job async (export/import/sync selesai) — FSD §14.2, §8.3.
// Store toast disiapkan di composable useToast() (lihat useToast.ts) supaya
// bisa dipicu dari mana saja (mis. setelah polling status export_job).
export type ToastTone = 'success' | 'error' | 'info'

defineProps<{
  message: string
  tone?: ToastTone
}>()

defineEmits<{ dismiss: [] }>()

const TONE_CLASS: Record<string, string> = {
  success: 'bg-green-600',
  error: 'bg-rose-600',
  info: 'bg-slate-800',
}
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-white shadow-lg"
    :class="TONE_CLASS[tone ?? 'info']"
    role="status"
  >
    <span class="flex-1">{{ message }}</span>
    <button type="button" class="text-white/70 hover:text-white" @click="$emit('dismiss')">✕</button>
  </div>
</template>
