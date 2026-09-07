import api from './api'

export const notificationService = {
  // Student
  async list() {
    const { data } = await api.get('/notifications')
    return data.data
  },

  async unreadCount(): Promise<number> {
    const { data } = await api.get('/notifications/unread-count')
    return data.data.count
  },

  async markRead(id: number) {
    await api.patch(`/notifications/${id}/read`)
  },

  async markAllRead() {
    await api.patch('/notifications/read-all')
  },

  // Admin
  async adminList() {
    const { data } = await api.get('/admin/notifications')
    return data.data
  },

  async adminUnreadCount(): Promise<number> {
    const { data } = await api.get('/admin/notifications/unread-count')
    return data.data.count
  },

  async adminMarkRead(id: number) {
    await api.patch(`/admin/notifications/${id}/read`)
  },

  async adminMarkAllRead() {
    await api.patch('/admin/notifications/read-all')
  },
}
