<script setup lang="ts">
defineProps<{ source: 'online' | 'kiosk' | 'mobile' | null | undefined }>()
</script>

<template>
  <span v-if="source" :class="[
    'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold',
    source === 'online' ? 'bg-sky-100 text-sky-700' :
    source === 'kiosk'  ? 'bg-amber-100 text-amber-700' :
                          'bg-purple-100 text-purple-700'
  ]">
    <!-- online -->
    <svg v-if="source === 'online'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
    <!-- kiosk -->
    <svg v-else-if="source === 'kiosk'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
    </svg>
    <!-- mobile -->
    <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
    </svg>
    {{ source === 'online' ? 'Online' : source === 'kiosk' ? 'Kiosk' : 'Mobile' }}
  </span>
</template>
