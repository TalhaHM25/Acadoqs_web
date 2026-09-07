<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '@/stores/toast.store'
import { authService } from '@/services/auth.service'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { programOptions, gradeLevelOptions } from '@/utils/academicData'

const router  = useRouter()
const toast   = useToastStore()
const loading = ref(false)

function inputClass(errs?: string[]): string {
  const base = 'block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1'
  return errs?.length
    ? `${base} border-red-400 focus:border-red-400 focus:ring-red-400`
    : `${base} border-gray-300 focus:border-blue-500 focus:ring-blue-500`
}

function lettersOnly(value: string): string {
  return value.replace(/[^A-Za-z ]/g, '').replace(/\s{2,}/g, ' ')
}

function digitsOnly(value: string, maxLength?: number): string {
  const cleaned = value.replace(/\D/g, '')
  return maxLength ? cleaned.slice(0, maxLength) : cleaned
}

function hasOnlyLetters(value: string): boolean {
  return /^[A-Za-z ]+$/.test(value.trim())
}

const form = reactive({
  level:                 '' as 'college' | 'senior_high' | '',
  first_name:            '',
  middle_name:           '',
  last_name:             '',
  gender:                '',
  birthday:              '',
  birthplace:            '',
  student_id:            '',
  program:               '',
  year_level:            '',
  phone_number:          '',
  email:                 '',
  address:               '',
  password:              '',
  password_confirmation: '',
})

const programs    = computed(() => programOptions(form.level))
const gradeLevels = computed(() => gradeLevelOptions(form.level))

// Reset program/year_level when level changes
watch(() => form.level, () => { form.program = ''; form.year_level = '' })

const errors = reactive<Record<string, string[]>>({})

function clearErrors() {
  Object.keys(errors).forEach(k => delete errors[k])
}

function validate(): boolean {
  clearErrors()
  if (!form.level)                                        errors.level       = ['Please select your level.']
  if (!form.first_name)                                   errors.first_name  = ['First name is required.']
  else if (!hasOnlyLetters(form.first_name))              errors.first_name  = ['First name must contain letters only.']
  if (form.middle_name && !hasOnlyLetters(form.middle_name)) errors.middle_name = ['Middle name must contain letters only.']
  if (!form.last_name)                                    errors.last_name   = ['Last name is required.']
  else if (!hasOnlyLetters(form.last_name))               errors.last_name   = ['Last name must contain letters only.']
  if (!form.gender)                                       errors.gender      = ['Gender is required.']
  if (!form.birthday)                                     errors.birthday    = ['Birthday is required.']
  if (!form.birthplace)                                   errors.birthplace  = ['Birthplace is required.']
  if (!form.phone_number)
    errors.phone_number = ['Phone number is required.']
  else if (!/^639\d{9}$/.test(form.phone_number))
    errors.phone_number = ['Must be exactly 12 digits starting with 639.']
  if (!form.student_id)
    errors.student_id   = ['Student ID is required.']
  else if (!/^\d+$/.test(form.student_id))
    errors.student_id   = ['Student ID must contain numbers only.']
  if (!form.email)                                        errors.email       = ['Email is required.']
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.email    = ['Invalid email address.']
  if (!form.address)                                      errors.address     = ['Address is required.']
  if (!form.password)                                     errors.password    = ['Password is required.']
  else if (form.password.length < 8)                      errors.password    = ['Password must be at least 8 characters.']
  if (form.password !== form.password_confirmation)       errors.password_confirmation = ['Passwords do not match.']
  return Object.keys(errors).length === 0
}

async function handleSubmit() {
  if (!validate()) return
  loading.value = true
  try {
    const result = await authService.register({
      ...form,
      level:       form.level as 'college' | 'senior_high',
      program:     form.program     || undefined,
      year_level:  form.year_level  || undefined,
      student_id:  form.student_id  || undefined,
      middle_name: form.middle_name || undefined,
    })
    router.push({ name: 'VerifyEmailSent', query: { email: result.email } })
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) Object.assign(errors, resp.errors)
    else toast.error(resp?.message ?? 'Registration failed. Please try again.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout title="Create account" subtitle="Register to request academic documents">
    <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>

      <!-- Level selection -->
      <div>
        <p class="text-sm font-medium text-gray-700 mb-2">I am a <span class="text-red-500">*</span></p>
        <div class="grid grid-cols-2 gap-3">
          <button type="button"
            v-for="opt in [{ value: 'college', label: 'College Student' }, { value: 'senior_high', label: 'Senior High Student' }]"
            :key="opt.value"
            @click="form.level = opt.value as any"
            :class="[
              'rounded-xl border-2 px-4 py-3 text-sm font-semibold text-center transition',
              form.level === opt.value
                ? 'border-blue-500 bg-blue-50 text-blue-700'
                : 'border-gray-200 bg-white text-gray-600 hover:border-sky-300'
            ]"
          >{{ opt.label }}</button>
        </div>
        <p v-if="errors.level" class="mt-1 text-xs text-red-500">{{ errors.level[0] }}</p>
      </div>

      <hr class="border-gray-100" />
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Personal Information</p>

      <!-- Name row -->
      <div class="grid grid-cols-2 gap-3">
        <FormField label="First Name" required :error="errors.first_name?.[0]">
          <input v-model="form.first_name" type="text" :class="inputClass(errors.first_name)"
            @input="form.first_name = lettersOnly(($event.target as HTMLInputElement).value)" />
        </FormField>
        <FormField label="Last Name" required :error="errors.last_name?.[0]">
          <input v-model="form.last_name" type="text" :class="inputClass(errors.last_name)"
            @input="form.last_name = lettersOnly(($event.target as HTMLInputElement).value)" />
        </FormField>
      </div>

      <FormField label="Middle Name" :error="errors.middle_name?.[0]">
        <input v-model="form.middle_name" type="text" :class="inputClass(errors.middle_name)"
          @input="form.middle_name = lettersOnly(($event.target as HTMLInputElement).value)" />
      </FormField>

      <div class="grid grid-cols-2 gap-3">
        <FormField label="Gender" required :error="errors.gender?.[0]">
          <select v-model="form.gender" :class="inputClass(errors.gender)">
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </FormField>
        <FormField label="Birthday" required :error="errors.birthday?.[0]">
          <input v-model="form.birthday" type="date" :class="inputClass(errors.birthday)" />
        </FormField>
      </div>

      <FormField label="Birthplace" required :error="errors.birthplace?.[0]">
        <input v-model="form.birthplace" type="text" placeholder="City / Province" :class="inputClass(errors.birthplace)" />
      </FormField>

      <hr class="border-gray-100" />
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Academic Information</p>

      <div class="grid grid-cols-2 gap-3">
        <FormField label="Student ID" required :error="errors.student_id?.[0]">
          <input v-model="form.student_id" type="text" inputmode="numeric" placeholder="Numbers only" :class="inputClass(errors.student_id)"
            @input="form.student_id = digitsOnly(($event.target as HTMLInputElement).value)" />
        </FormField>
        <FormField :label="form.level === 'senior_high' ? 'Strand' : 'Program / Course'" :error="errors.program?.[0]">
          <select v-model="form.program" :class="inputClass()" :disabled="!form.level">
            <option value="">{{ form.level ? 'Select' : 'Select level first' }}</option>
            <option v-for="p in programs" :key="p" :value="p">{{ p }}</option>
          </select>
        </FormField>
      </div>

      <FormField :label="form.level === 'senior_high' ? 'Grade Level' : 'Year Level'" :error="errors.year_level?.[0]">
        <select v-model="form.year_level" :class="inputClass()" :disabled="!form.level">
          <option value="">{{ form.level ? 'Select' : 'Select level first' }}</option>
          <option v-for="g in gradeLevels" :key="g" :value="g">{{ g }}</option>
        </select>
      </FormField>

      <hr class="border-gray-100" />
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Contact Information</p>

      <FormField label="Phone Number" required :error="errors.phone_number?.[0]">
        <input v-model="form.phone_number" type="text" inputmode="numeric" maxlength="12" placeholder="639XXXXXXXXX" :class="inputClass(errors.phone_number)"
          @input="form.phone_number = digitsOnly(($event.target as HTMLInputElement).value, 12)" />
      </FormField>

      <FormField label="Email Address" required :error="errors.email?.[0]">
        <input v-model="form.email" type="email" autocomplete="email" :class="inputClass(errors.email)" />
      </FormField>

      <FormField label="Personal Address" required :error="errors.address?.[0]">
        <input v-model="form.address" type="text" placeholder="Barangay, City, Province" :class="inputClass(errors.address)" />
      </FormField>

      <hr class="border-gray-100" />
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Account Security</p>

      <FormField label="Password" required :error="errors.password?.[0]">
        <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass(errors.password)" />
      </FormField>

      <FormField label="Confirm Password" required :error="errors.password_confirmation?.[0]">
        <input v-model="form.password_confirmation" type="password" :class="inputClass(errors.password_confirmation)" />
      </FormField>

      <AppButton type="submit" :loading="loading" :full="true" size="lg">
        Create Account
      </AppButton>

      <p class="text-center text-sm text-gray-600">
        Already have an account?
        <router-link to="/login" class="font-medium text-blue-600 hover:text-blue-700">Sign in</router-link>
      </p>
    </form>
  </AuthLayout>
</template>
