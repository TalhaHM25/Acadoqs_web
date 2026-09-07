<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { authService } from '@/services/auth.service'

const router = useRouter()

const token    = ref('')
const form     = ref({ password: '', password_confirmation: '' })
const errors   = ref<Record<string, string>>({})
const loading  = ref(false)
const success  = ref(false)
const tokenMissing = ref(false)

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  token.value = params.get('token') ?? ''
  if (!token.value) tokenMissing.value = true
})

async function handleSubmit() {
  errors.value = {}

  if (!form.value.password || form.value.password.length < 8) {
    errors.value.password = 'Password must be at least 8 characters.'
  }
  if (!form.value.password_confirmation) {
    errors.value.password_confirmation = 'Please confirm your password.'
  } else if (form.value.password !== form.value.password_confirmation) {
    errors.value.password_confirmation = 'Passwords do not match.'
  }
  if (Object.keys(errors.value).length) return

  loading.value = true
  try {
    await authService.resetPassword({
      token: token.value,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })
    success.value = true
    setTimeout(() => router.push('/login'), 2500)
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) {
      Object.entries(resp.errors).forEach(([k, v]: any) => {
        errors.value[k] = Array.isArray(v) ? v[0] : v
      })
    } else {
      errors.value.token = resp?.message ?? 'This reset link is invalid or has expired.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="Set new password" subtitle="Choose a strong password for your account">

    <!-- Invalid / missing token -->
    <div v-if="tokenMissing" class="rounded-xl bg-red-50 border border-red-200 px-5 py-4 text-sm text-red-800 space-y-2">
      <p class="font-semibold">Invalid reset link</p>
      <p>This link is missing a reset token. Please request a new one.</p>
      <router-link to="/forgot-password" class="text-blue-600 hover:text-blue-700 font-medium">Request new link</router-link>
    </div>

    <!-- Success -->
    <div v-else-if="success" class="rounded-xl bg-green-50 border border-green-200 px-5 py-4 text-sm text-green-800 space-y-1">
      <p class="font-semibold">Password updated!</p>
      <p>Your password has been reset. Redirecting you to login...</p>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="handleSubmit" class="space-y-5" novalidate>
      <div v-if="errors.token" class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ errors.token }}
        <br>
        <router-link to="/forgot-password" class="text-blue-600 hover:text-blue-700 font-medium mt-1 inline-block">
          Request a new reset link
        </router-link>
      </div>

      <FormField label="New Password" required :error="errors.password">
        <input
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': errors.password }"
        />
      </FormField>

      <FormField label="Confirm New Password" required :error="errors.password_confirmation">
        <input
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': errors.password_confirmation }"
        />
      </FormField>

      <AppButton type="submit" :loading="loading" :full="true">
        Reset Password
      </AppButton>

      <p class="text-center text-sm">
        <router-link to="/login" class="text-blue-600 hover:text-blue-700 font-medium">Back to login</router-link>
      </p>
    </form>

  </AuthLayout>
</template>
