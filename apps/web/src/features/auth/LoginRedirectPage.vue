<script setup lang="ts">
// myAMETA Survey tidak boleh punya form login sendiri (aturan dokumen 15) —
// halaman ini hanya transit: redirect penuh ke apps/api -> Portal.
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/features/auth/store/auth'

const route = useRoute()
const auth = useAuthStore()
const ssoError = ref(route.query.error === 'sso_failed')
const correlationId = ref(typeof route.query.correlation_id === 'string' ? route.query.correlation_id : null)

onMounted(() => {
  if (!ssoError.value) {
    auth.redirectToLogin()
  }
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="max-w-sm text-center">
      <div class="mx-auto h-10 w-10 rounded-lg bg-brand-600" />
      <h1 class="mt-4 text-base font-semibold text-slate-900">myAMETA Survey</h1>

      <template v-if="ssoError">
        <p class="mt-2 text-sm text-rose-600">Login gagal. Silakan coba lagi.</p>
        <p v-if="correlationId" class="mt-1 text-xs text-slate-400">
          Kode referensi: {{ correlationId }}
        </p>
        <button
          type="button"
          class="mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
          @click="auth.redirectToLogin()"
        >
          Coba Lagi
        </button>
      </template>
      <p v-else class="mt-2 text-sm text-slate-500">Mengarahkan ke Portal institusi...</p>
    </div>
  </div>
</template>
