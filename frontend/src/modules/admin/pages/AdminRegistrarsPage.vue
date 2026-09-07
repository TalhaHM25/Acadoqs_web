<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import FormField from '@/components/forms/FormField.vue'
import api from '@/services/api'

const ALL_MODULES = [
  { key: 'requests',       label: 'Requests' },
  { key: 'queue',          label: 'Queue' },
  { key: 'reports',        label: 'Reports' },
  { key: 'analytics',      label: 'Analytics' },
  { key: 'notifications',  label: 'Notifications' },
  { key: 'calendar',       label: 'Calendar' },
  { key: 'document_types', label: 'Document Types' },
  { key: 'settings',       label: 'Settings' },
]

const registrars = ref<any[]>([])
const loading    = ref(true)
const saving     = ref(false)
const alert      = ref<{ type:'success'|'error'; message: string } | null>(null)
const showForm   = ref(false)
const editing    = ref<any>(null)

const blank = () => ({
  email: '', password: '', role: 'college_registrar',
  permissions: ['requests'] as string[],
})
const form = ref(blank())

async function load() {
  loading.value = true
  try { const { data } = await api.get('/admin/registrars'); registrars.value = data.data ?? [] }
  finally { loading.value = false }
}

onMounted(load)

function openCreate(role: 'college_registrar' | 'senior_high_registrar' | 'cashier' = 'college_registrar') {
  editing.value = null
  form.value = role === 'cashier'
    ? { email: '', password: '', role, permissions: ['cashier'] }
    : { ...blank(), role }
  showForm.value = true
}
function openEdit(r: any) {
  editing.value = r
  form.value = { email: r.email, password: '', role: r.role, permissions: [...(r.permissions ?? [])] }
  showForm.value = true
}

function toggleModule(mod: string) {
  const idx = form.value.permissions.indexOf(mod)
  if (idx >= 0) form.value.permissions.splice(idx, 1)
  else form.value.permissions.push(mod)
}

async function save() {
  saving.value = true; alert.value = null
  try {
    if (editing.value) {
      await api.put(`/admin/registrars/${editing.value.id}`, form.value)
    } else {
      await api.post('/admin/registrars', form.value)
    }
    showForm.value = false; await load()
    alert.value = { type: 'success', message: `Registrar ${editing.value ? 'updated' : 'created'}.` }
  } catch (err: any) {
    alert.value = { type: 'error', message: err?.response?.data?.message ?? 'Failed.' }
  } finally { saving.value = false }
}

async function toggle(r: any) { await api.patch(`/admin/registrars/${r.id}/toggle`); await load() }
async function destroy(r: any) {
  if (!confirm(`Delete registrar "${r.email}"?`)) return
  await api.delete(`/admin/registrars/${r.id}`); await load()
}

function formatDate(d: string) { return new Date(d).toLocaleDateString() }
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Staff Accounts</h1>
        <div class="flex gap-2">
          <AppButton @click="openCreate()">+ Add Registrar</AppButton>
          <AppButton variant="outline" @click="openCreate('cashier')">+ Add Cashier</AppButton>
        </div>
      </div>

      <AppAlert v-if="alert" :type="alert.type" :message="alert.message" />

      <!-- Form -->
      <div v-if="showForm" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
        <p class="text-sm font-semibold text-gray-700">{{ editing ? 'Edit Staff Account' : 'New Staff Account' }}</p>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Email" required>
            <input v-model="form.email" type="email" :disabled="!!editing" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 disabled:bg-gray-50" />
          </FormField>
          <FormField :label="editing ? 'New Password (leave blank to keep)' : 'Password'" :required="!editing">
            <input v-model="form.password" type="password" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </FormField>
        </div>

        <FormField label="Role" required>
          <select v-model="form.role" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="college_registrar">College Registrar</option>
            <option value="senior_high_registrar">Senior High Registrar</option>
            <option value="cashier">Cashier</option>
          </select>
        </FormField>

        <div v-if="form.role !== 'cashier'">
          <p class="text-sm font-medium text-gray-700 mb-2">Module Access</p>
          <div class="flex flex-wrap gap-3">
            <label v-for="mod in ALL_MODULES" :key="mod.key" class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" :checked="form.permissions.includes(mod.key)" @change="toggleModule(mod.key)"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-sky-500" />
              <span class="text-sm text-gray-700">{{ mod.label }}</span>
            </label>
          </div>
        </div>

        <div class="flex gap-3">
          <AppButton :loading="saving" @click="save">{{ editing ? 'Save Changes' : 'Create Staff Account' }}</AppButton>
          <AppButton variant="ghost" @click="showForm = false">Cancel</AppButton>
        </div>
      </div>

      <!-- List -->
      <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading...</div>
      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div v-if="!registrars.length" class="py-10 text-center text-sm text-gray-400">No staff accounts yet.</div>
        <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Modules</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in registrars" :key="r.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 font-medium text-gray-900">{{ r.email }}</td>
              <td class="px-5 py-3 text-gray-600 capitalize text-xs">{{ r.role.replace(/_/g,' ') }}</td>
              <td class="px-5 py-3">
                <div class="flex flex-wrap gap-1">
                  <span v-for="p in r.permissions" :key="p" class="px-1.5 py-0.5 rounded text-xs bg-sky-100 text-sky-700 capitalize">{{ p.replace('_',' ') }}</span>
                  <span v-if="!r.permissions?.length" class="text-xs text-gray-400">None</span>
                </div>
              </td>
              <td class="px-5 py-3">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ r.is_active ? 'Active' : 'Inactive' }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex gap-2 justify-end">
                  <AppButton size="sm" variant="outline" @click="openEdit(r)">Edit</AppButton>
                  <AppButton size="sm" variant="ghost" @click="toggle(r)">{{ r.is_active ? 'Disable' : 'Enable' }}</AppButton>
                  <AppButton size="sm" variant="danger" @click="destroy(r)">Delete</AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
