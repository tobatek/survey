<script setup lang="ts">
// Pengaturan > Tampilan & Logo: admin mengunggah logo/favicon dan mengatur
// nama institusi, warna utama, serta teks halaman login/footer.
import { onMounted, reactive, ref } from 'vue'
import AppShell from '@/features/shared/components/AppShell.vue'
import Card from '@/features/shared/components/Card.vue'
import { NAV_ITEMS } from '@/router/nav'
import { apiFetch, ApiError } from '@/lib/api'
import { useToast } from '@/features/shared/useToast'
import { useBrandingStore, type Branding } from '@/features/branding/store/branding'

const branding = useBrandingStore()
const { push } = useToast()

const form = reactive({
  institution_name: '',
  primary_color: '#254bdb',
  login_title: '',
  login_description: '',
  footer_text: '',
})
const logoFile = ref<File | null>(null)
const faviconFile = ref<File | null>(null)
const removeLogo = ref(false)
const removeFavicon = ref(false)
const logoPreview = ref<string | null>(null)
const faviconPreview = ref<string | null>(null)
const saving = ref(false)

function fillFromStore() {
  const d = branding.data
  form.institution_name = d?.institution_name ?? ''
  form.primary_color = d?.primary_color ?? '#254bdb'
  form.login_title = d?.login_title ?? ''
  form.login_description = d?.login_description ?? ''
  form.footer_text = d?.footer_text ?? ''
  logoPreview.value = d?.logo_url ?? null
  faviconPreview.value = d?.favicon_url ?? null
}

onMounted(async () => {
  if (!branding.loaded) await branding.load()
  fillFromStore()
})

function onPick(kind: 'logo' | 'favicon', event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] ?? null
  if (!file) return
  const preview = URL.createObjectURL(file)

  if (kind === 'logo') {
    logoFile.value = file
    removeLogo.value = false
    logoPreview.value = preview
  } else {
    faviconFile.value = file
    removeFavicon.value = false
    faviconPreview.value = preview
  }
}

function clearImage(kind: 'logo' | 'favicon') {
  if (kind === 'logo') {
    logoFile.value = null
    removeLogo.value = true
    logoPreview.value = null
  } else {
    faviconFile.value = null
    removeFavicon.value = true
    faviconPreview.value = null
  }
}

async function save() {
  saving.value = true

  const body = new FormData()
  body.append('institution_name', form.institution_name)
  body.append('primary_color', form.primary_color)
  body.append('login_title', form.login_title)
  body.append('login_description', form.login_description)
  body.append('footer_text', form.footer_text)
  if (logoFile.value) body.append('logo', logoFile.value)
  if (faviconFile.value) body.append('favicon', faviconFile.value)
  if (removeLogo.value) body.append('remove_logo', '1')
  if (removeFavicon.value) body.append('remove_favicon', '1')

  try {
    const res = await apiFetch<{ data: Branding }>('/branding', { method: 'POST', body })
    branding.set(res.data)
    logoFile.value = null
    faviconFile.value = null
    removeLogo.value = false
    removeFavicon.value = false
    fillFromStore()
    push('Tampilan berhasil disimpan.', 'success')
  } catch (e) {
    push(e instanceof ApiError ? e.message : 'Gagal menyimpan tampilan.', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppShell :nav-items="NAV_ITEMS" active-role-label="LPM Admin">
    <h1 class="text-lg font-semibold text-slate-900">Pengaturan &middot; Tampilan &amp; Logo</h1>
    <p class="mt-1 text-sm text-slate-500">
      Atur identitas institusi yang tampil di halaman login, sidebar, dan tab browser.
    </p>

    <form class="mt-6 grid gap-6 lg:grid-cols-2" @submit.prevent="save">
      <Card>
        <template #header>
          <h2 class="text-sm font-semibold text-slate-900">Identitas</h2>
        </template>

        <template #body>
        <div class="space-y-4">
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Nama institusi / aplikasi</span>
            <input
              v-model="form.institution_name"
              type="text"
              maxlength="120"
              placeholder="myAMETA Survey"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </label>

          <div class="text-sm">
            <span class="font-medium text-slate-700">Warna utama</span>
            <div class="mt-1 flex items-center gap-3">
              <input v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-slate-300" />
              <input
                v-model="form.primary_color"
                type="text"
                maxlength="7"
                class="w-28 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
              />
            </div>
          </div>

          <div class="text-sm">
            <span class="font-medium text-slate-700">Logo (PNG/JPG/WebP, maks 1 MB)</span>
            <div class="mt-1 flex items-center gap-3">
              <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
                <img v-if="logoPreview" :src="logoPreview" alt="Pratinjau logo" class="max-h-14 max-w-14 object-contain" />
                <span v-else class="text-xs text-slate-400">Belum ada</span>
              </div>
              <input type="file" accept="image/png,image/jpeg,image/webp" class="text-xs" @change="onPick('logo', $event)" />
              <button v-if="logoPreview" type="button" class="text-xs text-rose-600 hover:underline" @click="clearImage('logo')">
                Hapus
              </button>
            </div>
          </div>

          <div class="text-sm">
            <span class="font-medium text-slate-700">Favicon (PNG/ICO/WebP, maks 256 KB)</span>
            <div class="mt-1 flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
                <img v-if="faviconPreview" :src="faviconPreview" alt="Pratinjau favicon" class="max-h-8 max-w-8 object-contain" />
                <span v-else class="text-xs text-slate-400">&mdash;</span>
              </div>
              <input type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/webp,.ico" class="text-xs" @change="onPick('favicon', $event)" />
              <button v-if="faviconPreview" type="button" class="text-xs text-rose-600 hover:underline" @click="clearImage('favicon')">
                Hapus
              </button>
            </div>
          </div>
        </div>
        </template>
      </Card>

      <Card>
        <template #header>
          <h2 class="text-sm font-semibold text-slate-900">Teks Halaman Login</h2>
        </template>

        <template #body>
        <div class="space-y-4">
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Judul sambutan</span>
            <input
              v-model="form.login_title"
              type="text"
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Deskripsi</span>
            <textarea
              v-model="form.login_description"
              rows="4"
              maxlength="500"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Teks footer</span>
            <input
              v-model="form.footer_text"
              type="text"
              maxlength="200"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </label>
        </div>
        </template>
      </Card>

      <div class="lg:col-span-2">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
