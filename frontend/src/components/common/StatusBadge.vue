<script setup lang="ts">
import type { RequestStatus } from '@/types/request.types'
import { STATUS_COLORS, STATUS_LABELS } from '@/utils/constants'

defineProps<{ status: RequestStatus }>()
</script>

<template>
  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', STATUS_COLORS[status]]">
    {{ STATUS_LABELS[status] ?? status }}
  </span>
</template>
