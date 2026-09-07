<script setup lang="ts">
import StatusBadge from '@/components/common/StatusBadge.vue'
import SourceBadge from '@/components/common/SourceBadge.vue'
import DueDateReminder from '@/components/common/DueDateReminder.vue'
import AppButton from '@/components/common/AppButton.vue'
import { formatCurrency, formatDate } from '@/utils/formatters'
import type { DocumentRequest } from '@/types/request.types'

defineProps<{
  request: DocumentRequest | null
  loading: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const apiBase = import.meta.env.VITE_API_URL ?? 'http://localhost/document-request-system/backend/public/api'

function proofUrl(path: string): string {
  const token = localStorage.getItem('auth_token') ?? ''
  return `${apiBase}/admin/file?path=${encodeURIComponent(path)}&token=${encodeURIComponent(token)}`
}

function fullName(request: DocumentRequest): string {
  return `${request.req_first_name ?? ''} ${request.req_last_name ?? ''}`.trim() || request.walkin_name || 'Requestor'
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
      <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
        <div>
          <p class="font-mono text-xs text-gray-400">{{ request?.request_number ?? 'Request details' }}</p>
          <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ request?.document_type_name ?? 'Loading request' }}</h2>
        </div>
        <AppButton variant="ghost" size="sm" @click="emit('close')">Close</AppButton>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

      <div v-else-if="request" class="overflow-y-auto px-5 py-5">
        <div class="grid gap-4 md:grid-cols-2">
          <section class="space-y-3 rounded-lg border border-gray-100 p-4">
            <div class="flex flex-wrap items-center gap-2">
              <SourceBadge :source="request.request_source" />
              <StatusBadge :status="request.status" />
              <DueDateReminder :due-date="request.expected_release_at" :status="request.status" />
            </div>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Requestor</dt>
                <dd class="text-right font-medium text-gray-900">{{ fullName(request) }}</dd>
              </div>
              <div v-if="request.req_student_id" class="flex justify-between gap-4">
                <dt class="text-gray-500">Student ID</dt>
                <dd class="text-right text-gray-700">{{ request.req_student_id }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Email</dt>
                <dd class="text-right text-gray-700">{{ request.req_email || request.walkin_email || '-' }}</dd>
              </div>
              <div v-if="request.req_phone || request.walkin_phone" class="flex justify-between gap-4">
                <dt class="text-gray-500">Phone</dt>
                <dd class="text-right text-gray-700">{{ request.req_phone || request.walkin_phone }}</dd>
              </div>
              <div v-if="request.req_program" class="flex justify-between gap-4">
                <dt class="text-gray-500">Program</dt>
                <dd class="text-right text-gray-700">{{ request.req_program }}</dd>
              </div>
              <div v-if="request.grade_level" class="flex justify-between gap-4">
                <dt class="text-gray-500">Grade Level</dt>
                <dd class="text-right text-gray-700">{{ request.grade_level }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Submitted</dt>
                <dd class="text-right text-gray-700">{{ formatDate(request.created_at) }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Due Date</dt>
                <dd class="text-right text-gray-700">{{ formatDate(request.expected_release_at ?? '') }}</dd>
              </div>
            </dl>
          </section>

          <section class="space-y-3 rounded-lg border border-gray-100 p-4">
            <p class="text-xs font-semibold uppercase text-gray-400">Request</p>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Document</dt>
                <dd class="text-right font-medium text-gray-900">{{ request.document_type_name }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Category</dt>
                <dd class="text-right text-gray-700">{{ request.category_name || '-' }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Level</dt>
                <dd class="text-right text-gray-700">{{ request.level?.replace('_', ' ') || '-' }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Purpose</dt>
                <dd class="max-w-48 text-right text-gray-700">{{ request.purpose || '-' }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500">Total Fee</dt>
                <dd class="text-right font-semibold text-blue-700">{{ formatCurrency(request.total_fee) }}</dd>
              </div>
              <div v-if="request.is_representative" class="flex justify-between gap-4">
                <dt class="text-gray-500">Representative</dt>
                <dd class="text-right text-gray-700">{{ request.rep_name }} ({{ request.rep_relationship }})</dd>
              </div>
            </dl>
          </section>
        </div>

        <section v-if="request.items?.length" class="mt-4 rounded-lg border border-gray-100 p-4">
          <p class="text-xs font-semibold uppercase text-gray-400">Documents Requested</p>
          <div class="mt-3 space-y-2">
            <div v-for="item in request.items" :key="item.id" class="flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-3 py-2 text-sm">
              <div>
                <p class="font-medium text-gray-900">{{ item.document_type_name }}</p>
                <p class="text-xs text-gray-500">{{ item.copies }} {{ item.copies === 1 ? 'copy' : 'copies' }} - {{ item.is_certified_copy ? 'Certified' : 'Original Copy' }}</p>
              </div>
              <span class="shrink-0 font-semibold text-blue-700">{{ formatCurrency(item.item_fee) }}</span>
            </div>
          </div>
        </section>

        <section v-if="request.payment" class="mt-4 rounded-lg border border-gray-100 p-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase text-gray-400">Payment</p>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ request.payment.status }}</span>
          </div>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-gray-500">Amount</dt>
              <dd class="font-semibold text-blue-700">{{ formatCurrency(request.payment.amount) }}</dd>
            </div>
            <div v-if="request.payment.reference_number" class="flex justify-between gap-4">
              <dt class="text-gray-500">Reference #</dt>
              <dd class="font-mono text-gray-700">{{ request.payment.reference_number }}</dd>
            </div>
            <div v-if="request.payment.proof_path" class="flex justify-between gap-4">
              <dt class="text-gray-500">Proof</dt>
              <dd><a :href="proofUrl(request.payment.proof_path)" target="_blank" class="text-sky-600 hover:underline">View screenshot</a></dd>
            </div>
          </dl>
        </section>

        <section v-if="request.attachments?.length" class="mt-4 rounded-lg border border-gray-100 p-4">
          <p class="text-xs font-semibold uppercase text-gray-400">Attachments</p>
          <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <a
              v-for="attachment in request.attachments"
              :key="attachment.id"
              :href="proofUrl(attachment.file_path)"
              target="_blank"
              class="rounded-lg bg-sky-50 px-3 py-2 font-medium text-sky-700 hover:bg-sky-100"
            >
              {{ attachment.label.replace('_', ' ') }}
            </a>
          </div>
        </section>

        <section v-if="request.status_logs?.length" class="mt-4 rounded-lg border border-gray-100 p-4">
          <p class="text-xs font-semibold uppercase text-gray-400">Status Timeline</p>
          <ol class="mt-3 space-y-3 border-l border-gray-200 pl-4 text-sm">
            <li v-for="log in request.status_logs" :key="log.id">
              <p class="font-medium text-gray-900">{{ log.to_status.replace(/_/g, ' ') }}</p>
              <p v-if="log.notes" class="text-xs text-gray-500">{{ log.notes }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(log.created_at) }} - {{ log.changed_by_email }}</p>
            </li>
          </ol>
        </section>
      </div>
    </div>
  </div>
</template>
