<script setup lang="ts">
// Placeholder Fase 0 — setara StudentListPage, diisi penuh di Fase 1.
import AppShell from '@/features/shared/components/AppShell.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import { NAV_ITEMS } from '@/router/nav'
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Data Pegawai</h1>
    <p class="mt-1 text-sm text-slate-500">Master data dosen &amp; tenaga kependidikan.</p>
    <div class="mt-6">
      <EmptyState title="Belum terhubung ke Portal" description="Sinkronisasi data pegawai dibangun di Fase 1." />
    </div>
  </AppShell>
</template>
