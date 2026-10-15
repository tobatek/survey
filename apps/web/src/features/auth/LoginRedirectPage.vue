<script setup lang="ts">
// Halaman landing/login (belum login & login gagal), gaya hero layar penuh.
// myAMETA Survey tidak boleh punya form login sendiri (aturan dokumen 15):
// satu-satunya jalur masuk adalah tombol "Login SSO" -> apps/api -> Portal.
// Branding (logo, nama, judul, deskripsi, warna) dari Pengaturan > Tampilan.
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/features/auth/store/auth'
import { useBrandingStore } from '@/features/branding/store/branding'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const branding = useBrandingStore()
const ssoError = ref(route.query.error === 'sso_failed')
const correlationId = ref(typeof route.query.correlation_id === 'string' ? route.query.correlation_id : null)

onMounted(() => {
  // Sudah punya sesi: tidak perlu melihat halaman login lagi.
  if (auth.isAuthenticated) {
    router.replace('/')
  }
})
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[#1c1f3d] text-white">
    <!-- Dekorasi latar -->
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute -left-40 top-1/3 h-[34rem] w-[34rem] rounded-full bg-brand-700/40 blur-3xl" />
      <div class="absolute -top-24 left-1/3 h-64 w-64 rounded-full bg-brand-400/30 blur-2xl" />
      <div class="absolute right-[-8rem] top-10 h-[30rem] w-[30rem] rounded-full bg-brand-500/25 blur-3xl" />
      <div class="absolute left-[38%] top-8 h-28 w-28 rounded-full bg-gradient-to-br from-brand-300/70 to-brand-600/60" />
      <div class="absolute right-[24%] top-16 h-20 w-20 rounded-full bg-slate-300/30" />
      <div class="absolute bottom-24 left-[28%] h-16 w-16 rounded-full bg-brand-300/20" />
    </div>

    <div class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 sm:px-10 lg:px-16">
      <!-- Header: logo + nama institusi -->
      <header class="flex items-center gap-3">
        <img
          v-if="branding.data?.logo_url"
          :src="branding.data.logo_url"
          alt="Logo"
          class="h-12 w-12 object-contain"
        />
        <div v-else class="h-12 w-12 rounded-xl bg-white/15" />
        <span class="text-lg font-semibold uppercase tracking-wide">{{ branding.appName }}</span>
      </header>

      <div class="grid flex-1 items-center gap-10 lg:grid-cols-2">
        <!-- Teks hero -->
        <main class="py-10 lg:py-0">
          <h1 class="text-4xl font-extrabold uppercase leading-tight tracking-tight sm:text-5xl lg:text-6xl">
            {{ branding.data?.login_title || 'Survei Institusi' }}
          </h1>
          <p class="mt-6 max-w-xl text-base leading-relaxed text-indigo-100/80 sm:text-lg">
            {{
              branding.data?.login_description ||
              'Sampaikan penilaian Anda untuk membantu peningkatan mutu layanan dan pembelajaran.'
            }}
          </p>

          <div
            v-if="ssoError"
            role="alert"
            class="mt-8 max-w-md rounded-xl border border-rose-300/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-100"
          >
            <p class="font-semibold">Login gagal. Silakan coba lagi.</p>
            <p v-if="correlationId" class="mt-1 break-all text-xs text-rose-200/80">
              Kode referensi: {{ correlationId }}
            </p>
          </div>

          <button
            type="button"
            class="mt-8 rounded-full bg-gradient-to-r from-brand-500 to-fuchsia-500 px-9 py-3.5 text-sm font-bold uppercase tracking-wide text-white shadow-lg shadow-brand-900/40 transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c1f3d]"
            @click="auth.redirectToLogin()"
          >
            Login SSO
          </button>
        </main>

        <!-- Ilustrasi dashboard (murni CSS, tanpa gambar eksternal) -->
        <div class="relative hidden lg:block" aria-hidden="true">
          <div class="ml-auto w-full max-w-xl rounded-t-2xl border border-white/20 bg-indigo-50 p-3 shadow-2xl">
            <div class="flex items-center gap-2 rounded-md bg-white px-3 py-2">
              <div class="h-3 flex-1 rounded-full bg-slate-200" />
              <div class="h-3 w-12 rounded-full bg-brand-300" />
            </div>
            <div class="mt-2 h-6 rounded-md bg-brand-200" />
            <div class="mt-2 grid grid-cols-3 gap-2">
              <div class="rounded-lg bg-white p-3">
                <div class="mx-auto h-12 w-12 rounded-full border-4 border-emerald-300" />
                <div class="mt-2 h-2 rounded-full bg-slate-200" />
              </div>
              <div class="flex items-end gap-1.5 rounded-lg bg-white p-3">
                <div class="h-8 w-3 rounded-sm bg-brand-300" />
                <div class="h-12 w-3 rounded-sm bg-brand-400" />
                <div class="h-16 w-3 rounded-sm bg-amber-400" />
                <div class="h-20 w-3 rounded-sm bg-brand-500" />
              </div>
              <div class="flex items-end gap-1.5 rounded-lg bg-brand-600 p-3">
                <div class="h-10 w-3 rounded-sm bg-white/80" />
                <div class="h-16 w-3 rounded-sm bg-amber-300" />
                <div class="h-12 w-3 rounded-sm bg-white/80" />
                <div class="h-20 w-3 rounded-sm bg-emerald-300" />
              </div>
            </div>
            <div class="mt-2 space-y-2">
              <div class="h-8 rounded-lg bg-white" />
              <div class="h-8 w-4/5 rounded-lg bg-white" />
            </div>
          </div>
          <div class="ml-auto h-3 w-[calc(100%+2rem)] max-w-[34rem] rounded-b-2xl bg-slate-300/80" />
        </div>
      </div>

      <footer v-if="branding.data?.footer_text" class="pb-2 text-xs text-indigo-100/60">
        {{ branding.data.footer_text }}
      </footer>
    </div>
  </div>
</template>
