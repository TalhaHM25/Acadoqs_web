<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import FormField from '@/components/forms/FormField.vue'
import api from '@/services/api'

const keys    = ref<any[]>([])
const loading = ref(true)
const alert   = ref<{ type: 'success'|'error'; message: string } | null>(null)
const showForm = ref(false)
const saving   = ref(false)
const newKey   = ref('')   // shown once after creation
const form     = ref({ name: '', type: 'kiosk' })

async function load() {
  loading.value = true
  try { const { data } = await api.get('/admin/api-keys'); keys.value = data.data ?? [] }
  finally { loading.value = false }
}

onMounted(load)

async function create() {
  saving.value = true; alert.value = null; newKey.value = ''
  try {
    const { data } = await api.post('/admin/api-keys', form.value)
    newKey.value  = data.data.key_value
    showForm.value = false
    form.value     = { name: '', type: 'kiosk' }
    await load()
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed.' }
  } finally { saving.value = false }
}

async function toggle(k: any) { await api.patch(`/admin/api-keys/${k.id}/toggle`); await load() }
async function destroy(k: any) {
  if (!confirm(`Revoke key "${k.name}"?`)) return
  await api.delete(`/admin/api-keys/${k.id}`); await load()
}

function formatDate(d: string) { return new Date(d).toLocaleDateString() }
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">API Keys</h1>
        <AppButton @click="showForm = !showForm">+ Generate Key</AppButton>
      </div>

      <p class="text-sm text-gray-500">API keys grant kiosk and mobile app access to public endpoints without requiring user login. The full key is shown only once after creation.</p>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <!-- New key reveal -->
      <div v-if="newKey" class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-2">
        <p class="text-sm font-semibold text-green-800">API Key created — copy it now, it will not be shown again:</p>
        <code class="block text-sm font-mono bg-white border border-green-200 rounded-lg p-3 break-all text-gray-800 select-all">{{ newKey }}</code>
        <AppButton size="sm" variant="outline" @click="newKey = ''">Dismiss</AppButton>
      </div>

      <!-- Create form -->
      <div v-if="showForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <p class="text-sm font-semibold text-gray-700">Generate New API Key</p>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Name / Description" required>
            <input v-model="form.name" type="text" placeholder="e.g. Main Kiosk" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>
          <FormField label="Type" required>
            <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option value="kiosk">Kiosk</option>
              <option value="mobile">Mobile App</option>
            </select>
          </FormField>
        </div>
        <div class="flex gap-3">
          <AppButton :loading="saving" @click="create">Generate Key</AppButton>
          <AppButton variant="ghost" @click="showForm = false">Cancel</AppButton>
        </div>
      </div>

      <!-- List -->
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>
      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="!keys.length" class="py-10 text-center text-sm text-gray-400">No API keys yet.</div>
        <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Key (preview)</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Last Used</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="k in keys" :key="k.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 font-medium text-gray-900">{{ k.name }}</td>
              <td class="px-5 py-3 capitalize text-gray-600">{{ k.type }}</td>
              <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ k.key_preview }}</td>
              <td class="px-5 py-3 text-gray-500 text-xs">{{ k.last_used_at ? formatDate(k.last_used_at) : 'Never' }}</td>
              <td class="px-5 py-3">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', k.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ k.is_active ? 'Active' : 'Inactive' }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex gap-2 justify-end">
                  <AppButton size="sm" variant="ghost" @click="toggle(k)">{{ k.is_active ? 'Revoke' : 'Restore' }}</AppButton>
                  <AppButton size="sm" variant="danger" @click="destroy(k)">Delete</AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
