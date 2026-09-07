<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  ArcElement,
  CategoryScale,
  Chart as ChartJS,
  DoughnutController,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'
import type { ChartOptions, Plugin } from 'chart.js'
import { Doughnut, Line } from 'vue-chartjs'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { adminService } from '@/services/admin.service'
import { formatDateTime } from '@/utils/formatters'
import type { RequestStatus } from '@/types/request.types'

ChartJS.register(
  ArcElement,
  CategoryScale,
  DoughnutController,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
)

const stats = ref<any>(null)
const trendReport = ref<any>(null)
const loading = ref(true)
const trendLoading = ref(false)
const trendRange = ref('last_6_months')

const trendRangeOptions = [
  { value: 'last_6_months', label: 'Last 6 Months' },
  { value: 'this_year', label: 'This Year' },
  { value: 'all_time', label: 'All Time' },
]

const trendRows = computed(() => trendReport.value?.by_period ?? [])
const statusRows = computed(() => {
  const byStatus = stats.value?.by_status ?? {}
  return Object.entries(byStatus)
    .map(([status, count]) => ({ status, count: Number(count) || 0 }))
    .filter((row) => row.count > 0)
})
const totalRequests = computed(() => Number(stats.value?.total_requests) || 0)
const recentRequests = computed(() => stats.value?.recent_requests ?? [])

const statCards = computed(() => [
  {
    label: 'Total Requests',
    value: totalRequests.value,
    hint: 'All document requests',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    iconClass: 'bg-blue-100 text-blue-600',
  },
  {
    label: 'This Month',
    value: Number(stats.value?.requests_this_month) || 0,
    hint: 'Requests this month',
    icon: 'M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z',
    iconClass: 'bg-violet-100 text-violet-600',
  },
  {
    label: 'Completed Requests',
    value: Number(stats.value?.summary?.completed) || 0,
    hint: 'Released or completed',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    iconClass: 'bg-emerald-100 text-emerald-600',
  },
  {
    label: 'Today',
    value: Number(stats.value?.requests_today) || 0,
    hint: 'Requests submitted today',
    icon: 'M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z',
    iconClass: 'bg-orange-100 text-orange-600',
  },
])

const trendChartData = computed(() => ({
  labels: trendRows.value.map((row: any) => monthLabel(row.period)),
  datasets: [
    {
      label: 'Requests',
      data: trendRows.value.map((row: any) => Number(row.count) || 0),
      borderColor: '#2563eb',
      backgroundColor: 'rgba(37, 99, 235, 0.12)',
      pointBackgroundColor: '#2563eb',
      pointBorderColor: '#ffffff',
      pointBorderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 6,
      borderWidth: 3,
      fill: true,
      tension: 0.35,
    },
  ],
}))

const statusChartData = computed(() => ({
  labels: statusRows.value.map((row) => statusLabel(row.status)),
  datasets: [
    {
      data: statusRows.value.map((row) => row.count),
      backgroundColor: statusRows.value.map((row) => statusColor(row.status)),
      borderColor: '#ffffff',
      borderWidth: 4,
      hoverOffset: 6,
    },
  ],
}))

const trendChartOptions: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      ticks: { color: '#475569', font: { size: 11, weight: 600 } },
      grid: { display: false },
      border: { display: false },
    },
    y: {
      beginAtZero: true,
      ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
      grid: { color: 'rgba(148, 163, 184, 0.18)' },
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
          return ` Requests: ${formatNumber(Number(context.parsed.y) || 0)}`
        },
      },
    },
  },
}

const statusChartOptions: ChartOptions<'doughnut'> = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '64%',
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#0f172a',
      padding: 12,
      callbacks: {
        label(context: any) {
          const value = Number(context.parsed) || 0
          return ` ${context.label}: ${formatNumber(value)} (${percentage(value)}%)`
        },
      },
    },
  },
}

const centerTextPlugin: Plugin<'doughnut'> = {
  id: 'dashboardStatusCenterText',
  afterDraw(chart) {
    const center = chart.getDatasetMeta(0).data[0] as any
    if (!center) return

    const { ctx } = chart
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillStyle = '#0f172a'
    ctx.font = '700 24px Inter, ui-sans-serif, system-ui'
    ctx.fillText(formatNumber(totalRequests.value), center.x, center.y - 6)
    ctx.fillStyle = '#64748b'
    ctx.font = '500 11px Inter, ui-sans-serif, system-ui'
    ctx.fillText('Total', center.x, center.y + 16)
    ctx.restore()
  },
}

function trendRangeDates(): { date_from?: string; date_to?: string } {
  const today = new Date()
  if (trendRange.value === 'all_time') return {}
  if (trendRange.value === 'this_year') {
    return {
      date_from: `${today.getFullYear()}-01-01`,
      date_to: dateInputValue(today),
    }
  }

  const start = new Date(today.getFullYear(), today.getMonth() - 5, 1)
  return {
    date_from: dateInputValue(start),
    date_to: dateInputValue(today),
  }
}

function dateInputValue(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function monthLabel(value: string): string {
  const [year, month] = String(value).split('-')
  const date = new Date(Date.UTC(Number(year), Number(month) - 1, 1))
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('en-US', { month: 'short' }).format(date)
}

function statusLabel(status: string): string {
  return status
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function statusColor(status: string): string {
  const colors: Record<string, string> = {
    completed: '#22c55e',
    payment_verified: '#2563eb',
    processing: '#0ea5e9',
    ready_for_release: '#38bdf8',
    rejected: '#f43f5e',
    cancelled: '#64748b',
    pending_payment: '#f59e0b',
    submitted: '#fb923c',
    draft: '#94a3b8',
    paid: '#2563eb',
  }
  return colors[status] ?? '#64748b'
}

function percentage(value: number): number {
  if (totalRequests.value === 0) return 0
  return Math.round((value / totalRequests.value) * 100)
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat('en-US').format(value)
}

function studentName(request: any): string {
  return `${request.req_first_name ?? ''} ${request.req_last_name ?? ''}`.trim() || 'Unnamed Student'
}

async function fetchTrendReport() {
  trendLoading.value = true
  try {
    const range = trendRangeDates()
    trendReport.value = await adminService.getReport({
      date_from: range.date_from,
      date_to: range.date_to,
      group_by: 'month',
    })
  } finally {
    trendLoading.value = false
  }
}

onMounted(async () => {
  try {
    const [dashboardData] = await Promise.all([
      adminService.getDashboard(),
      fetchTrendReport(),
    ])
    stats.value = dashboardData
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">Overview of document requests and recent activity.</p>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-32 animate-pulse rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="m-5 h-5 w-24 rounded bg-gray-100" />
        <div class="mx-5 mt-5 h-8 w-20 rounded bg-gray-100" />
      </div>
    </div>

    <template v-else-if="stats">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="card in statCards" :key="card.label" class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
          <div class="flex items-center gap-4">
            <div :class="['flex h-14 w-14 shrink-0 items-center justify-center rounded-xl', card.iconClass]">
              <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-500">{{ card.label }}</p>
              <p class="mt-1 text-3xl font-bold leading-none text-gray-900">{{ formatNumber(card.value) }}</p>
              <p class="mt-2 text-xs text-gray-500">{{ card.hint }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm xl:col-span-3">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h2 class="text-base font-semibold text-gray-900">Request Trends</h2>
              <p class="mt-1 text-sm text-gray-500">Requests grouped by month</p>
            </div>
            <select
              v-model="trendRange"
              class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              @change="fetchTrendReport"
            >
              <option v-for="option in trendRangeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div v-if="trendLoading" class="flex h-72 items-center justify-center text-sm text-gray-400">
            Loading trend...
          </div>
          <div v-else-if="trendRows.length" class="h-72">
            <Line :data="trendChartData" :options="trendChartOptions" />
          </div>
          <p v-else class="py-24 text-center text-sm text-gray-400">No monthly trend data for this range.</p>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm xl:col-span-2">
          <h2 class="text-base font-semibold text-gray-900">Requests by Status</h2>
          <div v-if="statusRows.length" class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px] xl:grid-cols-1">
            <div class="h-64">
              <Doughnut :data="statusChartData" :options="statusChartOptions" :plugins="[centerTextPlugin]" />
            </div>
            <div class="space-y-3">
              <div v-for="row in statusRows" :key="row.status" class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: statusColor(row.status) }" />
                  <span class="truncate text-sm font-semibold text-gray-700">{{ statusLabel(row.status) }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                  <span class="text-sm font-bold text-gray-900">{{ formatNumber(row.count) }}</span>
                  <span class="rounded-full px-2 py-0.5 text-xs font-bold" :style="{ color: statusColor(row.status), backgroundColor: `${statusColor(row.status)}20` }">
                    {{ percentage(row.count) }}%
                  </span>
                </div>
              </div>
            </div>
            <router-link to="/admin/reports" class="text-center text-sm font-semibold text-blue-600 hover:text-blue-700">
              View full report →
            </router-link>
          </div>
          <p v-else class="py-24 text-center text-sm text-gray-400">No status data yet.</p>
        </section>
      </div>

      <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
          <h2 class="text-base font-semibold text-gray-900">Recent Requests</h2>
          <router-link to="/admin/requests" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
            View full Requests
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </router-link>
        </div>

        <div v-if="recentRequests.length" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Student</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Document Type</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date Requested</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="request in recentRequests" :key="request.id" class="transition hover:bg-slate-50">
                <td class="px-5 py-4">
                  <router-link :to="`/admin/requests/${request.id}`" class="font-semibold text-gray-900 hover:text-blue-600">
                    {{ studentName(request) }}
                  </router-link>
                </td>
                <td class="px-5 py-4 font-medium text-gray-700">{{ request.document_type_name }}</td>
                <td class="px-5 py-4">
                  <StatusBadge :status="request.status as RequestStatus" />
                </td>
                <td class="px-5 py-4 text-gray-700">{{ formatDateTime(request.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="px-5 py-12 text-center text-sm text-gray-400">
          No recent requests yet.
        </div>
      </section>

      <div class="flex gap-3">
        <router-link
          to="/admin/requests"
          class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
          style="background: linear-gradient(135deg, #0ea5e9, #2563eb);"
        >
          View All Requests
        </router-link>
        <router-link
          to="/admin/reports"
          class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
        >
          View Reports
        </router-link>
      </div>
    </template>
  </div>
</template>
