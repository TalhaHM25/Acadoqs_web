<script setup lang="ts">
import { onMounted } from 'vue'
import AppButton from '@/components/common/AppButton.vue'
import { useNotificationStore } from '@/stores/notification.store'
import { timeAgo } from '@/utils/formatters'

const store = useNotificationStore()
onMounted(() => store.fetchAll())
</script>

<template>
    <div class="max-w-2xl space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Notifications</h1>
        <AppButton v-if="store.unreadCount > 0" variant="outline" size="sm" @click="store.markAllRead">
          Mark all read
        </AppButton>
      </div>

      <div v-if="!store.notifications.length" class="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center">
        <p class="text-sm text-gray-500">No notifications yet.</p>
      </div>

      <ul v-else class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        <li
          v-for="n in store.notifications"
          :key="n.id"
          :class="['px-5 py-4 flex gap-4 hover:bg-gray-50 cursor-pointer transition', !n.is_read ? 'bg-blue-50/40' : '']"
          @click="store.markRead(n.id)"
        >
          <div class="shrink-0 mt-0.5">
            <span :class="['h-2 w-2 rounded-full inline-block mt-1', !n.is_read ? 'bg-blue-500' : 'bg-transparent']" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900">{{ n.title }}</p>
            <p class="text-sm text-gray-600 mt-0.5">{{ n.message }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ timeAgo(n.created_at) }}</p>
          </div>
        </li>
      </ul>
    </div>
</template>
