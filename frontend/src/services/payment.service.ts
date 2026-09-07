import api from './api'

export const paymentService = {
  async getInfo(requestId: number) {
    const { data } = await api.get(`/requests/${requestId}/payment`)
    return data.data
  },

  async createCheckout(requestId: number) {
    const { data } = await api.post(`/requests/${requestId}/payment`)
    return data.data
  },

  async sync(requestId: number, checkoutSessionId?: string) {
    const { data } = await api.post(`/requests/${requestId}/payment/sync`, {
      checkout_session_id: checkoutSessionId,
    })
    return data.data
  },
}
