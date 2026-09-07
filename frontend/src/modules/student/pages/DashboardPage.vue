<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import { requestService } from '@/services/request.service'
import StatusBadge from '@/components/common/StatusBadge.vue'
import AppButton from '@/components/common/AppButton.vue'
import { formatDate, formatCurrency } from '@/utils/formatters'
import type { DocumentRequest } from '@/types/request.types'

const auth     = useAuthStore()
const requests = ref<DocumentRequest[]>([])
const loading  = ref(true)

const statusCounts = ref<Record<string, number>>({})

onMounted(async () => {
  try {
    const result = await requestService.list({ per_page: 5 } as any)
    requests.value = result.data ?? result

    // Aggregate counts
    const all = await requestService.list({ per_page: 100 } as any)
    const items: DocumentRequest[] = all.data ?? all
    items.forEach((r) => {
      statusCounts.value[r.status] = (statusCounts.value[r.status] ?? 0) + 1
    })
  } finally {
    loading.value = false
  }
})

const statCards = [
  { label: 'Total Requests',   key: null },
  { label: 'Payment Verified', key: 'payment_verified' },
  { label: 'Completed',        key: 'completed' },
  { label: 'Rejected',         key: 'rejected' },
]

function countForKey(key: string | null) {
  if (!key) return Object.values(statusCounts.value).reduce((a, b) => a + b, 0)
  return statusCounts.value[key] ?? 0
}
</script>

<template>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-900">
            Hello, {{ auth.user?.email?.split('@')[0] }}
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">Manage your document requests below.</p>
        </div>
        <router-link to="/requests/new">
          <AppButton variant="primary">+ New Request</AppButton>
        </router-link>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="card in statCards"
          :key="card.label"
          class="bg-white rounded-xl border border-gray-200 p-4"
        >
          <p class="text-sm text-gray-500">{{ card.label }}</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ countForKey(card.key) }}</p>
        </div>
      </div>

      <!-- Recent requests -->
      <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-900">Recent Requests</h2>
          <router-link to="/requests" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
            View all
          </router-link>
        </div>

        <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-400">Loading...</div>

        <div v-else-if="!requests.length" class="px-5 py-10 text-center">
          <p class="text-sm text-gray-500">No requests yet.</p>
          <router-link to="/requests/new" class="mt-2 inline-block text-sm text-blue-600 font-medium hover:text-blue-700">
            Submit your first request →
          </router-link>
        </div>

        <div v-else class="overflow-x-auto">
        <ul class="w-full min-w-[680px] divide-y divide-gray-100">
          <li
            v-for="req in requests"
            :key="req.id"
            class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ req.document_type_name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ req.request_number }} · {{ formatDate(req.created_at) }}</p>
            </div>
            <div class="ml-4 flex items-center gap-3 shrink-0">
              <span class="text-sm font-medium text-gray-700">{{ formatCurrency(req.total_fee) }}</span>
              <StatusBadge :status="req.status" />
              <router-link :to="`/requests/${req.id}`" class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                View
              </router-link>
            </div>
          </li>
        </ul>
        </div>
      </div>
    </div>
</template>
