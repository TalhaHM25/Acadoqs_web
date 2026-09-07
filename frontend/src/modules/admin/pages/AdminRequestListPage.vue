<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SourceBadge from '@/components/common/SourceBadge.vue'
import DueDateReminder from '@/components/common/DueDateReminder.vue'
import AppButton from '@/components/common/AppButton.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import StaffRequestDetailsModal from '@/modules/admin/components/StaffRequestDetailsModal.vue'
import { adminService } from '@/services/admin.service'
import { formatDate, formatCurrency } from '@/utils/formatters'
import { STATUS_COLORS, STATUS_LABELS } from '@/utils/constants'
import type { DocumentRequest, RequestStatus } from '@/types/request.types'

const route = useRoute()
const router = useRouter()

const requests = ref<DocumentRequest[]>([])
const loading = ref(true)
const meta = ref<any>(null)
const alert = ref<{ type: 'success' | 'error'; message: string } | null>(null)

const search = ref('')
const statusFilter = ref('')
const sourceFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const page = ref(1)

const selectedRequest = ref<DocumentRequest | null>(null)
const detailsLoading = ref(false)
const activeDetailsRequestId = ref<number | null>(null)
const savingStatusAction = ref<{ requestId: number; status: RequestStatus } | null>(null)

const statusOptions = [
  { value: '', label: 'All Statuses' },
  { value: 'payment_verified', label: 'Payment Verified' },
  { value: 'completed', label: 'Completed' },
  { value: 'rejected', label: 'Rejected' },
]

const sourceOptions = [
  { value: '', label: 'All Sources' },
  { value: 'online', label: 'Online' },
  { value: 'kiosk', label: 'Kiosk' },
  { value: 'mobile', label: 'Mobile' },
]

const transitions: Record<string, { value: RequestStatus; label: string }[]> = {
  payment_verified: [
    { value: 'completed', label: 'Complete' },
    { value: 'rejected', label: 'Reject' },
  ],
}

const summary = computed<Record<string, number>>(() => meta.value?.status_summary ?? {})
const resultCount = computed(() => meta.value?.total ?? 0)
const kpiCards = computed(() => [
  { label: 'Total Requests', value: resultCount.value },
  { label: 'Payment Verified', value: summary.value.payment_verified ?? 0 },
  { label: 'Completed', value: summary.value.completed ?? 0 },
  { label: 'Rejected', value: summary.value.rejected ?? 0 },
])

async function fetchRequests() {
  loading.value = true
  try {
    const result = await adminService.listRequests({
      status: statusFilter.value || undefined,
      source: sourceFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      search: search.value || undefined,
      page: page.value,
      per_page: 15,
    })
    requests.value = result.data ?? result
    meta.value = result.meta ?? null
  } finally {
    loading.value = false
  }
}

function resetAndFetch() {
  page.value = 1
  fetchRequests()
}

function clearFilters() {
  search.value = ''
  statusFilter.value = ''
  sourceFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  fetchRequests()
}

let searchTimer: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(resetAndFetch, 400)
}

function nextStatusOptions(request: DocumentRequest) {
  return transitions[request.status] ?? []
}

function statusLabel(status: RequestStatus) {
  return STATUS_LABELS[status] ?? status
}

function isSavingStatusAction(requestId: number, status: RequestStatus) {
  return savingStatusAction.value?.requestId === requestId && savingStatusAction.value.status === status
}

function isSavingRequestStatus(requestId: number) {
  return savingStatusAction.value?.requestId === requestId
}

async function openDetails(request: DocumentRequest) {
  router.replace({
    query: {
      ...route.query,
      request_id: String(request.id),
    },
  })
}

async function openDetailsById(requestId: number) {
  if (activeDetailsRequestId.value === requestId && (detailsLoading.value || selectedRequest.value)) return

  activeDetailsRequestId.value = requestId
  selectedRequest.value = null
  detailsLoading.value = true
  try {
    const request = await adminService.getRequest(requestId)
    if (activeDetailsRequestId.value === requestId) {
      selectedRequest.value = request
    }
  } finally {
    if (activeDetailsRequestId.value === requestId) {
      detailsLoading.value = false
    }
  }
}

function queryRequestId(): number | null {
  const value = route.query.request_id
  const id = Number(typeof value === 'string' ? value : '')
  return Number.isInteger(id) && id > 0 ? id : null
}

function syncDetailsModalFromQuery() {
  const requestId = queryRequestId()
  if (!requestId) {
    activeDetailsRequestId.value = null
    selectedRequest.value = null
    detailsLoading.value = false
    return
  }

  openDetailsById(requestId)
}

function closeDetails() {
  selectedRequest.value = null
  detailsLoading.value = false
  activeDetailsRequestId.value = null

  const query = { ...route.query }
  delete query.request_id
  router.replace({ query })
}

async function updateRowStatus(request: DocumentRequest, nextStatus: RequestStatus) {
  const rejectionReason = nextStatus === 'rejected' ? window.prompt('Reason for rejection')?.trim() : undefined
  if (nextStatus === 'rejected' && !rejectionReason) return

  savingStatusAction.value = { requestId: request.id, status: nextStatus }
  alert.value = null
  try {
    await adminService.updateStatus(request.id, {
      status: nextStatus,
      rejection_reason: rejectionReason,
    })
    alert.value = { type: 'success', message: 'Status updated successfully.' }
    await fetchRequests()
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to update status.' }
  } finally {
    savingStatusAction.value = null
  }
}

onMounted(() => {
  fetchRequests()
  syncDetailsModalFromQuery()
})
watch([statusFilter, sourceFilter, dateFrom, dateTo], resetAndFetch)
watch(page, fetchRequests)
watch(
  () => route.query.request_id,
  syncDetailsModalFromQuery
)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-semibold text-gray-900">Requests</h1>
      <span v-if="meta" class="text-sm text-gray-400">{{ resultCount }} total results</span>
    </div>

    <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="card in kpiCards" :key="card.label" class="rounded-xl border border-gray-200 bg-white p-4">
        <p class="text-xs font-medium text-gray-500">{{ card.label }}</p>
        <p class="mt-2 text-2xl font-bold text-gray-900">{{ card.value }}</p>
      </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
      <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_repeat(4,minmax(8rem,auto))_auto]">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Search student name, ID, email..."
          class="min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
        />
        <select v-model="statusFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
          <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <select v-model="sourceFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
          <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <input v-model="dateFrom" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
        <input v-model="dateTo" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
        <AppButton variant="outline" @click="clearFilters">Clear</AppButton>
      </div>
      <p v-if="meta" class="mt-3 text-sm font-medium text-gray-600">
        {{ resultCount }} document request result{{ resultCount === 1 ? '' : 's' }} based on current filters
      </p>
    </div>

    <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

    <div v-else-if="!requests.length" class="rounded-xl border border-gray-200 bg-white py-14 text-center">
      <p class="text-sm text-gray-500">No requests found.</p>
    </div>

    <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[1140px] divide-y divide-gray-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="w-40 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Request #</th>
              <th class="w-64 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Student</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Document</th>
              <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Source</th>
              <th class="w-40 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
              <th class="w-48 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Due Reminder</th>
              <th class="w-32 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Fee</th>
              <th class="w-36 px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
              <th class="w-56 px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="req in requests" :key="req.id" class="transition hover:bg-slate-50">
              <td class="w-40 px-5 py-3 whitespace-nowrap font-mono text-xs text-gray-600">{{ req.request_number }}</td>
              <td class="w-64 px-5 py-3">
                <p class="max-w-56 truncate whitespace-nowrap font-medium text-gray-900">{{ req.req_first_name }} {{ req.req_last_name }}</p>
                <p class="max-w-56 truncate whitespace-nowrap text-xs text-gray-400">{{ req.req_email }}</p>
              </td>
              <td class="px-5 py-3 text-gray-700">{{ req.document_type_name }}</td>
              <td class="px-5 py-3"><SourceBadge :source="req.request_source" /></td>
              <td class="w-40 px-5 py-3 whitespace-nowrap">
                <span :class="['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap', STATUS_COLORS[req.status as RequestStatus]]">
                  {{ statusLabel(req.status as RequestStatus) }}
                </span>
              </td>
              <td class="w-48 px-5 py-3 whitespace-nowrap">
                <div class="inline-flex min-w-max shrink-0 whitespace-nowrap">
                  <DueDateReminder :due-date="req.expected_release_at" :status="req.status as RequestStatus" />
                </div>
              </td>
              <td class="w-32 px-5 py-3 whitespace-nowrap text-gray-700">{{ formatCurrency(req.total_fee) }}</td>
              <td class="w-36 px-5 py-3 whitespace-nowrap text-xs text-gray-500">{{ formatDate(req.created_at) }}</td>
              <td class="w-56 px-5 py-3 whitespace-nowrap">
                <div class="flex min-w-max justify-end gap-2 whitespace-nowrap">
                  <button
                    v-for="opt in nextStatusOptions(req)"
                    :key="opt.value"
                    type="button"
                    :disabled="isSavingRequestStatus(req.id)"
                    :class="[
                      'shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60 whitespace-nowrap',
                      opt.value === 'rejected' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'
                    ]"
                    @click="updateRowStatus(req, opt.value)"
                  >
                    {{ isSavingStatusAction(req.id, opt.value) ? 'Saving...' : opt.label }}
                  </button>
                  <button type="button" class="shrink-0 whitespace-nowrap text-xs font-medium text-sky-600 hover:text-sky-700" @click="openDetails(req)">
                  View
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
        <p class="text-xs text-gray-400">Page {{ meta.current_page }} of {{ meta.last_page }}</p>
        <div class="flex gap-2">
          <AppButton variant="outline" size="sm" :disabled="page <= 1" @click="page--">Prev</AppButton>
          <AppButton variant="outline" size="sm" :disabled="page >= meta.last_page" @click="page++">Next</AppButton>
        </div>
      </div>
    </div>

    <StaffRequestDetailsModal
      v-if="detailsLoading || selectedRequest"
      :request="selectedRequest"
      :loading="detailsLoading"
      @close="closeDetails"
    />
  </div>
</template>
