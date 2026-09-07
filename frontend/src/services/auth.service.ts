import api from './api'
import type { LoginPayload, RegisterPayload, AuthResponse } from '@/types/auth.types'

export const authService = {
  async login(payload: LoginPayload): Promise<AuthResponse> {
    const { data } = await api.post('/auth/login', payload)
    return data.data as AuthResponse
  },

  async register(payload: RegisterPayload): Promise<{ requires_verification: true; email: string }> {
    const { data } = await api.post('/auth/register', payload)
    return data.data
  },

  async verifyEmail(token: string): Promise<AuthResponse> {
    const { data } = await api.get(`/auth/verify-email?token=${encodeURIComponent(token)}`)
    return data.data as AuthResponse
  },

  async resendVerification(email: string): Promise<void> {
    await api.post('/auth/resend-verification', { email })
  },

  async logout(): Promise<void> {
    await api.post('/auth/logout')
  },

  async me() {
    const { data } = await api.get('/auth/me')
    return data.data
  },

  async changePassword(payload: {
    current_password: string
    new_password: string
    new_password_confirmation: string
  }): Promise<void> {
    await api.post('/auth/change-password', payload)
  },

  async forgotPassword(email: string): Promise<void> {
    await api.post('/auth/forgot-password', { email })
  },

  async resetPassword(payload: {
    token: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await api.post('/auth/reset-password', payload)
  },
}
