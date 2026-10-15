<script setup lang="ts">
defineEmits<{ close: []; confirm: [] }>()
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
      <h2 class="text-sm font-semibold text-slate-900">Tutup Campaign?</h2>
      <p class="mt-2 text-xs text-slate-500">
        Setelah ditutup, campaign tidak bisa diaktifkan atau dijeda lagi. Undangan yang belum direspons tidak akan
        dikirim ulang.
      </p>
      <div class="mt-4 flex justify-end gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
          @click="$emit('close')"
        >
          Batal
        </button>
        <button
          type="button"
          class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700"
          @click="$emit('confirm')"
        >
          Ya, Tutup Campaign
        </button>
      </div>
    </div>
  </div>
</template>
