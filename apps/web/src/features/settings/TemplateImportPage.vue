<script setup lang="ts">
// Placeholder Fase 0 — wizard import template (XLSX/JSON) dibangun di Fase 2.
import AppShell from '@/features/shared/components/AppShell.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import { NAV_ITEMS } from '@/router/nav'
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Pengaturan &middot; Import Template Kuesioner</h1>
    <div class="mt-6">
      <EmptyState
        title="Belum ada template diimpor"
        description="Wizard upload XLSX/JSON dengan preview & validasi dibangun di Fase 2."
      />
    </div>
  </AppShell>
</template>
