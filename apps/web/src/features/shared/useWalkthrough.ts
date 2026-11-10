import { reactive } from 'vue'

export interface WalkthroughStep {
  /** CSS selector elemen yang mau disorot. Kalau tidak ketemu, step dilewati. */
  target: string
  title: string
  description: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
}

interface WalkthroughState {
  storageKey: string | null
  steps: WalkthroughStep[]
  stepIndex: number
  active: boolean
}

const state = reactive<WalkthroughState>({
  storageKey: null,
  steps: [],
  stepIndex: 0,
  active: false,
})

function hasSeenKey(storageKey: string) {
  return `myameta.walkthrough.${storageKey}.selesai`
}

/** Sudah pernah menyelesaikan/menutup tur untuk storageKey ini? */
export function hasCompletedWalkthrough(storageKey: string): boolean {
  try {
    return localStorage.getItem(hasSeenKey(storageKey)) === '1'
  } catch {
    return false
  }
}

function markCompleted(storageKey: string) {
  try {
    localStorage.setItem(hasSeenKey(storageKey), '1')
  } catch {
    // localStorage bisa diblokir (mode privat) — abaikan, tur tetap jalan per sesi.
  }
}

/** Composable tunggal (shared state) untuk menjalankan tur jalan-jalan step-by-step. */
export function useWalkthrough() {
  function start(storageKey: string, steps: WalkthroughStep[]) {
    state.storageKey = storageKey
    state.steps = steps
    state.stepIndex = 0
    state.active = steps.length > 0
  }

  /** Mulai otomatis hanya kalau belum pernah diselesaikan/ditutup sebelumnya. */
  function startIfNew(storageKey: string, steps: WalkthroughStep[]) {
    if (hasCompletedWalkthrough(storageKey)) return
    start(storageKey, steps)
  }

  function next() {
    if (state.stepIndex < state.steps.length - 1) {
      state.stepIndex += 1
    } else {
      finish()
    }
  }

  function back() {
    if (state.stepIndex > 0) state.stepIndex -= 1
  }

  function skip() {
    finish()
  }

  function finish() {
    if (state.storageKey) markCompleted(state.storageKey)
    state.active = false
    state.stepIndex = 0
  }

  return { state, start, startIfNew, next, back, skip, finish }
}
