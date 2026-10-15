<script setup lang="ts">
// Panel filter collapsible ala sidebar filter karirlink.id (job type, lokasi,
// gaji...) — di sini dipakai untuk Data Mahasiswa/Pegawai & Hasil Analisa
// (prodi/angkatan/unit/status/periode). Filter diterapkan lewat tombol
// "Terapkan Filter" (bukan on-change) supaya hemat request saat beban tinggi
// — lihat catatan desain di docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md.
import { ref } from 'vue'

const emit = defineEmits<{ apply: []; reset: [] }>()

const expanded = ref(true)
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white">
    <button
      type="button"
      class="flex w-full items-center justify-between px-4 py-3 text-sm font-semibold text-slate-900 lg:hidden"
      @click="expanded = !expanded"
    >
      Filter
      <svg
        class="h-4 w-4 transition-transform"
        :class="{ 'rotate-180': expanded }"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-show="expanded" class="space-y-4 border-t border-slate-100 p-4 lg:border-t-0">
      <slot />

      <div class="flex gap-2 pt-2">
        <button
          type="button"
          class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
          @click="emit('apply')"
        >
          Terapkan Filter
        </button>
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
          @click="emit('reset')"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>
