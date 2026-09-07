<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import { adminService } from '@/services/admin.service'

const loading = ref(false)
const analytics = ref<any>(null)
const dateFrom = ref('')
const dateTo = ref('')
const today = new Date().toISOString().split('T')[0] ?? ''

const monthlyRows = computed(() => analytics.value?.monthly_totals ?? [])
const topDocuments = computed(() => analytics.value?.top_documents ?? [])
const trendRows = computed(() => analytics.value?.monthly_document_trends ?? [])
const maxMonthlyCount = computed(() =>
  Math.max(1, ...monthlyRows.value.map((row: any) => Number(row.total_requests) || 0))
)
const monthlyChartRows = computed(() =>
  monthlyRows.value.map((row: any) => {
    const count = Number(row.total_requests) || 0
    return {
      month: row.month,
      count,
      percent: Math.round((count / maxMonthlyCount.value) * 100),
    }
  })
)

const peakMonth = computed(() => {
  const peak = monthlyRows.value.reduce((best: any, row: any) => {
    const count = Number(row.total_requests) || 0
    if (!best || count > best.count) return { month: row.month, count }
    return best
  }, null)

  return peak && peak.count > 0
    ? { ...peak, label: formatMonthLabel(peak.month) }
    : null
})

const peakSeasonMessage = computed(() => {
  if (!peakMonth.value) return ''
  return `${peakMonth.value.label} is currently the peak demand month with ${peakMonth.value.count} request${peakMonth.value.count === 1 ? '' : 's'}. Consider preparing extra processing capacity for this season.`
})

function formatMonthLabel(month: string): string {
  const [year, value] = String(month).split('-')
  const monthIndex = Number(value) - 1
  if (!year || Number.isNaN(monthIndex)) return String(month)
  return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' })
    .format(new Date(Date.UTC(Number(year), monthIndex, 1)))
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

onMounted(fetchAnalytics)
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Request Analytics</h1>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date From</label>
            <input v-model="dateFrom" type="date" :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-medium text-gray-500">Date To</label>
            <input v-model="dateTo" type="date" :min="dateFrom || undefined" :max="today"
              class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" />
          </div>
          <AppButton :loading="loading" @click="fetchAnalytics">Analyze</AppButton>
        </div>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading analytics...</div>

      <template v-else-if="analytics">
        <div v-if="peakMonth" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
          <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Peak Season Alert</p>
          <p class="mt-1 text-sm font-medium text-amber-900">{{ peakSeasonMessage }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm font-semibold text-gray-700 mb-3">Monthly Request Volume</p>
            <div v-if="monthlyRows.length" class="space-y-2">
              <div v-for="row in monthlyRows" :key="row.month" class="flex items-center justify-between text-sm">
                <span class="text-gray-600">{{ row.month }}</span>
                <span class="font-semibold text-gray-900">{{ row.total_requests }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">No monthly data.</p>
          </div>

          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm font-semibold text-gray-700 mb-3">Top Requested Documents</p>
            <div v-if="topDocuments.length" class="space-y-2">
              <div v-for="doc in topDocuments" :key="doc.document_type" class="flex items-center justify-between text-sm">
                <span class="text-gray-700">{{ doc.document_type }}</span>
                <span class="font-semibold text-emerald-700">{{ doc.request_count }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-400">No document trend data.</p>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <p class="text-sm font-semibold text-gray-700 mb-4">Monthly Request Volume Graph</p>
          <div v-if="monthlyChartRows.length" class="h-72 overflow-x-auto">
            <div class="flex h-full min-w-full items-end gap-3">
            <div v-for="row in monthlyChartRows" :key="row.month" class="flex h-full min-w-12 flex-1 flex-col items-center justify-end gap-2">
              <span class="text-[11px] text-gray-500">{{ row.count }}</span>
              <div class="flex h-48 w-full items-end">
                <div class="w-full rounded-t-md bg-emerald-500/80 transition-all hover:bg-emerald-500"
                  :style="{ height: `${Math.max(8, row.percent)}%` }" />
              </div>
              <span class="text-[11px] text-gray-500">{{ row.month }}</span>
            </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">No monthly data to graph.</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-700">Monthly Demand by Document Type</p>
          </div>
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Month</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Document Type</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Requests</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in trendRows" :key="`${row.month}-${row.document_type}`" class="hover:bg-slate-50">
                <td class="px-5 py-3 text-gray-600">{{ row.month }}</td>
                <td class="px-5 py-3 text-gray-800 font-medium">{{ row.document_type }}</td>
                <td class="px-5 py-3 text-right text-gray-700 font-semibold">{{ row.request_count }}</td>
              </tr>
              <tr v-if="!trendRows.length">
                <td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No trend rows found for selected period.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
</template>
