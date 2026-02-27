<template>
  <div class="flex h-[calc(100vh-4rem)] gap-0 -m-6">
    <!-- Sidebar -->
    <div class="w-60 shrink-0 bg-white/[0.02] border-r border-white/[0.06] p-4 flex flex-col">
      <!-- Compose button -->
      <button class="btn-primary w-full mb-4 flex items-center justify-center gap-2" @click="showCompose = true">
        <PencilSquareIcon class="w-5 h-5" />
        Verfassen
      </button>

      <!-- Folders -->
      <nav class="space-y-1 flex-1">
        <button
          v-for="folder in emailStore.folders"
          :key="folder.id"
          class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors"
          :class="emailStore.activeFolder === folder.id ? 'bg-white/[0.08] text-white' : 'text-gray-400 hover:bg-white/[0.04] hover:text-white'"
          @click="selectFolder(folder.id)"
        >
          <span class="flex items-center gap-2">
            <component :is="getFolderIcon(folder.id)" class="w-4 h-4" />
            {{ folder.name }}
          </span>
          <span v-if="folder.unread > 0" class="text-xs bg-primary-500 text-white rounded-full px-1.5 py-0.5 min-w-[20px] text-center">
            {{ folder.unread }}
          </span>
        </button>
      </nav>

      <!-- Accounts -->
      <div class="border-t border-white/[0.06] pt-3 mt-3">
        <p class="text-xs text-gray-500 uppercase tracking-wider mb-2 px-2">Konten</p>
        <button
          v-for="account in emailStore.accounts"
          :key="account.id"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-400 truncate rounded-lg transition-colors hover:bg-white/[0.04]"
          :class="{ 'bg-white/[0.06] text-white': emailStore.activeAccountId === account.id }"
          @click="selectAccount(account.id)"
        >
          <div class="w-2 h-2 rounded-full shrink-0" :class="account.is_active ? 'bg-green-500' : 'bg-gray-600'" />
          <span class="truncate">{{ account.email }}</span>
        </button>
        <button
          v-if="emailStore.accounts.length > 0 && emailStore.activeAccountId"
          class="w-full flex items-center gap-2 px-2 py-1.5 text-xs text-gray-500 rounded-lg transition-colors hover:bg-white/[0.04] hover:text-gray-300 mt-1"
          @click="selectAccount(null)"
        >
          Alle Konten anzeigen
        </button>
        <button
          class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-500 rounded-lg transition-colors hover:bg-white/[0.04] hover:text-gray-300 mt-1"
          @click="showAccountModal = true"
        >
          <PlusIcon class="w-4 h-4" />
          Konto hinzufügen
        </button>
      </div>
    </div>

    <!-- Message List -->
    <div class="w-80 shrink-0 border-r border-white/[0.06] flex flex-col">
      <!-- Search bar -->
      <div class="p-3 border-b border-white/[0.06]">
        <div class="relative">
          <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Nachrichten durchsuchen..."
            class="input pl-9 w-full text-sm"
            @keyup.enter="handleSearch"
          />
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="emailStore.messagesLoading" class="flex-1 flex items-center justify-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>

      <!-- Empty state -->
      <div v-else-if="emailStore.messages.length === 0" class="flex-1 flex items-center justify-center p-4">
        <div class="text-center">
          <InboxIcon class="w-12 h-12 text-gray-600 mx-auto mb-3" />
          <p class="text-gray-500 text-sm">Keine Nachrichten</p>
        </div>
      </div>

      <!-- Messages -->
      <div v-else class="flex-1 overflow-y-auto">
        <div
          v-for="msg in emailStore.messages"
          :key="msg.id"
          class="p-3 border-b border-white/[0.06] cursor-pointer hover:bg-white/[0.04] transition-colors"
          :class="{ 'bg-white/[0.04]': emailStore.currentMessage?.id === msg.id }"
          @click="openMessage(msg.id)"
        >
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium truncate" :class="msg.is_read ? 'text-gray-400' : 'text-white'">
              {{ msg.from_name || msg.from_address }}
            </span>
            <div class="flex items-center gap-1 shrink-0 ml-2">
              <StarIcon
                v-if="msg.is_starred"
                class="w-3.5 h-3.5 text-yellow-500"
              />
              <span class="text-xs text-gray-500">{{ formatDate(msg.received_at) }}</span>
            </div>
          </div>
          <p class="text-sm truncate" :class="msg.is_read ? 'text-gray-500' : 'text-gray-300'">
            {{ msg.subject || '(Kein Betreff)' }}
          </p>
          <p class="text-xs text-gray-600 truncate mt-0.5">
            {{ (msg.body_preview || msg.body_text || '').substring(0, 80) }}
          </p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="emailStore.pagination.pages > 1" class="p-2 border-t border-white/[0.06] flex items-center justify-between text-xs text-gray-500">
        <button
          class="px-2 py-1 rounded hover:bg-white/[0.06] disabled:opacity-50"
          :disabled="emailStore.pagination.page <= 1"
          @click="changePage(emailStore.pagination.page - 1)"
        >
          Zurück
        </button>
        <span>{{ emailStore.pagination.page }} / {{ emailStore.pagination.pages }}</span>
        <button
          class="px-2 py-1 rounded hover:bg-white/[0.06] disabled:opacity-50"
          :disabled="emailStore.pagination.page >= emailStore.pagination.pages"
          @click="changePage(emailStore.pagination.page + 1)"
        >
          Weiter
        </button>
      </div>
    </div>

    <!-- Reader Pane -->
    <div class="flex-1 overflow-y-auto">
      <!-- No message selected -->
      <div v-if="!emailStore.currentMessage" class="h-full flex items-center justify-center">
        <div class="text-center">
          <EnvelopeOpenIcon class="w-16 h-16 text-gray-700 mx-auto mb-4" />
          <p class="text-gray-500">Nachricht auswählen zum Lesen</p>
        </div>
      </div>

      <!-- Message content -->
      <div v-else class="p-6">
        <!-- Message header -->
        <div class="mb-6">
          <div class="flex items-start justify-between mb-4">
            <h1 class="text-xl font-semibold text-white">{{ emailStore.currentMessage.subject || '(Kein Betreff)' }}</h1>
            <div class="flex items-center gap-2 shrink-0 ml-4">
              <button
                class="p-1.5 rounded-lg hover:bg-white/[0.06] transition-colors"
                :class="emailStore.currentMessage.is_starred ? 'text-yellow-500' : 'text-gray-500'"
                @click="handleToggleStar(emailStore.currentMessage.id)"
                title="Stern umschalten"
              >
                <StarIcon class="w-5 h-5" />
              </button>
              <button
                class="p-1.5 rounded-lg hover:bg-white/[0.06] transition-colors text-gray-500 hover:text-white"
                @click="handleToggleRead(emailStore.currentMessage.id)"
                title="Lesestatus umschalten"
              >
                <EnvelopeIcon class="w-5 h-5" />
              </button>
              <button
                class="p-1.5 rounded-lg hover:bg-white/[0.06] transition-colors text-gray-500 hover:text-red-400"
                @click="handleDelete(emailStore.currentMessage.id)"
                title="Löschen"
              >
                <TrashIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <div class="card-glass p-4 space-y-2">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-primary-500/20 flex items-center justify-center text-primary-400 font-medium">
                {{ (emailStore.currentMessage.from_name || emailStore.currentMessage.from_address).charAt(0).toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white">
                  {{ emailStore.currentMessage.from_name || emailStore.currentMessage.from_address }}
                </p>
                <p class="text-xs text-gray-500">{{ emailStore.currentMessage.from_address }}</p>
              </div>
              <span class="text-xs text-gray-500 shrink-0">{{ formatDateTime(emailStore.currentMessage.received_at) }}</span>
            </div>

            <div class="text-xs text-gray-500">
              <span>An: {{ formatAddresses(emailStore.currentMessage.to_addresses) }}</span>
              <span v-if="emailStore.currentMessage.cc_addresses && emailStore.currentMessage.cc_addresses.length > 0">
                <br />CC: {{ formatAddresses(emailStore.currentMessage.cc_addresses) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Message body -->
        <div class="card-glass p-6">
          <div
            v-if="emailStore.currentMessage.body_html"
            class="prose prose-invert max-w-none text-sm email-body"
            v-html="sanitizeHtml(emailStore.currentMessage.body_html)"
          />
          <pre v-else class="text-sm text-gray-300 whitespace-pre-wrap font-sans">{{ emailStore.currentMessage.body_text || 'Kein Inhalt' }}</pre>
        </div>
      </div>
    </div>

    <!-- Compose Modal -->
    <Teleport to="body">
      <div v-if="showCompose" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCompose = false" />
        <div class="modal w-full max-w-2xl relative">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">Neue Nachricht</h2>
            <button class="text-gray-400 hover:text-white transition-colors" @click="showCompose = false">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="handleSend" class="space-y-4">
            <!-- Account selector -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Von</label>
              <select v-model="composeForm.account_id" class="select w-full">
                <option v-for="account in emailStore.activeAccounts" :key="account.id" :value="account.id">
                  {{ account.name }} &lt;{{ account.email }}&gt;
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">An</label>
              <input v-model="composeForm.to" type="text" class="input w-full" placeholder="empfaenger@beispiel.de" required />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">CC</label>
              <input v-model="composeForm.cc" type="text" class="input w-full" placeholder="cc@beispiel.de" />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Betreff</label>
              <input v-model="composeForm.subject" type="text" class="input w-full" placeholder="Betreff eingeben..." />
            </div>

            <div>
              <label class="block text-sm text-gray-400 mb-1">Nachricht</label>
              <textarea
                v-model="composeForm.body"
                class="textarea w-full"
                rows="12"
                placeholder="Nachricht schreiben..."
              />
            </div>

            <div class="flex items-center justify-between pt-2">
              <button
                type="button"
                class="btn-secondary"
                @click="handleSaveDraft"
                :disabled="emailStore.sendingMessage"
              >
                Entwurf speichern
              </button>
              <div class="flex items-center gap-3">
                <button type="button" class="btn-secondary" @click="showCompose = false">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary" :disabled="emailStore.sendingMessage">
                  <span v-if="emailStore.sendingMessage">Senden...</span>
                  <span v-else>Senden</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Account Modal -->
    <Teleport to="body">
      <div v-if="showAccountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showAccountModal = false" />
        <div class="modal w-full max-w-lg relative">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-white">{{ editingAccount ? 'Konto bearbeiten' : 'E-Mail-Konto hinzufügen' }}</h2>
            <button class="text-gray-400 hover:text-white transition-colors" @click="closeAccountModal">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="handleSaveAccount" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Anzeigename</label>
                <input v-model="accountForm.name" type="text" class="input w-full" placeholder="Mein E-Mail" required />
              </div>

              <div class="col-span-2">
                <label class="block text-sm text-gray-400 mb-1">E-Mail-Adresse</label>
                <input v-model="accountForm.email" type="email" class="input w-full" placeholder="mail@beispiel.de" required />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">IMAP-Server</label>
                <input v-model="accountForm.imap_host" type="text" class="input w-full" placeholder="imap.beispiel.de" required />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">IMAP-Port</label>
                <input v-model.number="accountForm.imap_port" type="number" class="input w-full" />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">SMTP-Server</label>
                <input v-model="accountForm.smtp_host" type="text" class="input w-full" placeholder="smtp.beispiel.de" required />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">SMTP-Port</label>
                <input v-model.number="accountForm.smtp_port" type="number" class="input w-full" />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">IMAP-Verschlüsselung</label>
                <select v-model="accountForm.imap_encryption" class="select w-full">
                  <option value="ssl">SSL</option>
                  <option value="tls">TLS</option>
                  <option value="none">Keine</option>
                </select>
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">SMTP-Verschlüsselung</label>
                <select v-model="accountForm.smtp_encryption" class="select w-full">
                  <option value="ssl">SSL</option>
                  <option value="tls">TLS</option>
                  <option value="none">Keine</option>
                </select>
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">Benutzername</label>
                <input v-model="accountForm.username" type="text" class="input w-full" placeholder="Benutzername" required />
              </div>

              <div>
                <label class="block text-sm text-gray-400 mb-1">Passwort</label>
                <input v-model="accountForm.password" type="password" class="input w-full" :placeholder="editingAccount ? 'Unverändert lassen...' : 'Passwort'" :required="!editingAccount" />
              </div>
            </div>

            <div class="flex items-center justify-between pt-2">
              <button
                v-if="editingAccount"
                type="button"
                class="btn-danger"
                @click="handleDeleteAccount"
              >
                Löschen
              </button>
              <div v-else />
              <div class="flex items-center gap-3">
                <button type="button" class="btn-secondary" @click="closeAccountModal">
                  Abbrechen
                </button>
                <button type="submit" class="btn-primary">
                  {{ editingAccount ? 'Speichern' : 'Hinzufügen' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useEmailStore } from '@/stores/email'
import { sanitizeHtml } from '@/core/services/sanitize'
import {
  PencilSquareIcon,
  InboxIcon,
  PaperAirplaneIcon,
  DocumentTextIcon,
  TrashIcon,
  ExclamationTriangleIcon,
  StarIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'
import type { Component } from 'vue'

const emailStore = useEmailStore()

// Local state
const showCompose = ref(false)
const showAccountModal = ref(false)
const editingAccount = ref<string | null>(null)
const searchQuery = ref('')

const composeForm = ref({
  account_id: '',
  to: '',
  cc: '',
  subject: '',
  body: '',
})

const accountForm = ref({
  name: '',
  email: '',
  imap_host: '',
  imap_port: 993,
  imap_encryption: 'ssl' as 'ssl' | 'tls' | 'none',
  smtp_host: '',
  smtp_port: 587,
  smtp_encryption: 'tls' as 'ssl' | 'tls' | 'none',
  username: '',
  password: '',
})

// Folder icon mapping
const folderIcons: Record<string, Component> = {
  'INBOX': InboxIcon,
  'Gesendet': PaperAirplaneIcon,
  'Entwürfe': DocumentTextIcon,
  'Papierkorb': TrashIcon,
  'Spam': ExclamationTriangleIcon,
}

function getFolderIcon(folderId: string): Component {
  return folderIcons[folderId] || InboxIcon
}

// Format helpers
function formatDate(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const dayMs = 86400000

  if (diff < dayMs) {
    return date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
  }
  if (diff < dayMs * 7) {
    return date.toLocaleDateString('de-DE', { weekday: 'short' })
  }
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}

function formatDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString('de-DE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatAddresses(addresses: { email: string; name?: string }[]): string {
  if (!addresses || !Array.isArray(addresses)) return ''
  return addresses.map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ')
}

// Actions
async function selectFolder(folderId: string): Promise<void> {
  emailStore.setActiveFolder(folderId)
  await emailStore.loadMessages({
    folder: folderId,
    account_id: emailStore.activeAccountId || undefined,
  })
}

async function selectAccount(accountId: string | null): Promise<void> {
  emailStore.setActiveAccount(accountId)
  await emailStore.loadMessages({
    folder: emailStore.activeFolder,
    account_id: accountId || undefined,
  })
  await emailStore.loadStats()
}

async function openMessage(id: string): Promise<void> {
  await emailStore.loadMessage(id)
}

async function handleSearch(): Promise<void> {
  await emailStore.loadMessages({
    folder: emailStore.activeFolder,
    account_id: emailStore.activeAccountId || undefined,
    search: searchQuery.value || undefined,
  })
}

async function changePage(page: number): Promise<void> {
  await emailStore.loadMessages({
    folder: emailStore.activeFolder,
    account_id: emailStore.activeAccountId || undefined,
    search: searchQuery.value || undefined,
    page,
  })
}

async function handleToggleStar(id: string): Promise<void> {
  await emailStore.toggleStar(id)
}

async function handleToggleRead(id: string): Promise<void> {
  await emailStore.toggleRead(id)
}

async function handleDelete(id: string): Promise<void> {
  await emailStore.deleteMessage(id)
}

async function handleSend(): Promise<void> {
  if (!composeForm.value.account_id && emailStore.defaultAccount) {
    composeForm.value.account_id = emailStore.defaultAccount.id
  }

  await emailStore.sendMessage({
    account_id: composeForm.value.account_id,
    to: composeForm.value.to,
    cc: composeForm.value.cc || undefined,
    subject: composeForm.value.subject,
    body: composeForm.value.body,
  })

  showCompose.value = false
  resetComposeForm()

  // Refresh messages if in sent folder
  if (emailStore.activeFolder === 'Gesendet') {
    await emailStore.loadMessages({ folder: 'Gesendet', account_id: emailStore.activeAccountId || undefined })
  }
}

async function handleSaveDraft(): Promise<void> {
  if (!composeForm.value.account_id && emailStore.defaultAccount) {
    composeForm.value.account_id = emailStore.defaultAccount.id
  }

  await emailStore.sendMessage({
    account_id: composeForm.value.account_id,
    to: composeForm.value.to || 'draft@local',
    subject: composeForm.value.subject,
    body: composeForm.value.body,
    is_draft: true,
  })

  showCompose.value = false
  resetComposeForm()
}

function resetComposeForm(): void {
  composeForm.value = {
    account_id: emailStore.defaultAccount?.id || '',
    to: '',
    cc: '',
    subject: '',
    body: '',
  }
}

async function handleSaveAccount(): Promise<void> {
  if (editingAccount.value) {
    const data: Record<string, unknown> = { ...accountForm.value }
    if (!data.password) delete data.password
    await emailStore.updateAccount(editingAccount.value, data)
  } else {
    await emailStore.createAccount({ ...accountForm.value })
  }

  closeAccountModal()
  await emailStore.loadAccounts()
  await emailStore.loadFolders()
  await emailStore.loadStats()
}

async function handleDeleteAccount(): Promise<void> {
  if (!editingAccount.value) return
  await emailStore.deleteAccount(editingAccount.value)
  closeAccountModal()
  await emailStore.loadFolders()
  await emailStore.loadStats()
}

function closeAccountModal(): void {
  showAccountModal.value = false
  editingAccount.value = null
  accountForm.value = {
    name: '',
    email: '',
    imap_host: '',
    imap_port: 993,
    imap_encryption: 'ssl',
    smtp_host: '',
    smtp_port: 587,
    smtp_encryption: 'tls',
    username: '',
    password: '',
  }
}

// Watch for compose modal to set default account
watch(showCompose, (val) => {
  if (val && emailStore.defaultAccount) {
    composeForm.value.account_id = emailStore.defaultAccount.id
  }
})

// Initialize
onMounted(async () => {
  await emailStore.loadAccounts()
  await emailStore.loadFolders()
  await emailStore.loadStats()
  await emailStore.loadMessages({ folder: 'INBOX' })
})
</script>

<style scoped>
.email-body :deep(img) {
  max-width: 100%;
  height: auto;
}

.email-body :deep(a) {
  color: rgb(var(--color-primary-400));
  text-decoration: underline;
}

.email-body :deep(table) {
  max-width: 100%;
  overflow-x: auto;
}
</style>
