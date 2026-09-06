<script setup lang="ts">
// Placeholder Fase 0 — daftar Kuesioner penuh (tab status, builder, dst.)
// dibangun di Fase 2 sesuai docs/16_PLAN_FULLSTACK_LARAVEL_MYAMETA_SURVEY.md.
import AppShell from '@/features/shared/components/AppShell.vue'
import Card from '@/features/shared/components/Card.vue'
import StatusBadge from '@/features/shared/components/StatusBadge.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import { NAV_ITEMS } from '@/router/nav'
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <template #topbar-actions>
      <button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
        Buat Kuesioner
      </button>
    </template>

    <h1 class="text-lg font-semibold text-slate-900">Kuesioner</h1>
    <p class="mt-1 text-sm text-slate-500">Kelola kuesioner dari draft hingga hasil analisa.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Card clickable>
        <template #header>
          <p class="text-xs text-slate-400">SKM-MHS-01</p>
          <h2 class="truncate text-sm font-semibold text-slate-900">Survei Kepuasan Mahasiswa 2026</h2>
        </template>
        <template #badge>
          <StatusBadge status="active" />
        </template>
        <template #meta>
          <p class="mt-1 text-xs text-slate-500">Target: Mahasiswa &middot; 1 Sep &ndash; 30 Sep 2026</p>
        </template>
        <template #actions>
          <button class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600">
            Pantau
          </button>
        </template>
      </Card>
    </div>

    <div class="mt-6">
      <EmptyState
        title="Belum ada kuesioner lain"
        description="Data contoh di atas akan diganti data asli pada Fase 2 (builder & lifecycle)."
      />
    </div>
  </AppShell>
</template>
