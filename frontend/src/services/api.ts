import axios from 'axios'
import type { AxiosInstance } from 'axios'

export function apiBaseUrl(): string {
  const raw = String(import.meta.env.VITE_API_URL || '').trim()
  const normalized = raw
    .replace(/^['"]|['"]$/g, '')
    .replace(/^VITE_API_URL=/, '')
    .replace(/\/+$/, '')

  return normalized || 'http://localhost:8000/api'
}

export const API_BASE_URL = apiBaseUrl()

const api: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: false,
})

// Attach JWT token to every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Global response error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      // Redirect to login without importing router (avoids circular deps)
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
