import api, { API_BASE_URL } from './api'

export const adminService = {
  // Dashboard
  async getDashboard() {
    const { data } = await api.get('/admin/dashboard')
    return data.data
  },

  // Requests
  async listRequests(params?: {
    status?: string
    document_type_id?: number
    source?: string
    search?: string
    date_from?: string
    date_to?: string
    page?: number
    per_page?: number
  }) {
    const { data } = await api.get('/admin/requests', { params })
    return data
  },

  async getRequest(id: number) {
    const { data } = await api.get(`/admin/requests/${id}`)
    return data.data
  },

  async getRequestCalendar(params?: { date_from?: string; date_to?: string }) {
    const { data } = await api.get('/admin/requests/calendar', { params })
    return data.data
  },

  async updateStatus(id: number, payload: { status: string; notes?: string; rejection_reason?: string }) {
    const { data } = await api.patch(`/admin/requests/${id}/status`, payload)
    return data.data
  },

  // Payment
  async verifyPayment(requestId: number, notes?: string) {
    const { data } = await api.post(`/admin/requests/${requestId}/payment/verify`, { notes })
    return data
  },

  async rejectPayment(requestId: number, reason: string) {
    const { data } = await api.post(`/admin/requests/${requestId}/payment/reject`, { reason })
    return data
  },

  // Audit logs
  async getAuditLogs(params?: {
    request_number?: string
    actor?: string
    date_from?: string
    date_to?: string
    page?: number
    per_page?: number
  }) {
    const { data } = await api.get('/admin/audit-logs', { params })
    return data
  },

  // Reports
  async getReport(params?: {
    date_from?: string
    date_to?: string
    group_by?: string
    document_type_id?: number
    status?: string
  }) {
    const { data } = await api.get('/admin/reports', { params })
    return data.data
  },

  async getRequestAnalytics(params?: {
    date_from?: string
    date_to?: string
  }) {
    const { data } = await api.get('/admin/request-analytics', { params })
    return data.data
  },

  exportReportUrl(params?: Record<string, any>): string {
    // Strip undefined / null / empty-string values so URLSearchParams
    // doesn't turn them into the literal string "undefined" in the URL.
    const clean = Object.fromEntries(
      Object.entries(params ?? {}).filter(([, v]) => v !== undefined && v !== null && v !== '')
    )
    const token  = localStorage.getItem('auth_token') ?? ''
    const qs     = new URLSearchParams({ ...clean, token }).toString()
    return `${API_BASE_URL}/admin/reports/export?${qs}`
  },

  // Document types
  async getDocumentTypes() {
    const { data } = await api.get('/admin/document-types')
    return data.data
  },

  async createDocumentType(payload: any) {
    const { data } = await api.post('/admin/document-types', payload)
    return data.data
  },

  async updateDocumentType(id: number, payload: any) {
    const { data } = await api.put(`/admin/document-types/${id}`, payload)
    return data.data
  },

  async toggleDocumentType(id: number) {
    const { data } = await api.patch(`/admin/document-types/${id}/toggle`)
    return data
  },

  // Settings
  async getSettings() {
    const { data } = await api.get('/admin/settings')
    return data.data
  },

  async updateSettings(payload: Record<string, string>) {
    const { data } = await api.put('/admin/settings', payload)
    return data
  },

  async uploadGcashQr(file: File) {
    const formData = new FormData()
    formData.append('qr', file)
    const { data } = await api.post('/admin/settings/gcash-qr', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },
}
