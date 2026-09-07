<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import { adminService } from '@/services/admin.service'
import { formatDate } from '@/utils/formatters'

interface AuditLogRow {
  id: number
  request_id: number
  request_number: string
  req_first_name: string
  req_last_name: string
  req_student_id: string | null
  req_email: string
  document_type: string | null
  from_status: string | null
  to_status: string
  current_status: string
  rejection_reason: string | null
  notes: string | null
  actor_email: string
  created_at: string
}

const rows = ref<AuditLogRow[]>([])
const loading = ref(false)
const meta = ref<any>(null)
const page = ref(1)

const requestNumber = ref('')
const actor = ref('')
const dateFrom = ref('')
const dateTo = ref('')

const today = new Date().toISOString().split('T')[0] ?? ''

async function fetchLogs() {
  loading.value = true
  try {
    const result = await adminService.getAuditLogs({
      request_number: requestNumber.value || undefined,
      actor: actor.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      page: page.value,
      per_page: 15,
    })

    rows.value = result.data ?? []
    meta.value = result.meta ?? null
  } finally {
    loading.value = false
  }
}

onMounted(fetchLogs)
watch(page, fetchLogs)

let searchTimer: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    fetchLogs()
  }, 400)
}

function onFilterDate() {
  page.value = 1
  fetchLogs()
}
</script>

<template>
    <div class="space-y-5">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Audit Logs</h1>
        <span v-if="meta" class="text-sm text-gray-400">{{ meta.total }} total activity logs</span>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
          <input
            v-model="requestNumber"
            @input="onSearchInput"
            type="text"
            placeholder="Request # (e.g. REQ-2026-00001)"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
          <input
            v-model="actor"
            @input="onSearchInput"
            type="text"
            placeholder="Actor email"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
          <input
            v-model="dateFrom"
            @change="onFilterDate"
            type="date"
            :max="today"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
          <input
            v-model="dateTo"
            @change="onFilterDate"
            type="date"
            :min="dateFrom || undefined"
            :max="today"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
          />
        </div>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading audit logs...</div>

      <div v-else-if="!rows.length" class="bg-white rounded-xl border border-gray-200 py-14 text-center">
        <p class="text-sm text-gray-500">No audit logs found.</p>
      </div>

      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Request #</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Student</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Document</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actor</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
                <th class="px-5 py-3" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="log in rows" :key="log.id" class="hover:bg-slate-50 transition">
                <td class="px-5 py-3 text-xs text-gray-500">{{ formatDate(log.created_at) }}</td>
                <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ log.request_number }}</td>
                <td class="px-5 py-3">
                  <p class="font-medium text-gray-900">{{ log.req_first_name }} {{ log.req_last_name }}</p>
                  <p class="text-xs text-gray-400">{{ log.req_email }}</p>
                </td>
                <td class="px-5 py-3 text-xs text-gray-700">{{ log.document_type || '-' }}</td>
                <td class="px-5 py-3 text-xs text-gray-700">
                  <span class="font-medium">{{ log.from_status || 'created' }}</span>
                  <span class="text-gray-400"> -> </span>
                  <span class="font-medium">{{ log.to_status }}</span>
                </td>
                <td class="px-5 py-3 text-xs text-gray-700">{{ log.actor_email }}</td>
                <td class="px-5 py-3 text-xs text-red-700 max-w-sm">
                  {{ log.rejection_reason || log.notes || '-' }}
                </td>
                <td class="px-5 py-3 text-right">
                  <router-link :to="`/admin/requests/${log.request_id}`" class="text-sky-600 hover:text-sky-700 font-medium text-xs">
                    View Request
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
          <p class="text-xs text-gray-400">Page {{ meta.current_page }} of {{ meta.last_page }}</p>
          <div class="flex gap-2">
            <AppButton variant="outline" size="sm" :disabled="page <= 1" @click="page--">Prev</AppButton>
            <AppButton variant="outline" size="sm" :disabled="page >= meta.last_page" @click="page++">Next</AppButton>
          </div>
        </div>
      </div>
    </div>
</template>
