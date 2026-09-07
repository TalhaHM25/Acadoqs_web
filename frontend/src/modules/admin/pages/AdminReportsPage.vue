<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  CategoryScale,
  Chart as ChartJS,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'
import type { ChartOptions } from 'chart.js'
import { Line } from 'vue-chartjs'
import AppButton from '@/components/common/AppButton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { adminService } from '@/services/admin.service'
import { formatDate, formatCurrency } from '@/utils/formatters'
import { useToastStore } from '@/stores/toast.store'
import type { RequestStatus } from '@/types/request.types'

ChartJS.register(
  CategoryScale,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
)

const loading = ref(false)
const report  = ref<any>(null)
const toast   = useToastStore()

const dateFrom = ref('')
const dateTo   = ref('')
const groupBy  = ref('status')

const today = new Date().toISOString().split('T')[0] ?? ''

const groupOptions = [
  { value: 'status',        label: 'By Status' },
  { value: 'document_type', label: 'By Document Type' },
  { value: 'date',          label: 'By Date (Month)' },
]

// Backend always returns all three breakdowns; we pick the right one to display.
const breakdown = computed(() => {
  if (!report.value) return []
  if (groupBy.value === 'document_type') return report.value.by_document_type ?? []
  if (groupBy.value === 'date')          return report.value.by_period ?? []
  return report.value.by_status ?? []
})

// Normalise each row so template can always use row.label + row.count + row.revenue
const breakdownRows = computed(() =>
  breakdown.value.map((row: any) => ({
    label:   row.name ?? row.status ?? row.period ?? '—',
    count:   row.count   ?? 0,
    revenue: row.revenue ?? 0,
  }))
)

const monthlyRows = computed(() => report.value?.by_period ?? [])
const monthlyChartData = computed(() => ({
  labels: monthlyRows.value.map((row: any) => formatMonthLabel(row.period)),
  datasets: [
    {
      label: 'Requests',
      data: monthlyRows.value.map((row: any) => Number(row.count) || 0),
      borderColor: '#0284c7',
      backgroundColor: 'rgba(14, 165, 233, 0.12)',
      pointBackgroundColor: '#0284c7',
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

const monthlyChartOptions: ChartOptions<'line'> = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: {
      ticks: { color: '#64748b', font: { size: 11 } },
      grid: { color: 'rgba(148, 163, 184, 0.14)' },
      border: { display: false },
    },
    y: {
      beginAtZero: true,
      title: { display: true, text: 'Requests', color: '#64748b', font: { size: 11, weight: 600 } },
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
      titleFont: { size: 12, weight: 700 },
      bodyFont: { size: 12 },
      callbacks: {
        label(context: any) {
          return ` Requests: ${new Intl.NumberFormat('en-US').format(Number(context.parsed.y) || 0)}`
        },
      },
    },
  },
}

const peakMonth = computed(() => {
  const rows = report.value?.by_period ?? []
  const peak = rows.reduce((best: any, row: any) => {
    const count = Number(row.count) || 0
    if (!best || count > best.count) return { month: row.period, count }
    return best
  }, null)

  return peak && peak.count > 0
    ? { ...peak, label: formatMonthLabel(peak.month) }
    : null
})

const peakSeasonMessage = computed(() => {
  if (!peakMonth.value) return ''
  return `${peakMonth.value.label} is the peak request month with ${peakMonth.value.count} request${peakMonth.value.count === 1 ? '' : 's'}. Prepare staffing, document stock, and payment verification coverage around this period.`
})

function formatMonthLabel(month: string): string {
  const [year, value] = String(month).split('-')
  const monthIndex = Number(value) - 1
  if (!year || Number.isNaN(monthIndex)) return String(month)
  return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })
    .format(new Date(Date.UTC(Number(year), monthIndex, 1)))
}

async function fetchReport() {
  if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
    toast.error('"Date From" cannot be later than "Date To".')
    return
  }
  if (dateFrom.value > today) {
    toast.error('"Date From" cannot be a future date.')
    return
  }
  loading.value = true
  try {
    report.value = await adminService.getReport({
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
      group_by:  groupBy.value,
    })
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  const url = adminService.exportReportUrl({
    date_from: dateFrom.value || undefined,
    date_to:   dateTo.value   || undefined,
    group_by:  groupBy.value,
  })
  window.open(url, '_blank')
}

onMounted(fetchReport)
</script>

<template>
    <div class="space-y-6">

      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Reports</h1>
        <AppButton variant="outline" size="sm" @click="exportCsv">Export CSV ↗</AppButton>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date From</label>
            <input v-model="dateFrom" type="date" :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date To</label>
            <input v-model="dateTo" type="date" :min="dateFrom || undefined" :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Group By</label>
            <select v-model="groupBy"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option v-for="opt in groupOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <AppButton :loading="loading" @click="fetchReport">Generate</AppButton>
        </div>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Generating report...</div>

      <template v-else-if="report">

        <div v-if="peakMonth" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
          <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Peak Season Alert</p>
          <p class="mt-1 text-sm font-medium text-amber-900">{{ peakSeasonMessage }}</p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div v-for="card in [
            { label: 'Total Requests', value: report.summary.total },
            { label: 'Total Revenue',  value: formatCurrency(report.summary.revenue) },
            { label: 'Completed',      value: report.summary.completed },
            { label: 'Rejected',       value: report.summary.rejected },
          ]" :key="card.label" class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">{{ card.label }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ card.value }}</p>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
          <div class="mb-5 flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-gray-900">Monthly Request Volume</p>
              <p class="mt-1 text-xs text-gray-500">Request totals grouped by month for the selected report range.</p>
            </div>
          </div>
          <div v-if="monthlyRows.length" class="h-80">
            <Line :data="monthlyChartData" :options="monthlyChartOptions" />
          </div>
          <p v-else class="py-20 text-center text-sm text-gray-400">No monthly request volume data for this report.</p>
        </div>

        <!-- Breakdown table -->
        <div v-if="breakdownRows.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-700">
              {{ groupOptions.find(o => o.value === groupBy)?.label }}
            </p>
          </div>
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  {{ groupBy === 'status' ? 'Status' : groupBy === 'document_type' ? 'Document Type' : 'Period' }}
                </th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Count</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Revenue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in breakdownRows" :key="row.label" class="hover:bg-slate-50">
                <td class="px-5 py-3">
                  <StatusBadge v-if="groupBy === 'status'" :status="row.label as RequestStatus" />
                  <span v-else class="text-gray-800 font-medium">{{ row.label }}</span>
                </td>
                <td class="px-5 py-3 text-right text-gray-700 font-medium">{{ row.count }}</td>
                <td class="px-5 py-3 text-right text-gray-700">{{ formatCurrency(row.revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="bg-white rounded-xl border border-gray-200 py-10 text-center text-sm text-gray-400">
          No data for the selected period.
        </div>

      </template>
    </div>
</template>
