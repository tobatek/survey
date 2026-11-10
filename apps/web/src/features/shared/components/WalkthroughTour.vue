<script setup lang="ts">
// Tur jalan-jalan (onboarding) step-by-step yang generik: dipakai lintas halaman
// dengan hanya mengoper daftar `steps` (lihat useWalkthrough.ts). Elemen target
// disorot pakai teknik "spotlight" (box-shadow raksasa di sekeliling elemen),
// lalu kartu penjelasan ditempel di sebelahnya. Murni navigasi lewat tombol
// Lanjut/Kembali/Lewati — area gelap tidak bisa diklik supaya user tidak
// "tersasar" klik sesuatu di tengah tur.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useWalkthrough } from '@/features/shared/useWalkthrough'

const { state, next, back, skip } = useWalkthrough()

const currentStep = computed(() => state.steps[state.stepIndex] ?? null)
const isFirst = computed(() => state.stepIndex === 0)
const isLast = computed(() => state.stepIndex === state.steps.length - 1)

const spotlightRect = ref<{ top: number; left: number; width: number; height: number } | null>(null)
const tooltipPos = ref<{ top: number; left: number } | null>(null)
const tooltipPlacement = ref<'top' | 'bottom' | 'left' | 'right'>('bottom')
const tooltipEl = ref<HTMLElement | null>(null)
const ready = ref(false)

const PADDING = 8
const GAP = 16
const VIEWPORT_MARGIN = 12

function measure() {
  const step = currentStep.value
  if (!step) return

  const target = document.querySelector<HTMLElement>(step.target)
  if (!target || (target.offsetWidth === 0 && target.offsetHeight === 0)) {
    // Target tidak ada di DOM atau tersembunyi (mis. list kosong, breakpoint
    // mobile) — lewati step ini otomatis.
    if (isLast.value) skip()
    else next()
    return
  }

  target.scrollIntoView({ behavior: 'smooth', block: 'center' })

  // Beri waktu browser menuntaskan smooth-scroll sebelum mengukur posisi akhir.
  window.setTimeout(() => {
    const rect = target.getBoundingClientRect()
    spotlightRect.value = {
      top: rect.top - PADDING,
      left: rect.left - PADDING,
      width: rect.width + PADDING * 2,
      height: rect.height + PADDING * 2,
    }
    placeTooltip(rect, step.placement ?? 'bottom')
    ready.value = true
  }, 260)
}

function placeTooltip(rect: DOMRect, preferred: 'top' | 'bottom' | 'left' | 'right') {
  const tooltipWidth = tooltipEl.value?.offsetWidth ?? 340
  const tooltipHeight = tooltipEl.value?.offsetHeight ?? 160
  const vw = window.innerWidth
  const vh = window.innerHeight

  let placement = preferred
  const spotTop = rect.top - PADDING
  const spotBottom = rect.bottom + PADDING
  const spotLeft = rect.left - PADDING
  const spotRight = rect.right + PADDING

  // Balik posisi otomatis kalau ruang di sisi yang disukai tidak cukup.
  if (placement === 'bottom' && spotBottom + GAP + tooltipHeight > vh) placement = 'top'
  else if (placement === 'top' && spotTop - GAP - tooltipHeight < 0) placement = 'bottom'
  else if (placement === 'right' && spotRight + GAP + tooltipWidth > vw) placement = 'left'
  else if (placement === 'left' && spotLeft - GAP - tooltipWidth < 0) placement = 'right'

  let top = 0
  let left = 0

  if (placement === 'bottom') {
    top = spotBottom + GAP
    left = rect.left + rect.width / 2 - tooltipWidth / 2
  } else if (placement === 'top') {
    top = spotTop - GAP - tooltipHeight
    left = rect.left + rect.width / 2 - tooltipWidth / 2
  } else if (placement === 'right') {
    top = rect.top + rect.height / 2 - tooltipHeight / 2
    left = spotRight + GAP
  } else {
    top = rect.top + rect.height / 2 - tooltipHeight / 2
    left = spotLeft - GAP - tooltipWidth
  }

  left = Math.min(Math.max(left, VIEWPORT_MARGIN), vw - tooltipWidth - VIEWPORT_MARGIN)
  top = Math.min(Math.max(top, VIEWPORT_MARGIN), vh - tooltipHeight - VIEWPORT_MARGIN)

  tooltipPlacement.value = placement
  tooltipPos.value = { top, left }
}

function onViewportChange() {
  if (!state.active || !currentStep.value) return
  const target = document.querySelector<HTMLElement>(currentStep.value.target)
  if (!target) return
  const rect = target.getBoundingClientRect()
  spotlightRect.value = {
    top: rect.top - PADDING,
    left: rect.left - PADDING,
    width: rect.width + PADDING * 2,
    height: rect.height + PADDING * 2,
  }
  placeTooltip(rect, currentStep.value.placement ?? 'bottom')
}

watch(
  () => [state.active, state.stepIndex] as const,
  async ([active]) => {
    if (!active) {
      ready.value = false
      return
    }
    ready.value = false
    await nextTick()
    measure()
  },
  { immediate: true },
)

window.addEventListener('resize', onViewportChange)
window.addEventListener('scroll', onViewportChange, true)
onBeforeUnmount(() => {
  window.removeEventListener('resize', onViewportChange)
  window.removeEventListener('scroll', onViewportChange, true)
})

function onKeydown(e: KeyboardEvent) {
  if (!state.active) return
  if (e.key === 'Escape') skip()
  else if (e.key === 'ArrowRight' || e.key === 'Enter') next()
  else if (e.key === 'ArrowLeft') back()
}
window.addEventListener('keydown', onKeydown)
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="state.active && currentStep" class="fixed inset-0 z-[100]">
      <!-- Lapisan gelap + "lubang" spotlight lewat box-shadow, bukan klik-tembus. -->
      <div
        v-if="spotlightRect"
        class="pointer-events-none fixed rounded-xl ring-2 ring-white/80 transition-all duration-300 ease-out"
        :style="{
          top: spotlightRect.top + 'px',
          left: spotlightRect.left + 'px',
          width: spotlightRect.width + 'px',
          height: spotlightRect.height + 'px',
          boxShadow: '0 0 0 9999px rgba(15, 23, 42, 0.65)',
        }"
      />
      <div v-else class="fixed inset-0 bg-slate-900/65" />

      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="ready && tooltipPos"
          ref="tooltipEl"
          class="fixed w-[340px] max-w-[calc(100vw-24px)] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5"
          :style="{ top: tooltipPos.top + 'px', left: tooltipPos.left + 'px' }"
          role="dialog"
          aria-modal="true"
        >
          <div class="h-1.5 w-full bg-gradient-to-r from-brand-500 to-fuchsia-500" />

          <div class="p-5">
            <div class="flex items-start justify-between gap-3">
              <h3 class="text-sm font-semibold text-slate-900">{{ currentStep.title }}</h3>
              <button
                type="button"
                class="shrink-0 rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                aria-label="Tutup tur"
                @click="skip"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
              </button>
            </div>

            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ currentStep.description }}</p>

            <div class="mt-4 flex items-center justify-between">
              <div class="flex items-center gap-1.5">
                <span
                  v-for="(_, i) in state.steps"
                  :key="i"
                  class="h-1.5 rounded-full transition-all"
                  :class="i === state.stepIndex ? 'w-5 bg-brand-600' : 'w-1.5 bg-slate-200'"
                />
              </div>

              <div class="flex items-center gap-2">
                <button
                  v-if="!isFirst"
                  type="button"
                  class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100"
                  @click="back"
                >
                  Kembali
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-gradient-to-r from-brand-500 to-fuchsia-500 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:brightness-110"
                  @click="next"
                >
                  {{ isLast ? 'Selesai' : 'Lanjut' }}
                </button>
              </div>
            </div>

            <button
              v-if="!isLast"
              type="button"
              class="mt-3 text-xs font-medium text-slate-400 transition hover:text-slate-600"
              @click="skip"
            >
              Lewati tur
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </Teleport>
</template>
