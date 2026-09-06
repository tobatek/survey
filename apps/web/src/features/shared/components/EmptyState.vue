<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-slate-200 p-10 text-center">
    <div class="rounded-full bg-slate-100 p-3">
      <svg class="h-6 w-6 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-6 4h6M9 9h1M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
    </div>
    <p class="text-sm font-medium text-slate-900">{{ title }}</p>
    <p v-if="description" class="max-w-sm text-sm text-slate-500">{{ description }}</p>
    <slot name="action" />
  </div>
</template>
