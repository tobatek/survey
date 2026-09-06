<script setup lang="ts">
// Placeholder Fase 0. Fase 1: redirect otomatis ke
// `${SSO_PORTAL_BASE_URL}/sso/authorize?client_id=...&redirect_uri=...`
// myAMETA Survey tidak boleh punya form login sendiri (aturan dokumen 15).
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="max-w-sm text-center">
      <div class="mx-auto h-10 w-10 rounded-lg bg-brand-600" />
      <h1 class="mt-4 text-base font-semibold text-slate-900">myAMETA Survey</h1>
      <p class="mt-2 text-sm text-slate-500">
        Login akan mengarahkan Anda ke Portal institusi. Integrasi SSO dibangun di Fase 1.
      </p>
    </div>
  </div>
</template>
