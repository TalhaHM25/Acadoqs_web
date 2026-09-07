<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import { notificationService } from '@/services/notification.service'
import { useToastStore } from '@/stores/toast.store'
import { useAuthStore } from '@/stores/auth.store'

const toast         = useToastStore()
const auth          = useAuthStore()
const notifications = ref<any[]>([])
const loading       = ref(true)
const markingAll    = ref(false)

onMounted(async () => {
  try {
    notifications.value = await notificationService.adminList()
  } finally {
    loading.value = false
  }
})

async function markRead(id: number) {
  await notificationService.adminMarkRead(id)
  const n = notifications.value.find((n) => n.id === id)
  if (n) n.is_read = 1
}

async function markAllRead() {
  markingAll.value = true
  try {
    await notificationService.adminMarkAllRead()
    notifications.value.forEach((n) => (n.is_read = 1))
    toast.success('All notifications marked as read.')
  } catch {
    toast.error('Failed to mark notifications as read.')
  } finally {
    markingAll.value = false
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
}

const TYPE_ICON: Record<string, string> = {
  new_request:     'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  payment_pending: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
}

const TYPE_COLOR: Record<string, string> = {
  new_request:     'bg-blue-100 text-blue-600',
  payment_pending: 'bg-amber-100 text-amber-600',
}

function iconPath(type: string): string {
  return TYPE_ICON[type] ?? 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
}

function iconColor(type: string): string {
  return TYPE_COLOR[type] ?? 'bg-sky-100 text-sky-600'
}

function requestPath(requestId: number): string {
  return auth.isRegistrar ? `/registrar/requests/${requestId}` : `/admin/requests/${requestId}`
}
</script>

<template>
    <div class="max-w-3xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-gray-900">Notifications</h1>
          <p class="text-sm text-gray-500 mt-0.5">Activity alerts for new requests and payments</p>
        </div>
        <AppButton
          v-if="notifications.some((n) => !n.is_read)"
          variant="outline"
          size="sm"
          :loading="markingAll"
          @click="markAllRead"
        >
          Mark all as read
        </AppButton>
      </div>

      <div v-if="loading" class="py-20 text-center text-sm text-gray-400">Loading...</div>

      <div v-else-if="notifications.length === 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm py-16 text-center">
        <div class="mx-auto h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
          <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <p class="text-sm text-gray-500">No notifications yet.</p>
      </div>

      <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-100 overflow-hidden">
        <div
          v-for="n in notifications"
          :key="n.id"
          class="flex items-start gap-4 px-5 py-4 transition"
          :class="n.is_read ? 'bg-white' : 'bg-sky-50/50'"
        >
          <div :class="['mt-0.5 h-9 w-9 rounded-full flex items-center justify-center shrink-0', iconColor(n.type)]">
            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(n.type)" />
            </svg>
          </div>

          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900">{{ n.title }}</p>
            <p class="text-sm text-gray-600 mt-0.5">{{ n.message }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ formatDate(n.created_at) }}</p>
          </div>

          <div class="flex items-center gap-3 shrink-0">
            <span v-if="!n.is_read" class="h-2 w-2 rounded-full bg-sky-500" />
            <button
              v-if="n.data?.request_id"
              class="text-xs text-sky-600 hover:text-sky-700 font-medium whitespace-nowrap"
              @click="$router.push(requestPath(n.data.request_id))"
            >
              View
            </button>
            <button
              v-if="!n.is_read"
              class="text-xs text-gray-400 hover:text-gray-600"
              @click="markRead(n.id)"
            >
              Dismiss
            </button>
          </div>
        </div>
      </div>
    </div>
</template>
