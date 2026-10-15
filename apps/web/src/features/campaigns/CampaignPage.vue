<script setup lang="ts">
// Halaman detail satu Campaign. Selama status draft, tampilkan wizard
// (target -> jadwal -> aktivasi). Begitu aktif/dijeda/ditutup, tampilkan
// dashboard pemantauan (funnel, breakdown, tombol pause/resume/close).
import { onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/features/shared/components/AppShell.vue'
import StatusBadge from '@/features/shared/components/StatusBadge.vue'
import { useToast } from '@/features/shared/useToast'
import { NAV_ITEMS } from '@/router/nav'
import { ApiError } from '@/lib/api'
import { useCampaignStore } from './store/campaign'
import CampaignWizard from './components/CampaignWizard.vue'
import CampaignMonitoringDashboard from './components/CampaignMonitoringDashboard.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const store = useCampaignStore()

async function loadCampaign() {
  try {
    await store.load(route.params.campaignId as string)
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memuat campaign.', 'error')
  }
}

watch(() => route.params.campaignId, loadCampaign)
onMounted(loadCampaign)
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <div v-if="store.loading" class="p-10 text-center text-sm text-slate-400">Memuat campaign...</div>

    <template v-else-if="store.campaign">
      <button type="button" class="text-xs text-slate-400 hover:text-slate-600" @click="router.back()">
        &larr; Kembali
      </button>

      <div class="mt-1 flex items-center gap-2">
        <h1 class="text-lg font-semibold text-slate-900">Campaign</h1>
        <StatusBadge :status="store.campaign.status" />
      </div>

      <CampaignWizard v-if="store.campaign.status === 'draft'" class="mt-4" />

      <CampaignMonitoringDashboard v-else />
    </template>
  </AppShell>
</template>
