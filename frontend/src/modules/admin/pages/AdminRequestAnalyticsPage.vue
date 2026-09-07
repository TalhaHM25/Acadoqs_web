<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  Tooltip,
} from 'chart.js'
import type { ChartOptions } from 'chart.js'
import { Bar } from 'vue-chartjs'
import AppButton from '@/components/common/AppButton.vue'
import { adminService } from '@/services/admin.service'

ChartJS.register(
  BarElement,
  CategoryScale,
  Legend,
  LinearScale,
  Tooltip,
)

type RangePreset = 'last_6_months' | 'this_year' | 'all_time'

const loading = ref(false)
const analytics = ref<any>(null)
const rangePreset = ref<RangePreset>('last_6_months')
const dateFrom = ref('')
const dateTo = ref('')
const today = new Date().toISOString().split('T')[0] ?? ''
const showAllDocuments = ref(false)

const monthlyRows = computed(() => analytics.value?.monthly_totals ?? [])
const topDocuments = computed(() => analytics.value?.top_documents ?? [])
const trendRows = computed(() => analytics.value?.monthly_document_trends ?? [])
const displayedDocuments = computed(() => showAllDocuments.value ? topDocuments.value : topDocuments.value.slice(0, 5))
const documentChartHeight = computed(() => `${Math.max(320, displayedDocuments.value.length * 44)}px`)
const hasAnyData = computed(() => monthlyRows.value.length > 0 || topDocuments.value.length > 0 || trendRows.value.length > 0)

const totalRequests = computed(() =>
  monthlyRows.value.reduce((sum: number, row: any) => sum + requestTotal(row), 0)
)
const currentMonthKey = computed(() => today.slice(0, 7))
const requestsThisMonth = computed(() =>
  requestTotal(monthlyRows.value.find((row: any) => row.month === currentMonthKey.value))
)
const mostRequestedDocument = computed(() => topDocuments.value[0]?.document_type ?? 'No data')
const highestMonth = computed(() => {
  const peak = monthlyRows.value.reduce((best: any, row: any) => {
    if (!best || requestTotal(row) > requestTotal(best)) return row
    return best
  }, null)

  return peak
    ? { label: formatMonthLabel(peak.month), count: requestTotal(peak) }
    : null
})

const statCards = computed(() => [
  {
    label: 'Total Requests',
    value: formatNumber(totalRequests.value),
    hint: 'Sum of monthly request volume',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    iconClass: 'bg-sky-100 text-sky-600',
  },
  {
    label: 'Requests This Month',
    value: formatNumber(requestsThisMonth.value),
    hint: currentMonthKey.value,
    icon: 'M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    iconClass: 'bg-indigo-100 text-indigo-600',
  },
  {
    label: 'Most Requested Document',
    value: mostRequestedDocument.value,
    hint: topDocuments.value[0] ? `${formatNumber(requestCount(topDocuments.value[0]))} requests` : 'No document requests yet',
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    iconClass: 'bg-amber-100 text-amber-600',
  },
  {
    label: 'Highest Monthly Request Volume',
    value: highestMonth.value ? formatNumber(highestMonth.value.count) : '0',
    hint: highestMonth.value?.label ?? 'No monthly data yet',
    icon: 'M3 3v18h18M7 13l3-3 3 2 4-5',
    iconClass: 'bg-emerald-100 text-emerald-600',
  },
])

const topDocumentsChartData = computed(() => ({
  labels: displayedDocuments.value.map((doc: any) => doc.document_type),
  datasets: [
    {
      label: 'Requests',
      data: displayedDocuments.value.map((doc: any) => requestCount(doc)),
      backgroundColor: '#2563eb',
      borderRadius: 8,
      borderSkipped: false,
      barThickness: 18,
    },
  ],
}))

const topDocumentsChartOptions: ChartOptions<'bar'> = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      beginAtZero: true,
      ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
      grid: { color: 'rgba(148, 163, 184, 0.18)' },
      border: { display: false },
    },
    y: {
      ticks: { color: '#334155', font: { size: 11, weight: 600 } },
      grid: { display: false },
      border: { display: false },
    },
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#0f172a',
      padding: 12,
      callbacks: {
        label(context: any) {
          return ` ${formatNumber(Number(context.parsed.x) || 0)} requests`
        },
      },
    },
  },
}

function presetDates(preset: RangePreset): { date_from?: string; date_to?: string } {
  const now = new Date()
  if (preset === 'all_time') return {}

  if (preset === 'this_year') {
    return {
      date_from: `${now.getFullYear()}-01-01`,
      date_to: today,
    }
  }

  const start = new Date(now.getFullYear(), now.getMonth() - 5, 1)
  return {
    date_from: toDateInputValue(start),
    date_to: today,
  }
}

function toDateInputValue(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function applyPreset() {
  const dates = presetDates(rangePreset.value)
  dateFrom.value = dates.date_from ?? ''
  dateTo.value = dates.date_to ?? ''
  fetchAnalytics()
}

async function fetchAnalytics() {
  loading.value = true
  try {
    analytics.value = await adminService.getRequestAnalytics({
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })
  } finally {
    loading.value = false
  }
}

function requestTotal(row: any): number {
  return Number(row?.total_requests ?? row?.count ?? 0) || 0
}

function requestCount(row: any): number {
  return Number(row?.request_count ?? row?.count ?? 0) || 0
}

function formatMonthLabel(month: string): string {
  const [year, value] = String(month).split('-')
  const monthIndex = Number(value) - 1
  if (!year || Number.isNaN(monthIndex)) return String(month)
  return new Intl.DateTimeFormat('en-US', { month: 'short', year: 'numeric' })
    .format(new Date(Date.UTC(Number(year), monthIndex, 1)))
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value)
}

onMounted(applyPreset)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Request Analytics</h1>
        <p class="mt-1 text-sm text-gray-500">Monitor document request volume and document trends.</p>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <div class="space-y-1">
          <label class="text-xs font-semibold text-gray-500">Range</label>
          <select
            v-model="rangePreset"
            class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            @change="applyPreset"
          >
            <option value="last_6_months">Last 6 Months</option>
            <option value="this_year">This Year</option>
            <option value="all_time">All Time</option>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-xs font-semibold text-gray-500">Date From</label>
          <input
            v-model="dateFrom"
            type="date"
            :max="today"
            class="h-10 rounded-lg border border-gray-300 px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
        </div>
        <div class="space-y-1">
          <label class="text-xs font-semibold text-gray-500">Date To</label>
          <input
            v-model="dateTo"
            type="date"
            :min="dateFrom || undefined"
            :max="today"
            class="h-10 rounded-lg border border-gray-300 px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
        </div>
        <AppButton :loading="loading" @click="fetchAnalytics">Analyze</AppButton>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-32 animate-pulse rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="m-5 h-5 w-24 rounded bg-gray-100" />
        <div class="mx-5 mt-5 h-8 w-20 rounded bg-gray-100" />
      </div>
    </div>

    <template v-else-if="analytics">
      <div v-if="!hasAnyData" class="rounded-xl border border-gray-200 bg-white px-6 py-14 text-center shadow-sm">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-sky-50 text-sky-600">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 13l3-3 3 2 4-5" />
          </svg>
        </div>
        <p class="mt-4 text-sm font-semibold text-gray-800">No analytics data found</p>
        <p class="mt-1 text-sm text-gray-500">Try a wider date range to include existing request records.</p>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div v-for="card in statCards" :key="card.label" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ card.label }}</p>
                <p class="mt-3 truncate text-2xl font-bold text-gray-900">{{ card.value }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ card.hint }}</p>
              </div>
              <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', card.iconClass]">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
          <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Top Requested Documents</h2>
              <p class="mt-1 text-xs text-gray-500">Document types ranked by request count.</p>
            </div>
            <button
              v-if="topDocuments.length > 5"
              type="button"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              @click="showAllDocuments = !showAllDocuments"
            >
              {{ showAllDocuments ? 'Show Top 5' : `View All ${topDocuments.length}` }}
            </button>
          </div>
          <div v-if="displayedDocuments.length" :style="{ height: documentChartHeight }">
            <Bar :data="topDocumentsChartData" :options="topDocumentsChartOptions" />
          </div>
          <p v-else class="py-24 text-center text-sm text-gray-400">No document trend data for this range.</p>
        </section>
      </template>
    </template>
  </div>
</template>
