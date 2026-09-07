<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useToastStore } from '@/stores/toast.store'
import { authService } from '@/services/auth.service'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'

const router = useRouter()
const auth   = useAuthStore()
const toast  = useToastStore()

const form = reactive({ email: '', password: '' })
const errors: Record<string, string[]> = reactive({})
const loading = ref(false)

async function handleSubmit() {
  // Clear previous errors
  Object.keys(errors).forEach((k) => delete errors[k])

  // Basic client-side validation
  if (!form.email) { errors.email = ['Email is required.']; }
  if (!form.password) { errors.password = ['Password is required.']; }
  if (Object.keys(errors).length) return

  loading.value = true

  try {
    const result = await authService.login({ email: form.email, password: form.password })
    auth.setAuth(result.user, result.token)
    router.push(result.user.role === 'admin' ? '/admin/dashboard' : '/dashboard')
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) Object.assign(errors, resp.errors)
    else toast.error(resp?.message ?? 'Login failed. Please try again.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="Welcome back" subtitle="Sign in to your account">
    <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>
      <FormField label="Email address" required :error="errors.email?.[0]">
        <input
          v-model="form.email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': errors.email }"
        />
      </FormField>

      <FormField label="Password" required :error="errors.password?.[0]">
        <input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          placeholder="••••••••"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-400': errors.password }"
        />
      </FormField>

      <div class="flex items-center justify-end">
        <router-link to="/forgot-password" class="text-sm text-blue-600 hover:text-blue-700">
          Forgot password?
        </router-link>
      </div>

      <AppButton type="submit" :loading="loading" :full="true" size="lg">
        Sign In
      </AppButton>

      <p class="text-center text-sm text-gray-600">
        Don't have an account?
        <router-link to="/register" class="font-medium text-blue-600 hover:text-blue-700">
          Register
        </router-link>
      </p>
    </form>
  </AuthLayout>
</template>
