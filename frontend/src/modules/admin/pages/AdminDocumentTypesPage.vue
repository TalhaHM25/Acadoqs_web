<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import { adminService } from '@/services/admin.service'
import { formatCurrency } from '@/utils/formatters'
import api from '@/services/api'
import type { DocumentType } from '@/types/request.types'

const types      = ref<DocumentType[]>([])
const categories = ref<{ id: number; name: string }[]>([])
const loading    = ref(true)
const alert      = ref<{ type: 'success' | 'error'; message: string } | null>(null)

const showModal  = ref(false)
const editTarget = ref<DocumentType | null>(null)
const saving     = ref(false)
const formErrors = ref<Record<string, string[]>>({})

const form = reactive({
  category_id:        0,
  name:               '',
  description:        '',
  level:              'college' as 'college' | 'senior_high' | 'all',
  base_fee:           0,
  certified_copy_fee: 0,
  processing_days:    3,
})

async function load() {
  loading.value = true
  try {
    const [typesRes, catsRes] = await Promise.all([
      adminService.getDocumentTypes(),
      api.get('/document-categories'),
    ])
    types.value      = typesRes
    categories.value = catsRes.data.data.map((c: any) => ({ id: c.id, name: c.name }))
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editTarget.value = null
  Object.assign(form, {
    category_id: categories.value[0]?.id ?? 0,
    name: '', description: '', level: 'college', base_fee: 0, certified_copy_fee: 0, processing_days: 3,
  })
  formErrors.value = {}
  showModal.value  = true
}

function openEdit(type: DocumentType) {
  editTarget.value = type
  Object.assign(form, {
    category_id:        (type as any).category_id ?? categories.value[0]?.id ?? 0,
    name:               type.name,
    description:        type.description ?? '',
    level:              type.level ?? 'college',
    base_fee:           type.base_fee,
    certified_copy_fee: type.certified_copy_fee,
    processing_days:    type.processing_days,
  })
  formErrors.value = {}
  showModal.value  = true
}

async function save() {
  const e: Record<string, string[]> = {}
  if (!form.category_id)        e.category_id     = ['Category is required.']
  if (!form.name)               e.name            = ['Name is required.']
  if (form.base_fee < 0)        e.base_fee        = ['Fee must be 0 or more.']
  if (form.processing_days < 1) e.processing_days = ['Must be at least 1 day.']
  if (Object.keys(e).length) { formErrors.value = e; return }

  saving.value = true
  alert.value  = null
  const wasEdit = !!editTarget.value
  try {
    if (editTarget.value) {
      await adminService.updateDocumentType(editTarget.value.id, { ...form })
    } else {
      await adminService.createDocumentType({ ...form })
    }
    showModal.value = false
    await load()
    alert.value = { type: 'success', message: `Document type ${wasEdit ? 'updated' : 'created'} successfully.` }
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) formErrors.value = resp.errors
    else alert.value = { type: 'error', message: resp?.message ?? 'Failed to save.' }
  } finally {
    saving.value = false
  }
}

async function toggle(type: DocumentType) {
  try {
    await adminService.toggleDocumentType(type.id)
    await load()
  } catch {
    alert.value = { type: 'error', message: 'Failed to toggle status.' }
  }
}
</script>

<template>
    <div class="space-y-5">

      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Document Types</h1>
        <AppButton @click="openCreate">+ Add Type</AppButton>
      </div>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>

      <div v-else-if="!types.length" class="bg-white rounded-xl border border-gray-200 py-14 text-center">
        <p class="text-sm text-gray-500">No document types yet.</p>
        <AppButton class="mt-3" size="sm" @click="openCreate">Add First Type</AppButton>
      </div>

      <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Level</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Base Fee</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Certified Fee</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Days</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Active</th>
              <th class="px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="type in types" :key="type.id" class="hover:bg-slate-50 transition">
              <td class="px-5 py-3">
                <p class="font-medium text-gray-900">{{ type.name }}</p>
                <p v-if="type.description" class="text-xs text-gray-400 mt-0.5">{{ type.description }}</p>
              </td>
              <td class="px-5 py-3 text-gray-500 text-xs">{{ type.category_name }}</td>
              <td class="px-5 py-3">
                <span :class="['px-1.5 py-0.5 rounded text-xs font-semibold', type.level === 'college' ? 'bg-blue-100 text-blue-700' : type.level === 'senior_high' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600']">
                  {{ type.level === 'senior_high' ? 'SHS' : type.level === 'college' ? 'College' : 'All' }}
                </span>
              </td>
              <td class="px-5 py-3 text-right text-gray-700">{{ formatCurrency(type.base_fee) }}</td>
              <td class="px-5 py-3 text-right text-gray-700">{{ formatCurrency(type.certified_copy_fee) }}</td>
              <td class="px-5 py-3 text-center text-gray-600">{{ type.processing_days }}</td>
              <td class="px-5 py-3 text-center">
                <button
                  @click="toggle(type)"
                  :title="type.is_active ? 'Deactivate' : 'Activate'"
                  class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                  :class="type.is_active ? 'bg-sky-500' : 'bg-gray-300'"
                >
                  <span class="sr-only">Toggle</span>
                  <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition"
                    :class="type.is_active ? 'translate-x-4' : 'translate-x-1'" />
                </button>
              </td>
              <td class="px-5 py-3 text-right">
                <button @click="openEdit(type)" class="text-sky-600 hover:text-sky-700 text-xs font-medium">Edit</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
          <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">{{ editTarget ? 'Edit' : 'Add' }} Document Type</h2>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>

          <div class="space-y-4">
            <FormField label="Category" required :error="formErrors.category_id?.[0]">
              <select v-model.number="form.category_id"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                :class="{ 'border-red-400': formErrors.category_id }">
                <option :value="0" disabled>Select category</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </FormField>

            <FormField label="Name" required :error="formErrors.name?.[0]">
              <input v-model="form.name" type="text"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                :class="{ 'border-red-400': formErrors.name }" />
            </FormField>

            <FormField label="Description" :error="formErrors.description?.[0]">
              <input v-model="form.description" type="text"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
            </FormField>

            <FormField label="Student Level" required>
              <select v-model="form.level"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="college">College</option>
                <option value="senior_high">Senior High</option>
                <option value="all">All Levels</option>
              </select>
            </FormField>

            <div class="grid grid-cols-2 gap-3">
              <FormField label="Base Fee (₱)" required :error="formErrors.base_fee?.[0]">
                <input v-model.number="form.base_fee" type="number" min="0" step="0.01"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                  :class="{ 'border-red-400': formErrors.base_fee }" />
              </FormField>
              <FormField label="Certified Copy (+₱ additional)" :error="formErrors.certified_copy_fee?.[0]">
                <input v-model.number="form.certified_copy_fee" type="number" min="0" step="0.01"
                  class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
              </FormField>
            </div>

            <FormField label="Processing Days" required :error="formErrors.processing_days?.[0]">
              <input v-model.number="form.processing_days" type="number" min="1"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                :class="{ 'border-red-400': formErrors.processing_days }" />
            </FormField>
          </div>

          <div class="flex gap-3 pt-1">
            <AppButton variant="outline" @click="showModal = false">Cancel</AppButton>
            <AppButton :full="true" :loading="saving" @click="save">
              {{ editTarget ? 'Save Changes' : 'Create' }}
            </AppButton>
          </div>
        </div>
      </div>

    </div>
</template>
