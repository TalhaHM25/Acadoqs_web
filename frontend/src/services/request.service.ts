import api from './api'
import type { CreateRequestPayload, DocumentRequest, DocumentCategory, DocumentType, RequestCheckout } from '@/types/request.types'

export const requestService = {
  // Document types (public)
  async getCategories(): Promise<DocumentCategory[]> {
    const { data } = await api.get('/document-categories')
    return data.data
  },

  async getDocumentType(id: number): Promise<DocumentType> {
    const { data } = await api.get(`/document-types/${id}`)
    return data.data
  },

  // Student requests
  async list(params?: { status?: string; page?: number }): Promise<{ data: DocumentRequest[]; meta: any }> {
    const { data } = await api.get('/requests', { params })
    return data
  },

  async create(payload: CreateRequestPayload, identityProof: File): Promise<RequestCheckout> {
    const formData = new FormData()
    formData.append('identity_proof', identityProof)
    formData.append('items', JSON.stringify(payload.items))
    Object.entries(payload).forEach(([key, value]) => {
      if (key === 'items') return
      if (value === undefined || value === null) return
      if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0')
        return
      }
      formData.append(key, String(value))
    })

    const { data } = await api.post('/requests', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },

  async finalizePaidCheckout(checkoutToken: string, checkoutSessionId?: string): Promise<DocumentRequest> {
    const { data } = await api.post('/requests/payment/finalize', {
      checkout_token: checkoutToken,
      checkout_session_id: checkoutSessionId,
    })
    return data.data
  },

  async get(id: number): Promise<DocumentRequest> {
    const { data } = await api.get(`/requests/${id}`)
    return data.data
  },

  async cancel(id: number): Promise<void> {
    await api.delete(`/requests/${id}`)
  },
}
