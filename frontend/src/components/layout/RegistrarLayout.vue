<script setup lang="ts">
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { authService } from '@/services/auth.service'
import { notificationService } from '@/services/notification.service'
import api from '@/services/api'
import { timeAgo } from '@/utils/formatters'

const router      = useRouter()
const auth        = useAuthStore()
const sidebarOpen = ref(false)
const sidebarCollapsedKey = 'acadocs:registrar-sidebar-collapsed'
const sidebarCollapsed = ref(localStorage.getItem(sidebarCollapsedKey) === 'true')
const unread      = ref(0)
const notifications = ref<any[]>([])
const notificationPopoverOpen = ref(false)
const notificationPopoverRef = ref<HTMLElement | null>(null)
const permissions = ref<string[]>(auth.user?.permissions ?? [])

const previewNotifications = computed(() => notifications.value.slice(0, 5))

onMounted(async () => {
  document.addEventListener('click', closeNotificationPopover)
  document.addEventListener('keydown', closeNotificationPopoverOnEscape)
  try { unread.value = await notificationService.adminUnreadCount() } catch {}
  try {
    // Fetch own permissions from server
    const { data } = await api.get('/auth/me')
    permissions.value = data.data?.permissions ?? []
    if (auth.user) auth.user.permissions = permissions.value
  } catch {}
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closeNotificationPopover)
  document.removeEventListener('keydown', closeNotificationPopoverOnEscape)
})
watch(sidebarCollapsed, value => localStorage.setItem(sidebarCollapsedKey, String(value)))

const levelLabel = computed(() =>
  auth.isCollegeRegistrar ? 'College Registrar' : 'SHS Registrar'
)

const navItems = computed(() => {
  const all = [
    { to: '/registrar/dashboard',       label: 'Dashboard',       module: 'requests',        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { to: '/registrar/requests',        label: 'Requests',        module: 'requests',        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { to: '/registrar/calendar',        label: 'Calendar',        module: 'calendar',        icon: 'M8 7V3m8 4V3M5 11h14M6 5h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2z' },
    { to: '/registrar/queue',        label: 'Queue',           module: 'queue',           icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
    { to: '/registrar/document-types',  label: 'Document Types',  module: 'document_types',  icon: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
    { to: '/registrar/reports',         label: 'Reports',         module: 'reports',         icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10' },
    { to: '/registrar/request-analytics', label: 'Request Analytics', module: 'analytics',   icon: 'M3 3v18h18M7 13l3-3 3 2 4-5' },
    { to: '/registrar/settings',        label: 'Settings',        module: 'settings',        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  ]
  return all.filter(item => permissions.value.includes(item.module) || item.module === 'requests')
})

const canViewNotifications = computed(() => permissions.value.includes('notifications'))

async function logout() {
  try { await authService.logout() } catch {}
  auth.clearAuth()
  router.push('/login')
}

async function toggleNotificationPopover() {
  notificationPopoverOpen.value = !notificationPopoverOpen.value
  if (notificationPopoverOpen.value) {
    notifications.value = await notificationService.adminList()
  }
}

function closeNotificationPopover(event: MouseEvent) {
  if (!notificationPopoverOpen.value) return
  if (notificationPopoverRef.value?.contains(event.target as Node)) return
  notificationPopoverOpen.value = false
}

function closeNotificationPopoverOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') notificationPopoverOpen.value = false
}

async function markNotificationRead(id: number) {
  await notificationService.adminMarkRead(id)
  const notification = notifications.value.find((n) => n.id === id)
  const wasUnread = notification && !notification.is_read
  if (notification) notification.is_read = 1
  if (wasUnread && unread.value > 0) unread.value--
}

async function markEveryNotificationRead() {
  await notificationService.adminMarkAllRead()
  notifications.value.forEach((n) => (n.is_read = 1))
  unread.value = 0
}

function viewAllNotifications() {
  notificationPopoverOpen.value = false
  router.push('/registrar/notifications')
}
</script>

<template>
  <div class="h-screen overflow-hidden bg-slate-50">

    <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="sidebarOpen = false" />

    <aside :class="[
      'fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-hidden transition-all duration-200 lg:translate-x-0',
      sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
      sidebarOpen ? 'translate-x-0' : '-translate-x-full'
    ]"
      style="background: linear-gradient(180deg, #065f46 0%, #047857 100%);">

      <div
        :class="[
          'h-16 flex items-center border-b border-white/10',
          sidebarCollapsed ? 'px-3 lg:justify-center' : 'px-5'
        ]"
      >
        <div :class="['h-8 w-8 bg-white/20 rounded-lg flex items-center justify-center shrink-0', sidebarCollapsed ? '' : 'mr-2.5']">
          <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
          </svg>
        </div>
        <div :class="['leading-tight', sidebarCollapsed ? 'lg:hidden' : '']">
          <span class="text-white font-bold text-sm">AcaDocs</span>
          <span class="block text-emerald-200 text-xs">{{ levelLabel }}</span>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <router-link v-for="item in navItems" :key="item.to" :to="item.to"
          :class="[
            'flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-emerald-100 transition hover:bg-white/10 hover:text-white',
            sidebarCollapsed ? 'lg:justify-center lg:gap-0' : 'gap-3'
          ]"
          active-class="!bg-white/20 !text-white"
          :title="sidebarCollapsed ? item.label : undefined">
          <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span :class="['truncate', sidebarCollapsed ? 'lg:hidden' : '']">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="px-3 py-4 border-t border-white/10 space-y-1">
        <div :class="['px-3 py-2 text-xs text-emerald-200 truncate', sidebarCollapsed ? 'lg:hidden' : '']">{{ auth.user?.email }}</div>
        <router-link to="/registrar/change-password"
          :class="[
            'flex items-center rounded-lg px-3 py-2 text-sm font-medium text-emerald-200 transition hover:bg-white/10 hover:text-white',
            sidebarCollapsed ? 'lg:justify-center lg:gap-0' : 'gap-3'
          ]"
          :title="sidebarCollapsed ? 'Change Password' : undefined">
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
          <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Change Password</span>
        </router-link>
        <button @click="logout"
          :class="[
            'flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-emerald-200 transition hover:bg-white/10 hover:text-red-300',
            sidebarCollapsed ? 'lg:justify-center lg:gap-0' : 'gap-3'
          ]"
          :title="sidebarCollapsed ? 'Sign Out' : undefined">
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Sign Out</span>
        </button>
      </div>
    </aside>

    <div :class="['flex h-screen min-w-0 flex-col transition-all duration-200', sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64']">
      <header class="h-16 shrink-0 bg-white border-b border-gray-200 flex items-center px-4 lg:px-6 gap-3">
        <button class="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="sidebarOpen = true">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <button
          class="hidden rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 lg:inline-flex"
          :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          @click="sidebarCollapsed = !sidebarCollapsed"
        >
          <svg class="h-5 w-5 transition-transform" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M20 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 class="text-sm font-semibold text-gray-700">{{ levelLabel }}</h1>
        <div ref="notificationPopoverRef" class="relative ml-auto flex items-center gap-2">
          <button
            v-if="canViewNotifications"
            type="button"
            class="relative inline-flex rounded-lg p-2 text-gray-500 transition hover:bg-gray-100"
            :aria-expanded="notificationPopoverOpen"
            aria-label="Open notifications"
            @click.stop="toggleNotificationPopover"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unread > 0" class="absolute top-1 right-1 h-4 min-w-4 px-0.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
              {{ unread > 99 ? '99+' : unread }}
            </span>
          </button>

          <div
            v-if="notificationPopoverOpen"
            class="absolute right-0 top-11 z-50 w-[calc(100vw-2rem)] max-w-sm overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl sm:w-96"
            @click.stop
          >
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
              <div>
                <h2 class="text-sm font-semibold text-gray-900">Notifications</h2>
                <p class="text-xs text-gray-500">{{ unread }} unread</p>
              </div>
              <button type="button" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="Close notifications" @click="notificationPopoverOpen = false">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div v-if="!previewNotifications.length" class="px-5 py-10 text-center">
              <p class="text-sm font-medium text-gray-700">No notifications yet</p>
              <p class="mt-1 text-xs text-gray-500">New updates will appear here.</p>
            </div>

            <ul v-else class="max-h-80 overflow-y-auto divide-y divide-gray-100">
              <li v-for="n in previewNotifications" :key="n.id" :class="['flex gap-3 px-4 py-3 transition hover:bg-gray-50', !n.is_read ? 'bg-emerald-50/60' : '']">
                <span :class="['mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full', !n.is_read ? 'bg-emerald-500' : 'bg-gray-200']" />
                <button type="button" class="min-w-0 flex-1 text-left" @click="markNotificationRead(n.id)">
                  <p class="truncate text-sm font-semibold text-gray-900">{{ n.title }}</p>
                  <p class="mt-0.5 line-clamp-2 text-sm text-gray-600">{{ n.message }}</p>
                  <p class="mt-1 text-xs text-gray-400">{{ timeAgo(n.created_at) }}</p>
                </button>
                <button v-if="!n.is_read" type="button" class="self-start rounded-lg p-1 text-gray-400 transition hover:bg-white hover:text-gray-600" aria-label="Mark notification as read" @click="markNotificationRead(n.id)">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </button>
              </li>
            </ul>

            <div class="grid grid-cols-2 border-t border-gray-100 bg-gray-50">
              <button type="button" class="flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100" @click="viewAllNotifications">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                </svg>
                See all
              </button>
              <button type="button" class="flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-400" :disabled="unread === 0" @click="markEveryNotificationRead">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Mark all read
              </button>
            </div>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6"><slot /></main>
    </div>
  </div>
</template>
