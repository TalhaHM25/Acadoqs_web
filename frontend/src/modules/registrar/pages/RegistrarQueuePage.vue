<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import api from '@/services/api'

const queue   = ref<any[]>([])
const serving = ref<any[]>([])
const summary = ref<any[]>([])
const loading = ref(true)
const level   = ref('all')

let pollTimer: ReturnType<typeof setInterval>

async function load() {
  try {
    const { data } = await api.get('/admin/queue', { params: { level: level.value } })
    queue.value   = data.data?.queue   ?? []
    serving.value = data.data?.serving ?? []
    summary.value = data.data?.summary ?? []
  } finally { loading.value = false }
}

async function callNext(id: number) {
  await api.patch(`/admin/queue/${id}/call`)
  await load()
}
async function complete(id: number) {
  await api.patch(`/admin/queue/${id}/complete`)
  await load()
}
async function cancel(id: number) {
  await api.patch(`/admin/queue/${id}/cancel`)
  await load()
}

onMounted(() => { load(); pollTimer = setInterval(load, 15000) })
onUnmounted(() => clearInterval(pollTimer))

const statusColors: Record<string,string> = {
  waiting:   'bg-amber-100 text-amber-700',
  serving:   'bg-blue-100 text-blue-700',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-gray-100 text-gray-500',
}
</script>

<template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Queue — Today</h1>
        <div class="flex gap-2 items-center">
          <select v-model="level" @change="load()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <option value="all">All</option>
            <option value="college">College</option>
            <option value="senior_high">Senior High</option>
          </select>
          <AppButton size="sm" variant="outline" :loading="loading" @click="load">Refresh</AppButton>
        </div>
      </div>

      <!-- Summary -->
      <div class="grid grid-cols-4 gap-4">
        <div v-for="s in summary" :key="s.level" class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-xs text-gray-500 uppercase font-semibold">{{ s.level === 'college' ? 'College' : 'Senior High' }}</p>
          <div class="mt-2 grid grid-cols-2 gap-1 text-xs">
            <span class="text-amber-600">Waiting: {{ s.waiting }}</span>
            <span class="text-blue-600">Serving: {{ s.serving }}</span>
            <span class="text-green-600">Done: {{ s.completed }}</span>
            <span class="text-gray-400">Cancelled: {{ s.cancelled }}</span>
          </div>
        </div>
      </div>

      <!-- Currently serving -->
      <div v-if="serving.length" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-blue-800 mb-2">Now Serving</p>
        <div class="flex flex-wrap gap-3">
          <div v-for="q in serving" :key="q.id" class="bg-white rounded-xl border border-blue-200 px-4 py-3 flex items-center gap-4">
            <div>
              <p class="text-xl font-bold text-blue-700">{{ q.queue_number }}</p>
              <p class="text-xs text-gray-500">{{ q.level }} · {{ q.type }}</p>
            </div>
            <AppButton size="sm" @click="complete(q.id)">Done</AppButton>
          </div>
        </div>
      </div>

      <!-- Waiting queue -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-700">Waiting Queue</p>
        </div>
        <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Loading...</div>
        <div v-else-if="!queue.filter(q => q.status === 'waiting').length" class="py-10 text-center text-sm text-gray-400">No one waiting.</div>
        <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Queue #</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Level</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="q in queue.filter(q => q.status === 'waiting')" :key="q.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 font-bold text-gray-900">{{ q.queue_number }}</td>
              <td class="px-5 py-3 text-gray-600 capitalize">{{ q.level.replace('_', ' ') }}</td>
              <td class="px-5 py-3 text-gray-600 capitalize">{{ q.type.replace('_', ' ') }}</td>
              <td class="px-5 py-3">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusColors[q.status]]">{{ q.status }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex gap-2 justify-end">
                  <AppButton size="sm" @click="callNext(q.id)">Call</AppButton>
                  <AppButton size="sm" variant="ghost" @click="cancel(q.id)">Cancel</AppButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
