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
    name: 'Dashboard',
    icon: HomeIcon,
    href: '/',
    permission: 'dashboard.view',
  },

  // ─── News (direct link) ───
  {
    id: 'news',
    name: 'News',
    icon: NewspaperIcon,
    href: '/news',
    permission: 'news.view',
  },

  // ─── Inhalte ───
  {
    id: 'inhalte',
    name: 'Inhalte',
    icon: DocumentTextIcon,
    children: [
      { id: 'lists', name: 'Listen', href: '/lists', icon: ListBulletIcon, permission: 'lists.view' },
      { id: 'documents', name: 'Dokumente', href: '/documents', icon: DocumentTextIcon, permission: 'documents.view' },
      { id: 'notes', name: 'Notes', href: '/notes', icon: PencilSquareIcon, feature: 'notes', permission: 'notes.view' },
      { id: 'snippets', name: 'Snippets', href: '/snippets', icon: CodeBracketIcon, permission: 'snippets.view' },
      { id: 'bookmarks', name: 'Bookmarks', href: '/bookmarks', icon: BookmarkIcon, permission: 'bookmarks.view' },
      { id: 'habit-tracker', name: 'Habit Tracker', href: '/habit-tracker', icon: FireIcon },
    ],
  },

  // ─── KyuubiCloud ───
  {
    id: 'kyuubicloud',
    name: 'KyuubiCloud',
    icon: CloudIcon,
    children: [
      { id: 'storage', name: 'Cloud Storage', href: '/storage', icon: CloudArrowUpIcon, permission: 'storage.view' },
      { id: 'shares', name: 'Freigaben', href: '/storage/shares', icon: LinkIcon, permission: 'storage.share' },
      { id: 'checklists', name: 'Checklisten', href: '/checklists', icon: ClipboardDocumentListIcon, permission: 'checklists.view' },
      { id: 'links', name: 'Short Links', href: '/links', icon: LinkIcon, permission: 'links.view' },
      { id: 'galleries', name: 'Galerien', href: '/galleries', icon: PhotoIcon, feature: 'galleries', permission: 'galleries.view' },
      { id: 'mockup-editor', name: 'Mockup Editor', href: '/mockup-editor', icon: SwatchIcon, permission: 'mockup.view' },
    ],
  },

  // ─── Projektmanagement ───
  {
    id: 'projektmanagement',
    name: 'Projekte',
    icon: FolderIcon,
    children: [
      { id: 'kanban', name: 'Kanban', href: '/kanban', icon: ViewColumnsIcon, permission: 'kanban.view' },
      { id: 'projects', name: 'Projekte', href: '/projects', icon: FolderIcon, permission: 'projects.view' },
      { id: 'calendar', name: 'Kalender', href: '/calendar', icon: CalendarIcon, permission: 'calendar.view' },
      { id: 'time', name: 'Zeiterfassung', href: '/time', icon: ClockIcon, permission: 'time.view' },
      { id: 'recurring', name: 'Wiederkehrend', href: '/recurring-tasks', icon: ArrowPathIcon, permission: 'recurring.view' },
    ],
  },

  // ─── Entwicklung & Tools ───
  {
    id: 'devtools',
    name: 'DevTools',
    icon: CommandLineIcon,
    children: [
      { id: 'connections', name: 'Verbindungen', href: '/connections', icon: ServerIcon, permission: 'connections.view' },
      { id: 'server', name: 'Server', href: '/server', icon: CommandLineIcon, feature: 'server', permission: 'server.view' },
      { id: 'db-browser', name: 'DB Browser', href: '/database-browser', icon: CircleStackIcon, permission: 'connections.view' },
      { id: 'log-viewer', name: 'Log Viewer', href: '/logs', icon: QueueListIcon, permission: 'server.view' },
      { id: 'scripts', name: 'Script Vault', href: '/scripts', icon: CodeBracketIcon, feature: 'server', permission: 'server.view' },
      { id: 'git', name: 'Git Repos', href: '/git', icon: CodeBracketIcon, feature: 'git', permission: 'git.view' },
      { id: 'webhooks', name: 'Webhooks', href: '/webhooks', icon: BellIcon, permission: 'webhooks.view' },
      { id: 'uptime', name: 'Uptime Monitor', href: '/uptime', icon: SignalIcon, feature: 'uptime', permission: 'uptime.view' },
      { id: 'ssl', name: 'SSL Zertifikate', href: '/ssl', icon: LockClosedIcon, feature: 'ssl', permission: 'ssl.view' },
      { id: 'toolbox', name: 'Toolbox', href: '/toolbox', icon: WrenchScrewdriverIcon, feature: 'tools' },
      { id: 'workflows', name: 'Workflows', href: '/workflows', icon: BoltIcon, permission: 'automation.view' },
    ],
  },

  // ─── Docker ───
  {
    id: 'docker',
    name: 'Docker',
    icon: CubeIcon,
    feature: 'docker',
    permission: 'docker.view',
    children: [
      { id: 'docker-manager', name: 'Container Manager', href: '/docker', icon: ServerIcon, permission: 'docker.view' },
      { id: 'docker-hosts', name: 'Docker Hosts', href: '/docker/hosts', icon: ServerIcon, permission: 'docker.hosts' },
      { id: 'docker-dockerfile', name: 'Dockerfile Generator', href: '/docker/dockerfile', icon: DocumentTextIcon, permission: 'docker.view' },
      { id: 'docker-compose', name: 'Compose Builder', href: '/docker/compose', icon: ViewColumnsIcon, permission: 'docker.view' },
      { id: 'docker-command', name: 'Command Builder', href: '/docker/command', icon: CommandLineIcon, permission: 'docker.view' },
      { id: 'docker-ignore', name: '.dockerignore', href: '/docker/ignore', icon: ShieldCheckIcon, permission: 'docker.view' },
    ],
  },

  // ─── Support ───
  {
    id: 'support',
    name: 'Support',
    icon: TicketIcon,
    feature: 'tickets',
    permission: 'tickets.view',
    children: [
      { id: 'tickets', name: 'Tickets', href: '/tickets', icon: TicketIcon, permission: 'tickets.view' },
      { id: 'ticket-categories', name: 'Kategorien', href: '/tickets/categories', icon: TagIcon, permission: 'tickets.manage' },
    ],
  },

  // ─── Business ───
  {
    id: 'business',
    name: 'Business',
    icon: CurrencyDollarIcon,
    children: [
      { id: 'invoices', name: 'Rechnungen', href: '/invoices', icon: CurrencyDollarIcon, feature: 'invoices', permission: 'invoices.view' },
      { id: 'expenses', name: 'Ausgaben', href: '/expenses', icon: BanknotesIcon },
    ],
  },

  // ─── Communication ───
  {
    id: 'communication',
    name: 'Kommunikation',
    icon: ChatBubbleLeftRightIcon,
    children: [
      { id: 'contacts', name: 'Kontakte', href: '/contacts', icon: UsersIcon },
      { id: 'inbox', name: 'Inbox', href: '/inbox', icon: InboxArrowDownIcon },
      { id: 'email', name: 'E-Mail', href: '/email', icon: EnvelopeIcon },
      { id: 'chat', name: 'Team Chat', href: '/chat', icon: ChatBubbleLeftRightIcon },
      { id: 'discord', name: 'Discord', href: '/discord', icon: ChatBubbleLeftRightIcon, feature: 'discord', permission: 'discord.view' },
    ],
  },

  // ─── Wiki (direct link) ───
  {
    id: 'wiki',
    name: 'Wiki',
    icon: BookOpenIcon,
    href: '/wiki',
    permission: 'wiki.view',
  },

  // ─── Administration ───
  {
    id: 'administration',
    name: 'Admin',
    icon: Cog6ToothIcon,
    children: [
      { id: 'passwords', name: 'Passwörter', href: '/passwords', icon: KeyIcon, permission: 'passwords.view' },
      { id: 'backups', name: 'Backups', href: '/backups', icon: ArchiveBoxIcon, permission: 'backups.view' },
      { id: 'audit', name: 'Audit Log', href: '/audit', icon: ClipboardDocumentListIcon, permission: 'system.admin' },
      { id: 'settings', name: 'Einstellungen', href: '/settings', icon: Cog6ToothIcon, permission: 'settings.view' },
      { id: 'users', name: 'Benutzer', href: '/users', icon: UsersIcon, permission: 'users.view' },
      { id: 'roles', name: 'Rollen', href: '/roles', icon: ShieldCheckIcon, permission: 'users.write' },
      { id: 'system', name: 'System', href: '/system', icon: ShieldCheckIcon, permission: 'system.admin' },
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
