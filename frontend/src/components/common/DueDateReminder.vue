<script setup lang="ts">
import { computed } from 'vue'
import { formatDate } from '@/utils/formatters'
import type { RequestStatus } from '@/types/request.types'

const props = defineProps<{
  dueDate?: string | null
  status: RequestStatus
}>()

const closedStatuses: RequestStatus[] = ['completed', 'rejected']

function startOfDay(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

const state = computed(() => {
  if (!props.dueDate) {
    return { label: 'No due date', className: 'bg-gray-100 text-gray-600' }
  }

  if (closedStatuses.includes(props.status)) {
    return { label: `Closed ${formatDate(props.dueDate)}`, className: 'bg-gray-100 text-gray-600' }
  }

  const due = startOfDay(new Date(props.dueDate))
  const today = startOfDay(new Date())
  const diffDays = Math.round((due.getTime() - today.getTime()) / 86400000)

  if (diffDays < 0) {
    return { label: `Overdue ${formatDate(props.dueDate)}`, className: 'bg-red-100 text-red-700' }
  }

  if (diffDays === 0) {
    return { label: 'Due today', className: 'bg-amber-100 text-amber-700' }
  }

  if (diffDays === 1) {
    return { label: 'Due tomorrow', className: 'bg-yellow-100 text-yellow-700' }
  }

  return { label: `Due ${formatDate(props.dueDate)}`, className: 'bg-emerald-100 text-emerald-700' }
})
</script>

<template>
  <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', state.className]">
    {{ state.label }}
  </span>
</template>
