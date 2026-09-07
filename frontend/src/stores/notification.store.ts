import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationService } from '@/services/notification.service'

export const useNotificationStore = defineStore('notification', () => {
  const unreadCount   = ref(0)
  const notifications = ref<any[]>([])

  async function fetchUnreadCount() {
    try {
      unreadCount.value = await notificationService.unreadCount()
    } catch {
      // Silent fail — not critical
    }
  }

  async function fetchAll() {
    notifications.value = await notificationService.list()
  }

  async function markRead(id: number) {
    await notificationService.markRead(id)
    const n = notifications.value.find((n) => n.id === id)
    const wasUnread = n && !n.is_read
    if (n) n.is_read = true
    if (wasUnread && unreadCount.value > 0) unreadCount.value--
  }

  async function markAllRead() {
    await notificationService.markAllRead()
    notifications.value.forEach((n) => (n.is_read = true))
    unreadCount.value = 0
  }

  return { unreadCount, notifications, fetchUnreadCount, fetchAll, markRead, markAllRead }
})
