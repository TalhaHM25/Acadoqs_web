<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import { useToastStore } from '@/stores/toast.store'
import { paymentService } from '@/services/payment.service'
import { requestService } from '@/services/request.service'
import { formatCurrency, formatDateTime } from '@/utils/formatters'
import type { DocumentRequest } from '@/types/request.types'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const id = Number(route.params.id)

const request = ref<DocumentRequest | null>(null)
const paymentInfo = ref<any>(null)
const loading = ref(true)
const redirecting = ref(false)
const syncing = ref(false)

const payment = computed(() => paymentInfo.value?.payment ?? request.value?.payment ?? null)
const checkoutUrl = computed(() => payment.value?.paymongo_checkout_url ?? null)
const isPaid = computed(() => payment.value?.status === 'verified' || request.value?.status === 'payment_verified')

onMounted(async () => {
  try {
    const [reqRes, paymentRes] = await Promise.allSettled([
      requestService.get(id),
      paymentService.getInfo(id),
    ])
    if (reqRes.status === 'fulfilled') request.value = reqRes.value
    if (paymentRes.status === 'fulfilled') paymentInfo.value = paymentRes.value

    if (route.query.paymongo === 'success') {
      await syncPayment()
    } else if (route.query.paymongo === 'cancelled') {
      toast.error('Payment was cancelled.')
    }
  } finally {
    loading.value = false
  }
})

async function startCheckout() {
  if (checkoutUrl.value) {
    window.location.href = checkoutUrl.value
    return
  }

  redirecting.value = true
  try {
    const result = await paymentService.createCheckout(id)
    const url = result.checkout_url
    if (!url) throw new Error('Missing checkout URL')
    window.location.href = url
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? 'Unable to start payment.')
    redirecting.value = false
  }
}

async function syncPayment() {
  syncing.value = true
  try {
    const checkoutSessionId = typeof route.query.checkout_session_id === 'string'
      ? route.query.checkout_session_id
      : undefined
    const result = await paymentService.sync(id, checkoutSessionId)
    paymentInfo.value = { ...(paymentInfo.value ?? {}), payment: result.payment }
    request.value = await requestService.get(id)
    if (result.paid) {
      toast.success('Payment verified.')
      router.replace({ path: route.path })
    } else {
      toast.error('Payment has not been confirmed yet.')
    }
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? 'Unable to check payment status.')
  } finally {
    syncing.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-lg space-y-6">
    <div>
      <router-link :to="`/requests/${id}`" class="text-sm font-medium text-sky-600 hover:text-sky-700">Back to Request</router-link>
      <h1 class="mt-2 text-xl font-semibold text-gray-900">Complete Payment</h1>
    </div>

    <div v-if="loading" class="py-20 text-center text-sm text-gray-400">Loading...</div>

    <template v-else-if="request">
      <div class="rounded-2xl border-2 border-blue-100 bg-blue-50 p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-medium text-blue-600">Amount Due</p>
            <p class="mt-1 text-2xl font-extrabold text-blue-800">{{ formatCurrency(request.total_fee) }}</p>
          </div>
          <p class="font-mono text-xs text-gray-500">{{ request.request_number }}</p>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <StatusBadge :status="request.status" />
          <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-gray-600">Online Payment</span>
        </div>
      </div>

      <div v-if="isPaid" class="rounded-2xl border border-green-100 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
          <svg class="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="mt-4 text-lg font-semibold text-gray-900">Payment Verified</p>
        <p class="mt-1 text-sm text-gray-500">Your payment was confirmed successfully.</p>
        <router-link :to="`/requests/${id}`">
          <AppButton :full="true" class="mt-5">View Request</AppButton>
        </router-link>
      </div>

      <div v-else class="space-y-5 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <div>
          <p class="text-sm font-semibold text-gray-800">Secure Payment Portal</p>
          <p class="mt-1 text-sm text-gray-500">
            You will be redirected to a secure payment page to complete payment by card or supported e-wallets.
          </p>
        </div>

        <div v-if="payment?.submitted_at" class="rounded-xl border border-gray-100 bg-slate-50 px-4 py-3 text-sm text-gray-600">
          Last payment session started {{ formatDateTime(payment.submitted_at) }}.
        </div>

        <AppButton :full="true" :loading="redirecting" @click="startCheckout">
          {{ checkoutUrl ? 'Proceed to Payment' : 'Start Payment' }}
        </AppButton>

        <AppButton v-if="checkoutUrl" :full="true" variant="outline" :loading="syncing" @click="syncPayment">
          Check Payment Status
        </AppButton>
      </div>
    </template>
  </div>
</template>
