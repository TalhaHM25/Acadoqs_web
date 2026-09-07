import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    // ── Public / Auth ────────────────────────────────────────
    { path: '/login',              name: 'Login',           component: () => import('@/modules/auth/pages/LoginPage.vue'),           meta: { requiresGuest: true } },
    { path: '/register',           name: 'Register',        component: () => import('@/modules/auth/pages/RegisterPage.vue'),        meta: { requiresGuest: true } },
    { path: '/forgot-password',    name: 'ForgotPassword',  component: () => import('@/modules/auth/pages/ForgotPasswordPage.vue'),  meta: { requiresGuest: true } },
    { path: '/reset-password',     name: 'ResetPassword',   component: () => import('@/modules/auth/pages/ResetPasswordPage.vue'),   meta: { requiresGuest: true } },
    { path: '/verify-email-sent',  name: 'VerifyEmailSent', component: () => import('@/modules/auth/pages/VerifyEmailSentPage.vue') },
    { path: '/verify-email',       name: 'VerifyEmail',     component: () => import('@/modules/auth/pages/VerifyEmailPage.vue') },
    { path: '/queue-display/:area?', name: 'PublicQueueDisplay', component: () => import('@/modules/queue/pages/PublicQueueDisplayPage.vue') },

    // ── Root redirect ────────────────────────────────────────
    { path: '/', redirect: '/dashboard' },

    // ── Student ──────────────────────────────────────────────
    { path: '/dashboard',           name: 'StudentDashboard', component: () => import('@/modules/student/pages/DashboardPage.vue'),        meta: { requiresAuth: true, role: 'student' } },
    { path: '/profile',             name: 'Profile',          component: () => import('@/modules/student/pages/ProfilePage.vue'),           meta: { requiresAuth: true, role: 'student' } },
    { path: '/change-password',     name: 'ChangePassword',   component: () => import('@/modules/student/pages/ChangePasswordPage.vue'),    meta: { requiresAuth: true } },
    { path: '/notifications',       name: 'Notifications',    component: () => import('@/modules/student/pages/NotificationsPage.vue'),     meta: { requiresAuth: true, role: 'student' } },
    { path: '/requests',            name: 'MyRequests',       component: () => import('@/modules/request/pages/MyRequestsPage.vue'),        meta: { requiresAuth: true, role: 'student' } },
    { path: '/requests/new',        name: 'NewRequest',       component: () => import('@/modules/request/pages/NewRequestPage.vue'),        meta: { requiresAuth: true, role: 'student' } },
    { path: '/requests/payment/complete', name: 'PaymentComplete', component: () => import('@/modules/payment/pages/PaymentCompletePage.vue'), meta: { requiresAuth: true, role: 'student' } },
    { path: '/requests/:id',        name: 'RequestDetail',    component: () => import('@/modules/request/pages/RequestDetailPage.vue'),     meta: { requiresAuth: true, role: 'student' } },
    { path: '/requests/:id/payment',name: 'Payment',          component: () => import('@/modules/payment/pages/PaymentPage.vue'),           meta: { requiresAuth: true, role: 'student' } },

    // â”€â”€ Cashier â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    { path: '/cashier',                 redirect: '/cashier/dashboard' },
    { path: '/cashier/dashboard',       name: 'CashierDashboard', component: () => import('@/modules/cashier/pages/CashierPage.vue'), meta: { requiresAuth: true, role: 'cashier' } },

    // ── Registrar ────────────────────────────────────────────
    { path: '/registrar',                 redirect: '/registrar/dashboard' },
    { path: '/registrar/dashboard',       name: 'RegistrarDashboard',     component: () => import('@/modules/registrar/pages/RegistrarDashboardPage.vue'),   meta: { requiresAuth: true, role: 'registrar', module: 'requests' } },
    { path: '/registrar/requests',        name: 'RegistrarRequests',      component: () => import('@/modules/registrar/pages/RegistrarRequestsPage.vue'),    meta: { requiresAuth: true, role: 'registrar', module: 'requests' } },
    { path: '/registrar/calendar',        name: 'RegistrarCalendar',      component: () => import('@/modules/registrar/pages/RegistrarCalendarPage.vue'),    meta: { requiresAuth: true, role: 'registrar', module: 'calendar' } },
    { path: '/registrar/requests/:id',    name: 'RegistrarRequestDetail', component: () => import('@/modules/registrar/pages/RegistrarRequestDetailPage.vue'), meta: { requiresAuth: true, role: 'registrar', module: 'requests' } },
    { path: '/registrar/queue',           name: 'RegistrarQueue',         component: () => import('@/modules/cashier/pages/CashierPage.vue'),        meta: { requiresAuth: true, role: 'registrar', module: 'queue' } },
    { path: '/registrar/reports',         name: 'RegistrarReports',       component: () => import('@/modules/registrar/pages/RegistrarReportsPage.vue'),      meta: { requiresAuth: true, role: 'registrar', module: 'reports' } },
    { path: '/registrar/request-analytics', name: 'RegistrarRequestAnalytics', component: () => import('@/modules/registrar/pages/RegistrarRequestAnalyticsPage.vue'), meta: { requiresAuth: true, role: 'registrar', module: 'analytics' } },
    { path: '/registrar/notifications',   name: 'RegistrarNotifications', component: () => import('@/modules/admin/pages/AdminNotificationsPage.vue'),       meta: { requiresAuth: true, role: 'registrar', module: 'notifications' } },
    { path: '/registrar/document-types',  name: 'RegistrarDocumentTypes',  component: () => import('@/modules/registrar/pages/RegistrarDocumentTypesPage.vue'),  meta: { requiresAuth: true, role: 'registrar', module: 'document_types' } },
    { path: '/registrar/settings',        name: 'RegistrarSettings',        component: () => import('@/modules/registrar/pages/RegistrarSettingsPage.vue'),        meta: { requiresAuth: true, role: 'registrar', module: 'settings' } },
    { path: '/registrar/change-password', name: 'RegistrarChangePassword',  component: () => import('@/modules/registrar/pages/RegistrarChangePasswordPage.vue'),  meta: { requiresAuth: true, role: 'registrar' } },

    // ── Admin ────────────────────────────────────────────────
    { path: '/admin',                          redirect: '/admin/dashboard' },
    { path: '/admin/dashboard',                name: 'AdminDashboard',       component: () => import('@/modules/admin/pages/AdminDashboardPage.vue'),       meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/requests',                 name: 'AdminRequests',        component: () => import('@/modules/admin/pages/AdminRequestListPage.vue'),      meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/calendar',                 name: 'AdminCalendar',        component: () => import('@/modules/admin/pages/AdminCalendarPage.vue'),         meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/requests/:id',             name: 'AdminRequestDetail',   component: () => import('@/modules/admin/pages/AdminRequestDetailPage.vue'),    meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/reports',                  name: 'AdminReports',         component: () => import('@/modules/admin/pages/AdminReportsPage.vue'),          meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/request-analytics',        name: 'AdminRequestAnalytics', component: () => import('@/modules/admin/pages/AdminRequestAnalyticsPage.vue'),   meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/document-types',           name: 'AdminDocumentTypes',   component: () => import('@/modules/admin/pages/AdminDocumentTypesPage.vue'),    meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/payment-methods',          name: 'AdminPaymentMethods', component: () => import('@/modules/admin/pages/AdminPaymentMethodsPage.vue'), meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/queue',                    name: 'AdminQueue',          component: () => import('@/modules/cashier/pages/CashierPage.vue'),            meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/api-keys',                 name: 'AdminApiKeys',        component: () => import('@/modules/admin/pages/AdminApiKeysPage.vue'),         meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/registrars',               name: 'AdminRegistrars',      component: () => import('@/modules/admin/pages/AdminRegistrarsPage.vue'),       meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/settings',                 name: 'AdminSettings',        component: () => import('@/modules/admin/pages/AdminSettingsPage.vue'),         meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/notifications',            name: 'AdminNotifications',   component: () => import('@/modules/admin/pages/AdminNotificationsPage.vue'),    meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/audit-logs',               name: 'AdminAuditLogs',       component: () => import('@/modules/admin/pages/AdminAuditLogsPage.vue'),         meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/profile',                  name: 'AdminProfile',         component: () => import('@/modules/admin/pages/AdminProfilePage.vue'),          meta: { requiresAuth: true, role: 'admin' } },
    { path: '/admin/change-password',          name: 'AdminChangePassword',  component: () => import('@/modules/admin/pages/AdminChangePasswordPage.vue'),   meta: { requiresAuth: true, role: 'admin' } },

    // ── 404 ──────────────────────────────────────────────────
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
})

// ── Navigation guard ─────────────────────────────────────────
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.isAuthenticated && !auth.user) {
    await auth.fetchMe()
  }

  if (auth.isRegistrar && !auth.user?.permissions) {
    await auth.fetchMe()
  }

  // Redirect authenticated users away from guest-only pages
  if (to.meta.requiresGuest && auth.isAuthenticated) {
    return homePath(auth)
  }

  // Require auth
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return '/login'
  }

  // Role checks
  const role = to.meta.role as string | undefined
  if (role) {
    if (role === 'admin'     && !auth.isAdmin)     return homePath(auth)
    if (role === 'registrar' && !auth.isRegistrar) return homePath(auth)
    if (role === 'cashier'   && !auth.isCashier)   return homePath(auth)
    if (role === 'student'   && !auth.isStudent)   return homePath(auth)
  }

  const module = to.meta.module as string | undefined
  if (module && module !== 'requests' && auth.isRegistrar && !auth.user?.permissions?.includes(module)) {
    return '/registrar/dashboard'
  }
})

function homePath(auth: ReturnType<typeof useAuthStore>): string {
  if (auth.isAdmin)     return '/admin/dashboard'
  if (auth.isRegistrar) return '/registrar/dashboard'
  if (auth.isCashier)   return '/cashier/dashboard'
  return '/dashboard'
}

export default router
