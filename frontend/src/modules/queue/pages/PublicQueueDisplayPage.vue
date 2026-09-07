<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

interface DisplayWindow {
  id: number
  name: string
  level: 'college' | 'senior_high' | 'all'
  queue_id: number | null
  queue_number: string | null
  called_at: string | null
}

interface WaitingCount {
  level: 'college' | 'senior_high' | 'all'
  waiting: number
}

const windows = ref<DisplayWindow[]>([])
const waiting = ref<WaitingCount[]>([])
const loading = ref(true)
const offline = ref(false)
const soundEnabled = ref(false)
const clock = ref(new Date())
const highlightedId = ref<number | null>(null)
const route = useRoute()
let pollTimer: ReturnType<typeof setInterval>
let clockTimer: ReturnType<typeof setInterval>
let highlightTimer: ReturnType<typeof setTimeout>
let initialized = false
const callSignatures = new Map<number, string>()

const formattedTime = computed(() => clock.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }))
const formattedDate = computed(() => clock.value.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' }))
const serviceArea = computed(() => route.params.area === 'registrar' || route.query.area === 'registrar' ? 'registrar' : 'cashier')
const areaLabel = computed(() => serviceArea.value === 'registrar' ? 'Registrar Queue' : 'Cashier Queue')
const areaTitle = computed(() => serviceArea.value === 'registrar' ? 'REGISTRAR AREA' : 'CASHIER AREA')
const collegeWaiting = computed(() => Number(waiting.value.find(item => item.level === 'college')?.waiting ?? 0))
const seniorHighWaiting = computed(() => Number(waiting.value.find(item => item.level === 'senior_high')?.waiting ?? 0))
const totalWaiting = computed(() => waiting.value.reduce((sum, item) => sum + Number(item.waiting ?? 0), 0))
const visibleWindows = computed(() => windows.value.slice(0, 3))

function levelLabel(level: DisplayWindow['level']) {
  if (serviceArea.value === 'cashier') return 'Cashier Counter'
  if (level === 'all') return 'All Students'
  return level === 'senior_high' ? 'Senior High' : 'College'
}

function accentClass(window: DisplayWindow, target: 'border' | 'bar') {
  if (serviceArea.value === 'cashier') {
    return target === 'border' ? 'border-blue-200' : 'bg-blue-600'
  }
  return window.level === 'college'
    ? (target === 'border' ? 'border-cyan-200' : 'bg-cyan-500')
    : (target === 'border' ? 'border-amber-200' : 'bg-amber-500')
}

function announce(window: DisplayWindow) {
  if (!soundEnabled.value || !window.queue_number || !('speechSynthesis' in windowGlobal())) return
  const queueNumber = window.queue_number.replace('-', ' ')
  const message = `Queue number ${queueNumber}, please proceed to ${window.name}.`
  speechSynthesis.cancel()
  const utterance = new SpeechSynthesisUtterance(message)
  utterance.rate = 0.85
  utterance.pitch = 1
  speechSynthesis.speak(utterance)
}

function windowGlobal() {
  return globalThis as typeof globalThis & { speechSynthesis?: SpeechSynthesis }
}

async function load() {
  try {
    const { data } = await api.get('/queue/display', { params: { area: serviceArea.value } })
    const nextWindows: DisplayWindow[] = data.data?.windows ?? []
    waiting.value = data.data?.waiting ?? []

    for (const serviceWindow of nextWindows) {
      const signature = `${serviceWindow.queue_id ?? ''}:${serviceWindow.called_at ?? ''}`
      const previous = callSignatures.get(serviceWindow.id)
      if (initialized && serviceWindow.queue_id && previous !== signature) {
        highlightedId.value = serviceWindow.id
        announce(serviceWindow)
        clearTimeout(highlightTimer)
        highlightTimer = setTimeout(() => { highlightedId.value = null }, 6000)
      }
      callSignatures.set(serviceWindow.id, signature)
    }

    windows.value = nextWindows
    initialized = true
    offline.value = false
  } catch {
    offline.value = true
  } finally {
    loading.value = false
  }
}

function enableSound() {
  soundEnabled.value = !soundEnabled.value
  if (!soundEnabled.value) speechSynthesis?.cancel()
}

onMounted(() => {
  load()
  pollTimer = setInterval(load, 3000)
  clockTimer = setInterval(() => { clock.value = new Date() }, 1000)
})

onUnmounted(() => {
  clearInterval(pollTimer)
  clearInterval(clockTimer)
  clearTimeout(highlightTimer)
  speechSynthesis?.cancel()
})
</script>

<template>
  <main class="min-h-screen bg-gray-100 text-gray-950">
    <header class="border-b-4 border-amber-400 bg-emerald-900 px-5 py-4 text-white sm:px-8">
      <div class="mx-auto flex max-w-[1800px] items-center justify-between gap-6">
        <div class="min-w-0">
          <p class="text-2xl font-bold sm:text-3xl">AcaDocs</p>
          <p class="mt-0.5 text-sm text-emerald-100 sm:text-base">{{ areaLabel }}</p>
        </div>
        <div class="flex items-center gap-4 sm:gap-8">
          <button
            type="button"
            class="flex h-11 w-11 items-center justify-center rounded-lg border border-white/30 text-white hover:bg-white/10"
            :title="soundEnabled ? 'Turn announcements off' : 'Turn announcements on'"
            :aria-label="soundEnabled ? 'Turn announcements off' : 'Turn announcements on'"
            @click="enableSound"
          >
            <svg v-if="soundEnabled" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5zm4.5 3.5a5 5 0 010 7M18 6a8 8 0 010 12" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5L6 9H2v6h4l5 4V5zm5 4l5 6m0-6l-5 6" />
            </svg>
          </button>
          <div class="text-right">
            <p class="text-2xl font-bold tabular-nums sm:text-3xl">{{ formattedTime }}</p>
            <p class="text-xs text-emerald-100 sm:text-sm">{{ formattedDate }}</p>
          </div>
        </div>
      </div>
    </header>

    <div class="mx-auto max-w-[1800px] px-5 py-6 sm:px-8 sm:py-8">
      <div v-if="offline" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700">
        Reconnecting to the queue...
      </div>

      <div v-if="loading" class="flex min-h-[60vh] items-center justify-center text-lg font-medium text-gray-400">
        Loading queue display...
      </div>

      <template v-else>
        <section aria-labelledby="now-serving-title">
          <div class="mb-4 flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-bold uppercase text-emerald-700">Live queue</p>
              <h1 id="now-serving-title" class="mt-1 text-2xl font-bold sm:text-3xl">{{ areaTitle }}</h1>
            </div>
            <p class="text-sm text-gray-500">Updated automatically</p>
          </div>

          <div v-if="visibleWindows.length" class="grid gap-4 sm:grid-cols-3">
            <article
              v-for="serviceWindow in visibleWindows"
              :key="serviceWindow.id"
              :class="[
                'relative flex min-h-60 flex-col overflow-hidden rounded-lg border bg-white shadow-sm transition',
                accentClass(serviceWindow, 'border'),
                highlightedId === serviceWindow.id ? 'queue-highlight ring-4 ring-amber-400' : '',
              ]"
            >
              <div :class="['h-2 w-full', accentClass(serviceWindow, 'bar')]" />
              <div class="flex flex-1 flex-col p-5 sm:p-6">
                <p class="text-base font-semibold text-gray-700 sm:text-lg">{{ serviceWindow.name }}</p>
                <p class="mt-1 text-xs font-bold uppercase text-gray-400">{{ levelLabel(serviceWindow.level) }}</p>
                <div class="flex flex-1 items-center justify-center py-6">
                  <p v-if="serviceWindow.queue_number" class="text-5xl font-black text-gray-950 sm:text-6xl lg:text-7xl">{{ serviceWindow.queue_number }}</p>
                  <p v-else class="text-xl font-semibold text-gray-300">Available</p>
                </div>
                <p :class="['text-center text-sm font-semibold', serviceWindow.queue_number ? 'text-emerald-700' : 'text-gray-400']">
                  {{ serviceWindow.queue_number ? 'Please proceed to this window' : 'Waiting for next call' }}
                </p>
              </div>
            </article>
          </div>

          <div v-else class="border-y border-gray-300 py-20 text-center text-lg text-gray-400">
            No cashier windows are open.
          </div>
        </section>

        <section v-if="serviceArea === 'cashier'" class="mt-8" aria-label="Waiting queue totals">
          <div class="flex min-h-24 items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-6 py-4">
            <div>
              <p class="text-xs font-bold uppercase text-blue-700">Cashier</p>
              <p class="mt-1 text-lg font-semibold text-gray-800">Waiting</p>
            </div>
            <p class="text-5xl font-black text-blue-700">{{ totalWaiting }}</p>
          </div>
        </section>

        <section v-else class="mt-8 grid gap-4 sm:grid-cols-2" aria-label="Waiting queue totals">
          <div class="flex min-h-24 items-center justify-between rounded-lg border border-cyan-200 bg-cyan-50 px-6 py-4">
            <div>
              <p class="text-xs font-bold uppercase text-cyan-700">College</p>
              <p class="mt-1 text-lg font-semibold text-gray-800">Waiting</p>
            </div>
            <p class="text-5xl font-black text-cyan-700">{{ collegeWaiting }}</p>
          </div>
          <div class="flex min-h-24 items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-6 py-4">
            <div>
              <p class="text-xs font-bold uppercase text-amber-700">Senior High</p>
              <p class="mt-1 text-lg font-semibold text-gray-800">Waiting</p>
            </div>
            <p class="text-5xl font-black text-amber-700">{{ seniorHighWaiting }}</p>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>

<style scoped>
@keyframes queue-pulse {
  0%, 100% { transform: scale(1); }
  20% { transform: scale(1.015); }
  40% { transform: scale(1); }
  60% { transform: scale(1.015); }
}

.queue-highlight {
  animation: queue-pulse 1.5s ease-in-out 2;
}
</style>
