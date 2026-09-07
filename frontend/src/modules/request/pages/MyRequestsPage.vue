<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import DueDateReminder from '@/components/common/DueDateReminder.vue'
import AppButton from '@/components/common/AppButton.vue'
import { requestService } from '@/services/request.service'
import { formatDate, formatCurrency } from '@/utils/formatters'
import type { DocumentRequest, RequestStatus } from '@/types/request.types'

const requests   = ref<DocumentRequest[]>([])
const loading    = ref(true)
const activeTab  = ref<string>('all')

const tabs = [
  { key: 'all',              label: 'All' },
  { key: 'payment_verified', label: 'Payment Verified' },
  { key: 'completed',        label: 'Completed' },
  { key: 'rejected',         label: 'Rejected' },
]

async function fetchRequests() {
  loading.value = true
  try {
    const status = activeTab.value === 'all' ? undefined : activeTab.value
    const result = await requestService.list({ status } as any)
    requests.value = result.data ?? result
  } finally {
    loading.value = false
  }
}

onMounted(fetchRequests)
watch(activeTab, fetchRequests)
</script>

<template>
    <div class="space-y-5">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">My Requests</h1>
        <router-link to="/requests/new">
          <AppButton>+ New Request</AppButton>
        </router-link>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 overflow-x-auto border-b border-gray-200">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-3 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition',
            activeTab === tab.key
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Loading...</div>

      <!-- Empty -->
      <div v-else-if="!requests.length" class="bg-white rounded-xl border border-gray-200 py-12 text-center">
        <p class="text-sm text-gray-500">No requests found.</p>
        <router-link to="/requests/new" class="mt-2 inline-block text-sm text-blue-600 font-medium">Submit a request</router-link>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[860px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Request #</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Document</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Status</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Due Reminder</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Fee</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Date</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="req in requests" :key="req.id" class="hover:bg-gray-50 transition">
                <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ req.request_number }}</td>
                <td class="px-5 py-3 text-gray-900">{{ req.document_type_name }}</td>
                <td class="px-5 py-3"><StatusBadge :status="req.status as RequestStatus" /></td>
                <td class="px-5 py-3"><DueDateReminder :due-date="req.expected_release_at" :status="req.status as RequestStatus" /></td>
                <td class="px-5 py-3 text-gray-700">{{ formatCurrency(req.total_fee) }}</td>
                <td class="px-5 py-3 text-gray-500">{{ formatDate(req.created_at) }}</td>
                <td class="px-5 py-3 text-right">
                  <router-link :to="`/requests/${req.id}`" class="text-blue-600 hover:text-blue-700 font-medium text-xs">
                    View
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
</template>
