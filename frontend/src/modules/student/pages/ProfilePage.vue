<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import AppAlert from '@/components/common/AppAlert.vue'
import { useAuthStore } from '@/stores/auth.store'
import { programOptions, gradeLevelOptions } from '@/utils/academicData'
import api from '@/services/api'

const auth    = useAuthStore()
const loading = ref(true)
const saving  = ref(false)
const success = ref('')
const errors: Record<string, string[]> = reactive({})

const form = reactive({
  student_id:   '', first_name: '', middle_name: '', last_name: '',
  suffix:       '', gender:      '', birthday:    '', birthplace: '',
  phone_number: '', address:    '', program:      '', year_level: '',
})

const inputClass = 'block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500'

const programs    = computed(() => programOptions(auth.level ?? 'college'))
const gradeLevels = computed(() => gradeLevelOptions(auth.level ?? 'college'))

const programLabel    = computed(() => auth.isSHSStudent ? 'Strand' : 'Program / Course')
const yearLevelLabel  = computed(() => auth.isSHSStudent ? 'Grade Level' : 'Year Level')

onMounted(async () => {
  const { data } = await api.get('/profile')
  Object.assign(form, data.data)
  loading.value = false
})

async function handleSave() {
  saving.value = true
  Object.keys(errors).forEach((k) => delete errors[k])
  success.value = ''
  try {
    await api.put('/profile', form)
    success.value = 'Profile updated successfully.'
  } catch (err: any) {
    const resp = (err as any)?.response?.data
    if (resp?.errors) Object.assign(errors, resp.errors)
  } finally {
    saving.value = false
  }
}
</script>

<template>
    <div class="max-w-2xl space-y-6">
      <h1 class="text-xl font-semibold text-gray-900">My Profile</h1>

      <div v-if="loading" class="text-sm text-gray-400">Loading...</div>

      <form v-else @submit.prevent="handleSave" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <AppAlert v-if="success" type="success" :message="success" />

        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic Information</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="Student ID" :error="errors.student_id?.[0]">
            <input v-model="form.student_id" type="text" :class="inputClass" />
          </FormField>
          <FormField :label="programLabel" :error="errors.program?.[0]">
            <select v-model="form.program" :class="inputClass">
              <option value="">Select</option>
              <option v-for="p in programs" :key="p" :value="p">{{ p }}</option>
            </select>
          </FormField>
          <FormField :label="yearLevelLabel" :error="errors.year_level?.[0]">
            <select v-model="form.year_level" :class="inputClass">
              <option value="">Select</option>
              <option v-for="g in gradeLevels" :key="g" :value="g">{{ g }}</option>
            </select>
          </FormField>
        </div>

        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider pt-1">Personal Information</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <FormField label="First Name" required :error="errors.first_name?.[0]">
            <input v-model="form.first_name" type="text" :class="inputClass" />
          </FormField>
          <FormField label="Middle Name" :error="errors.middle_name?.[0]">
            <input v-model="form.middle_name" type="text" :class="inputClass" />
          </FormField>
          <FormField label="Last Name" required :error="errors.last_name?.[0]">
            <input v-model="form.last_name" type="text" :class="inputClass" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Gender" :error="errors.gender?.[0]">
            <select v-model="form.gender" :class="inputClass">
              <option value="">Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </FormField>
          <FormField label="Birthday" :error="errors.birthday?.[0]">
            <input v-model="form.birthday" type="date" :class="inputClass" />
          </FormField>
        </div>

        <FormField label="Birthplace" :error="errors.birthplace?.[0]">
          <input v-model="form.birthplace" type="text" placeholder="City / Province" :class="inputClass" />
        </FormField>

        <FormField label="Phone Number" :error="errors.phone_number?.[0]">
          <input v-model="form.phone_number" type="text" inputmode="numeric" maxlength="12" placeholder="639XXXXXXXXX" :class="inputClass"
            @input="form.phone_number = ($event.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 12)" />
        </FormField>

        <FormField label="Address" :error="errors.address?.[0]">
          <textarea v-model="form.address" rows="2" :class="inputClass" />
        </FormField>

        <div class="flex items-center justify-between pt-2">
          <router-link to="/change-password" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Change Password</router-link>
          <AppButton type="submit" :loading="saving">Save Changes</AppButton>
        </div>
      </form>
    </div>
</template>
