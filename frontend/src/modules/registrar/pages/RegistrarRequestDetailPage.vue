<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import FormField from '@/components/forms/FormField.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import { adminService } from '@/services/admin.service'
import { API_BASE_URL } from '@/services/api'
import { formatDate, formatCurrency } from '@/utils/formatters'

const route = useRoute()
const id    = Number(route.params.id)

const request      = ref<any>(null)
const loading      = ref(true)
const saving       = ref(false)
const savingPayment = ref(false)
const alert        = ref<{ type: 'success'|'error'; message: string } | null>(null)
const newStatus    = ref('')
const statusNotes  = ref('')
const rejectReason = ref('')
const paymentAction = ref<'verify'|'reject'|null>(null)
const paymentRejectReason = ref('')

function proofUrl(path: string) {
  const token = localStorage.getItem('auth_token') ?? ''
  return `${API_BASE_URL}/admin/file?path=${encodeURIComponent(path)}&token=${encodeURIComponent(token)}`
}

const hasPaymentPending = computed(() => false)
const identityProofAttachment = computed(() =>
  request.value?.attachments?.find((a: any) => a.label === 'identity_proof') ?? null
)

// Mirrors backend ADMIN_TRANSITIONS; payment verification is handled by PayMongo sync.
const TRANSITIONS: Record<string, { value: string; label: string }[]> = {
  payment_verified: [
    { value: 'completed', label: 'Completed' },
    { value: 'rejected',  label: 'Rejected' },
  ],
}

const statusOptions = computed(() => TRANSITIONS[request.value?.status ?? ''] ?? [])

async function load() {
  loading.value = true
  try {
    request.value = await adminService.getRequest(id)
    newStatus.value = statusOptions.value[0]?.value ?? ''
  } finally { loading.value = false }
}

watch(statusOptions, (opts) => { newStatus.value = opts[0]?.value ?? '' })

onMounted(load)

async function updateStatus() {
  saving.value = true; alert.value = null
  try {
    await adminService.updateStatus(id, { status: newStatus.value, notes: statusNotes.value || undefined, rejection_reason: newStatus.value === 'rejected' ? rejectReason.value : undefined })
    await load()
    alert.value = { type: 'success', message: 'Status updated.' }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed.' }
  } finally { saving.value = false }
}

async function handlePayment() {
  if (!paymentAction.value) return
  savingPayment.value = true; alert.value = null
  try {
    if (paymentAction.value === 'verify') {
      await adminService.verifyPayment(id)
    } else {
      if (!paymentRejectReason.value) { alert.value = { type: 'error', message: 'Rejection reason required.' }; savingPayment.value = false; return }
      await adminService.rejectPayment(id, paymentRejectReason.value)
    }
    paymentAction.value = null; paymentRejectReason.value = ''
    await load()
    alert.value = { type: 'success', message: `Payment ${paymentAction.value === 'verify' ? 'verified' : 'rejected'}.` }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed.' }
  } finally { savingPayment.value = false }
}
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">
      <div>
        <router-link to="/registrar/requests" class="text-sm text-sky-600 hover:text-sky-700">← All Requests</router-link>
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
            </div>
            <div class="flex items-center gap-2">
              <SourceBadge :source="request.request_source" />
              <StatusBadge :status="request.status" />
            </div>
          </div>
          <dl class="divide-y divide-gray-100 text-sm">
            <!-- Walk-in requestor info -->
            <template v-if="request.is_walkin">
              <div class="flex justify-between py-2.5"><dt class="text-gray-500">Walk-in Name</dt><dd class="font-medium">{{ request.walkin_name }}</dd></div>
              <div v-if="request.walkin_phone" class="flex justify-between py-2.5"><dt class="text-gray-500">Phone</dt><dd>{{ request.walkin_phone }}</dd></div>
              <div v-if="request.walkin_email" class="flex justify-between py-2.5"><dt class="text-gray-500">Email</dt><dd>{{ request.walkin_email }}</dd></div>
            </template>
            <!-- Online requestor info -->
            <template v-else>
              <div class="flex justify-between py-2.5"><dt class="text-gray-500">Student</dt><dd class="font-medium">{{ request.req_first_name }} {{ request.req_last_name }}</dd></div>
              <div class="flex justify-between py-2.5"><dt class="text-gray-500">Email</dt><dd>{{ request.req_email }}</dd></div>
              <div v-if="request.req_phone" class="flex justify-between py-2.5"><dt class="text-gray-500">Phone</dt><dd>{{ request.req_phone }}</dd></div>
            </template>
            <div class="flex justify-between py-2.5"><dt class="text-gray-500">Purpose</dt><dd class="text-right max-w-xs">{{ request.purpose }}</dd></div>
            <div class="flex justify-between py-2.5"><dt class="text-gray-500">Total Fee</dt><dd class="font-bold text-blue-700">{{ formatCurrency(request.total_fee) }}</dd></div>
            <div class="flex justify-between py-2.5"><dt class="text-gray-500">Submitted</dt><dd>{{ formatDate(request.created_at) }}</dd></div>
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
        </div>

        <!-- Payment -->
        <div v-if="request.payment" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Payment</p>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', request.payment.status === 'verified' ? 'bg-green-100 text-green-700' : request.payment.status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700']">{{ request.payment.status }}</span>
          </div>
          <dl class="divide-y divide-gray-100 text-sm">
            <div class="flex justify-between py-2.5"><dt class="text-gray-500">Amount</dt><dd class="font-bold text-blue-700">{{ formatCurrency(request.payment.amount) }}</dd></div>
            <div v-if="request.payment.reference_number" class="flex justify-between py-2.5"><dt class="text-gray-500">Ref #</dt><dd class="font-mono">{{ request.payment.reference_number }}</dd></div>
            <div v-if="request.payment.rejection_reason" class="flex justify-between py-2.5"><dt class="text-red-500">Rejected</dt><dd class="text-red-700">{{ request.payment.rejection_reason }}</dd></div>
          </dl>
          <div v-if="request.payment.proof_path" class="mt-1">
            <a :href="proofUrl(request.payment.proof_path)" target="_blank" class="text-xs text-sky-600 underline">View screenshot ↗</a>
          </div>
          <div v-if="identityProofAttachment" class="mt-2">
            <a :href="proofUrl(identityProofAttachment.file_path)" target="_blank" class="text-xs text-sky-600 underline">View identity proof ↗</a>
          </div>
          <div v-if="hasPaymentPending" class="space-y-3 pt-1">
            <div class="flex gap-2">
              <AppButton size="sm" @click="paymentAction = 'verify'">Verify Payment</AppButton>
              <AppButton variant="danger" size="sm" @click="paymentAction = 'reject'">Reject Payment</AppButton>
            </div>
            <div v-if="paymentAction === 'verify'" class="rounded-xl bg-green-50 border border-green-100 p-4 space-y-3">
              <p class="text-sm font-medium text-green-800">Confirm payment verification?</p>
              <div class="flex gap-2">
                <AppButton size="sm" :loading="savingPayment" @click="handlePayment">Confirm Verify</AppButton>
                <AppButton variant="ghost" size="sm" @click="paymentAction = null">Cancel</AppButton>
              </div>
            </div>
            <div v-if="paymentAction === 'reject'" class="rounded-xl bg-red-50 border border-red-100 p-4 space-y-3">
              <FormField label="Rejection Reason" required>
                <input v-model="paymentRejectReason" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
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
          <FormField label="New Status">
            <select v-model="newStatus" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </FormField>
          <FormField v-if="newStatus === 'rejected'" label="Rejection Reason" required>
            <input v-model="rejectReason" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </FormField>
          <FormField label="Notes (optional)">
            <textarea v-model="statusNotes" rows="2" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" />
          </FormField>
          <AppButton :loading="saving" @click="updateStatus">Update Status</AppButton>
        </div>
      </template>
    </div>
</template>
