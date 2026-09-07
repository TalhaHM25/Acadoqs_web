import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: number
  type: ToastType
  message: string
}

let _id = 0

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])

  function add(message: string, type: ToastType = 'success', duration = 3500) {
    const id = ++_id
    toasts.value.push({ id, type, message })
    setTimeout(() => remove(id), duration)
  }

  function remove(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const success = (msg: string) => add(msg, 'success')
  const error   = (msg: string) => add(msg, 'error', 5000)
  const info    = (msg: string) => add(msg, 'info')
  const warning = (msg: string) => add(msg, 'warning')

  return { toasts, add, remove, success, error, info, warning }
})
