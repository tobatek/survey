<script setup lang="ts">
// Wizard Import Template Kuesioner (Fase 2 slice 2, FSD §15 — slice JSON):
// 1) pilih file .json -> 2) pratinjau error/warning per path -> 3) konfirmasi
// & commit. XLSX, sample.json, dan aktif/nonaktif template dicatat sebagai
// backlog slice berikutnya.
import { computed, onMounted, ref } from 'vue'
import AppShell from '@/features/shared/components/AppShell.vue'
import Card from '@/features/shared/components/Card.vue'
import EmptyState from '@/features/shared/components/EmptyState.vue'
import { useToast } from '@/features/shared/useToast'
import { ApiError } from '@/lib/api'
import { NAV_ITEMS } from '@/router/nav'
import { useTemplateImportStore } from './store/templateImport'

const store = useTemplateImportStore()
const toast = useToast()
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFilename = ref<string | null>(null)

const hasErrors = computed(() => (store.current?.errors.length ?? 0) > 0)

async function onFileSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  selectedFilename.value = file.name
  const content = await file.text()

  try {
    await store.uploadContent(file.name, content)
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal memvalidasi file.', 'error')
  } finally {
    input.value = ''
  }
}

async function confirmCommit() {
  try {
    const result = await store.commit()
    if (result) {
      toast.push(`Template '${result.preview.template_code}' berhasil dibuat.`, 'success')
    }
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal mengonfirmasi import.', 'error')
  }
}

function startOver() {
  store.reset()
  selectedFilename.value = null
}

function statusLabel(status: string): string {
  return { pending_validation: 'Menunggu', validated: 'Valid', failed: 'Ada Error', committed: 'Terkonfirmasi' }[status] ?? status
}

onMounted(() => store.loadHistory())
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Pengaturan &middot; Import Template Kuesioner</h1>
    <p class="mt-1 text-sm text-slate-500">Unggah file JSON template, tinjau hasil validasi, lalu konfirmasi untuk membuat template baru.</p>

    <Card class="mt-6">
      <template #header>
        <h2 class="text-sm font-semibold text-slate-900">1. Unggah File</h2>
      </template>
      <template #body>
        <div class="flex items-center gap-3">
          <input
            ref="fileInput"
            type="file"
            accept=".json,application/json"
            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700"
            :disabled="store.uploading"
            @change="onFileSelected"
          />
        </div>
        <p v-if="store.uploading" class="mt-2 text-xs text-slate-400">Memvalidasi {{ selectedFilename }}...</p>
      </template>
    </Card>

    <Card v-if="store.current" class="mt-4">
      <template #header>
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-900">2. Pratinjau &amp; Validasi</h2>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="hasErrors ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
          >
            {{ statusLabel(store.current.status) }}
          </span>
        </div>
      </template>
      <template #body>
        <div class="grid grid-cols-3 gap-3 text-center">
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-lg font-semibold text-slate-900">{{ store.current.preview.sections }}</p>
            <p class="text-xs text-slate-500">Section</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-lg font-semibold text-slate-900">{{ store.current.preview.questions }}</p>
            <p class="text-xs text-slate-500">Pertanyaan</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-lg font-semibold text-slate-900">{{ store.current.preview.options }}</p>
            <p class="text-xs text-slate-500">Opsi</p>
          </div>
        </div>

        <div v-if="store.current.errors.length" class="mt-4">
          <p class="text-xs font-medium text-rose-600">Error ({{ store.current.errors.length }}) — wajib diperbaiki sebelum commit:</p>
          <ul class="mt-2 space-y-1">
            <li v-for="(issue, i) in store.current.errors" :key="i" class="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">
              <span class="font-mono text-rose-500">{{ issue.path }}</span> — {{ issue.message }}
            </li>
          </ul>
        </div>

        <div v-if="store.current.warnings.length" class="mt-4">
          <p class="text-xs font-medium text-amber-600">Peringatan ({{ store.current.warnings.length }}):</p>
          <ul class="mt-2 space-y-1">
            <li v-for="(issue, i) in store.current.warnings" :key="i" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
              <span class="font-mono text-amber-500">{{ issue.path }}</span> — {{ issue.message }}
            </li>
          </ul>
        </div>
      </template>
      <template #actions>
        <button
          type="button"
          class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600"
          @click="startOver"
        >
          Unggah Ulang
        </button>
        <button
          v-if="!store.current.committed_at"
          type="button"
          class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
          :disabled="!store.canCommit || store.committing"
          @click="confirmCommit"
        >
          {{ store.committing ? 'Mengonfirmasi...' : '3. Konfirmasi & Commit' }}
        </button>
        <span v-else class="self-center text-xs font-medium text-emerald-600">Sudah dikonfirmasi.</span>
      </template>
    </Card>

    <div class="mt-8">
      <h2 class="text-sm font-semibold text-slate-900">Riwayat Import</h2>

      <EmptyState
        v-if="!store.loadingHistory && store.history.length === 0"
        title="Belum ada riwayat import"
        description="Unggah file JSON di atas untuk memulai."
        class="mt-3"
      />

      <div v-else class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs text-slate-500">
            <tr>
              <th class="px-3 py-2">File</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Kode Template</th>
              <th class="px-3 py-2">Waktu</th>
              <th class="px-3 py-2">Diunggah oleh</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in store.history" :key="row.id">
              <td class="px-3 py-2">{{ row.filename }}</td>
              <td class="px-3 py-2">{{ statusLabel(row.status) }}</td>
              <td class="px-3 py-2">{{ row.preview.template_code ?? '-' }}</td>
              <td class="px-3 py-2 text-xs text-slate-400">{{ row.created_at }}</td>
              <td class="px-3 py-2 text-xs text-slate-400">{{ row.uploaded_by ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppShell>
</template>
