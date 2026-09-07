<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { adminService } from '@/services/admin.service'
import { formatCurrency } from '@/utils/formatters'
import { useToastStore } from '@/stores/toast.store'
import type { RequestStatus } from '@/types/request.types'

const loading = ref(false)
const report = ref<any>(null)
const toast = useToastStore()

const dateFrom = ref('')
const dateTo = ref('')
const groupBy = ref('status')
const today = new Date().toISOString().split('T')[0] ?? ''

const groupOptions = [
  { value: 'status', label: 'By Status' },
  { value: 'document_type', label: 'By Document Type' },
  { value: 'date', label: 'By Date (Month)' },
]

const breakdown = computed(() => {
  if (!report.value) return []
  if (groupBy.value === 'document_type') return report.value.by_document_type ?? []
  if (groupBy.value === 'date') return report.value.by_period ?? []
  return report.value.by_status ?? []
})

const breakdownRows = computed(() =>
  breakdown.value.map((row: any) => ({
    label: row.name ?? row.status ?? row.period ?? '-',
    count: row.count ?? 0,
    revenue: row.revenue ?? 0,
  }))
)

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
      date_to: dateTo.value || undefined,
      group_by: groupBy.value,
    })
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  const url = adminService.exportReportUrl({
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    group_by: groupBy.value,
  })
  window.open(url, '_blank')
}

onMounted(fetchReport)
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Reports</h1>
        <AppButton variant="outline" size="sm" @click="exportCsv">Export CSV -></AppButton>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date From</label>
            <input
              v-model="dateFrom"
              type="date"
              :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date To</label>
            <input
              v-model="dateTo"
              type="date"
              :min="dateFrom || undefined"
              :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Group By</label>
            <select
              v-model="groupBy"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            >
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

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="card in [
              { label: 'Total Requests', value: report.summary.total },
              { label: 'Total Revenue', value: formatCurrency(report.summary.revenue) },
              { label: 'Completed', value: report.summary.completed },
              { label: 'Rejected', value: report.summary.rejected },
            ]"
            :key="card.label"
            class="bg-white rounded-xl border border-gray-200 p-4"
          >
            <p class="text-xs text-gray-500">{{ card.label }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ card.value }}</p>
          </div>
        </div>

        <div v-if="breakdownRows.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-700">
              {{ groupOptions.find((o) => o.value === groupBy)?.label }}
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
