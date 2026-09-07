<script setup lang="ts">
import { useToastStore } from '@/stores/toast.store'

const toast = useToastStore()

const icons: Record<string, string> = {
  success: 'M5 13l4 4L19 7',
  error:   'M6 18L18 6M6 6l12 12',
  info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
}

const styles: Record<string, string> = {
  success: 'bg-white border-l-4 border-green-500 text-gray-800',
  error:   'bg-white border-l-4 border-red-500 text-gray-800',
  info:    'bg-white border-l-4 border-sky-500 text-gray-800',
  warning: 'bg-white border-l-4 border-amber-400 text-gray-800',
}

const iconStyles: Record<string, string> = {
  success: 'text-green-500',
  error:   'text-red-500',
  info:    'text-sky-500',
  warning: 'text-amber-500',
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4">
      <TransitionGroup name="toast">
        <div
          v-for="t in toast.toasts"
          :key="t.id"
          :class="['flex items-start gap-3 rounded-xl shadow-lg px-4 py-3 pointer-events-auto', styles[t.type]]"
        >
          <svg :class="['h-5 w-5 mt-0.5 shrink-0', iconStyles[t.type]]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="icons[t.type]" />
          </svg>
          <p class="text-sm flex-1 leading-snug">{{ t.message }}</p>
          <button @click="toast.remove(t.id)" class="text-gray-400 hover:text-gray-600 shrink-0 -mt-0.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(12px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
