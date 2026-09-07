import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/auth.types'
import { authService } from '@/services/auth.service'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value)

  // Role checks
  const isAdmin             = computed(() => user.value?.role === 'admin')
  const isRegistrar         = computed(() => ['college_registrar','senior_high_registrar'].includes(user.value?.role ?? ''))
  const isCollegeRegistrar  = computed(() => user.value?.role === 'college_registrar')
  const isSHSRegistrar      = computed(() => user.value?.role === 'senior_high_registrar')
  const isCashier           = computed(() => user.value?.role === 'cashier')
  const isStudent           = computed(() => ['college_student','senior_high_student'].includes(user.value?.role ?? ''))
  const isCollegeStudent    = computed(() => user.value?.role === 'college_student')
  const isSHSStudent        = computed(() => user.value?.role === 'senior_high_student')
  const isStaff             = computed(() => isAdmin.value || isRegistrar.value || isCashier.value)

  const level = computed(() => user.value?.level ?? null)

  function setAuth(userData: User, authToken: string) {
    user.value  = userData
    token.value = authToken
    localStorage.setItem('auth_token', authToken)
  }

  function clearAuth() {
    user.value  = null
    token.value = null
    localStorage.removeItem('auth_token')
  }

  async function fetchMe() {
    try {
      const result = await authService.me()
      user.value = result.user ?? result
    } catch {
      clearAuth()
    }
  }

  return {
    user, token,
    isAuthenticated,
    isAdmin, isRegistrar, isCollegeRegistrar, isSHSRegistrar, isCashier,
    isStudent, isCollegeStudent, isSHSStudent, isStaff,
    level,
    setAuth, clearAuth, fetchMe,
  }
})
