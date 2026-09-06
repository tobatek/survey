<script setup lang="ts">
import ToastNotification from '@/features/shared/components/ToastNotification.vue'
import { useToast } from '@/features/shared/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <RouterView />

  <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4">
    <div v-for="t in toasts" :key="t.id" class="pointer-events-auto w-full max-w-sm">
      <ToastNotification :message="t.message" :tone="t.tone" @dismiss="dismiss(t.id)" />
    </div>
  </div>
</template>
