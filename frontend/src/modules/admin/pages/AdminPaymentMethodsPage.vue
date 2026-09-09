<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import FormField from '@/components/forms/FormField.vue'
import api, { API_BASE_URL } from '@/services/api'

const backendBase = API_BASE_URL.replace(/\/api$/, '')

const methods   = ref<any[]>([])
const loading   = ref(true)
const alert     = ref<{ type: 'success'|'error'; message: string } | null>(null)
const showForm  = ref(false)
const saving    = ref(false)
const editing   = ref<any>(null)

// QR upload state per method (keyed by method id)
const qrUploading = ref<Record<number, boolean>>({})
const qrFile      = ref<Record<number, File | null>>({})
const qrPreview   = ref<Record<number, string>>({})
const expandedQr  = ref<number | null>(null)

const blank = () => ({ name: '', type: 'gcash', account_name: '', account_number: '', instructions: '', sort_order: 0 })
const form  = ref(blank())

function qrUrl(m: any): string {
  return `${backendBase}/api/payment-methods/${m.id}/qr?t=${Date.now()}`
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/payment-methods')
    methods.value = data.data ?? []
  } finally { loading.value = false }
}

onMounted(load)

function openCreate() { editing.value = null; form.value = blank(); showForm.value = true; expandedQr.value = null }
function openEdit(m: any) { editing.value = m; form.value = { ...m }; showForm.value = true; expandedQr.value = null }

async function save() {
  saving.value = true; alert.value = null
  try {
    if (editing.value) {
      await api.put(`/admin/payment-methods/${editing.value.id}`, form.value)
      alert.value = { type: 'success', message: 'Payment method updated.' }
    } else {
      await api.post('/admin/payment-methods', form.value)
      alert.value = { type: 'success', message: 'Payment method created.' }
    }
    showForm.value = false
    await load()
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed.' }
  } finally { saving.value = false }
}

async function toggle(m: any) {
  await api.patch(`/admin/payment-methods/${m.id}/toggle`)
  await load()
}

async function destroy(m: any) {
  if (!confirm(`Delete "${m.name}"?`)) return
  await api.delete(`/admin/payment-methods/${m.id}`)
  await load()
}

function onQrFileChange(e: Event, methodId: number) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  qrFile.value[methodId]    = file
  qrPreview.value[methodId] = URL.createObjectURL(file)
}

async function uploadQr(m: any) {
  const file = qrFile.value[m.id]
  if (!file) return
  qrUploading.value[m.id] = true
  alert.value = null
  try {
    const fd = new FormData()
    fd.append('qr', file)
    await api.post(`/admin/payment-methods/${m.id}/qr`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    qrFile.value[m.id]    = null
    qrPreview.value[m.id] = ''
    expandedQr.value = null
    alert.value = { type: 'success', message: `QR code updated for "${m.name}".` }
    await load()
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed to upload QR.' }
  } finally { qrUploading.value[m.id] = false }
}

function toggleQr(id: number) {
  expandedQr.value = expandedQr.value === id ? null : id
  if (expandedQr.value !== id) {
    qrFile.value[id]    = null
    qrPreview.value[id] = ''
  }
}
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Payment Methods</h1>
        <AppButton @click="openCreate">+ Add Method</AppButton>
      </div>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <!-- Create / Edit form -->
      <div v-if="showForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        <p class="text-sm font-semibold text-gray-700">{{ editing ? 'Edit Payment Method' : 'New Payment Method' }}</p>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Name" required>
            <input v-model="form.name" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>
          <FormField label="Type" required>
            <select v-model="form.type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="bank">Bank / BPI</option>
              <option value="other">Other</option>
            </select>
          </FormField>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <FormField label="Account Name">
            <input v-model="form.account_name" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>
          <FormField label="Account Number">
            <input v-model="form.account_number" type="text" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>
        </div>
        <FormField label="Instructions (shown to student)">
          <textarea v-model="form.instructions" rows="2" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
        </FormField>
        <FormField label="Sort Order">
          <input v-model.number="form.sort_order" type="number" min="0" class="block w-40 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
        </FormField>
        <div class="flex gap-3">
          <AppButton :loading="saving" @click="save">{{ editing ? 'Save Changes' : 'Create' }}</AppButton>
          <AppButton variant="ghost" @click="showForm = false">Cancel</AppButton>
        </div>
      </div>

      <!-- List -->
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>
      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="!methods.length" class="py-10 text-center text-sm text-gray-400">No payment methods yet.</div>
        <div v-else class="divide-y divide-gray-100">
          <div v-for="m in methods" :key="m.id">
            <!-- Method row -->
            <div class="flex items-center gap-4 px-5 py-4">
              <!-- QR thumbnail -->
              <div class="shrink-0 h-10 w-10 rounded-lg border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">
                <img v-if="m.qr_path" :src="qrUrl(m)" alt="QR" class="h-full w-full object-contain p-0.5"
                  @error="($event.target as HTMLImageElement).style.display='none'" />
                <svg v-else class="h-5 w-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ m.name }}</p>
                <p class="text-xs text-gray-500 capitalize">{{ m.type }}{{ m.account_number ? ` · ${m.account_number}` : '' }}</p>
              </div>

              <span :class="['shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold', m.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ m.is_active ? 'Active' : 'Inactive' }}
              </span>

              <div class="flex gap-2 shrink-0">
                <AppButton size="sm" variant="outline" @click="toggleQr(m.id)">
                  {{ expandedQr === m.id ? 'Close QR' : (m.qr_path ? 'Update QR' : 'Upload QR') }}
                </AppButton>
                <AppButton size="sm" variant="outline" @click="openEdit(m)">Edit</AppButton>
                <AppButton size="sm" variant="ghost" @click="toggle(m)">{{ m.is_active ? 'Disable' : 'Enable' }}</AppButton>
                <AppButton size="sm" variant="danger" @click="destroy(m)">Delete</AppButton>
              </div>
            </div>

            <!-- QR upload panel (inline) -->
            <div v-if="expandedQr === m.id" class="px-5 pb-5 pt-1 border-t border-gray-50 bg-slate-50">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">QR Code for {{ m.name }}</p>
              <div class="flex items-start gap-6">
                <!-- Current QR preview -->
                <div v-if="m.qr_path" class="shrink-0 text-center">
                  <p class="text-xs text-gray-400 mb-1">Current</p>
                  <div class="h-32 w-32 rounded-xl border-2 border-gray-200 bg-white overflow-hidden flex items-center justify-center">
                    <img :src="qrUrl(m)" alt="Current QR" class="h-full w-full object-contain p-1" />
                  </div>
                </div>

                <!-- Upload new -->
                <div class="flex-1 space-y-3">
                  <p class="text-xs text-gray-400">{{ m.qr_path ? 'Upload Replacement' : 'Upload QR Code' }}</p>
                  <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-xl cursor-pointer transition"
                    :class="qrPreview[m.id] ? 'border-sky-300 bg-sky-50' : 'border-gray-300 hover:border-sky-400 bg-white'">
                    <img v-if="qrPreview[m.id]" :src="qrPreview[m.id]" class="h-full w-full object-contain rounded-xl p-1" />
                    <div v-else class="text-center">
                      <svg class="mx-auto h-8 w-8 text-gray-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <p class="text-xs text-gray-500">Click to select image</p>
                      <p class="text-xs text-gray-400">PNG, JPG up to 5MB</p>
                    </div>
                    <input type="file" accept="image/*" class="hidden" @change="onQrFileChange($event, m.id)" />
                  </label>
                  <div class="flex gap-2">
                    <AppButton v-if="qrFile[m.id]" size="sm" :loading="qrUploading[m.id]" @click="uploadQr(m)">
                      Upload QR
                    </AppButton>
                    <button v-if="qrPreview[m.id]" @click="qrFile[m.id]=null; qrPreview[m.id]=''"
                      class="text-xs text-red-500 hover:text-red-600">Remove</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>
