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
} from '@heroicons/vue/24/outline'

/**
 * Single source of truth for all app navigation routes.
 * Used by: GlobalSearch (Command Palette), Breadcrumbs
 *
 * Shape: { name: string, href: string, icon: Component, group: string|null }
 */
export const navigationConfig = [
  // Top-level
  { name: 'Dashboard',         href: '/',                    icon: HomeIcon,                 group: null },
  { name: 'News',              href: '/news',                icon: NewspaperIcon,            group: null },
  // Inhalte
  { name: 'Listen',            href: '/lists',               icon: ListBulletIcon,           group: 'Inhalte' },
  { name: 'Dokumente',         href: '/documents',           icon: DocumentTextIcon,         group: 'Inhalte' },
  { name: 'Notes',             href: '/notes',               icon: PencilSquareIcon,         group: 'Inhalte' },
  { name: 'Snippets',          href: '/snippets',            icon: CodeBracketIcon,          group: 'Inhalte' },
  { name: 'Bookmarks',         href: '/bookmarks',           icon: BookmarkIcon,             group: 'Inhalte' },
  // KyuubiCloud
  { name: 'Cloud Storage',     href: '/storage',             icon: CloudArrowUpIcon,         group: 'KyuubiCloud' },
  { name: 'Freigaben',         href: '/storage/shares',      icon: LinkIcon,                 group: 'KyuubiCloud' },
  { name: 'Checklisten',       href: '/checklists',          icon: ClipboardDocumentListIcon, group: 'KyuubiCloud' },
  { name: 'Short Links',       href: '/links',               icon: LinkIcon,                 group: 'KyuubiCloud' },
  { name: 'Galerien',          href: '/galleries',           icon: PhotoIcon,                group: 'KyuubiCloud' },
  { name: 'Mockup Editor',     href: '/mockup-editor',       icon: SwatchIcon,               group: 'KyuubiCloud' },
  // Projektmanagement
  { name: 'Kanban',            href: '/kanban',              icon: ViewColumnsIcon,          group: 'Projektmanagement' },
  { name: 'Projekte',          href: '/projects',            icon: FolderIcon,               group: 'Projektmanagement' },
  { name: 'Kalender',          href: '/calendar',            icon: CalendarIcon,             group: 'Projektmanagement' },
  { name: 'Zeiterfassung',     href: '/time',                icon: ClockIcon,                group: 'Projektmanagement' },
  { name: 'Wiederkehrend',     href: '/recurring-tasks',     icon: ArrowPathIcon,            group: 'Projektmanagement' },
  // Entwicklung & Tools
  { name: 'Verbindungen',      href: '/connections',         icon: ServerIcon,               group: 'Entwicklung & Tools' },
  { name: 'Server',            href: '/server',              icon: CommandLineIcon,          group: 'Entwicklung & Tools' },
  { name: 'Git Repos',         href: '/git',                 icon: CodeBracketIcon,          group: 'Entwicklung & Tools' },
  { name: 'Webhooks',          href: '/webhooks',            icon: BellIcon,                 group: 'Entwicklung & Tools' },
  { name: 'Uptime Monitor',    href: '/uptime',              icon: SignalIcon,               group: 'Entwicklung & Tools' },
  { name: 'SSL Zertifikate',   href: '/ssl',                 icon: LockClosedIcon,           group: 'Entwicklung & Tools' },
  { name: 'Toolbox',           href: '/toolbox',             icon: WrenchScrewdriverIcon,    group: 'Entwicklung & Tools' },
  { name: 'Workflows',         href: '/workflows',           icon: BoltIcon,                 group: 'Entwicklung & Tools' },
  // Docker
  { name: 'Container Manager', href: '/docker',              icon: CubeIcon,                 group: 'Docker' },
  { name: 'Docker Hosts',      href: '/docker/hosts',        icon: ServerIcon,               group: 'Docker' },
  { name: 'Dockerfile Generator', href: '/docker/dockerfile', icon: DocumentTextIcon,        group: 'Docker' },
  { name: 'Compose Builder',   href: '/docker/compose',      icon: ViewColumnsIcon,          group: 'Docker' },
  { name: 'Command Builder',   href: '/docker/command',      icon: CommandLineIcon,          group: 'Docker' },
  { name: '.dockerignore',     href: '/docker/ignore',       icon: ShieldCheckIcon,          group: 'Docker' },
  // Support
  { name: 'Tickets',           href: '/tickets',             icon: TicketIcon,               group: 'Support' },
  { name: 'Ticket-Kategorien', href: '/tickets/categories',  icon: TagIcon,                  group: 'Support' },
  // Business
  { name: 'Finanzen',          href: '/expenses',            icon: BanknotesIcon,            group: 'Business' },
  { name: 'Rechnungen',        href: '/invoices',            icon: CurrencyDollarIcon,       group: 'Business' },
  // Standalone
  { name: 'Discord Manager',   href: '/discord',             icon: ChatBubbleLeftRightIcon,  group: null },
  { name: 'Wiki',              href: '/wiki',                icon: BookOpenIcon,             group: null },
  { name: 'Inbox',             href: '/inbox',               icon: CloudIcon,                group: null },
  { name: 'Team Chat',         href: '/chat',                icon: ChatBubbleLeftRightIcon,  group: null },
  // Administration
  { name: 'Passwörter',        href: '/passwords',           icon: KeyIcon,                  group: 'Administration' },
  { name: 'Backups',           href: '/backups',             icon: ArchiveBoxIcon,           group: 'Administration' },
  { name: 'Einstellungen',     href: '/settings',            icon: Cog6ToothIcon,            group: 'Administration' },
  { name: 'Benutzer',          href: '/users',               icon: UsersIcon,                group: 'Administration' },
  { name: 'Rollen',            href: '/roles',               icon: ShieldCheckIcon,          group: 'Administration' },
  { name: 'System',            href: '/system',              icon: ShieldCheckIcon,          group: 'Administration' },
]
