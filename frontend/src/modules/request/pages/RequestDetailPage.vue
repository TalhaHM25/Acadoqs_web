<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import { requestService } from '@/services/request.service'
import { formatDate, formatCurrency } from '@/utils/formatters'
import type { DocumentRequest } from '@/types/request.types'

const route  = useRoute()
const router = useRouter()

const request  = ref<DocumentRequest | null>(null)
const loading  = ref(true)
const cancelling = ref(false)

const id = Number(route.params.id)

onMounted(async () => {
  try {
    request.value = await requestService.get(id)
  } finally {
    loading.value = false
  }
})

const canPay = computed(() =>
  false
)

const canCancel = computed(() =>
  false
)

async function cancel() {
  if (!confirm('Cancel this request?')) return
  cancelling.value = true
  try {
    await requestService.cancel(id)
    request.value = await requestService.get(id)
  } finally {
    cancelling.value = false
  }
}

const statusColors: Record<string, string> = {
  payment_verified:    'bg-indigo-400',
  completed:           'bg-green-500',
  rejected:            'bg-red-400',
}
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">

      <div class="flex items-center justify-between">
        <div>
          <router-link to="/requests" class="text-sm text-sky-600 hover:text-sky-700 font-medium">← My Requests</router-link>
          <h1 class="mt-1 text-xl font-semibold text-gray-900">Request Detail</h1>
        </div>
        <div v-if="request" class="flex gap-2">
          <router-link v-if="canPay" :to="`/requests/${id}/payment`">
            <AppButton size="sm">Pay Now</AppButton>
          </router-link>
          <AppButton v-if="canCancel" variant="danger" size="sm" :loading="cancelling" @click="cancel">
            Cancel
          </AppButton>
        </div>
      </div>

      <div v-if="loading" class="py-20 text-center text-sm text-gray-400">Loading...</div>

      <template v-else-if="request">

        <!-- Summary card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs text-gray-400 font-mono">{{ request.request_number }}</p>
              <p class="text-lg font-bold text-gray-900 mt-0.5">{{ request.document_type_name }}</p>
              <p class="text-xs text-gray-500">{{ request.category_name }}</p>
            </div>
            <div class="flex items-center gap-2">
              <SourceBadge :source="request.request_source" />
              <StatusBadge :status="request.status" />
            </div>
          </div>

          <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Purpose</dt>
              <dd class="font-medium text-gray-900 text-right max-w-xs">{{ request.purpose }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Copies</dt>
              <dd class="font-medium text-gray-900">{{ request.copies }} × {{ request.is_certified_copy ? 'Certified' : 'Original Copy' }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Total Fee</dt>
              <dd class="font-bold text-blue-700">{{ formatCurrency(request.total_fee) }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Submitted</dt>
              <dd class="text-gray-700">{{ formatDate(request.created_at) }}</dd>
            </div>
            <div v-if="request.rejection_reason" class="flex justify-between py-2.5">
              <dt class="text-red-500 font-medium">Rejection Reason</dt>
              <dd class="text-red-700 text-right max-w-xs">{{ request.rejection_reason }}</dd>
            </div>
            <div v-if="request.admin_notes" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Admin Notes</dt>
              <dd class="text-gray-700 text-right max-w-xs">{{ request.admin_notes }}</dd>
            </div>
          </dl>
        </div>

        <!-- Student info -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Requestor</p>
          <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Name</dt>
              <dd class="font-medium text-gray-900">{{ request.req_first_name }} {{ request.req_middle_name }} {{ request.req_last_name }}</dd>
            </div>
            <div v-if="request.req_student_id" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Student ID</dt>
              <dd class="text-gray-700">{{ request.req_student_id }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Email</dt>
              <dd class="text-gray-700">{{ request.req_email }}</dd>
            </div>
            <div v-if="request.req_phone" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Phone</dt>
              <dd class="text-gray-700">{{ request.req_phone }}</dd>
            </div>
            <div v-if="request.grade_level" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Grade Level</dt>
              <dd class="text-gray-700">{{ request.grade_level }}</dd>
            </div>
            <div v-if="request.request_semester" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Semester</dt>
              <dd class="text-gray-700">{{ request.request_semester === '1st' ? 'First Semester' : 'Second Semester' }}</dd>
            </div>
            <div v-if="request.is_representative" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Representative</dt>
              <dd class="text-gray-700">{{ request.rep_name }} ({{ request.rep_relationship }})</dd>
            </div>
          </dl>
        </div>

        <!-- Payment -->
        <div v-if="request.payment" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment</p>
          <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Amount</dt>
              <dd class="font-bold text-blue-700">{{ formatCurrency(request.payment.amount) }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Status</dt>
              <dd>
                <span :class="[
                  'px-2 py-0.5 rounded-full text-xs font-semibold',
                  request.payment.status === 'verified' ? 'bg-green-100 text-green-700' :
                  request.payment.status === 'rejected' ? 'bg-red-100 text-red-700' :
                  'bg-amber-100 text-amber-700'
                ]">{{ request.payment.status }}</span>
              </dd>
            </div>
            <div v-if="request.payment.rejection_reason" class="flex justify-between py-2.5">
              <dt class="text-red-500">Rejection</dt>
              <dd class="text-red-700">{{ request.payment.rejection_reason }}</dd>
            </div>
          </dl>
          <router-link
            v-if="canPay"
            :to="`/requests/${id}/payment`"
          >
            <AppButton size="sm" class="mt-2">Proceed to Payment</AppButton>
          </router-link>
        </div>

        <!-- Status timeline -->
        <div v-if="request.status_logs?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Status Timeline</p>
          <ol class="relative border-l-2 border-gray-100 space-y-5 pl-5">
            <li v-for="log in request.status_logs" :key="log.id" class="relative">
              <span :class="['absolute -left-[23px] top-1 h-3 w-3 rounded-full border-2 border-white', statusColors[log.to_status] ?? 'bg-gray-300']" />
              <p class="text-sm font-semibold text-gray-800">
                {{ log.to_status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
              </p>
              <p v-if="log.notes" class="text-xs text-gray-500 mt-0.5">{{ log.notes }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(log.created_at) }} · {{ log.changed_by_email }}</p>
            </li>
          </ol>
        </div>

      </template>

      <div v-else class="py-20 text-center text-sm text-gray-400">Request not found.</div>
    </div>
</template>
