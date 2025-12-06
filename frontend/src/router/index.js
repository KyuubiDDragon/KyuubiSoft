import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/core/api/axios'

// Setup status cache
let setupChecked = false
let setupRequired = false

// Lazy load views
const LoginView = () => import('@/modules/auth/views/LoginView.vue')
const RegisterView = () => import('@/modules/auth/views/RegisterView.vue')
const SetupView = () => import('@/modules/auth/views/SetupView.vue')
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
const ToolboxView = () => import('@/modules/toolbox/views/ToolboxView.vue')
const DockerView = () => import('@/modules/docker/views/DockerView.vue')
const DockerfileGeneratorView = () => import('@/modules/docker/views/DockerfileGeneratorView.vue')
const DockerComposeView = () => import('@/modules/docker/views/DockerComposeView.vue')
const DockerCommandView = () => import('@/modules/docker/views/DockerCommandView.vue')
const DockerignoreView = () => import('@/modules/docker/views/DockerignoreView.vue')
const DockerHostsView = () => import('@/modules/docker/views/DockerHostsView.vue')
const ServerView = () => import('@/modules/server/views/ServerView.vue')
const CalendarView = () => import('@/modules/calendar/views/CalendarView.vue')
const SettingsView = () => import('@/modules/settings/views/SettingsView.vue')
const UsersView = () => import('@/modules/users/views/UsersView.vue')
const SystemView = () => import('@/modules/system/views/SystemView.vue')
const TicketsView = () => import('@/modules/tickets/views/TicketsView.vue')
const TicketDetailView = () => import('@/modules/tickets/views/TicketDetailView.vue')
const TicketCategoriesView = () => import('@/modules/tickets/views/TicketCategoriesView.vue')
const PublicTicketView = () => import('@/modules/tickets/views/PublicTicketView.vue')
const PublicDocumentView = () => import('@/modules/documents/views/PublicDocumentView.vue')
const SSHTerminalView = () => import('@/modules/connections/views/SSHTerminalView.vue')

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
  {
    path: '/setup',
    name: 'setup',
    component: SetupView,
    meta: { layout: 'auth', guest: true, isSetup: true },
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
    path: '/connections/:id/terminal',
    name: 'ssh-terminal',
    component: SSHTerminalView,
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
    path: '/toolbox',
    name: 'toolbox',
    component: ToolboxView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker',
    name: 'docker',
    component: DockerView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker/dockerfile',
    name: 'docker-dockerfile',
    component: DockerfileGeneratorView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker/compose',
    name: 'docker-compose',
    component: DockerComposeView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker/command',
    name: 'docker-command',
    component: DockerCommandView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker/ignore',
    name: 'docker-ignore',
    component: DockerignoreView,
    meta: { requiresAuth: true },
  },
  {
    path: '/docker/hosts',
    name: 'docker-hosts',
    component: DockerHostsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/server',
    name: 'server',
    component: ServerView,
    meta: { requiresAuth: true },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: CalendarView,
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

  // Ticket routes
  {
    path: '/tickets',
    name: 'tickets',
    component: TicketsView,
    meta: { requiresAuth: true },
  },
  {
    path: '/tickets/categories',
    name: 'ticket-categories',
    component: TicketCategoriesView,
    meta: { requiresAuth: true, roles: ['owner', 'admin'] },
  },
  {
    path: '/tickets/:id',
    name: 'ticket-detail',
    component: TicketDetailView,
    meta: { requiresAuth: true },
  },

  // Public ticket routes (no auth)
  {
    path: '/support',
    name: 'public-tickets',
    component: PublicTicketView,
    meta: { layout: 'auth', guest: true },
  },
  {
    path: '/support/:code',
    name: 'public-ticket-view',
    component: PublicTicketView,
    meta: { layout: 'auth', guest: true },
  },

  // Public document view (accessible to everyone - logged in or not)
  {
    path: '/doc/:token',
    name: 'public-document',
    component: PublicDocumentView,
    meta: { layout: 'public' },
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

  // Check setup status once on first navigation (except for setup page itself)
  if (!setupChecked && !to.meta.isSetup) {
    try {
      const response = await api.get('/api/v1/setup/status')
      setupRequired = response.data.data.setup_required
      setupChecked = true

      // If setup is required, redirect to setup wizard
      if (setupRequired) {
        return next({ name: 'setup' })
      }
    } catch (error) {
      console.error('Failed to check setup status:', error)
      // Continue anyway, the setup page will handle errors
      setupChecked = true
    }
  }

  // If going to setup but setup not required, redirect to login
  if (to.meta.isSetup && setupChecked && !setupRequired) {
    return next({ name: 'login' })
  }

  // Public routes that don't need any auth checks
  const isPublicRoute = to.meta.layout === 'public' || to.meta.guest

  // For public routes, skip auth initialization entirely and proceed immediately
  if (isPublicRoute) {
    // Still mark as initialized so the app doesn't hang
    if (!authStore.isInitialized) {
      authStore.isInitialized = true
    }
    return next()
  }

  // Wait for auth initialization (only for non-public routes)
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

// Function to mark setup as complete (called from SetupView after successful setup)
export function markSetupComplete() {
  setupRequired = false
}

export default router
