<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import { adminService } from '@/services/admin.service'

const loading = ref(true)
const saving  = ref(false)
const alert   = ref<{ type: 'success' | 'error'; message: string } | null>(null)

interface SettingsState extends Record<string, string> {
  school_name: string
  support_email: string
  support_phone: string
  daily_request_limit: string
}

const settings = reactive<SettingsState>({
  school_name:          '',
  support_email:        '',
  support_phone:        '',
  daily_request_limit:  '0',
})

async function load() {
  loading.value = true
  try {
    const data = await adminService.getSettings()
    if (Array.isArray(data)) {
      data.forEach((item: { key: string; value: string }) => {
        if (item.key in settings) settings[item.key] = item.value ?? ''
      })
    } else {
      Object.keys(settings).forEach((k) => {
        if (data[k] !== undefined) {
          const raw = data[k]
          settings[k] = (typeof raw === 'object' && raw !== null ? raw.value : raw) ?? ''
        }
      })
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function saveSettings() {
  saving.value = true
  alert.value  = null
  try {
    await adminService.updateSettings({ ...settings })
    alert.value = { type: 'success', message: 'Settings saved successfully.' }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to save settings.' }
  } finally {
    saving.value = false
  }
}

</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">

      <h1 class="text-xl font-semibold text-gray-900">Settings</h1>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

      <template v-else>

        <!-- General settings -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">General</p>

          <FormField label="School Name">
            <input v-model="settings.school_name" type="text"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>

          <FormField label="Support Email">
            <input v-model="settings.support_email" type="email"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>

          <FormField label="Support Phone">
            <input v-model="settings.support_phone" type="tel"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>

          <FormField label="Daily Request Limit">
            <input v-model="settings.daily_request_limit" type="number" min="0" step="1" inputmode="numeric"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
              @input="settings.daily_request_limit = settings.daily_request_limit.replace(/\D/g, '')" />
          </FormField>
        </div>

        <AppButton :loading="saving" :full="true" @click="saveSettings">Save Settings</AppButton>

      </template>
    </div>
</template>
