<script setup lang="ts">
import { ref, reactive } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { useToastStore } from '@/stores/toast.store'
import { authService } from '@/services/auth.service'

const toast = useToastStore()
const form  = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })
const errors: Record<string, string[]> = reactive({})
const loading = ref(false)

async function handleSubmit() {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!form.current_password)                            errors.current_password         = ['Current password is required.']
  if (!form.new_password || form.new_password.length < 8) errors.new_password            = ['New password must be at least 8 characters.']
  if (form.new_password !== form.new_password_confirmation) errors.new_password_confirmation = ['Passwords do not match.']
  if (Object.keys(errors).length) return

  loading.value = true
  try {
    await authService.changePassword(form)
    toast.success('Password changed successfully.')
    form.current_password = form.new_password = form.new_password_confirmation = ''
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) Object.assign(errors, resp.errors)
    else errors.current_password = [resp?.message ?? 'Failed to change password.']
  } finally {
    loading.value = false
  }
}
</script>

<template>
    <div class="max-w-md mx-auto space-y-6">
      <div>
        <router-link to="/admin/profile" class="text-sm text-sky-600 hover:text-sky-700 font-medium">← Back to Profile</router-link>
        <h1 class="mt-2 text-xl font-semibold text-gray-900">Change Password</h1>
      </div>

      <form @submit.prevent="handleSubmit" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4" novalidate>
        <FormField label="Current Password" required :error="errors.current_password?.[0]">
          <input v-model="form.current_password" type="password"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            :class="{ 'border-red-400': errors.current_password }" />
        </FormField>
        <FormField label="New Password" required :error="errors.new_password?.[0]">
          <input v-model="form.new_password" type="password"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            :class="{ 'border-red-400': errors.new_password }" />
          <p class="mt-1 text-xs text-gray-400">Minimum 8 characters.</p>
        </FormField>
        <FormField label="Confirm New Password" required :error="errors.new_password_confirmation?.[0]">
          <input v-model="form.new_password_confirmation" type="password"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            :class="{ 'border-red-400': errors.new_password_confirmation }" />
        </FormField>
        <AppButton type="submit" :loading="loading" :full="true">Change Password</AppButton>
      </form>
    </div>
</template>
