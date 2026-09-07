<script setup lang="ts">
import { ref } from 'vue'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { authService } from '@/services/auth.service'

const email   = ref('')
const emailError = ref('')
const success = ref(false)
const loading = ref(false)

async function handleSubmit() {
  emailError.value = ''
  if (!email.value) {
    emailError.value = 'Email is required.'
    return
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    emailError.value = 'Please enter a valid email address.'
    return
  }
  loading.value = true
  try {
    await authService.forgotPassword(email.value)
    success.value = true
  } catch {
    // Always show success — never reveal whether email exists
    success.value = true
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="Reset password" subtitle="Enter your email to receive reset instructions">
    <div
      v-if="success"
      class="rounded-xl bg-green-50 border border-green-200 px-5 py-4 text-sm text-green-800 space-y-1"
    >
      <p class="font-semibold">Check your email</p>
      <p>If an account with <strong>{{ email }}</strong> exists, a password reset link has been sent. Check your inbox (and spam folder).</p>
      <p class="pt-1">
        <router-link to="/login" class="text-blue-600 hover:text-blue-700 font-medium">Back to login</router-link>
      </p>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-5" novalidate>
      <FormField label="Email address" required :error="emailError">
        <input
          v-model="email"
          type="email"
          autocomplete="email"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': emailError }"
        />
      </FormField>

      <AppButton type="submit" :loading="loading" :full="true">
        Send Reset Link
      </AppButton>

      <p class="text-center text-sm">
        <router-link to="/login" class="text-blue-600 hover:text-blue-700 font-medium">Back to login</router-link>
      </p>
    </form>
  </AuthLayout>
</template>
