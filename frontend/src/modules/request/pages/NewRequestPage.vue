<script setup lang="ts">
import { ref, computed, nextTick, onBeforeUnmount, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'
import FormField from '@/components/forms/FormField.vue'
import { useToastStore } from '@/stores/toast.store'
import { useAuthStore } from '@/stores/auth.store'
import { requestService } from '@/services/request.service'
import { formatCurrency } from '@/utils/formatters'
import { programOptions, gradeLevelOptions } from '@/utils/academicData'
import api from '@/services/api'
import type { DocumentCategory, DocumentType } from '@/types/request.types'

const router = useRouter()
const toast  = useToastStore()
const auth   = useAuthStore()

// ── wizard state ────────────────────────────────────────────
const step        = ref(1)
const submitting  = ref(false)
const showConfirm = ref(false)
const identityProofFile = ref<File | null>(null)
const identityProofPreview = ref('')
const identityVideo = ref<HTMLVideoElement | null>(null)
const identityCanvas = ref<HTMLCanvasElement | null>(null)
const identityCameraStream = ref<MediaStream | null>(null)
const identityCameraActive = ref(false)
const identityCameraLoading = ref(false)
const identityCameraError = ref('')

// ── data ────────────────────────────────────────────────────
const categories     = ref<DocumentCategory[]>([])
const loadingCats    = ref(true)
const paymentMethods = ref<any[]>([])
const selectedPaymentMethodId = ref<number | null>(null)
const paymentProofFile        = ref<File | null>(null)
const paymentProofPreview     = ref('')
const paymentErrors           = ref<Record<string, string[]>>({})
const lightboxSrc             = ref<string | null>(null)

// ── step 1: selected document items ─────────────────────────
interface SelectedItem {
  type:             DocumentType
  copies:           number
  is_certified_copy: boolean
  special_data:     Record<string, string>  // tor_year, graduation_date, etc.
}
const selectedItems = ref<SelectedItem[]>([])

// ── step 2: request details ──────────────────────────────────
const form = ref({
  purpose:           '',
  req_last_name:     '',
  req_first_name:    '',
  req_middle_name:   '',
  req_student_id:    '',
  req_program:       '',
  req_email:         '',
  req_phone:         '',
  grade_level:       '',
  request_semester:  '1st' as '1st' | '2nd',
  gender:            '',
  birthday:          '',
  birthplace:        '',
  has_name_change:   false,
  original_name:     '',
  is_graduate:       '' as 'yes' | 'no' | '',
  graduation_date:   '',
  last_semester:     '',
  last_school_year:  '',
  is_representative: false,
  rep_name:          '',
  rep_relationship:  '',
})
const formErrors = ref<Record<string, string[]>>({})

// ── QR lightbox ──────────────────────────────────────────────
// ── computed ─────────────────────────────────────────────────
const totalFee = computed(() =>
  selectedItems.value.reduce((sum, item) => {
    const base = Number(item.type.base_fee ?? 0)
    const cert = item.is_certified_copy ? Number(item.type.certified_copy_fee ?? 0) : 0
    return sum + (base + cert) * item.copies
  }, 0)
)

const maxProcessingDays = computed(() =>
  selectedItems.value.reduce((max, item) => {
    const days = Number(item.type.processing_days ?? 0)
    return Math.max(max, Number.isFinite(days) ? days : 0)
  }, 0)
)

const estimatedReleaseDate = computed(() => {
  if (maxProcessingDays.value <= 0) return ''
  const dt = new Date()
  dt.setHours(0, 0, 0, 0)
  dt.setDate(dt.getDate() + maxProcessingDays.value)
  return dt.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
})

function processingDayLabel(days: number | string | null | undefined): string {
  const count = Number(days ?? 0)
  const safeCount = Number.isFinite(count) && count > 0 ? count : 0
  return `${safeCount} processing ${safeCount === 1 ? 'day' : 'days'}`
}

const selectedPaymentMethod = computed(() => null as any)

function qrUrl(_method?: any): string {
  return ''
}

const levelKey     = computed(() => auth.level ?? 'college')
const programs     = computed(() => programOptions(levelKey.value))
const gradeLevels  = computed(() => gradeLevelOptions(levelKey.value))
const programLabel = computed(() => auth.isSHSStudent ? 'Strand' : 'Program / Course')
const gradeLevelLabel = computed(() => auth.isSHSStudent ? 'Grade Level' : 'Year Level')
const isCertificationOnly = computed(() =>
  selectedItems.value.length > 0 &&
  selectedItems.value.every((i) => (i.type.category_name ?? '').toLowerCase() === 'certification')
)
const yearFieldLabel = computed(() => (isCertificationOnly.value ? 'Year' : gradeLevelLabel.value))

function toUiPhone(phone: string): string {
  const clean = String(phone || '').replace(/\D/g, '')
  if (clean.startsWith('639') && clean.length === 12) return `0${clean.slice(2)}`
  return clean
}

function toBackendPhone(phone: string): string {
  const clean = String(phone || '').replace(/\D/g, '')
  if (clean.startsWith('09') && clean.length === 11) return `63${clean.slice(1)}`
  return clean
}

function lettersOnly(value: string): string {
  return value.replace(/[^A-Za-z ]/g, '').replace(/\s{2,}/g, ' ')
}

function digitsOnly(value: string, maxLength?: number): string {
  const cleaned = value.replace(/\D/g, '')
  return maxLength ? cleaned.slice(0, maxLength) : cleaned
}

function schoolYearOnly(value: string): string {
  return value.replace(/[^\d-]/g, '').slice(0, 9)
}

function hasOnlyLetters(value: string): boolean {
  return /^[A-Za-z ]+$/.test(value.trim())
}

const steps = [
  { n: 1, label: 'Select Documents' },
  { n: 2, label: 'Request Details' },
  { n: 3, label: 'Payment' },
  { n: 4, label: 'Review & Submit' },
]

// ── mount ────────────────────────────────────────────────────
onMounted(async () => {
  const [catsRes, profileRes] = await Promise.allSettled([
    requestService.getCategories(),
    api.get('/profile'),
  ])

  if (catsRes.status === 'fulfilled')    categories.value     = catsRes.value
  if (profileRes.status === 'fulfilled') {
    const p = profileRes.value.data.data
    if (p) {
      form.value.req_last_name   = p.last_name    ?? ''
      form.value.req_first_name  = p.first_name   ?? ''
      form.value.req_middle_name = p.middle_name  ?? ''
      form.value.req_student_id  = p.student_id   ?? ''
      form.value.req_email       = p.email        ?? ''
      form.value.req_phone       = toUiPhone(p.phone_number ?? '')
      form.value.gender          = p.gender       ?? ''
      form.value.birthday        = p.birthday     ?? ''
      form.value.birthplace      = p.birthplace   ?? ''
      if (p.program)    form.value.req_program  = p.program
      if (p.year_level) form.value.grade_level  = p.year_level
    }
  }

  loadingCats.value = false
})

// ── step 1 helpers ───────────────────────────────────────────
function isSelected(typeId: number) {
  return selectedItems.value.some(i => i.type.id === typeId)
}

function toggleItem(type: DocumentType) {
  const idx = selectedItems.value.findIndex(i => i.type.id === type.id)
  if (idx >= 0) {
    selectedItems.value.splice(idx, 1)
  } else {
    selectedItems.value.push({
      type,
      copies:            1,
      is_certified_copy: false,
      special_data:      {},
    })
  }
}

function goStep2() {
  if (!selectedItems.value.length) {
    toast.error('Please select at least one document.')
    return
  }
  step.value = 2
}

// ── step 2 validation ────────────────────────────────────────
function goStep3() {
  const e: Record<string, string[]> = {}
  if (!form.value.req_last_name)  e.req_last_name  = ['Last name is required.']
  else if (!hasOnlyLetters(form.value.req_last_name)) e.req_last_name = ['Last name must contain letters only.']
  if (!form.value.req_first_name) e.req_first_name = ['First name is required.']
  else if (!hasOnlyLetters(form.value.req_first_name)) e.req_first_name = ['First name must contain letters only.']
  if (form.value.req_middle_name && !hasOnlyLetters(form.value.req_middle_name))
    e.req_middle_name = ['Middle name must contain letters only.']
  if (form.value.req_student_id && !/^\d+$/.test(form.value.req_student_id))
    e.req_student_id = ['ID number must contain numbers only.']
  if (!isCertificationOnly.value && !form.value.gender)         e.gender         = ['Gender is required.']
  if (!isCertificationOnly.value && !form.value.birthday)       e.birthday       = ['Birthday is required.']
  if (!isCertificationOnly.value && !form.value.birthplace)     e.birthplace     = ['Birthplace is required.']
  if (!isCertificationOnly.value && form.value.has_name_change && !form.value.original_name)
    e.original_name = ['Original name is required.']
  else if (!isCertificationOnly.value && form.value.original_name && !hasOnlyLetters(form.value.original_name))
    e.original_name = ['Original name must contain letters only.']
  if (!form.value.req_email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.req_email))
    e.req_email = ['Valid email is required.']
  const normalizedPhone = toBackendPhone(form.value.req_phone)
  if (!normalizedPhone || !/^639\d{9}$/.test(normalizedPhone))
    e.req_phone = ['Valid phone number is required (09XXXXXXXXX).']
  if (isCertificationOnly.value && !form.value.req_program)
    e.req_program = ['Program/Course is required.']
  if (!form.value.grade_level)    e.grade_level    = ['Grade level is required.']
  if (!isCertificationOnly.value && !form.value.is_graduate)    e.is_graduate    = ['Please indicate graduation status.']
  if (!isCertificationOnly.value && form.value.is_graduate === 'yes' && !form.value.graduation_date)
    e.graduation_date = ['Graduation year is required.']
  else if (!isCertificationOnly.value && form.value.graduation_date && !/^\d{4}$/.test(form.value.graduation_date))
    e.graduation_date = ['Graduation year must be a 4-digit number.']
  if (!isCertificationOnly.value && form.value.is_graduate === 'no') {
    if (!form.value.last_semester)    e.last_semester    = ['Last semester is required.']
    if (!form.value.last_school_year) e.last_school_year = ['School year is required.']
    else if (!/^\d{4}-\d{4}$/.test(form.value.last_school_year))
      e.last_school_year = ['School year must use the format 2022-2023.']
  }
  if (!form.value.purpose)        e.purpose        = ['Purpose is required.']
  if (!isCertificationOnly.value && form.value.is_representative) {
    if (!form.value.rep_name)         e.rep_name         = ['Representative name is required.']
    else if (!hasOnlyLetters(form.value.rep_name)) e.rep_name = ['Representative name must contain letters only.']
    if (!form.value.rep_relationship) e.rep_relationship = ['Relationship is required.']
  }
  if (!identityProofFile.value) e.identity_proof = ['Identity proof photo is required.']

  // Validate special_data per item
  selectedItems.value.forEach((item, idx) => {
    const fields: string[] = item.type.required_fields ?? []
    // TOR: requires tor_year instead of date picker
    if (fields.includes('tor_year') && !item.special_data.tor_year) {
      e[`item_${idx}_tor_year`] = ['Year of graduation is required for TOR.']
    } else if (fields.includes('tor_year') && !/^\d{4}$/.test(item.special_data.tor_year ?? '')) {
      e[`item_${idx}_tor_year`] = ['Year of graduation must be a 4-digit number.']
    }
  })

  if (Object.keys(e).length) { formErrors.value = e; return }
  formErrors.value = {}
  step.value = 3
}

// ── step 3 validation ────────────────────────────────────────
function goStep4() {
  step.value = 4
}

// ── file upload ──────────────────────────────────────────────
function onIdentityProofChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  stopIdentityCamera()
  setIdentityProof(file)
}

function setIdentityProof(file: File) {
  if (identityProofPreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(identityProofPreview.value)
  }
  identityProofFile.value = file
  identityProofPreview.value = URL.createObjectURL(file)
  formErrors.value = { ...formErrors.value, identity_proof: [] }
}

function clearIdentityProof() {
  if (identityProofPreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(identityProofPreview.value)
  }
  identityProofFile.value = null
  identityProofPreview.value = ''
}

async function startIdentityCamera() {
  identityCameraError.value = ''

  if (!navigator.mediaDevices?.getUserMedia) {
    identityCameraError.value = 'Camera access is not available in this browser.'
    return
  }

  identityCameraLoading.value = true
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user' },
      audio: false,
    })

    identityCameraStream.value = stream
    identityCameraActive.value = true
    await nextTick()

    if (identityVideo.value) {
      identityVideo.value.srcObject = stream
      await identityVideo.value.play()
    }
  } catch {
    identityCameraError.value = 'Camera permission was denied or the camera is unavailable.'
    stopIdentityCamera()
  } finally {
    identityCameraLoading.value = false
  }
}

function stopIdentityCamera() {
  identityCameraStream.value?.getTracks().forEach(track => track.stop())
  identityCameraStream.value = null
  identityCameraActive.value = false
  identityCameraLoading.value = false
  if (identityVideo.value) identityVideo.value.srcObject = null
}

function captureIdentityProof() {
  const video = identityVideo.value
  const canvas = identityCanvas.value
  if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
    identityCameraError.value = 'Camera is still starting. Please try again.'
    return
  }

  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const context = canvas.getContext('2d')
  if (!context) {
    identityCameraError.value = 'Unable to capture camera image.'
    return
  }

  context.drawImage(video, 0, 0, canvas.width, canvas.height)
  canvas.toBlob((blob) => {
    if (!blob) {
      identityCameraError.value = 'Unable to save captured image.'
      return
    }

    const file = new File([blob], `identity-proof-${Date.now()}.jpg`, { type: 'image/jpeg' })
    setIdentityProof(file)
    stopIdentityCamera()
  }, 'image/jpeg', 0.9)
}

// ── submit ───────────────────────────────────────────────────
async function submit() {
  showConfirm.value = false
  submitting.value  = true
  try {
    // Build payload with items
    const payload: any = {
      ...form.value,
      req_phone: toBackendPhone(form.value.req_phone),
      level: auth.level,
      items: selectedItems.value.map(i => ({
        document_type_id:  i.type.id,
        copies:            i.copies,
        is_certified_copy: i.is_certified_copy,
        special_data:      Object.keys(i.special_data).length ? i.special_data : undefined,
      })),
    }

    if (!identityProofFile.value) {
      formErrors.value = { ...formErrors.value, identity_proof: ['Identity proof photo is required.'] }
      step.value = 2
      return
    }

    const checkout = await requestService.create(payload, identityProofFile.value)
    if (!checkout.checkout_url) throw new Error('Missing checkout URL')

    toast.success('Redirecting to secure payment.')
    window.location.href = checkout.checkout_url
  } catch (err: any) {
    const resp = err?.response?.data
    if (resp?.errors) formErrors.value = resp.errors
    else toast.error(resp?.message ?? 'Submission failed. Please try again.')
    step.value = 2
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  stopIdentityCamera()
  if (identityProofPreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(identityProofPreview.value)
  }
})
</script>

<template>
    <!-- QR lightbox -->
    <div v-if="lightboxSrc" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="lightboxSrc = null">
      <div class="relative bg-white rounded-2xl p-4 shadow-2xl max-w-sm w-full text-center">
        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-600 text-xl leading-none" @click="lightboxSrc = null">✕</button>
        <img :src="lightboxSrc" alt="QR Code" class="w-full h-auto rounded-xl" />
        <p class="text-xs text-gray-400 mt-2">Tap outside to close</p>
      </div>
    </div>

    <div class="max-w-2xl mx-auto space-y-6">

      <div>
        <router-link to="/requests" class="text-sm text-sky-600 hover:text-sky-700 font-medium">← Back to Requests</router-link>
        <h1 class="mt-2 text-xl font-semibold text-gray-900">New Document Request</h1>
      </div>

      <!-- Confirmation modal -->
      <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 space-y-4">
          <p class="text-lg font-semibold text-gray-900 text-center">Confirm Request?</p>
          <p class="text-sm text-gray-500 text-center">
            You are about to submit {{ selectedItems.length }} document(s) with a total of
            <strong class="text-blue-700">{{ formatCurrency(totalFee) }}</strong>.
          </p>
          <div class="flex gap-3 pt-1">
            <AppButton variant="outline" :full="true" @click="showConfirm = false">Cancel</AppButton>
            <AppButton :full="true" :loading="submitting" @click="submit">Confirm & Submit</AppButton>
          </div>
        </div>
      </div>

      <!-- Step indicator -->
      <div class="flex items-center">
        <template v-for="(s, i) in steps" :key="s.n">
          <div class="flex items-center gap-2">
            <div :class="['h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold transition',
              step > s.n ? 'bg-sky-500 text-white' : step === s.n ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400']">
              <svg v-if="step > s.n" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              <span v-else>{{ s.n }}</span>
            </div>
            <span :class="['text-sm font-medium hidden sm:block', step === s.n ? 'text-blue-600' : 'text-gray-400']">{{ s.label }}</span>
          </div>
          <div v-if="i < steps.length - 1" class="flex-1 h-px bg-gray-200 mx-3" />
        </template>
      </div>

      <!-- ── STEP 1: Select documents ── -->
      <div v-if="step === 1" class="space-y-5">
        <div v-if="loadingCats" class="py-10 text-center text-sm text-gray-400">Loading...</div>
        <template v-else>

          <!-- Selected summary -->
          <div v-if="selectedItems.length" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
            <p class="text-xs font-semibold text-blue-700 mb-2">Selected ({{ selectedItems.length }})</p>
            <div class="space-y-2">
              <div v-for="(item, idx) in selectedItems" :key="item.type.id" class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm text-gray-800">{{ item.type.name }}</p>
                  <p class="text-xs text-blue-600">{{ processingDayLabel(item.type.processing_days) }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                  <select v-model="item.copies" class="rounded border border-gray-300 px-2 py-1 text-xs">
                    <option v-for="n in 10" :key="n" :value="n">{{ n }} copy</option>
                  </select>
                  <select v-model="item.is_certified_copy" class="rounded border border-gray-300 px-2 py-1 text-xs">
                    <option :value="false">Original Copy</option>
                    <option :value="true">Certified</option>
                  </select>
                  <button @click="selectedItems.splice(idx, 1)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                </div>
              </div>
            </div>
            <p class="mt-2 text-xs text-blue-600 font-semibold">Estimated Total: {{ formatCurrency(totalFee) }}</p>
          </div>

          <!-- Category list -->
          <div v-for="cat in categories" :key="cat.id" class="space-y-2">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ cat.name }}</p>
            <div class="grid gap-2">
              <button v-for="type in cat.document_types" :key="type.id"
                @click="toggleItem(type)"
                :class="['w-full text-left px-4 py-3.5 rounded-xl border-2 transition bg-white',
                  isSelected(type.id) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-sky-300']">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div :class="['h-5 w-5 rounded border-2 flex items-center justify-center shrink-0',
                      isSelected(type.id) ? 'border-blue-500 bg-blue-500' : 'border-gray-300']">
                      <svg v-if="isSelected(type.id)" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-gray-900">{{ type.name }}</p>
                      <p class="text-xs font-medium text-blue-600 mt-0.5">{{ processingDayLabel(type.processing_days) }}</p>
                      <p v-if="type.description" class="text-xs text-gray-500 mt-0.5">{{ type.description }}</p>
                    </div>
                  </div>
                  <p class="text-sm font-bold text-blue-700 ml-4 shrink-0">{{ formatCurrency(type.base_fee) }}</p>
                </div>
              </button>
            </div>
          </div>

          <AppButton :disabled="!selectedItems.length" :full="true" @click="goStep2">
            Continue with {{ selectedItems.length }} document(s) →
          </AppButton>
        </template>
      </div>

      <!-- ── STEP 2: Request details ── -->
      <div v-else-if="step === 2" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">

        <!-- TOR special data per item -->
        <template v-for="(item, idx) in selectedItems" :key="item.type.id">
          <div v-if="(item.type.required_fields ?? []).includes('tor_year')" class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 space-y-3">
            <p class="text-xs font-semibold text-amber-700">{{ item.type.name }} — Additional Info</p>
            <FormField label="Year of Graduation" required :error="formErrors[`item_${idx}_tor_year`]?.[0]">
              <input v-model="item.special_data.tor_year" type="text" inputmode="numeric" placeholder="e.g. 2023" maxlength="4"
                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                @input="item.special_data.tor_year = digitsOnly(($event.target as HTMLInputElement).value, 4)" />
            </FormField>
          </div>
        </template>

        <hr class="border-gray-100" />
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Student Information</p>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Last Name" required :error="formErrors.req_last_name?.[0]">
            <input v-model="form.req_last_name" type="text"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_last_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.req_last_name = lettersOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
          <FormField label="First Name" required :error="formErrors.req_first_name?.[0]">
            <input v-model="form.req_first_name" type="text"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_first_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.req_first_name = lettersOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Middle Name" :error="formErrors.req_middle_name?.[0]">
            <input v-model="form.req_middle_name" type="text"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_middle_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.req_middle_name = lettersOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
          <FormField label="ID Number" :error="formErrors.req_student_id?.[0]">
            <input v-model="form.req_student_id" type="text" inputmode="numeric"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_student_id ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.req_student_id = digitsOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
        </div>

        <div v-if="!isCertificationOnly" class="grid grid-cols-2 gap-4">
          <FormField label="Gender" required :error="formErrors.gender?.[0]">
            <select v-model="form.gender"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.gender ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']">
              <option value="">Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </FormField>
          <FormField label="Birthday (mm/dd/yyyy)" required :error="formErrors.birthday?.[0]">
            <input v-model="form.birthday" type="date"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.birthday ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']" />
          </FormField>
        </div>

        <FormField v-if="!isCertificationOnly" label="Birthplace" required :error="formErrors.birthplace?.[0]">
          <input v-model="form.birthplace" type="text" placeholder="City / Province"
            :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.birthplace ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']" />
        </FormField>

        <!-- Name change -->
        <label v-if="!isCertificationOnly" class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="form.has_name_change" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-sky-500" />
          <span class="text-sm text-gray-700">Did you have a change or correction of name at STI College Cotabato?</span>
        </label>
        <div v-if="!isCertificationOnly && form.has_name_change" class="pl-7">
          <FormField label="Original Name" required :error="formErrors.original_name?.[0]">
            <input v-model="form.original_name" type="text" placeholder="Your original name on record"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.original_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.original_name = lettersOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField label="Email" required :error="formErrors.req_email?.[0]">
            <input v-model="form.req_email" type="email"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_email ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']" />
          </FormField>
          <FormField label="Phone Number" required :error="formErrors.req_phone?.[0]">
            <input v-model="form.req_phone" type="text" inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.req_phone ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.req_phone = ($event.target as HTMLInputElement).value.replace(/\D/g, '').slice(0, 11)" />
          </FormField>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormField :label="programLabel" :required="isCertificationOnly" :error="formErrors.req_program?.[0]">
            <select v-model="form.req_program"
              class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option value="">Select</option>
              <option v-for="p in programs" :key="p" :value="p">{{ p }}</option>
            </select>
          </FormField>
          <FormField :label="yearFieldLabel" required :error="formErrors.grade_level?.[0]">
            <select v-model="form.grade_level"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.grade_level ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']">
              <option value="">Select</option>
              <option v-for="g in gradeLevels" :key="g" :value="g">{{ g }}</option>
            </select>
          </FormField>
        </div>

        <FormField label="Request Semester" required>
          <select v-model="form.request_semester" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="1st">First Semester</option>
            <option value="2nd">Second Semester</option>
          </select>
        </FormField>

        <!-- Graduation info -->
        <div v-if="!isCertificationOnly" class="rounded-xl border border-gray-200 bg-slate-50 px-4 py-4 space-y-4">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Graduation Status</p>
          <FormField label="Did you graduate from STI College Cotabato?" required :error="formErrors.is_graduate?.[0]">
            <div class="flex gap-6 mt-1">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.is_graduate" type="radio" value="yes" class="text-blue-600 focus:ring-sky-500" /> Yes
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input v-model="form.is_graduate" type="radio" value="no" class="text-blue-600 focus:ring-sky-500" /> No
              </label>
            </div>
          </FormField>
          <FormField v-if="form.is_graduate === 'yes'" label="What year did you graduate?" required :error="formErrors.graduation_date?.[0]">
            <input v-model="form.graduation_date" type="text" inputmode="numeric" maxlength="4" placeholder="e.g. 2023"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.graduation_date ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.graduation_date = digitsOnly(($event.target as HTMLInputElement).value, 4)" />
          </FormField>
          <div v-if="form.is_graduate === 'no'" class="grid grid-cols-2 gap-4">
            <FormField label="Last Enrollment Semester" required :error="formErrors.last_semester?.[0]">
              <select v-model="form.last_semester"
                :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.last_semester ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']">
                <option value="">Select</option>
                <option value="1st">1st Semester</option>
                <option value="2nd">2nd Semester</option>
                <option value="Summer">Summer</option>
              </select>
            </FormField>
            <FormField label="School Year" required :error="formErrors.last_school_year?.[0]">
              <input v-model="form.last_school_year" type="text" inputmode="numeric" maxlength="9" placeholder="e.g. 2022-2023"
                :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.last_school_year ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
                @input="form.last_school_year = schoolYearOnly(($event.target as HTMLInputElement).value)" />
            </FormField>
          </div>
        </div>

        <FormField label="Purpose" required :error="formErrors.purpose?.[0]">
          <textarea v-model="form.purpose" rows="2" placeholder="e.g. Scholarship application, Employment..."
            :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.purpose ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']" />
        </FormField>

        <label v-if="!isCertificationOnly" class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="form.is_representative" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-sky-500" />
          <span class="text-sm text-gray-700">A representative will claim on my behalf</span>
        </label>
        <div v-if="!isCertificationOnly && form.is_representative" class="grid grid-cols-2 gap-4 pl-7">
          <FormField label="Representative Name" required :error="formErrors.rep_name?.[0]">
            <input v-model="form.rep_name" type="text"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.rep_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']"
              @input="form.rep_name = lettersOnly(($event.target as HTMLInputElement).value)" />
          </FormField>
          <FormField label="Relationship" required :error="formErrors.rep_relationship?.[0]">
            <input v-model="form.rep_relationship" type="text" placeholder="e.g. Parent, Sibling"
              :class="['block w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1', formErrors.rep_relationship ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-300 focus:border-sky-500 focus:ring-sky-500']" />
          </FormField>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 space-y-3">
          <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Identity Proof Requirement</p>
          <p class="text-sm text-amber-800">
            Capture one photo showing your name, signature, date, and yourself holding the paper.
          </p>
          <FormField label="Identity Proof Photo" required :error="formErrors.identity_proof?.[0]">
            <div class="space-y-3">
              <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                <div v-if="identityCameraActive" class="space-y-3 p-3">
                  <video ref="identityVideo" playsinline muted autoplay class="aspect-video w-full rounded-lg bg-gray-900 object-cover" />
                  <canvas ref="identityCanvas" class="hidden" />
                  <div class="flex flex-wrap gap-2">
                    <AppButton size="sm" @click="captureIdentityProof">Capture Photo</AppButton>
                    <AppButton size="sm" variant="outline" @click="stopIdentityCamera">Cancel Camera</AppButton>
                  </div>
                </div>

                <div v-else-if="identityProofPreview" class="p-3">
                  <img :src="identityProofPreview" class="max-h-72 w-full rounded-lg object-contain" />
                </div>

                <div v-else class="flex min-h-44 flex-col items-center justify-center px-4 py-8 text-center">
                  <p class="text-sm font-medium text-gray-700">No identity proof captured yet.</p>
                  <p class="mt-1 text-xs text-gray-400">Use the camera or upload an image file.</p>
                </div>
              </div>

              <p v-if="identityCameraError" class="text-xs text-red-500">{{ identityCameraError }}</p>

              <div class="flex flex-wrap gap-2">
                <AppButton
                  v-if="!identityCameraActive"
                  size="sm"
                  :loading="identityCameraLoading"
                  @click="startIdentityCamera"
                >
                  Use Camera
                </AppButton>

                <label class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                  Upload Image
                  <input type="file" accept="image/*" capture="user" class="hidden" @change="onIdentityProofChange" />
                </label>

                <AppButton
                  v-if="identityProofPreview"
                  size="sm"
                  variant="ghost"
                  @click="clearIdentityProof"
                >
                  Remove
                </AppButton>
              </div>
            </div>
          </FormField>
        </div>

        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 flex items-center justify-between">
          <span class="text-sm text-gray-600">Estimated Total</span>
          <span class="text-lg font-bold text-blue-700">{{ formatCurrency(totalFee) }}</span>
        </div>
        <div v-if="estimatedReleaseDate" class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 flex items-center justify-between">
          <span class="text-sm text-emerald-700">Estimated Release Date</span>
          <span class="text-sm font-semibold text-emerald-800">
            {{ estimatedReleaseDate }} ({{ maxProcessingDays }} {{ maxProcessingDays === 1 ? 'day' : 'days' }})
          </span>
        </div>

        <div class="flex gap-3">
          <AppButton variant="outline" @click="step = 1">← Back</AppButton>
          <AppButton :full="true" @click="goStep3">Continue →</AppButton>
        </div>
      </div>

      <!-- ── STEP 3: Payment ── -->
      <div v-else-if="step === 3" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">

        <div class="rounded-xl border-2 border-blue-100 bg-blue-50 px-5 py-4">
          <p class="text-xs text-blue-600 font-medium">Amount to Pay</p>
          <p class="text-2xl font-extrabold text-blue-800">{{ formatCurrency(totalFee) }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
          <div>
            <p class="text-sm font-semibold text-gray-900">Online Payment</p>
            <p class="mt-1 text-sm text-gray-500">
              Payment will be collected through a secure payment gateway before your request is submitted.
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">Card</span>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">GCash</span>
            <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">Maya</span>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">GrabPay</span>
          </div>
          <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
            You will be redirected to secure payment first. Your request will be submitted after payment is confirmed.
          </p>
        </div>

        <!-- Payment method selection -->
        <div v-if="false">
          <p class="text-sm font-medium text-gray-700 mb-3">Payment Option <span class="text-red-500">*</span></p>
          <p v-if="paymentErrors.payment_method" class="text-xs text-red-500 mb-2">{{ paymentErrors.payment_method?.[0] }}</p>
          <div class="space-y-2">
            <button v-for="pm in paymentMethods" :key="pm.id"
              @click="selectedPaymentMethodId = pm.id"
              :class="['w-full text-left px-4 py-3 rounded-xl border-2 transition',
                selectedPaymentMethodId === pm.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-sky-300 bg-white']">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ pm.name }}</p>
                  <p v-if="pm.account_name" class="text-xs text-gray-500">{{ pm.account_name }} · {{ pm.account_number }}</p>
                  <p v-if="pm.instructions" class="text-xs text-gray-400 mt-0.5">{{ pm.instructions }}</p>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize"
                  :class="pm.type === 'cash' ? 'bg-green-100 text-green-700' : 'bg-sky-100 text-sky-700'">
                  {{ pm.type }}
                </span>
              </div>
            </button>
          </div>
        </div>

        <!-- QR code + account details for selected non-cash method -->
        <template v-if="selectedPaymentMethod && selectedPaymentMethod.type !== 'cash'">
          <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
            <div v-if="selectedPaymentMethod.account_name || selectedPaymentMethod.account_number" class="space-y-0.5">
              <p v-if="selectedPaymentMethod.account_name" class="text-sm text-gray-700">
                <span class="text-xs text-gray-400">Account: </span><strong>{{ selectedPaymentMethod.account_name }}</strong>
              </p>
              <p v-if="selectedPaymentMethod.account_number" class="text-sm font-mono text-gray-700">{{ selectedPaymentMethod.account_number }}</p>
            </div>
            <div v-if="selectedPaymentMethod.qr_path" class="flex justify-center py-1">
              <button class="rounded-2xl border-2 border-sky-100 p-3 inline-block text-center hover:border-sky-300 transition"
                @click="lightboxSrc = qrUrl(selectedPaymentMethod)">
                <img :src="qrUrl(selectedPaymentMethod)" :alt="`${selectedPaymentMethod.name} QR`"
                  class="h-40 w-40 object-contain rounded-xl" />
                <p class="text-xs text-gray-400 mt-1">Tap to enlarge</p>
              </button>
            </div>
            <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">
              Send exactly <strong>{{ formatCurrency(totalFee) }}</strong> to complete the payment.
            </p>
          </div>
        </template>

        <div class="flex gap-3">
          <AppButton variant="outline" @click="step = 2">← Back</AppButton>
          <AppButton :full="true" @click="goStep4">Review →</AppButton>
        </div>
      </div>

      <!-- ── STEP 4: Review ── -->
      <div v-else-if="step === 4" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5">
        <p class="text-sm font-semibold text-gray-700">Review your request before submitting.</p>

        <!-- Documents -->
        <div class="space-y-2">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Documents</p>
          <div v-for="item in selectedItems" :key="item.type.id" class="flex items-center justify-between text-sm border-b border-gray-100 pb-2">
            <div>
              <span class="font-medium text-gray-900">{{ item.type.name }}</span>
              <span class="text-gray-500 text-xs ml-2">{{ item.copies }} × {{ item.is_certified_copy ? 'Certified' : 'Original Copy' }}</span>
            </div>
            <span class="font-medium text-gray-700">
              {{ formatCurrency((Number(item.type.base_fee) + (item.is_certified_copy ? Number(item.type.certified_copy_fee) : 0)) * item.copies) }}
            </span>
          </div>
        </div>

        <dl class="divide-y divide-gray-100 text-sm">
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Purpose</dt><dd class="font-medium text-gray-900 text-right max-w-xs">{{ form.purpose }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Name</dt><dd class="font-medium text-gray-900">{{ form.req_first_name }} {{ form.req_middle_name }} {{ form.req_last_name }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Email</dt><dd class="text-gray-700">{{ form.req_email }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Phone</dt><dd class="text-gray-700">{{ form.req_phone }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">{{ yearFieldLabel }}</dt><dd class="text-gray-700">{{ form.grade_level }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Semester</dt><dd class="text-gray-700">{{ form.request_semester === '1st' ? 'First' : 'Second' }} Semester</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-gray-500">Payment</dt><dd class="font-medium text-gray-900">{{ selectedPaymentMethod?.name ?? '—' }}</dd></div>
          <div class="flex justify-between py-2.5 text-base">
            <dt class="font-semibold text-gray-700">Total Fee</dt>
            <dd class="font-bold text-blue-700">{{ formatCurrency(totalFee) }}</dd>
          </div>
          <div v-if="estimatedReleaseDate" class="flex justify-between py-2.5">
            <dt class="text-emerald-700 font-semibold">Estimated Release Date</dt>
            <dd class="text-emerald-800 font-semibold text-right">
              {{ estimatedReleaseDate }} ({{ maxProcessingDays }} {{ maxProcessingDays === 1 ? 'day' : 'days' }})
            </dd>
          </div>
        </dl>

        <div class="flex gap-3">
          <AppButton variant="outline" @click="step = 3">← Edit Payment</AppButton>
          <AppButton :full="true" :loading="submitting" @click="showConfirm = true">Submit Request</AppButton>
        </div>
      </div>

    </div>
</template>
