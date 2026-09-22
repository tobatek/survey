<script setup lang="ts">
// Stub sementara -- form pengisian penuh (stepper, autosave, branching)
// dibangun di Fase 4 slice 5. Untuk sekarang cukup konfirmasi sesi berhasil
// dibuat/dilanjutkan (slice 2), supaya jalur start bisa didemokan end-to-end.
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm">
      <h1 class="text-sm font-semibold text-slate-900">Sesi pengisian dimulai</h1>
      <p class="mt-2 text-sm text-slate-500">Formulir lengkap sedang dibangun.</p>
      <p class="mt-3 break-all text-xs text-slate-400">Session ID: {{ route.params.sessionId }}</p>
    </div>
  </div>
</template>
