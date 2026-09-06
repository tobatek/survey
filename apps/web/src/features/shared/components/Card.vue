<script setup lang="ts">
// Card generik ala listing karirlink.id (judul, meta, badge, aksi kontekstual).
// Dipakai untuk daftar Kuesioner (admin) dan My Surveys (responden).
defineProps<{
  clickable?: boolean
}>()
</script>

<template>
  <div
    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition sm:p-5"
    :class="clickable ? 'cursor-pointer hover:border-brand-300 hover:shadow-md' : ''"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 flex-1">
        <slot name="header" />
        <slot name="meta" />
      </div>
      <div class="shrink-0">
        <slot name="badge" />
      </div>
    </div>
    <div v-if="$slots.body" class="mt-3 text-sm text-slate-600">
      <slot name="body" />
    </div>
    <div v-if="$slots.actions" class="mt-4 flex flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
