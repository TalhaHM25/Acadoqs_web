<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import { requestService } from '@/services/request.service'
import { useToastStore } from '@/stores/toast.store'
import type { DocumentRequest } from '@/types/request.types'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const loading = ref(true)
const request = ref<DocumentRequest | null>(null)
const error = ref('')

onMounted(finalize)

async function finalize() {
  const token = typeof route.query.checkout_token === 'string' ? route.query.checkout_token : ''
  const checkoutSessionId = typeof route.query.checkout_session_id === 'string'
    ? route.query.checkout_session_id
    : undefined

  if (!token || route.query.paymongo !== 'success') {
    error.value = 'Payment was not completed.'
    loading.value = false
    return
  }

  try {
    request.value = await requestService.finalizePaidCheckout(token, checkoutSessionId)
    toast.success('Payment verified. Your request has been submitted.')
    router.replace(`/requests/${request.value.id}`)
  } catch (err: any) {
    error.value = err?.response?.data?.message ?? 'Unable to confirm payment.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-lg space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-6 text-center">
      <div v-if="loading" class="py-10">
        <p class="text-sm font-medium text-gray-700">Confirming payment...</p>
        <p class="mt-1 text-sm text-gray-500">Please keep this page open while your request is submitted.</p>
      </div>

      <div v-else-if="error" class="py-8">
        <p class="text-base font-semibold text-gray-900">Payment Confirmation Needed</p>
        <p class="mt-2 text-sm text-gray-500">{{ error }}</p>
        <router-link to="/requests/new">
          <AppButton class="mt-5">Create Request Again</AppButton>
        </router-link>
      </div>
    </div>
  </div>
</template>
