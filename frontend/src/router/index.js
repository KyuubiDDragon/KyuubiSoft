import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Lazy load views
const LoginView = () => import('@/modules/auth/views/LoginView.vue')
const RegisterView = () => import('@/modules/auth/views/RegisterView.vue')
const DashboardView = () => import('@/modules/dashboard/views/DashboardView.vue')
const ListsView = () => import('@/modules/lists/views/ListsView.vue')
const DocumentsView = () => import('@/modules/documents/views/DocumentsView.vue')
const ConnectionsView = () => import('@/modules/connections/views/ConnectionsView.vue')
const SnippetsView = () => import('@/modules/snippets/views/SnippetsView.vue')
const KanbanView = () => import('@/modules/kanban/views/KanbanView.vue')
const ProjectsView = () => import('@/modules/projects/views/ProjectsView.vue')
const TimeTrackingView = () => import('@/modules/time/views/TimeTrackingView.vue')
const WebhooksView = () => import('@/modules/webhooks/views/WebhooksView.vue')
const BookmarksView = () => import('@/modules/bookmarks/views/BookmarksView.vue')
const UptimeView = () => import('@/modules/uptime/views/UptimeView.vue')
const InvoicesView = () => import('@/modules/invoices/views/InvoicesView.vue')
const ApiTesterView = () => import('@/modules/api-tester/views/ApiTesterView.vue')
const YouTubeDownloaderView = () => import('@/modules/youtube-downloader/views/YouTubeDownloaderView.vue')
const SettingsView = () => import('@/modules/settings/views/SettingsView.vue')
const UsersView = () => import('@/modules/users/views/UsersView.vue')
const SystemView = () => import('@/modules/system/views/SystemView.vue')

const routes = [
  // Auth routes
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { layout: 'auth', guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterView,
    meta: { layout: 'auth', guest: true },
  },

  // Protected routes
  {
    path: '/',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true },
  },
  {
    path: '/lists',
    name: 'lists',
    component: ListsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/documents',
    name: 'documents',
    component: DocumentsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/connections',
    name: 'connections',
    component: ConnectionsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/snippets',
    name: 'snippets',
    component: SnippetsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/kanban',
    name: 'kanban',
    component: KanbanView,
    meta: { requiresAuth: true },
  },
  {
    path: '/projects',
    name: 'projects',
    component: ProjectsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/time',
    name: 'time',
    component: TimeTrackingView,
    meta: { requiresAuth: true },
  },
  {
    path: '/webhooks',
    name: 'webhooks',
    component: WebhooksView,
    meta: { requiresAuth: true },
  },
  {
    path: '/bookmarks',
    name: 'bookmarks',
    component: BookmarksView,
    meta: { requiresAuth: true },
  },
  {
    path: '/uptime',
    name: 'uptime',
    component: UptimeView,
    meta: { requiresAuth: true },
  },
  {
    path: '/invoices',
    name: 'invoices',
    component: InvoicesView,
    meta: { requiresAuth: true },
  },
  {
    path: '/api-tester',
    name: 'api-tester',
    component: ApiTesterView,
    meta: { requiresAuth: true },
  },
  {
    path: '/youtube-downloader',
    name: 'youtube-downloader',
    component: YouTubeDownloaderView,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'settings',
    component: SettingsView,
    meta: { requiresAuth: true },
  },

  // Admin routes (role-protected)
  {
    path: '/users',
    name: 'users',
    component: UsersView,
    meta: { requiresAuth: true, roles: ['owner', 'admin'] },
  },
  {
    path: '/system',
    name: 'system',
    component: SystemView,
    meta: { requiresAuth: true, roles: ['owner'] },
  },

  // Catch all
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Wait for auth initialization
  if (!authStore.isInitialized) {
    await authStore.initialize()
  }

  const requiresAuth = to.meta.requiresAuth
  const guestOnly = to.meta.guest
  const isAuthenticated = authStore.isAuthenticated

  if (requiresAuth && !isAuthenticated) {
    // Redirect to login
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  if (guestOnly && isAuthenticated) {
    // Redirect to dashboard
    return next({ name: 'dashboard' })
  }

  // Check permissions if required
  if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
    return next({ name: 'dashboard' })
  }

  // Check roles if required
  if (to.meta.roles) {
    const hasRequiredRole = to.meta.roles.some(role => authStore.hasRole(role))
    if (!hasRequiredRole) {
      return next({ name: 'dashboard' })
    }
  }

  next()
})

export default router
