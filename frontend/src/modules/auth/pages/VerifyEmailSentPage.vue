<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import { authService } from '@/services/auth.service'
import { useToastStore } from '@/stores/toast.store'

const route   = useRoute()
const toast   = useToastStore()
const email   = route.query.email as string ?? ''
const sending = ref(false)
const sent    = ref(false)

async function resend() {
  sending.value = true
  try {
    await authService.resendVerification(email)
    sent.value = true
    toast.success('Verification email resent! Please check your inbox.')
  } catch {
    toast.error('Failed to resend. Please try again.')
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <AuthLayout title="Check your email" subtitle="One more step to activate your account">
    <div class="space-y-5 text-center">

      <!-- Icon -->
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
        <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
      </div>

      <div class="space-y-2">
        <p class="text-sm text-gray-600">
          We sent a verification link to
        </p>
        <p class="font-semibold text-gray-900 break-all">{{ email || 'your email address' }}</p>
        <p class="text-sm text-gray-500">
          Click the link in the email to verify your account and start using AcaDocs.
        </p>
      </div>

      <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800 text-left space-y-1">
        <p class="font-medium">Didn't receive it?</p>
        <ul class="list-disc list-inside space-y-0.5 text-amber-700">
          <li>Check your spam or junk folder.</li>
          <li>Make sure you entered the correct email.</li>
          <li>The link expires in 24 hours.</li>
        </ul>
      </div>

      <AppButton
        :full="true"
        variant="outline"
        :loading="sending"
        :disabled="sent"
        @click="resend"
      >
        {{ sent ? 'Email sent!' : 'Resend verification email' }}
      </AppButton>

      <p class="text-sm text-gray-500">
        Wrong email?
        <router-link to="/register" class="font-medium text-blue-600 hover:text-blue-700">Register again</router-link>
      </p>

      <p class="text-sm text-gray-500">
        Already verified?
        <router-link to="/login" class="font-medium text-blue-600 hover:text-blue-700">Sign in</router-link>
      </p>

    </div>
  </AuthLayout>
</template>
