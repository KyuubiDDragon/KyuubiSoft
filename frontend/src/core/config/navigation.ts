import { type Component } from 'vue'
import {
  HomeIcon,
  NewspaperIcon,
  ListBulletIcon,
  DocumentTextIcon,
  PencilSquareIcon,
  CodeBracketIcon,
  BookmarkIcon,
  CloudArrowUpIcon,
  LinkIcon,
  ClipboardDocumentListIcon,
  PhotoIcon,
  SwatchIcon,
  ViewColumnsIcon,
  FolderIcon,
  CalendarIcon,
  ClockIcon,
  ArrowPathIcon,
  ServerIcon,
  CommandLineIcon,
  BellIcon,
  SignalIcon,
  LockClosedIcon,
  WrenchScrewdriverIcon,
  BoltIcon,
  CubeIcon,
  TicketIcon,
  TagIcon,
  CurrencyDollarIcon,
  BanknotesIcon,
  ChatBubbleLeftRightIcon,
  BookOpenIcon,
  CloudIcon,
  KeyIcon,
  ArchiveBoxIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  FireIcon,
  InboxArrowDownIcon,
  CircleStackIcon,
  QueueListIcon,
  EnvelopeIcon,
  BellAlertIcon,
  RocketLaunchIcon,
  GlobeAltIcon,
} from '@heroicons/vue/24/outline'

/**
 * Navigation item within a group
 */
export interface NavItem {
  id: string
  name: string
  href: string
  icon: Component
  feature?: string
  permission?: string
  description?: string
}

/**
 * Navigation group (shows as icon in the rail)
 */
export interface NavGroup {
  id: string
  name: string
  icon: Component
  feature?: string
  permission?: string
  /** If set, this group is a direct link (no children) */
  href?: string
  children?: NavItem[]
}

/**
 * Icon name to component mapping for serialization/deserialization
 */
export const iconMap: Record<string, Component> = {
  HomeIcon,
  NewspaperIcon,
  ListBulletIcon,
  DocumentTextIcon,
  PencilSquareIcon,
  CodeBracketIcon,
  BookmarkIcon,
  CloudArrowUpIcon,
  LinkIcon,
  ClipboardDocumentListIcon,
  PhotoIcon,
  SwatchIcon,
  ViewColumnsIcon,
  FolderIcon,
  CalendarIcon,
  ClockIcon,
  ArrowPathIcon,
  ServerIcon,
  CommandLineIcon,
  BellIcon,
  SignalIcon,
  LockClosedIcon,
  WrenchScrewdriverIcon,
  BoltIcon,
  CubeIcon,
  TicketIcon,
  TagIcon,
  CurrencyDollarIcon,
  BanknotesIcon,
  ChatBubbleLeftRightIcon,
  BookOpenIcon,
  CloudIcon,
  KeyIcon,
  ArchiveBoxIcon,
  Cog6ToothIcon,
  UsersIcon,
  ShieldCheckIcon,
  FireIcon,
  InboxArrowDownIcon,
  CircleStackIcon,
  QueueListIcon,
  EnvelopeIcon,
  BellAlertIcon,
  RocketLaunchIcon,
  GlobeAltIcon,
}

/**
 * Reverse lookup: component -> name string
 */
const reverseIconMap = new Map<Component, string>()
for (const [name, component] of Object.entries(iconMap)) {
  reverseIconMap.set(component, name)
}

export function getIconName(icon: Component): string {
  return reverseIconMap.get(icon) ?? 'HomeIcon'
}

export function getIconComponent(name: string): Component {
  return iconMap[name] ?? HomeIcon
}

/**
 * Single Source of Truth for ALL navigation.
 * Used by: IconRail, Flyout, CommandPalette, Breadcrumbs, GlobalSearch
 */
export const navigationGroups: NavGroup[] = [
  // ─── Dashboard (direct link) ───
  {
    id: 'dashboard',
    name: 'navigation.dashboard',
    icon: HomeIcon,
    href: '/',
    permission: 'dashboard.view',
  },

  // ─── News (direct link) ───
  {
    id: 'news',
    name: 'navigation.news',
    icon: NewspaperIcon,
    href: '/news',
    permission: 'news.view',
  },

  // ─── Inhalte ───
  {
    id: 'inhalte',
    name: 'navigation.content',
    icon: DocumentTextIcon,
    children: [
      { id: 'lists', name: 'navigation.lists', href: '/lists', icon: ListBulletIcon, permission: 'lists.view' },
      { id: 'documents', name: 'navigation.documents', href: '/documents', icon: DocumentTextIcon, permission: 'documents.view' },
      { id: 'notes', name: 'navigation.notes', href: '/notes', icon: PencilSquareIcon, feature: 'notes', permission: 'notes.view' },
      { id: 'snippets', name: 'navigation.snippets', href: '/snippets', icon: CodeBracketIcon, permission: 'snippets.view' },
      { id: 'bookmarks', name: 'navigation.bookmarks', href: '/bookmarks', icon: BookmarkIcon, permission: 'bookmarks.view' },
      { id: 'habit-tracker', name: 'navigation.habitTracker', href: '/habit-tracker', icon: FireIcon, permission: 'habits.view' },
    ],
  },

  // ─── KyuubiCloud ───
  {
    id: 'kyuubicloud',
    name: 'navigation.kyuubiCloud',
    icon: CloudIcon,
    children: [
      { id: 'storage', name: 'navigation.cloudStorage', href: '/storage', icon: CloudArrowUpIcon, permission: 'storage.view' },
      { id: 'shares', name: 'navigation.shares', href: '/storage/shares', icon: LinkIcon, permission: 'storage.share' },
      { id: 'checklists', name: 'navigation.checklists', href: '/checklists', icon: ClipboardDocumentListIcon, permission: 'checklists.view' },
      { id: 'links', name: 'navigation.shortLinks', href: '/links', icon: LinkIcon, permission: 'links.view' },
      { id: 'galleries', name: 'navigation.galleries', href: '/galleries', icon: PhotoIcon, feature: 'galleries', permission: 'galleries.view' },
      { id: 'mockup-editor', name: 'navigation.mockupEditor', href: '/mockup-editor', icon: SwatchIcon, permission: 'mockup.view' },
    ],
  },

  // ─── Projektmanagement ───
  {
    id: 'projektmanagement',
    name: 'navigation.projectManagement',
    icon: FolderIcon,
    children: [
      { id: 'kanban', name: 'navigation.kanban', href: '/kanban', icon: ViewColumnsIcon, permission: 'kanban.view' },
      { id: 'projects', name: 'navigation.projects', href: '/projects', icon: FolderIcon, permission: 'projects.view' },
      { id: 'calendar', name: 'navigation.calendar', href: '/calendar', icon: CalendarIcon, permission: 'calendar.view' },
      { id: 'time', name: 'navigation.timeTracking', href: '/time', icon: ClockIcon, permission: 'time.view' },
      { id: 'recurring', name: 'navigation.recurringTasks', href: '/recurring-tasks', icon: ArrowPathIcon, permission: 'recurring.view' },
    ],
  },

  // ─── Entwicklung & Tools ───
  {
    id: 'devtools',
    name: 'navigation.devTools',
    icon: CommandLineIcon,
    children: [
      { id: 'connections', name: 'navigation.connections', href: '/connections', icon: ServerIcon, permission: 'connections.view' },
      { id: 'server', name: 'navigation.server', href: '/server', icon: CommandLineIcon, feature: 'server', permission: 'server.view' },
      { id: 'db-browser', name: 'navigation.dbBrowser', href: '/database-browser', icon: CircleStackIcon, permission: 'connections.view' },
      { id: 'log-viewer', name: 'navigation.logViewer', href: '/logs', icon: QueueListIcon, permission: 'server.view' },
      { id: 'scripts', name: 'navigation.scriptVault', href: '/scripts', icon: CodeBracketIcon, feature: 'server', permission: 'server.view' },
      { id: 'git', name: 'navigation.gitRepos', href: '/git', icon: CodeBracketIcon, feature: 'git', permission: 'git.view' },
      { id: 'webhooks', name: 'navigation.webhooks', href: '/webhooks', icon: BellIcon, permission: 'webhooks.view' },
      { id: 'uptime', name: 'navigation.uptimeMonitor', href: '/uptime', icon: SignalIcon, feature: 'uptime', permission: 'uptime.view' },
      { id: 'status-page', name: 'navigation.statusPage', href: '/status-page', icon: SignalIcon, feature: 'uptime', permission: 'uptime.view' },
      { id: 'ssl', name: 'navigation.sslCertificates', href: '/ssl', icon: LockClosedIcon, feature: 'ssl', permission: 'ssl.view' },
      { id: 'toolbox', name: 'navigation.toolbox', href: '/toolbox', icon: WrenchScrewdriverIcon, feature: 'tools', permission: 'tools.ping' },
      { id: 'workflows', name: 'navigation.workflows', href: '/workflows', icon: BoltIcon, permission: 'automation.view' },
      { id: 'cron', name: 'navigation.cronJobs', href: '/cron', icon: ClockIcon, feature: 'server', permission: 'server.view' },
      { id: 'dns', name: 'navigation.dnsManager', href: '/dns', icon: GlobeAltIcon, permission: 'server.view' },
      { id: 'environments', name: 'navigation.environments', href: '/environments', icon: KeyIcon, permission: 'server.view' },
      { id: 'deployments', name: 'navigation.deployments', href: '/deployments', icon: RocketLaunchIcon, permission: 'server.view' },
    ],
  },

  // ─── Docker ───
  {
    id: 'docker',
    name: 'navigation.docker',
    icon: CubeIcon,
    feature: 'docker',
    permission: 'docker.view',
    children: [
      { id: 'docker-manager', name: 'navigation.containerManager', href: '/docker', icon: ServerIcon, permission: 'docker.view' },
      { id: 'docker-hosts', name: 'navigation.dockerHosts', href: '/docker/hosts', icon: ServerIcon, permission: 'docker.hosts' },
      { id: 'docker-dockerfile', name: 'navigation.dockerfileGenerator', href: '/docker/dockerfile', icon: DocumentTextIcon, permission: 'docker.view' },
      { id: 'docker-compose', name: 'navigation.composeBuilder', href: '/docker/compose', icon: ViewColumnsIcon, permission: 'docker.view' },
      { id: 'docker-command', name: 'navigation.commandBuilder', href: '/docker/command', icon: CommandLineIcon, permission: 'docker.view' },
      { id: 'docker-ignore', name: 'navigation.dockerignore', href: '/docker/ignore', icon: ShieldCheckIcon, permission: 'docker.view' },
    ],
  },

  // ─── Support ───
  {
    id: 'support',
    name: 'navigation.support',
    icon: TicketIcon,
    feature: 'tickets',
    permission: 'tickets.view',
    children: [
      { id: 'tickets', name: 'navigation.tickets', href: '/tickets', icon: TicketIcon, permission: 'tickets.view' },
      { id: 'ticket-categories', name: 'navigation.categories', href: '/tickets/categories', icon: TagIcon, permission: 'tickets.manage' },
      { id: 'knowledge-base', name: 'navigation.knowledgeBase', href: '/knowledge-base', icon: BookOpenIcon, permission: 'tickets.view' },
    ],
  },

  // ─── Business ───
  {
    id: 'business',
    name: 'navigation.business',
    icon: CurrencyDollarIcon,
    children: [
      { id: 'invoices', name: 'navigation.invoices', href: '/invoices', icon: CurrencyDollarIcon, feature: 'invoices', permission: 'invoices.view' },
      { id: 'contracts', name: 'navigation.contracts', href: '/contracts', icon: DocumentTextIcon, feature: 'contracts', permission: 'contracts.view' },
      { id: 'expenses', name: 'navigation.expenses', href: '/expenses', icon: BanknotesIcon, permission: 'finance.view' },
    ],
  },

  // ─── Communication ───
  {
    id: 'communication',
    name: 'navigation.communication',
    icon: ChatBubbleLeftRightIcon,
    children: [
      { id: 'contacts', name: 'navigation.contacts', href: '/contacts', icon: UsersIcon, permission: 'contacts.view' },
      { id: 'inbox', name: 'navigation.inbox', href: '/inbox', icon: InboxArrowDownIcon, permission: 'inbox.view' },
      { id: 'email', name: 'navigation.email', href: '/email', icon: EnvelopeIcon, permission: 'email.view' },
      { id: 'chat', name: 'navigation.teamChat', href: '/chat', icon: ChatBubbleLeftRightIcon, permission: 'chat.view' },
      { id: 'discord', name: 'navigation.discord', href: '/discord', icon: ChatBubbleLeftRightIcon, feature: 'discord', permission: 'discord.view' },
    ],
  },

  // ─── Wiki (direct link) ───
  {
    id: 'wiki',
    name: 'navigation.wiki',
    icon: BookOpenIcon,
    href: '/wiki',
    permission: 'wiki.view',
  },

  // ─── Administration ───
  {
    id: 'administration',
    name: 'navigation.admin',
    icon: Cog6ToothIcon,
    children: [
      { id: 'passwords', name: 'navigation.passwords', href: '/passwords', icon: KeyIcon, permission: 'passwords.view' },
      { id: 'backups', name: 'navigation.backups', href: '/backups', icon: ArchiveBoxIcon, permission: 'backups.view' },
      { id: 'audit', name: 'navigation.auditLog', href: '/audit', icon: ClipboardDocumentListIcon, permission: 'system.admin' },
      { id: 'notification-rules', name: 'navigation.notificationRules', href: '/notification-rules', icon: BellAlertIcon, permission: 'settings.view' },
      { id: 'settings', name: 'navigation.settings', href: '/settings', icon: Cog6ToothIcon, permission: 'settings.view' },
      { id: 'users', name: 'navigation.users', href: '/users', icon: UsersIcon, permission: 'users.view' },
      { id: 'roles', name: 'navigation.roles', href: '/roles', icon: ShieldCheckIcon, permission: 'users.write' },
      { id: 'system', name: 'navigation.system', href: '/system', icon: ShieldCheckIcon, permission: 'system.admin' },
    ],
  },
]

/**
 * Flat list of all navigation items (for search, breadcrumbs)
 */
export function getAllNavItems(): NavItem[] {
  const items: NavItem[] = []

  for (const group of navigationGroups) {
    if (group.href) {
      items.push({
        id: group.id,
        name: group.name,
        href: group.href,
        icon: group.icon,
        permission: group.permission,
        feature: group.feature,
      })
    }
    if (group.children) {
      items.push(...group.children)
    }
  }

  return items
}

/**
 * Find navigation item by href
 */
export function findNavItemByHref(href: string): NavItem | undefined {
  return getAllNavItems().find(item => {
    if (item.href === href) return true
    if (href !== '/' && item.href !== '/' && href.startsWith(item.href)) return true
    return false
  })
}

/**
 * Find the group containing a given href
 */
export function findGroupByHref(href: string): NavGroup | undefined {
  for (const group of navigationGroups) {
    if (group.href === href) return group
    if (group.children?.some(child => {
      if (child.href === href) return true
      if (href !== '/' && child.href !== '/' && href.startsWith(child.href)) return true
      return false
    })) {
      return group
    }
  }
  return undefined
}
