<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import { adminService } from '@/services/admin.service'
import { formatDate } from '@/utils/formatters'

const stats = ref<any>(null)
const loading = ref(true)
const router = useRouter()

const today = new Date()
const calendarMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const monthLabel = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(calendarMonth)
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

function dateKey(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const calendarDays = computed(() => {
  const firstDay = calendarMonth.getDay()
  const daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 0).getDate()
  const cells: Array<{ day: number | null; key: string | null; isToday: boolean }> = []

  for (let i = 0; i < firstDay; i += 1) {
    cells.push({ day: null, key: null, isToday: false })
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), day)
    cells.push({
      day,
      key: dateKey(date),
      isToday:
        day === today.getDate() &&
        calendarMonth.getMonth() === today.getMonth() &&
        calendarMonth.getFullYear() === today.getFullYear(),
    })
  }

  return cells
})

function openCalendarDate(key: string | null) {
  if (!key) return
  router.push({ name: 'RegistrarCalendar', query: { date: key, dates: key } })
}

const todoItems = computed(() => {
  const summary = stats.value?.summary ?? {}
  return [
    {
      label: 'Complete Requests',
      count: stats.value?.ready_to_complete ?? summary.payment_verified ?? 0,
      hint: 'Mark verified requests as completed when documents are released.',
      to: '/registrar/requests?status=payment_verified',
    },
  ]
})

const focusSummary = computed(() => ({
  readyToComplete: todoItems.value[0]?.count ?? 0,
}))

const upcomingReleases = computed(() => stats.value?.upcoming_releases ?? [])

const statCards = computed(() => [
  { label: 'Total Requests', value: stats.value?.summary?.total_requests ?? 0 },
  { label: 'Today', value: stats.value?.requests_today ?? 0 },
  { label: 'This Week', value: stats.value?.requests_this_week ?? 0 },
  { label: 'Payment Verified', value: stats.value?.summary?.payment_verified ?? 0 },
])

function studentName(req: any): string {
  return `${req.req_first_name ?? ''} ${req.req_last_name ?? ''}`.trim() || 'Unnamed Student'
}

function releaseDate(req: any): string {
  return req.expected_release_at ? formatDate(req.expected_release_at) : 'No date set'
}

onMounted(async () => {
  try {
    stats.value = await adminService.getDashboard()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="max-w-full space-y-5 overflow-hidden sm:space-y-6">
    <div class="space-y-1">
      <p class="text-sm text-gray-500">Registrar workspace</p>
      <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
    </div>

    <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

    <template v-else-if="stats">
      <div class="grid min-w-0 gap-5 sm:gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="min-w-0 space-y-5 sm:space-y-6">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-2 2xl:grid-cols-4">
            <div v-for="card in statCards" :key="card.label" class="min-h-20 rounded-xl border border-gray-200 bg-white p-4">
              <p class="text-xs font-medium leading-snug text-gray-500">{{ card.label }}</p>
              <p class="mt-2 break-words text-2xl font-bold leading-none text-gray-900 sm:text-3xl">{{ card.value }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 sm:px-5">
            <p class="text-sm font-semibold text-emerald-900">Today’s Focus</p>
            <p class="mt-1 text-sm text-emerald-800">
              {{ focusSummary.readyToComplete }} verified request{{ focusSummary.readyToComplete === 1 ? '' : 's' }} can be completed.
            </p>
          </div>

          <div class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-5 py-3">
              <p class="text-sm font-semibold text-gray-700">Recent Requests</p>
            </div>
            <div v-if="!stats.recent_requests?.length" class="py-10 text-center text-sm text-gray-400">No requests yet.</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full min-w-[900px] divide-y divide-gray-100 text-sm">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Request #</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Document</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Source</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Date</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="req in stats.recent_requests" :key="req.id" class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-xs text-gray-600">
                      <router-link :to="`/registrar/requests/${req.id}`" class="text-emerald-600 hover:underline">
                        {{ req.request_number }}
                      </router-link>
                    </td>
                    <td class="px-5 py-3 text-gray-800">{{ studentName(req) }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ req.document_type_name }}</td>
                    <td class="px-5 py-3"><SourceBadge :source="req.request_source" /></td>
                    <td class="px-5 py-3"><StatusBadge :status="req.status" /></td>
                    <td class="px-5 py-3 text-right text-xs text-gray-500">{{ formatDate(req.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <aside class="min-w-0 space-y-4">
          <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-semibold text-gray-800">{{ monthLabel }}</p>
              <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Open dates</span>
            </div>
            <div class="mt-4 grid grid-cols-7 gap-0.5 text-center sm:gap-1">
              <span v-for="day in weekDays" :key="day" class="py-1 text-[11px] font-semibold text-gray-400">{{ day }}</span>
              <button
                v-for="(cell, index) in calendarDays"
                :key="`${cell.day ?? 'blank'}-${index}`"
                type="button"
                :class="[
                  'flex h-7 items-center justify-center rounded-lg text-xs sm:h-8',
                  cell.day ? 'text-gray-600 hover:bg-emerald-50' : 'text-transparent',
                  cell.isToday ? 'bg-emerald-600 font-bold text-white' : 'hover:bg-slate-50'
                ]"
                :disabled="!cell.day"
                @click="openCalendarDate(cell.key)"
              >
                {{ cell.day ?? '' }}
              </button>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <p class="text-sm font-semibold text-gray-800">To Do</p>
            <div class="mt-3 space-y-3">
              <router-link
                v-for="item in todoItems"
                :key="item.label"
                :to="item.to"
                class="block rounded-lg border border-gray-100 px-3 py-3 transition hover:border-emerald-200 hover:bg-emerald-50"
              >
                <div class="flex items-start justify-between gap-3">
                  <p class="min-w-0 text-sm font-medium text-gray-800">{{ item.label }}</p>
                  <span class="shrink-0 rounded-full bg-gray-900 px-2 py-0.5 text-xs font-bold text-white">{{ item.count }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ item.hint }}</p>
              </router-link>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
            <p class="text-sm font-semibold text-gray-800">Upcoming Releases</p>
            <div v-if="upcomingReleases.length" class="mt-3 space-y-3">
              <router-link
                v-for="req in upcomingReleases"
                :key="req.id"
                :to="`/registrar/requests/${req.id}`"
                class="block rounded-lg border border-gray-100 px-3 py-3 transition hover:border-emerald-200 hover:bg-emerald-50"
              >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <div class="min-w-0">
                    <p class="break-words text-sm font-medium text-gray-900">{{ req.document_type_name }} for {{ studentName(req) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ req.request_number }}</p>
                  </div>
                  <div class="shrink-0">
                    <StatusBadge :status="req.status" />
                  </div>
                </div>
                <p class="mt-2 text-xs font-semibold text-emerald-700">{{ releaseDate(req) }}</p>
              </router-link>
            </div>
            <p v-else class="mt-3 rounded-lg bg-slate-50 px-3 py-6 text-center text-sm text-gray-400">
              No upcoming releases.
            </p>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
