<script setup lang="ts">
// My Surveys — halaman responden (mahasiswa/pegawai), tanpa sidebar admin.
// Ini yang paling wajib mobile-first (dipakai dari HP) — FSD §11.1.
import Card from '@/features/shared/components/Card.vue'
import StatusBadge from '@/features/shared/components/StatusBadge.vue'
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-200 bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-2xl items-center gap-2">
        <div class="h-8 w-8 rounded-lg bg-brand-600" />
        <span class="font-semibold text-slate-900">myAMETA Survey</span>
      </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6 sm:px-6">
      <h1 class="text-lg font-semibold text-slate-900">Survei Saya</h1>
      <p class="mt-1 text-sm text-slate-500">Daftar survei yang ditujukan untuk Anda.</p>

      <div class="mt-6 space-y-3">
        <Card clickable>
          <template #header>
            <p class="text-xs text-slate-400">Layanan Mahasiswa &middot; ~5 menit</p>
            <h2 class="text-sm font-semibold text-slate-900">Survei Kepuasan Mahasiswa 2026</h2>
          </template>
          <template #badge>
            <StatusBadge status="active" />
          </template>
          <template #actions>
            <button class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white sm:w-auto">
              Mulai Mengisi
            </button>
          </template>
        </Card>
      </div>
    </main>
  </div>
</template>
