<script setup lang="ts">
// Placeholder Fase 0 — StatCard/ChartCard penuh dengan data nyata di Fase 5.
import AppShell from '@/features/shared/components/AppShell.vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import { NAV_ITEMS } from '@/router/nav'
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Hasil Analisa Kuesioner</h1>
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
      <StatCard label="Eligible" value="—" />
      <StatCard label="Selesai" value="—" />
      <StatCard label="Response Rate" value="—" tone="positive" />
      <StatCard label="n (sample)" value="—" />
    </div>
    <p class="mt-6 text-sm text-slate-500">Data agregat & chart dibangun di Fase 5.</p>
  </AppShell>
</template>
