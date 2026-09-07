<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationStore } from '@/stores/notification.store'
import { authService } from '@/services/auth.service'
import { timeAgo } from '@/utils/formatters'

const router   = useRouter()
const auth     = useAuthStore()
const notifs   = useNotificationStore()
const sidebarOpen = ref(false)
const notificationPopoverOpen = ref(false)
const notificationPopoverRef = ref<HTMLElement | null>(null)
const sidebarCollapsedKey = 'acadocs:student-sidebar-collapsed'
const sidebarCollapsed = ref(localStorage.getItem(sidebarCollapsedKey) === 'true')

const previewNotifications = computed(() => notifs.notifications.slice(0, 5))

onMounted(() => {
  notifs.fetchUnreadCount()
  document.addEventListener('click', closeNotificationPopover)
  document.addEventListener('keydown', closeNotificationPopoverOnEscape)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closeNotificationPopover)
  document.removeEventListener('keydown', closeNotificationPopoverOnEscape)
})
watch(sidebarCollapsed, value => localStorage.setItem(sidebarCollapsedKey, String(value)))

const navItems = [
  { to: '/dashboard',    label: 'Dashboard',  icon: 'home' },
  { to: '/requests',     label: 'My Requests', icon: 'doc' },
  { to: '/requests/new', label: 'New Request', icon: 'plus' },
  { to: '/profile',      label: 'Profile',    icon: 'user' },
]

const iconPaths: Record<string, string> = {
  home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  plus: 'M12 4v16m8-8H4',
  user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
}

async function logout() {
  try { await authService.logout() } catch { /* ok */ }
  auth.clearAuth()
  router.push('/login')
}

async function toggleNotificationPopover() {
  notificationPopoverOpen.value = !notificationPopoverOpen.value
  if (notificationPopoverOpen.value) {
    await notifs.fetchAll()
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
  await notifs.markRead(id)
}

async function markEveryNotificationRead() {
  await notifs.markAllRead()
}

function viewAllNotifications() {
  notificationPopoverOpen.value = false
  router.push('/notifications')
}
</script>

<template>
  <div class="h-screen overflow-hidden bg-slate-50">

    <!-- Mobile overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-black/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-hidden transition-all duration-200 lg:translate-x-0',
        sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
      style="background: linear-gradient(180deg, #0369a1 0%, #1d4ed8 100%);"
    >
      <div
        :class="[
          'h-16 flex items-center border-b border-white/10',
          sidebarCollapsed ? 'px-3 lg:justify-center' : 'px-5'
        ]"
      >
        <div :class="['h-8 w-8 bg-white/20 rounded-lg flex items-center justify-center shrink-0', sidebarCollapsed ? '' : 'mr-2.5']">
          <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627
                 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0
                 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399
                 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112
                 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75
                 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007
                 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
          </svg>
        </div>
        <div :class="['leading-tight', sidebarCollapsed ? 'lg:hidden' : '']">
          <span class="text-white font-bold text-sm">AcaDocs</span>
          <span class="block text-sky-200 text-xs font-normal">
            {{ auth.isCollegeStudent ? 'College Portal' : auth.isSHSStudent ? 'Senior High Portal' : 'Student Portal' }}
          </span>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="[
            'flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-sky-100 transition hover:bg-white/10 hover:text-white',
            sidebarCollapsed ? 'lg:justify-center lg:gap-0' : 'gap-3'
          ]"
          active-class="!bg-white/20 !text-white"
          :title="sidebarCollapsed ? item.label : undefined"
        >
          <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPaths[item.icon]" />
          </svg>
          <span :class="['truncate', sidebarCollapsed ? 'lg:hidden' : '']">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="px-3 py-4 border-t border-white/10 space-y-1">
        <div :class="['px-3 py-2 text-xs text-sky-100 truncate', sidebarCollapsed ? 'lg:hidden' : '']">{{ auth.user?.email }}</div>
        <button
          @click="logout"
          :class="[
            'flex w-full items-center rounded-lg px-3 py-2 text-sm font-medium text-sky-200 transition hover:bg-white/10 hover:text-red-300',
            sidebarCollapsed ? 'lg:justify-center lg:gap-0' : 'gap-3'
          ]"
          :title="sidebarCollapsed ? 'Sign Out' : undefined"
        >
          <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <div :class="['flex h-screen min-w-0 flex-col transition-all duration-200', sidebarCollapsed ? 'lg:ml-20' : 'lg:ml-64']">
      <!-- Topbar -->
      <header class="h-16 shrink-0 bg-white border-b border-gray-200 flex items-center px-4 lg:px-6 gap-4">
        <button class="lg:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="sidebarOpen = true">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
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

        <span class="flex-1 text-sm font-medium text-gray-500 lg:hidden">Registrar Portal</span>

        <!-- Notification bell -->
        <div ref="notificationPopoverRef" class="relative ml-auto">
          <button
            type="button"
            class="relative p-1.5 text-gray-500 transition hover:text-gray-700"
            :aria-expanded="notificationPopoverOpen"
            aria-label="Open notifications"
            @click.stop="toggleNotificationPopover"
          >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span
              v-if="notifs.unreadCount > 0"
              class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-sky-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white"
            >
              {{ notifs.unreadCount > 99 ? '99+' : notifs.unreadCount }}
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
                <p class="text-xs text-gray-500">{{ notifs.unreadCount }} unread</p>
              </div>
              <button
                type="button"
                class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                aria-label="Close notifications"
                @click="notificationPopoverOpen = false"
              >
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
              <li
                v-for="n in previewNotifications"
                :key="n.id"
                :class="['flex gap-3 px-4 py-3 transition hover:bg-gray-50', !n.is_read ? 'bg-sky-50/60' : '']"
              >
                <span :class="['mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full', !n.is_read ? 'bg-sky-500' : 'bg-gray-200']" />
                <button type="button" class="min-w-0 flex-1 text-left" @click="markNotificationRead(n.id)">
                  <p class="truncate text-sm font-semibold text-gray-900">{{ n.title }}</p>
                  <p class="mt-0.5 line-clamp-2 text-sm text-gray-600">{{ n.message }}</p>
                  <p class="mt-1 text-xs text-gray-400">{{ timeAgo(n.created_at) }}</p>
                </button>
                <button
                  v-if="!n.is_read"
                  type="button"
                  class="self-start rounded-lg p-1 text-gray-400 transition hover:bg-white hover:text-gray-600"
                  aria-label="Mark notification as read"
                  @click="markNotificationRead(n.id)"
                >
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </button>
              </li>
            </ul>

            <div class="grid grid-cols-2 border-t border-gray-100 bg-gray-50">
              <button
                type="button"
                class="flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100"
                @click="viewAllNotifications"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                </svg>
                See all
              </button>
              <button
                type="button"
                class="flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-400"
                :disabled="notifs.unreadCount === 0"
                @click="markEveryNotificationRead"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Mark all read
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-auto p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
