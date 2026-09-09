<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import FormField from '@/components/forms/FormField.vue'
import { adminService } from '@/services/admin.service'
import { API_BASE_URL } from '@/services/api'
import { formatDate, formatCurrency } from '@/utils/formatters'
import type { DocumentRequest, RequestStatus } from '@/types/request.types'

const route   = useRoute()
const id      = Number(route.params.id)

function proofUrl(path: string): string {
  const token = localStorage.getItem('auth_token') ?? ''
  return `${API_BASE_URL}/admin/file?path=${encodeURIComponent(path)}&token=${encodeURIComponent(token)}`
}

const request   = ref<DocumentRequest | null>(null)
const loading   = ref(true)
const saving    = ref(false)
const alert     = ref<{ type: 'success' | 'error'; message: string } | null>(null)

const newStatus  = ref('')
const statusNotes = ref('')
const rejectReason = ref('')

const paymentAction = ref<'verify' | 'reject' | null>(null)
const paymentNotes  = ref('')
const paymentRejectReason = ref('')
const savingPayment = ref(false)

// Mirrors backend ADMIN_TRANSITIONS; payment verification is handled by PayMongo sync.
const TRANSITIONS: Record<string, { value: string; label: string }[]> = {
  payment_verified: [
    { value: 'completed', label: 'Completed' },
    { value: 'rejected',  label: 'Rejected' },
  ],
}

const statusOptions = computed(() => TRANSITIONS[request.value?.status ?? ''] ?? [])

const statusColors: Record<string, string> = {
  payment_verified:    'bg-indigo-400',
  completed:           'bg-green-500',
  rejected:            'bg-red-400',
}

const hasPaymentPending = computed(() => false)
const identityProofAttachment = computed(() =>
  request.value?.attachments?.find((a) => a.label === 'identity_proof') ?? null
)

async function load() {
  loading.value = true
  try {
    request.value = await adminService.getRequest(id)
    newStatus.value = statusOptions.value[0]?.value ?? ''
  } finally {
    loading.value = false
  }
}

watch(statusOptions, (opts) => { newStatus.value = opts[0]?.value ?? '' })

onMounted(load)

async function updateStatus() {
  if (!newStatus.value) return
  saving.value = true
  alert.value  = null
  try {
    await adminService.updateStatus(id, {
      status: newStatus.value,
      notes:  statusNotes.value || undefined,
      rejection_reason: newStatus.value === 'rejected' ? rejectReason.value : undefined,
    })
    statusNotes.value  = ''
    rejectReason.value = ''
    await load()
    alert.value = { type: 'success', message: 'Status updated successfully.' }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to update status.' }
  } finally {
    saving.value = false
  }
}

async function handlePayment() {
  if (!paymentAction.value) return
  const action = paymentAction.value   // capture before clearing
  savingPayment.value = true
  alert.value = null
  try {
    if (action === 'verify') {
      await adminService.verifyPayment(id, paymentNotes.value || undefined)
    } else {
      if (!paymentRejectReason.value) {
        alert.value = { type: 'error', message: 'Rejection reason is required.' }
        savingPayment.value = false
        return
      }
      await adminService.rejectPayment(id, paymentRejectReason.value)
    }
    paymentAction.value       = null
    paymentNotes.value        = ''
    paymentRejectReason.value = ''
    await load()
    alert.value = { type: 'success', message: `Payment ${action === 'verify' ? 'verified' : 'rejected'} successfully.` }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to process payment.' }
  } finally {
    savingPayment.value = false
  }
}
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">

      <div>
        <router-link to="/admin/requests" class="text-sm text-sky-600 hover:text-sky-700 font-medium">← All Requests</router-link>
        <h1 class="mt-1 text-xl font-semibold text-gray-900">Request Detail</h1>
      </div>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <div v-if="loading" class="py-20 text-center text-sm text-gray-400">Loading...</div>

      <template v-else-if="request">

        <!-- Summary -->
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
            <!-- Walk-in requestor info -->
            <template v-if="request.is_walkin">
              <div class="flex justify-between py-2.5">
                <dt class="text-gray-500">Walk-in Name</dt>
                <dd class="font-medium text-gray-900">{{ request.walkin_name }}</dd>
              </div>
              <div v-if="request.walkin_phone" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Phone</dt>
                <dd class="text-gray-700">{{ request.walkin_phone }}</dd>
              </div>
              <div v-if="request.walkin_email" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Email</dt>
                <dd class="text-gray-700">{{ request.walkin_email }}</dd>
              </div>
            </template>
            <!-- Online requestor info -->
            <template v-else>
              <div class="flex justify-between py-2.5">
                <dt class="text-gray-500">Student</dt>
                <dd class="font-medium text-gray-900">{{ request.req_first_name }} {{ request.req_last_name }}</dd>
              </div>
              <div class="flex justify-between py-2.5">
                <dt class="text-gray-500">Email</dt>
                <dd class="text-gray-700">{{ request.req_email }}</dd>
              </div>
              <div v-if="request.req_student_id" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Student ID</dt>
                <dd class="text-gray-700">{{ request.req_student_id }}</dd>
              </div>
              <div v-if="request.req_program" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Program</dt>
                <dd class="text-gray-700">{{ request.req_program }}</dd>
              </div>
              <div v-if="request.grade_level" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Grade Level</dt>
                <dd class="text-gray-700">{{ request.grade_level }}</dd>
              </div>
              <div v-if="request.request_semester" class="flex justify-between py-2.5">
                <dt class="text-gray-500">Semester</dt>
                <dd class="text-gray-700">{{ request.request_semester === '1st' ? 'First Semester' : 'Second Semester' }}</dd>
              </div>
            </template>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Purpose</dt>
              <dd class="font-medium text-gray-900 text-right max-w-xs">{{ request.purpose }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Total Fee</dt>
              <dd class="font-bold text-blue-700">{{ formatCurrency(request.total_fee) }}</dd>
            </div>
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Submitted</dt>
              <dd class="text-gray-500">{{ formatDate(request.created_at) }}</dd>
            </div>
            <div v-if="!request.is_walkin && request.is_representative" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Representative</dt>
              <dd class="text-gray-700">{{ request.rep_name }} ({{ request.rep_relationship }})</dd>
            </div>
          </dl>

          <!-- Document items -->
          <div v-if="request.items?.length" class="mt-4 space-y-2">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Documents Requested</p>
            <div v-for="item in request.items" :key="item.id"
              class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2.5 text-sm">
              <div>
                <p class="font-medium text-gray-800">{{ item.document_type_name }}</p>
                <p class="text-xs text-gray-500">
                  {{ item.copies }} {{ item.copies === 1 ? 'copy' : 'copies' }} ·
                  {{ item.is_certified_copy ? 'Certified' : 'Original Copy' }}
                </p>
              </div>
              <span class="font-semibold text-blue-700">{{ formatCurrency(item.item_fee) }}</span>
            </div>
          </div>

          <div v-if="identityProofAttachment" class="mt-2">
            <p class="text-xs text-gray-400 mb-1">Identity Proof Photo</p>
            <a
              :href="proofUrl(identityProofAttachment.file_path)"
              target="_blank"
              class="text-xs text-sky-600 hover:text-sky-700 font-medium underline"
            >
              View identity proof ↗
            </a>
          </div>
        </div>

        <!-- Payment verification -->
        <div v-if="request.payment" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment</p>
            <span :class="[
              'px-2 py-0.5 rounded-full text-xs font-semibold',
              request.payment.status === 'verified' ? 'bg-green-100 text-green-700' :
              request.payment.status === 'rejected' ? 'bg-red-100 text-red-700' :
              'bg-amber-100 text-amber-700'
            ]">{{ request.payment.status }}</span>
          </div>

          <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-2.5">
              <dt class="text-gray-500">Amount</dt>
              <dd class="font-bold text-blue-700">{{ formatCurrency(request.payment.amount) }}</dd>
            </div>
            <div v-if="request.payment.reference_number" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Reference #</dt>
              <dd class="font-mono text-gray-700">{{ request.payment.reference_number }}</dd>
            </div>
            <div v-if="request.payment.submitted_at" class="flex justify-between py-2.5">
              <dt class="text-gray-500">Submitted</dt>
              <dd class="text-gray-500">{{ formatDate(request.payment.submitted_at) }}</dd>
            </div>
            <div v-if="request.payment.rejection_reason" class="flex justify-between py-2.5">
              <dt class="text-red-500">Rejected</dt>
              <dd class="text-red-700">{{ request.payment.rejection_reason }}</dd>
            </div>
          </dl>

          <!-- View proof -->
          <div v-if="request.payment.proof_path" class="mt-1">
            <p class="text-xs text-gray-400 mb-1">Payment Screenshot</p>
            <a
              :href="proofUrl(request.payment.proof_path)"
              target="_blank"
              class="text-xs text-sky-600 hover:text-sky-700 font-medium underline"
            >
              View screenshot ↗
            </a>
          </div>

          <!-- Verify / reject actions -->
          <div v-if="hasPaymentPending" class="space-y-3 pt-1">
            <div class="flex gap-2">
              <AppButton size="sm" @click="paymentAction = 'verify'">Verify Payment</AppButton>
              <AppButton variant="danger" size="sm" @click="paymentAction = 'reject'">Reject Payment</AppButton>
            </div>

            <div v-if="paymentAction === 'verify'" class="space-y-3 rounded-xl bg-green-50 border border-green-100 p-4">
              <p class="text-sm font-medium text-green-800">Confirm payment verification?</p>
              <FormField label="Notes (optional)">
                <input v-model="paymentNotes" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
              </FormField>
              <div class="flex gap-2">
                <AppButton size="sm" :loading="savingPayment" @click="handlePayment">Confirm Verify</AppButton>
                <AppButton variant="ghost" size="sm" @click="paymentAction = null">Cancel</AppButton>
              </div>
            </div>

            <div v-if="paymentAction === 'reject'" class="space-y-3 rounded-xl bg-red-50 border border-red-100 p-4">
              <p class="text-sm font-medium text-red-800">Reject this payment?</p>
              <FormField label="Rejection Reason" required>
                <input v-model="paymentRejectReason" type="text" placeholder="e.g., Invalid reference number"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
              </FormField>
              <div class="flex gap-2">
                <AppButton variant="danger" size="sm" :loading="savingPayment" @click="handlePayment">Confirm Reject</AppButton>
                <AppButton variant="ghost" size="sm" @click="paymentAction = null">Cancel</AppButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Update status -->
        <div v-if="statusOptions.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Update Status</p>
          <div class="space-y-3">
            <FormField label="New Status">
              <select v-model="newStatus"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </FormField>
            <FormField v-if="newStatus === 'rejected'" label="Rejection Reason" required>
              <input v-model="rejectReason" type="text" placeholder="Reason for rejection"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
            </FormField>
            <FormField label="Notes (optional)">
              <textarea v-model="statusNotes" rows="2" placeholder="Internal notes..."
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
            </FormField>
            <AppButton :loading="saving" @click="updateStatus">Update Status</AppButton>
          </div>
        </div>

        <!-- Status timeline -->
        <div v-if="request.status_logs?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Status Timeline</p>
          <ol class="relative border-l-2 border-gray-100 space-y-5 pl-5">
            <li v-for="log in request.status_logs" :key="log.id" class="relative">
              <span :class="['absolute -left-[23px] top-1 h-3 w-3 rounded-full border-2 border-white', statusColors[log.to_status] ?? 'bg-gray-300']" />
              <p class="text-sm font-semibold text-gray-800">
                {{ log.to_status.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase()) }}
              </p>
              <p v-if="log.notes" class="text-xs text-gray-500 mt-0.5">{{ log.notes }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(log.created_at) }} · {{ log.changed_by_email }}</p>
            </li>
          </ol>
        </div>

      </template>
    </div>
</template>
