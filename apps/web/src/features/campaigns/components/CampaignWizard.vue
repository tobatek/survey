<script setup lang="ts">
// Wizard 3 bagian (Target -> Jadwal -> Aktifkan) untuk campaign berstatus
// draft. Tidak ada komponen wizard/step generik di proyek ini dan tidak ada
// endpoint untuk mengambil daftar nilai fakultas/prodi/unit yang valid,
// jadi filter target dibuat sebagai input teks dipisah koma (bukan
// dropdown) -- sederhana tapi berfungsi untuk MVP.
import { computed, reactive, ref } from 'vue'
import StatCard from '@/features/shared/components/StatCard.vue'
import { useToast } from '@/features/shared/useToast'
import { useAuthStore } from '@/features/auth/store/auth'
import { ApiError } from '@/lib/api'
import { useCampaignStore, type AudienceFilter } from '../store/campaign'

const store = useCampaignStore()
const auth = useAuthStore()
const toast = useToast()

const canFilterByPosition = computed(() => auth.user?.roles.includes('lpm_admin') ?? false)

const filterForm = reactive({
  faculty_names: '',
  program_study_codes: '',
  degree_levels: '',
  cohort_years: '',
  student_statuses: '',
  employee_types: '',
  work_unit_names: '',
  employee_statuses: '',
  position_names: '',
})

const startsAt = ref('')
const endsAt = ref('')
const timezone = ref(store.campaign?.timezone ?? 'Asia/Jakarta')

function toList(value: string): string[] | undefined {
  const items = value.split(',').map((s) => s.trim()).filter(Boolean)
  return items.length ? items : undefined
}

function buildFilter(): AudienceFilter {
  return {
    faculty_names: toList(filterForm.faculty_names),
    program_study_codes: toList(filterForm.program_study_codes),
    degree_levels: toList(filterForm.degree_levels),
    cohort_years: toList(filterForm.cohort_years),
    student_statuses: toList(filterForm.student_statuses),
    employee_types: toList(filterForm.employee_types),
    work_unit_names: toList(filterForm.work_unit_names),
    employee_statuses: toList(filterForm.employee_statuses),
    position_names: canFilterByPosition.value ? toList(filterForm.position_names) : undefined,
  }
}

async function runPreview() {
  try {
    await store.previewAudience(buildFilter())
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menghitung perkiraan target.', 'error')
  }
}

async function saveSchedule() {
  try {
    await store.updateSchedule({
      starts_at: startsAt.value ? new Date(startsAt.value).toISOString() : null,
      ends_at: endsAt.value ? new Date(endsAt.value).toISOString() : null,
      timezone: timezone.value,
    })
    toast.push('Jadwal tersimpan.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal menyimpan jadwal.', 'error')
  }
}

async function runActivate() {
  try {
    await store.activate()
    toast.push('Campaign berhasil diaktifkan. Undangan sedang dikirim ke semua target.', 'success')
  } catch (error) {
    toast.push(error instanceof ApiError ? error.message : 'Gagal mengaktifkan campaign.', 'error')
  }
}
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="text-sm font-semibold text-slate-900">1. Target Responden</h2>
      <p class="mt-1 text-xs text-slate-400">Isi salah satu atau beberapa filter (pisahkan dengan koma untuk lebih dari satu nilai). Kosongkan semua untuk menargetkan semua orang.</p>

      <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <h3 class="text-xs font-medium text-slate-600">Mahasiswa</h3>
          <div class="mt-2 space-y-2">
            <label class="block text-xs text-slate-500">Fakultas
              <input v-model="filterForm.faculty_names" type="text" placeholder="mis. Teknik, Ekonomi" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Kode Prodi
              <input v-model="filterForm.program_study_codes" type="text" placeholder="mis. TI, SI" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Jenjang
              <input v-model="filterForm.degree_levels" type="text" placeholder="mis. S1, D3" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Angkatan
              <input v-model="filterForm.cohort_years" type="text" placeholder="mis. 2022, 2023" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Status
              <input v-model="filterForm.student_statuses" type="text" placeholder="mis. active" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
          </div>
        </div>

        <div>
          <h3 class="text-xs font-medium text-slate-600">Pegawai</h3>
          <div class="mt-2 space-y-2">
            <label class="block text-xs text-slate-500">Jenis Pegawai
              <input v-model="filterForm.employee_types" type="text" placeholder="mis. dosen, tendik" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Unit Kerja
              <input v-model="filterForm.work_unit_names" type="text" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label class="block text-xs text-slate-500">Status
              <input v-model="filterForm.employee_statuses" type="text" placeholder="mis. active" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
            <label v-if="canFilterByPosition" class="block text-xs text-slate-500">
              Jabatan <span class="text-amber-600">(khusus LPM Admin)</span>
              <input v-model="filterForm.position_names" type="text" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
            </label>
          </div>
        </div>
      </div>

      <button
        type="button"
        class="mt-4 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
        :disabled="store.saving"
        @click="runPreview"
      >
        Hitung Perkiraan
      </button>

      <div v-if="store.previewResult" class="mt-4">
        <div v-if="store.previewResult.is_empty" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700 ring-1 ring-amber-200">
          Tidak ada target yang cocok dengan filter ini. Campaign tidak bisa diaktifkan sampai ada target.
        </div>
        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <StatCard label="Total Target" :value="store.previewResult.total" tone="positive" />
          <StatCard label="Mahasiswa" :value="store.previewResult.students.count" />
          <StatCard label="Pegawai" :value="store.previewResult.employees.count" />
        </div>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="text-sm font-semibold text-slate-900">2. Jadwal</h2>
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <label class="block text-xs text-slate-500">Mulai
          <input v-model="startsAt" type="datetime-local" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
        </label>
        <label class="block text-xs text-slate-500">Selesai
          <input v-model="endsAt" type="datetime-local" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
        </label>
        <label class="block text-xs text-slate-500">Zona Waktu
          <input v-model="timezone" type="text" class="mt-1 w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
        </label>
      </div>
      <button
        type="button"
        class="mt-4 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
        :disabled="store.saving"
        @click="saveSchedule"
      >
        Simpan Jadwal
      </button>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="text-sm font-semibold text-slate-900">3. Aktifkan</h2>
      <p class="mt-1 text-xs text-slate-400">
        Setelah diaktifkan, undangan email akan langsung dikirim ke semua target dan struktur target tidak bisa diubah lagi.
      </p>
      <button
        type="button"
        class="mt-4 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
        :disabled="store.saving || !store.previewResult || store.previewResult.is_empty"
        @click="runActivate"
      >
        Aktifkan Campaign
      </button>
    </section>
  </div>
</template>
