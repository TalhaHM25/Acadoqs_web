<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { authService } from '@/services/auth.service'
import { useAuthStore } from '@/stores/auth.store'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const status      = ref<'verifying' | 'success' | 'error' | 'manual'>('verifying')
const message     = ref('')
const manualToken = ref('')
const verifying   = ref(false)
const tokenError  = ref('')

function extractToken(input: string): string {
  const trimmed = input.trim()
  try {
    // If the user pasted a full URL, pull the token query param out of it
    const url = new URL(trimmed)
    return url.searchParams.get('token') ?? trimmed
  } catch {
    // Not a URL — treat the whole string as the token itself
    return trimmed
  }
}

async function verify(token: string) {
  verifying.value = true
  tokenError.value = ''
  try {
    const result = await authService.verifyEmail(token.trim())
    auth.setAuth(result.user, result.token)
    status.value = 'success'
    setTimeout(() => router.push('/dashboard'), 2000)
  } catch (err: any) {
    const msg = err?.response?.data?.message ?? 'Verification failed. The link may have expired.'
    if (status.value === 'verifying') {
      // Auto-verify attempt failed — drop to manual entry instead of hard error
      status.value  = 'manual'
      message.value = msg
    } else {
      tokenError.value = msg
    }
  } finally {
    verifying.value = false
  }
}

onMounted(() => {
  const token = (route.query.token as string ?? '').trim()
  if (token) {
    verify(token)
  } else {
    // No token in URL — go straight to manual entry
    status.value = 'manual'
  }
})
</script>

<template>
  <AuthLayout title="Email Verification" subtitle="Activating your account">
    <div class="space-y-5 text-center">

      <!-- Verifying spinner -->
      <template v-if="status === 'verifying'">
        <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600" />
        <p class="text-sm text-gray-500">Verifying your email address…</p>
      </template>

      <!-- Success -->
      <template v-else-if="status === 'success'">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
          <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div class="space-y-1">
          <p class="text-lg font-semibold text-gray-900">Email Verified!</p>
          <p class="text-sm text-gray-500">Your account is now active. Redirecting to your dashboard…</p>
        </div>
        <AppButton :full="true" @click="router.push('/dashboard')">Go to Dashboard</AppButton>
      </template>

      <!-- Manual entry (link click failed or no token in URL) -->
      <template v-else-if="status === 'manual'">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
          <svg class="h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
          </svg>
        </div>

        <div class="space-y-1 text-left">
          <p class="text-base font-semibold text-gray-900 text-center">Verify your email manually</p>
          <p class="text-sm text-gray-500 text-center">
            The link couldn't be opened automatically. Copy the full verification link from your email and paste it below.
          </p>
        </div>

        <div v-if="message" class="rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700 text-left">
          {{ message }}
        </div>

        <div class="space-y-3 text-left">
          <FormField label="Paste the verification link or token" :error="tokenError">
            <input
              v-model="manualToken"
              type="text"
              placeholder="http://localhost:5173/verify-email?token=… or just the token"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              :class="{ 'border-red-400': tokenError }"
              @keyup.enter="verify(extractToken(manualToken))"
            />
          </FormField>
          <AppButton
            :full="true"
            :loading="verifying"
            @click="verify(extractToken(manualToken))"
          >
            Verify Account
          </AppButton>
        </div>

        <p class="text-sm text-gray-500">
          Didn't receive an email?
          <router-link to="/verify-email-sent" class="font-medium text-blue-600 hover:text-blue-700">Resend it</router-link>
        </p>
        <p class="text-sm text-gray-500">
          <router-link to="/login" class="font-medium text-blue-600 hover:text-blue-700">Back to login</router-link>
        </p>
      </template>

      <!-- Hard error (only if manual submit also fails with a non-recoverable error) -->
      <template v-else>
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
          <svg class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <div class="space-y-1">
          <p class="text-lg font-semibold text-gray-900">Verification Failed</p>
          <p class="text-sm text-gray-500">{{ message }}</p>
        </div>
        <div class="flex flex-col gap-3">
          <router-link to="/verify-email-sent">
            <AppButton :full="true" variant="outline">Resend Verification Email</AppButton>
          </router-link>
          <router-link to="/login">
            <AppButton :full="true" variant="ghost">Back to Login</AppButton>
          </router-link>
        </div>
      </template>

    </div>
  </AuthLayout>
</template>
