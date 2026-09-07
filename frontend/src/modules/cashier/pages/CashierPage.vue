<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppAlert from '@/components/common/AppAlert.vue'
import AppButton from '@/components/common/AppButton.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth.store'

interface CashierWindow {
  id: number
  window_type: 'registrar' | 'cashier'
  name: string
  level: 'college' | 'senior_high' | 'all'
  sort_order: number
  is_active: number
  queue_id: number | null
  queue_number: string | null
  queue_type: string | null
  called_at: string | null
  call_count: number | null
}

interface WaitingCount {
  level: 'college' | 'senior_high' | 'all'
  waiting: number
}

type DashboardTab = 'dashboard' | 'windows'
type ServiceArea = 'registrar' | 'cashier'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const windows = ref<CashierWindow[]>([])
const waiting = ref<WaitingCount[]>([])
const selectedWindowId = ref<number | null>(null)
const loading = ref(true)
const action = ref<string | null>(null)
const alert = ref<{ type: 'success' | 'error' | 'info'; message: string } | null>(null)
const activeTab = ref<DashboardTab>(route.query.tab === 'windows' ? 'windows' : 'dashboard')
const serviceArea = ref<ServiceArea>(
  auth.isCashier ? 'cashier' : (route.query.area === 'registrar' || route.path.includes('/registrar/queue') ? 'registrar' : 'cashier')
)
const showWindowForm = ref(false)
const editingWindow = ref<CashierWindow | null>(null)
const savingWindow = ref(false)
const windowForm = ref({ name: '', level: 'all' as CashierWindow['level'], sort_order: 0, window_type: serviceArea.value })
let pollTimer: ReturnType<typeof setInterval>

const storageKey = computed(() => `acadocs:${serviceArea.value}-window:${auth.user?.id ?? 'staff'}`)
const selectedWindow = computed(() => windows.value.find(window => window.id === selectedWindowId.value) ?? null)
const activeWindows = computed(() => windows.value.filter(window => Boolean(Number(window.is_active))))
const dashboardWindows = computed(() => activeWindows.value.slice(0, 3))
const servingWindows = computed(() => activeWindows.value.filter(window => window.queue_id))
const totalWaiting = computed(() => waiting.value.reduce((sum, item) => sum + Number(item.waiting ?? 0), 0))
const collegeWaiting = computed(() => Number(waiting.value.find(item => item.level === 'college')?.waiting ?? 0))
const seniorHighWaiting = computed(() => Number(waiting.value.find(item => item.level === 'senior_high')?.waiting ?? 0))
const areaTitle = computed(() => serviceArea.value === 'registrar' ? 'Registrar Dashboard' : 'Cashier Dashboard')
const areaSubtitle = computed(() => serviceArea.value === 'registrar' ? 'Manage registrar queue and service windows' : 'Manage cashier queue and service windows')
const windowNoun = computed(() => serviceArea.value === 'registrar' ? 'Registrar' : 'Cashier')
const displayUrl = computed(() => `/queue-display/${serviceArea.value}`)
const waitingForSelected = computed(() => {
  if (!selectedWindow.value) return 0
  if (serviceArea.value === 'cashier') return totalWaiting.value
  return Number(waiting.value.find(item => item.level === selectedWindow.value?.level)?.waiting ?? 0)
})

watch(activeTab, tab => {
  router.replace({ query: { ...route.query, tab, area: serviceArea.value } })
})

watch(serviceArea, area => {
  selectedWindowId.value = null
  windowForm.value.window_type = area
  router.replace({ query: { ...route.query, tab: activeTab.value, area } })
  load(true)
})

function levelLabel(level: CashierWindow['level']) {
  if (level === 'all') return 'All Students'
  return level === 'senior_high' ? 'Senior High' : 'College'
}

function windowDetail(window: CashierWindow) {
  return serviceArea.value === 'cashier' ? 'Cashier counter' : levelLabel(window.level)
}

function errorMessage(error: any, fallback: string) {
  return error?.response?.data?.message ?? fallback
}

async function load(showLoading = false) {
  if (showLoading) loading.value = true
  try {
    const { data } = await api.get('/admin/cashier', { params: { area: serviceArea.value } })
    windows.value = data.data?.windows ?? []
    waiting.value = data.data?.waiting ?? []

    const savedId = Number(localStorage.getItem(storageKey.value))
    if (!selectedWindow.value && savedId && activeWindows.value.some(window => window.id === savedId)) {
      selectedWindowId.value = savedId
    }
    const onlyWindow = activeWindows.value.length === 1 ? activeWindows.value[0] : undefined
    if (!selectedWindow.value && onlyWindow) {
      selectWindow(onlyWindow.id)
    }
  } catch (error: any) {
    alert.value = { type: 'error', message: errorMessage(error, 'Unable to load cashier windows.') }
  } finally {
    loading.value = false
  }
}

function selectWindow(id: number) {
  selectedWindowId.value = id
  localStorage.setItem(storageKey.value, String(id))
  activeTab.value = 'windows'
  alert.value = null
}

async function runAction(kind: 'call-next' | 'recall' | 'complete') {
  if (!selectedWindow.value) return
  action.value = kind
  alert.value = null

  try {
    const { data } = await api.patch(`/admin/cashier/windows/${selectedWindow.value.id}/${kind}?area=${serviceArea.value}`)
    const messages = {
      'call-next': `${data.data?.queue_number ?? 'Next number'} called.`,
      recall: `${data.data?.queue_number ?? 'Queue number'} called again.`,
      complete: `${data.data?.queue_number ?? 'Queue number'} completed.`,
    }
    alert.value = { type: 'success', message: messages[kind] }
    await load()
  } catch (error: any) {
    alert.value = { type: 'error', message: errorMessage(error, 'Cashier action failed.') }
  } finally {
    action.value = null
  }
}

function openCreateWindow() {
  editingWindow.value = null
  windowForm.value = {
    name: '',
    level: serviceArea.value === 'cashier' ? 'all' : 'college',
    sort_order: windows.value.length + 1,
    window_type: serviceArea.value,
  }
  showWindowForm.value = true
}

function openEditWindow(window: CashierWindow) {
  editingWindow.value = window
  windowForm.value = {
    name: window.name,
    level: serviceArea.value === 'cashier' ? 'all' : window.level,
    sort_order: Number(window.sort_order),
    window_type: serviceArea.value,
  }
  showWindowForm.value = true
}

async function saveWindow() {
  savingWindow.value = true
  alert.value = null
  try {
    const payload = {
      ...windowForm.value,
      window_type: serviceArea.value,
      level: serviceArea.value === 'cashier' ? 'all' : windowForm.value.level,
    }
    if (editingWindow.value) await api.put(`/admin/cashier/windows/${editingWindow.value.id}`, payload)
    else await api.post('/admin/cashier/windows', payload)

    showWindowForm.value = false
    alert.value = { type: 'success', message: `${windowNoun.value} window ${editingWindow.value ? 'updated' : 'created'}.` }
    await load()
  } catch (error: any) {
    alert.value = { type: 'error', message: errorMessage(error, 'Unable to save cashier window.') }
  } finally {
    savingWindow.value = false
  }
}

async function toggleWindow(window: CashierWindow) {
  try {
    await api.patch(`/admin/cashier/windows/${window.id}/toggle`)
    if (window.id === selectedWindowId.value) selectedWindowId.value = null
    await load()
  } catch (error: any) {
    alert.value = { type: 'error', message: errorMessage(error, 'Unable to update cashier window.') }
  }
}

onMounted(() => {
  load(true)
  pollTimer = setInterval(() => load(), 5000)
})
onUnmounted(() => clearInterval(pollTimer))
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">{{ areaTitle }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ selectedWindow ? selectedWindow.name : areaSubtitle }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a :href="displayUrl" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Open Queue Display
        </a>
        <AppButton v-if="auth.isAdmin" size="sm" @click="openCreateWindow">Add {{ windowNoun }} Window</AppButton>
        <AppButton size="sm" variant="outline" :loading="loading" @click="load(true)">Refresh</AppButton>
      </div>
    </div>

    <div v-if="auth.isAdmin" class="border-b border-gray-200">
      <nav class="-mb-px flex gap-6">
        <button
          type="button"
          :class="[
            'border-b-2 px-1 py-3 text-sm font-semibold transition',
            serviceArea === 'registrar' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="serviceArea = 'registrar'"
        >
          Registrar Area
        </button>
        <button
          type="button"
          :class="[
            'border-b-2 px-1 py-3 text-sm font-semibold transition',
            serviceArea === 'cashier' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="serviceArea = 'cashier'"
        >
          Cashier Area
        </button>
      </nav>
    </div>

    <AppAlert v-if="alert" :type="alert.type" :message="alert.message" dismissible @dismiss="alert = null" />

    <div class="border-b border-gray-200">
      <nav class="-mb-px flex gap-6">
        <button
          type="button"
          :class="[
            'border-b-2 px-1 py-3 text-sm font-semibold transition',
            activeTab === 'dashboard' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="activeTab = 'dashboard'"
        >
          Dashboard
        </button>
        <button
          type="button"
          :class="[
            'border-b-2 px-1 py-3 text-sm font-semibold transition',
            activeTab === 'windows' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="activeTab = 'windows'"
        >
          Windows
        </button>
      </nav>
    </div>

    <div v-if="loading" class="py-16 text-center text-sm text-gray-400">Loading {{ serviceArea }} dashboard...</div>

    <template v-else>
      <template v-if="activeTab === 'dashboard'">
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Total in Queue</p>
            <p class="mt-2 text-3xl font-bold text-gray-950">{{ totalWaiting }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ serviceArea === 'cashier' ? 'Cashier line' : 'All waiting' }}</p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Now Serving</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ servingWindows.length }}</p>
            <p class="mt-1 text-xs text-gray-400">Active windows</p>
          </div>
          <div v-if="serviceArea === 'registrar'" class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">College Waiting</p>
            <p class="mt-2 text-3xl font-bold text-cyan-700">{{ collegeWaiting }}</p>
            <p class="mt-1 text-xs text-gray-400">Queue numbers</p>
          </div>
          <div v-if="serviceArea === 'registrar'" class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">SHS Waiting</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">{{ seniorHighWaiting }}</p>
            <p class="mt-1 text-xs text-gray-400">Queue numbers</p>
          </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
          <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-900">Your Window Status</h2>
              <button type="button" class="text-sm font-medium text-blue-700 hover:text-blue-900" @click="activeTab = 'windows'">Manage</button>
            </div>
            <div v-if="selectedWindow" class="grid gap-4 md:grid-cols-[240px_minmax(0,1fr)]">
              <div class="rounded-lg bg-blue-50 p-5">
                <p class="text-xs font-semibold uppercase text-blue-700">Now Serving</p>
                <p class="mt-3 text-5xl font-black tracking-normal text-blue-700">{{ selectedWindow.queue_number ?? '---' }}</p>
                <p class="mt-2 text-sm text-gray-500">{{ selectedWindow.name }}<span v-if="serviceArea === 'registrar'"> · {{ levelLabel(selectedWindow.level) }}</span></p>
              </div>
              <div class="grid content-center gap-3 sm:grid-cols-3">
                <AppButton :loading="action === 'call-next'" :disabled="Boolean(action) || waitingForSelected === 0 || Boolean(selectedWindow.queue_id)" @click="runAction('call-next')">Next Queue</AppButton>
                <AppButton variant="outline" :loading="action === 'recall'" :disabled="Boolean(action) || !selectedWindow.queue_id" @click="runAction('recall')">Recall</AppButton>
                <AppButton variant="outline" :loading="action === 'complete'" :disabled="Boolean(action) || !selectedWindow.queue_id" @click="runAction('complete')">Complete</AppButton>
              </div>
            </div>
            <div v-else class="border-y border-gray-200 py-10 text-center text-sm text-gray-500">
              Select a service window in the Windows tab.
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-white p-5">
            <h2 class="mb-4 text-sm font-semibold text-gray-900">TV Screen Preview</h2>
            <div class="rounded-lg border-4 border-gray-900 bg-yellow-400 p-5 text-center">
              <p class="text-xl font-black text-gray-950">{{ serviceArea === 'registrar' ? 'REGISTRAR AREA' : 'CASHIER AREA' }}</p>
              <div class="mt-6 grid grid-cols-3 gap-3">
                <div v-for="(window, index) in dashboardWindows" :key="window.id">
                  <p class="mb-2 text-xs font-bold uppercase text-gray-950">{{ windowNoun }} {{ index + 1 }}</p>
                  <div class="flex h-20 items-center justify-center bg-gray-950 px-2">
                    <span class="text-3xl font-black tracking-normal text-red-500">{{ window.queue_number?.replace(/^[A-Z]+-/, '') ?? '---' }}</span>
                  </div>
                </div>
              </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">Dashboard preview uses the first three active {{ serviceArea }} windows.</p>
          </div>
        </section>
      </template>

      <template v-else>
        <section>
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Service Windows</h2>
            <span class="text-xs text-gray-500">{{ activeWindows.length }} active</span>
          </div>

          <div v-if="!windows.length" class="border-y border-gray-200 py-12 text-center text-sm text-gray-400">
            No {{ serviceArea }} windows have been configured.
          </div>

          <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
              v-for="window in windows"
              :key="window.id"
              :class="[
                'min-h-32 rounded-lg border bg-white transition',
                selectedWindowId === window.id ? 'border-sky-500 bg-sky-50' : 'border-gray-200 bg-white hover:border-sky-300',
                !Number(window.is_active) ? 'opacity-55' : '',
              ]"
            >
              <button
                type="button"
                :disabled="!Number(window.is_active)"
                class="block w-full p-4 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-500 disabled:cursor-not-allowed"
                @click="selectWindow(window.id)"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ window.name }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ windowDetail(window) }}</p>
                  </div>
                  <span :class="['h-2.5 w-2.5 shrink-0 rounded-full', Number(window.is_active) ? (window.queue_id ? 'bg-blue-500' : 'bg-green-500') : 'bg-gray-300']" />
                </div>
                <p v-if="window.queue_number" class="mt-4 text-2xl font-bold text-blue-700">{{ window.queue_number }}</p>
                <p v-else class="mt-4 text-sm font-medium text-gray-400">Available</p>
              </button>
              <div v-if="auth.isAdmin" class="mx-4 flex gap-3 border-t border-gray-100 py-3">
                <button type="button" class="text-xs font-medium text-sky-700 hover:text-sky-900" @click="openEditWindow(window)">Edit</button>
                <button type="button" class="text-xs font-medium text-gray-500 hover:text-gray-800" @click="toggleWindow(window)">{{ Number(window.is_active) ? 'Disable' : 'Enable' }}</button>
              </div>
            </article>
          </div>
        </section>

        <section v-if="selectedWindow" class="border-y border-gray-200 bg-white px-4 py-6 sm:px-6">
          <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-center">
            <div>
              <div class="flex items-center gap-2">
                <span class="rounded bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">{{ serviceArea === 'cashier' ? 'All Students' : levelLabel(selectedWindow.level) }}</span>
                <span class="text-sm font-medium text-gray-600">{{ selectedWindow.name }}</span>
              </div>
              <p class="mt-5 text-xs font-semibold uppercase text-gray-400">Now serving</p>
              <p :class="['mt-1 font-bold tracking-normal', selectedWindow.queue_number ? 'text-5xl text-gray-950 sm:text-6xl' : 'text-3xl text-gray-300']">
                {{ selectedWindow.queue_number ?? 'No active number' }}
              </p>
              <p v-if="selectedWindow.queue_type" class="mt-2 text-sm capitalize text-gray-500">{{ selectedWindow.queue_type.replace('_', ' ') }}</p>
            </div>

            <div>
              <div class="mb-4 flex items-end justify-between border-b border-gray-100 pb-3">
                <span class="text-sm text-gray-500">Waiting</span>
                <strong class="text-3xl text-amber-600">{{ waitingForSelected }}</strong>
              </div>
              <div v-if="selectedWindow.queue_id" class="grid grid-cols-2 gap-2">
                <AppButton variant="outline" :loading="action === 'recall'" :disabled="Boolean(action)" @click="runAction('recall')">Call Again</AppButton>
                <AppButton :loading="action === 'complete'" :disabled="Boolean(action)" @click="runAction('complete')">Mark Done</AppButton>
              </div>
              <AppButton v-else full size="lg" :loading="action === 'call-next'" :disabled="Boolean(action) || waitingForSelected === 0" @click="runAction('call-next')">
                Call Next Number
              </AppButton>
            </div>
          </div>
        </section>

        <div v-else-if="activeWindows.length" class="border-y border-gray-200 py-12 text-center text-sm text-gray-500">
          Select a service window to begin.
        </div>
      </template>
    </template>

    <div v-if="showWindowForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showWindowForm = false">
      <form class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @submit.prevent="saveWindow">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-base font-semibold text-gray-900">{{ editingWindow ? `Edit ${windowNoun} Window` : `New ${windowNoun} Window` }}</h2>
          <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close" @click="showWindowForm = false">&times;</button>
        </div>
        <div class="space-y-4">
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Window name</span>
            <input v-model.trim="windowForm.name" required maxlength="100" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </label>
          <label v-if="serviceArea === 'registrar'" class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Level</span>
            <select v-model="windowForm.level" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
              <option value="college">College</option>
              <option value="senior_high">Senior High</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Display order</span>
            <input v-model.number="windowForm.sort_order" type="number" min="0" max="32767" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" />
          </label>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <AppButton variant="ghost" @click="showWindowForm = false">Cancel</AppButton>
          <AppButton type="submit" :loading="savingWindow">Save Window</AppButton>
        </div>
      </form>
    </div>
  </div>
</template>
