<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import DueDateReminder from '@/components/common/DueDateReminder.vue'
import { adminService } from '@/services/admin.service'
import { formatDate } from '@/utils/formatters'
import type { DocumentRequest } from '@/types/request.types'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const requests = ref<DocumentRequest[]>([])
const selectedDates = ref<string[]>([])

const today = new Date()
const visibleMonth = ref(monthFromQuery())
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

function dateKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function monthFromQuery(): Date {
  const date = typeof route.query.date === 'string' ? new Date(route.query.date) : today
  if (Number.isNaN(date.getTime())) return new Date(today.getFullYear(), today.getMonth(), 1)
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

function hydrateSelectedDates() {
  const raw = typeof route.query.dates === 'string' ? route.query.dates : ''
  const dates = raw.split(',').map(value => value.trim()).filter(Boolean)
  selectedDates.value = Array.from(new Set(dates))
}

const monthTitle = computed(() =>
  new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(visibleMonth.value)
)

const monthRange = computed(() => {
  const start = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth(), 1)
  const end = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() + 1, 0)
  return { start, end }
})

const calendarDays = computed(() => {
  const start = monthRange.value.start
  const end = monthRange.value.end
  const cells: Array<{ date: Date; key: string; inMonth: boolean; isToday: boolean }> = []
  const firstCell = new Date(start)
  firstCell.setDate(start.getDate() - start.getDay())
  const lastCell = new Date(end)
  lastCell.setDate(end.getDate() + (6 - end.getDay()))

  for (let cursor = new Date(firstCell); cursor <= lastCell; cursor.setDate(cursor.getDate() + 1)) {
    const date = new Date(cursor)
    cells.push({
      date,
      key: dateKey(date),
      inMonth: date.getMonth() === visibleMonth.value.getMonth(),
      isToday: dateKey(date) === dateKey(today),
    })
  }

  return cells
})

const requestsByDate = computed(() => {
  const groups = new Map<string, DocumentRequest[]>()
  for (const request of requests.value) {
    if (!request.expected_release_at) continue
    const key = dateKey(new Date(request.expected_release_at))
    groups.set(key, [...(groups.get(key) ?? []), request])
  }
  return groups
})

const selectedGroups = computed(() => {
  const keys = selectedDates.value.length ? selectedDates.value : [...requestsByDate.value.keys()].sort()
  return keys.map(key => ({
    key,
    label: formatDate(key),
    requests: requestsByDate.value.get(key) ?? [],
  }))
})

async function loadCalendar() {
  loading.value = true
  try {
    requests.value = await adminService.getRequestCalendar({
      date_from: dateKey(monthRange.value.start),
      date_to: dateKey(monthRange.value.end),
    })
  } finally {
    loading.value = false
  }
}

function setMonth(offset: number) {
  const next = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() + offset, 1)
  visibleMonth.value = next
  router.replace({ query: { ...route.query, date: dateKey(next) } })
}

function toggleDate(key: string) {
  const dates = selectedDates.value.includes(key)
    ? selectedDates.value.filter(date => date !== key)
    : [...selectedDates.value, key].sort()

  selectedDates.value = dates
  router.replace({
    query: {
      ...route.query,
      date: key,
      dates: dates.length ? dates.join(',') : undefined,
    },
  })
}

function openRequestDetails(requestId: number) {
  router.push({
    name: 'AdminRequests',
    query: {
      request_id: String(requestId),
    },
  })
}

onMounted(() => {
  hydrateSelectedDates()
  loadCalendar()
})

watch(visibleMonth, loadCalendar)
watch(
  () => route.query.dates,
  hydrateSelectedDates
)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm text-gray-500">Admin calendar</p>
        <h1 class="text-xl font-semibold text-gray-900">Request Calendar</h1>
      </div>
      <div class="flex items-center gap-2">
        <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="setMonth(-1)">
          Prev
        </button>
        <p class="min-w-40 text-center text-sm font-semibold text-gray-800">{{ monthTitle }}</p>
        <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="setMonth(1)">
          Next
        </button>
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

    <template v-else>
      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white">
          <div class="grid grid-cols-7 border-b border-gray-100 bg-slate-50">
            <div v-for="day in weekDays" :key="day" class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-500">
              {{ day }}
            </div>
          </div>
          <div class="grid grid-cols-7">
            <button
              v-for="cell in calendarDays"
              :key="cell.key"
              type="button"
              :class="[
                'min-h-28 border-b border-r border-gray-100 p-2 text-left transition hover:bg-sky-50',
                !cell.inMonth ? 'bg-slate-50 text-gray-300' : 'bg-white text-gray-700',
                selectedDates.includes(cell.key) ? 'bg-sky-50 ring-2 ring-inset ring-sky-500' : ''
              ]"
              @click="toggleDate(cell.key)"
            >
              <span
                :class="[
                  'inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold',
                  cell.isToday ? 'bg-sky-600 text-white' : ''
                ]"
              >
                {{ cell.date.getDate() }}
              </span>
              <div class="mt-2 space-y-1">
                <div
                  v-for="request in (requestsByDate.get(cell.key) ?? []).slice(0, 3)"
                  :key="request.id"
                  class="truncate rounded bg-sky-100 px-2 py-1 text-[11px] font-medium text-sky-800 hover:bg-sky-200"
                  @click.stop="openRequestDetails(request.id)"
                >
                  {{ request.request_number }}
                </div>
                <p v-if="(requestsByDate.get(cell.key) ?? []).length > 3" class="text-[11px] font-semibold text-sky-700">
                  +{{ (requestsByDate.get(cell.key) ?? []).length - 3 }} more
                </p>
              </div>
            </button>
          </div>
        </section>

        <aside class="space-y-4">
          <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-sm font-semibold text-gray-900">
              {{ selectedDates.length ? 'Selected Dates' : 'All Due Dates This Month' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">
              {{ requests.length }} request{{ requests.length === 1 ? '' : 's' }} loaded for {{ monthTitle }}.
            </p>
          </div>

          <div class="max-h-[calc(100vh-14rem)] space-y-3 overflow-y-auto pr-1">
            <div v-for="group in selectedGroups" :key="group.key" class="rounded-xl border border-gray-200 bg-white p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-900">{{ group.label }}</p>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ group.requests.length }}</span>
              </div>
              <div v-if="group.requests.length" class="mt-3 space-y-2">
                <router-link
                  v-for="request in group.requests"
                  :key="request.id"
                  :to="{ name: 'AdminRequests', query: { request_id: String(request.id) } }"
                  class="block rounded-lg border border-gray-100 px-3 py-3 transition hover:border-sky-200 hover:bg-sky-50"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold text-gray-900">{{ request.document_type_name }}</p>
                      <p class="mt-1 text-xs text-gray-500">{{ request.request_number }}</p>
                      <p class="mt-1 text-xs text-gray-500">{{ request.req_first_name }} {{ request.req_last_name }}</p>
                    </div>
                    <StatusBadge :status="request.status" />
                  </div>
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <SourceBadge :source="request.request_source" />
                    <DueDateReminder :due-date="request.expected_release_at" :status="request.status" />
                  </div>
                </router-link>
              </div>
              <p v-else class="mt-3 rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-gray-400">
                No due requests for this date.
              </p>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
