<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
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
  } catch {
    alert.value = { type: 'error', message: 'Failed to load settings.' }
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function saveDailyLimit() {
  saving.value = true
  alert.value = null
  try {
    await adminService.updateSettings({
      daily_request_limit: settings.daily_request_limit || '0',
    })
    alert.value = { type: 'success', message: 'Daily request limit saved.' }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to save daily request limit.' }
  } finally {
    saving.value = false
  }
}
</script>

<template>
    <div class="max-w-xl space-y-6">
      <h1 class="text-xl font-semibold text-gray-900">Settings</h1>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">School Information</p>
        <dl class="divide-y divide-gray-100 text-sm">
          <div class="flex justify-between py-3">
            <dt class="text-gray-500">School Name</dt>
            <dd class="font-medium text-gray-900">{{ settings.school_name || '—' }}</dd>
          </div>
          <div class="flex justify-between py-3">
            <dt class="text-gray-500">Support Email</dt>
            <dd class="text-gray-700">{{ settings.support_email || '—' }}</dd>
          </div>
          <div class="flex justify-between py-3">
            <dt class="text-gray-500">Support Phone</dt>
            <dd class="text-gray-700">{{ settings.support_phone || '—' }}</dd>
          </div>
        </dl>
        <p class="text-xs text-gray-400 pt-1">Contact your administrator to update these settings.</p>
      </div>

      <div v-if="!loading" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Request Intake</p>

        <label class="block space-y-1">
          <span class="text-sm font-medium text-gray-700">Daily Request Limit</span>
          <input
            v-model="settings.daily_request_limit"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            @input="settings.daily_request_limit = settings.daily_request_limit.replace(/\D/g, '')"
          />
        </label>

        <AppButton :loading="saving" :full="true" @click="saveDailyLimit">Save Limit</AppButton>
      </div>
    </div>
</template>
