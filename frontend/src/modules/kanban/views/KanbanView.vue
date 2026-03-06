<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
import { useToast } from '@/composables/useToast'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import draggable from 'vuedraggable'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  XMarkIcon,
  ViewColumnsIcon,
  CalendarIcon,
  TagIcon,
  FlagIcon,
  UserCircleIcon,
  PhotoIcon,
  PaperClipIcon,
  LinkIcon,
  DocumentIcon,
  ListBulletIcon,
  CodeBracketIcon,
  BookmarkIcon,
  CheckCircleIcon,
  ChatBubbleLeftIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  ShareIcon,
  UserPlusIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleIconSolid } from '@heroicons/vue/24/solid'
import { useAuthStore } from '@/stores/auth'
import { ViewColumnsIcon as ViewColumnsIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const uiStore = useUiStore()
const { t } = useI18n()
const projectStore = useProjectStore()
const authStore = useAuthStore()
const toast = useToast()
const { confirm } = useConfirmDialog()

// Watch for project changes
watch(() => projectStore.selectedProjectId, () => {
  fetchBoards()
  selectedBoard.value = null
})

// State
const boards = ref([])
const selectedBoard = ref(null)
const loading = ref(true)
const showBoardModal = ref(false)
const showCardModal = ref(false)
const showColumnModal = ref(false)
const editingBoard = ref(null)
const editingCard = ref(null)
const editingColumn = ref(null)
const targetColumnId = ref(null)
const boardUsers = ref([])

// Board form
const boardForm = ref({
  title: '',
  description: '',
  color: '#6366f1',
})

// Column form
const columnForm = ref({
  title: '',
  color: '#3B82F6',
  wip_limit: null,
  is_completed: false,
})

// Card form
const cardForm = ref({
  title: '',
  description: '',
  priority: 'medium',
  due_date: null,
  labels: [],
  color: null,
  assigned_to: null,
  attachments: [],
})

// Attachment upload state
const isUploadingAttachment = ref(false)
const attachmentPreview = ref(null)

// Tags state
const showTagModal = ref(false)
const tagForm = ref({ name: '', color: '#6B7280' })
const editingTag = ref(null)

// Links state
const showLinkModal = ref(false)
const linkType = ref('document')
const linkSearchQuery = ref('')
const linkableItems = ref([])
const isLoadingLinkables = ref(false)

// Checklists state
const checklists = ref([])
const newChecklistTitle = ref('')
const newItemContents = ref({}) // { checklistId: 'content' }

// Comments state
const comments = ref([])
const newComment = ref('')
const editingComment = ref(null)
const editCommentContent = ref('')

// Activities state
const activities = ref([])
const showActivities = ref(false)

// Share state
const showShareModal = ref(false)
const boardShares = ref([])
const loadingShares = ref(false)
const newShareEmail = ref('')
const newSharePermission = ref('view')

// Public share state
const publicShareInfo = ref(null)
const loadingPublicShare = ref(false)
const publicShareForm = ref({ username: '', password: '', can_edit: false, mode: 'readonly' })
const publicShareUrl = ref('')
const publicShareCopied = ref(false)

// Colors for boards
const boardColors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Priority options
const priorities = [
  { value: 'low', label: t('kanban.low'), color: 'bg-gray-500' },
  { value: 'medium', label: t('kanban.medium'), color: 'bg-blue-500' },
  { value: 'high', label: t('kanban.high'), color: 'bg-orange-500' },
  { value: 'urgent', label: t('kanban.urgent'), color: 'bg-red-500' },
]

// Label colors
const labelColors = [
  { name: 'red', bg: 'bg-red-500', text: 'text-red-500' },
  { name: 'orange', bg: 'bg-orange-500', text: 'text-orange-500' },
  { name: 'yellow', bg: 'bg-yellow-500', text: 'text-yellow-500' },
  { name: 'green', bg: 'bg-green-500', text: 'text-green-500' },
  { name: 'blue', bg: 'bg-blue-500', text: 'text-blue-500' },
  { name: 'purple', bg: 'bg-purple-500', text: 'text-purple-500' },
  { name: 'pink', bg: 'bg-pink-500', text: 'text-pink-500' },
]

// Fetch boards
async function fetchBoards() {
  loading.value = true
  try {
    const params = projectStore.selectedProjectId
      ? { project_id: projectStore.selectedProjectId }
      : {}
    const response = await api.get('/api/v1/kanban/boards', { params })
    boards.value = response.data.data.items || []
  } catch (error) {
    uiStore.showError(t('kanbanModule.fehlerBeimLadenDerBoards'))
  } finally {
    loading.value = false
  }
}

// Fetch single board with columns and cards
async function fetchBoard(boardId) {
  try {
    const response = await api.get(`/api/v1/kanban/boards/${boardId}`)
    selectedBoard.value = response.data.data
    // Also fetch board users
    await fetchBoardUsers(boardId)
  } catch (error) {
    uiStore.showError(t('kanbanModule.fehlerBeimLadenDesBoards'))
    selectedBoard.value = null
  }
}

// Fetch board users for assignment
async function fetchBoardUsers(boardId) {
  try {
    const response = await api.get(`/api/v1/kanban/boards/${boardId}/users`)
    boardUsers.value = response.data.data.users || []
  } catch (error) {
    boardUsers.value = []
  }
}

// Open board modal
function openBoardModal(board = null) {
  editingBoard.value = board
  if (board) {
    boardForm.value = {
      title: board.title,
      description: board.description || '',
      color: board.color || '#6366f1',
    }
  } else {
    boardForm.value = { title: '', description: '', color: '#6366f1' }
  }
  showBoardModal.value = true
}

// Save board
async function saveBoard() {
  if (!boardForm.value.title.trim()) {
    uiStore.showError(t('kanban.titleRequired'))
    return
  }

  try {
    if (editingBoard.value) {
      await api.put(`/api/v1/kanban/boards/${editingBoard.value.id}`, boardForm.value)
      uiStore.showSuccess(t('kanban.boardUpdated'))
      if (selectedBoard.value?.id === editingBoard.value.id) {
        await fetchBoard(selectedBoard.value.id)
      }
    } else {
      const response = await api.post('/api/v1/kanban/boards', boardForm.value)
      const newBoard = response.data.data

      // Link to selected project if one is active
      if (projectStore.selectedProjectId) {
        await projectStore.linkToSelectedProject('kanban_board', newBoard.id)
      }

      uiStore.showSuccess(t('kanban.boardCreated'))
      // Select the new board
      await fetchBoard(newBoard.id)
    }
    await fetchBoards()
    showBoardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('webhooks.bookmarksmodulefehlerbeimspeichern'))
  }
}

// Delete board
async function deleteBoard(board) {
  if (!await confirm({ message: `${t('kanban.confirmDeleteBoard')} \"${board.title}\"?`, type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${board.id}`)
    uiStore.showSuccess(t('kanbanModule.boardGeloescht'))
    if (selectedBoard.value?.id === board.id) {
      selectedBoard.value = null
    }
    await fetchBoards()
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

// Select board
function selectBoard(board) {
  fetchBoard(board.id)
}

// Go back to boards list
function backToBoards() {
  selectedBoard.value = null
}

// Open column modal
function openColumnModal(column = null) {
  editingColumn.value = column
  if (column) {
    columnForm.value = {
      title: column.title,
      color: column.color || '#3B82F6',
      wip_limit: column.wip_limit,
      is_completed: column.is_completed == 1 || column.is_completed === true,
    }
  } else {
    columnForm.value = { title: '', color: '#3B82F6', wip_limit: null, is_completed: false }
  }
  showColumnModal.value = true
}

// Save column
async function saveColumn() {
  if (!columnForm.value.title.trim()) {
    uiStore.showError(t('kanban.titleRequired'))
    return
  }

  try {
    if (editingColumn.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${editingColumn.value.id}`,
        columnForm.value
      )
      uiStore.showSuccess(t('kanban.columnUpdated'))
    } else {
      await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns`, columnForm.value)
      uiStore.showSuccess(t('kanban.columnCreated'))
    }
    await fetchBoard(selectedBoard.value.id)
    showColumnModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('webhooks.bookmarksmodulefehlerbeimspeichern'))
  }
}

// Delete column
async function deleteColumn(column) {
  if (!await confirm({ message: `${t('kanban.confirmDeleteColumn')} \"${column.title}\"?`, type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${column.id}`)
    uiStore.showSuccess(t('kanbanModule.spalteGeloescht'))
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

// Open card modal
function openCardModal(columnId, card = null) {
  targetColumnId.value = columnId
  editingCard.value = card
  attachmentPreview.value = null
  checklists.value = []
  comments.value = []
  activities.value = []
  showActivities.value = false
  newChecklistTitle.value = ''
  newItemContents.value = {}
  newComment.value = ''
  editingComment.value = null

  if (card) {
    cardForm.value = {
      title: card.title,
      description: card.description || '',
      priority: card.priority || 'medium',
      due_date: card.due_date,
      labels: card.labels || [],
      color: card.color,
      assigned_to: card.assigned_to || null,
      attachments: card.attachments || [],
      tags: card.tags || [],
      links: card.links || [],
    }
    // Fetch checklists and comments for existing cards
    fetchChecklists()
    fetchComments()
  } else {
    cardForm.value = {
      title: '',
      description: '',
      priority: 'medium',
      due_date: null,
      labels: [],
      color: null,
      assigned_to: null,
      attachments: [],
      tags: [],
      links: [],
    }
  }
  showCardModal.value = true
}

// Save card
async function saveCard() {
  if (!cardForm.value.title.trim()) {
    uiStore.showError(t('kanban.titleRequired'))
    return
  }

  try {
    if (editingCard.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}`,
        cardForm.value
      )
      uiStore.showSuccess(t('kanban.cardUpdated'))
    } else {
      await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${targetColumnId.value}/cards`,
        cardForm.value
      )
      uiStore.showSuccess(t('kanban.cardCreated'))
    }
    await fetchBoard(selectedBoard.value.id)
    showCardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('webhooks.bookmarksmodulefehlerbeimspeichern'))
  }
}

// Delete card
async function deleteCard(card) {
  if (!await confirm({ message: `${t('kanban.confirmDeleteCard')} \"${card.title}\"?`, type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${card.id}`)
    uiStore.showSuccess(t('kanbanModule.karteGeloescht'))
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

// Handle card drag end
async function onCardDragEnd(columnId, evt) {
  if (!evt.added && !evt.moved) return

  const card = evt.added?.element || evt.moved?.element
  const newIndex = evt.added?.newIndex ?? evt.moved?.newIndex

  try {
    await api.put(`/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${card.id}/move`, {
      column_id: columnId,
      position: newIndex,
    })
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimVerschieben'))
    await fetchBoard(selectedBoard.value.id)
  }
}

// Handle column drag end
async function onColumnDragEnd() {
  // Wait for Vue to update the model before reading column order
  await nextTick()
  const columnIds = selectedBoard.value.columns.map(col => col.id)
  console.log('Reordering columns:', columnIds)
  try {
    const response = await api.put(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns/reorder`, {
      columns: columnIds,
    })
    console.log('Reorder response:', response.data)

    // Sync local state with server response
    if (response.data.data?.columns) {
      const serverOrder = response.data.data.columns
      selectedBoard.value.columns.sort((a, b) => {
        const posA = serverOrder.find(c => c.id === a.id)?.position ?? 999
        const posB = serverOrder.find(c => c.id === b.id)?.position ?? 999
        return posA - posB
      })
    }
  } catch (error) {
    console.error('Reorder error:', error)
    uiStore.showError(t('tickets.fehlerBeimSortieren'))
    await fetchBoard(selectedBoard.value.id)
  }
}

// Toggle label on card form
function toggleLabel(color) {
  const idx = cardForm.value.labels.indexOf(color)
  if (idx >= 0) {
    cardForm.value.labels.splice(idx, 1)
  } else {
    cardForm.value.labels.push(color)
  }
}

// Upload attachment
async function uploadAttachment(event) {
  const file = event.target.files?.[0]
  if (!file) return

  // Only allow when editing existing card
  if (!editingCard.value) {
    uiStore.showError(t('kanbanModule.kanbanmodulebitteerstdiekartespeicherndannbilder'))
    event.target.value = ''
    return
  }

  // Validate file type
  if (!file.type.startsWith('image/')) {
    uiStore.showError(t('kanban.onlyImagesAllowed'))
    event.target.value = ''
    return
  }

  // Validate file size (5MB)
  if (file.size > 5 * 1024 * 1024) {
    uiStore.showError(t('kanbanModule.dateiZuGrossMax5mb'))
    event.target.value = ''
    return
  }

  isUploadingAttachment.value = true

  try {
    const formData = new FormData()
    formData.append('file', file)

    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/attachments`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    )

    // Add to local attachments
    cardForm.value.attachments.push(response.data.data)
    uiStore.showSuccess(t('kanban.imageUploaded'))
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('checklistsModule.fehlerBeimHochladen'))
  } finally {
    isUploadingAttachment.value = false
    event.target.value = ''
  }
}

// Delete attachment
async function deleteAttachment(attachmentId) {
  if (!editingCard.value) return

  if (!await confirm({ message: t('checklistsModule.bildWirklichLoeschen'), type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/attachments/${attachmentId}`
    )

    // Remove from local attachments
    cardForm.value.attachments = cardForm.value.attachments.filter(a => a.id !== attachmentId)
    uiStore.showSuccess(t('checklistsModule.bildGeloescht'))
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

// Get attachment URL
function getAttachmentUrl(filename) {
  return `/api/v1/kanban/attachments/${filename}`
}

// Open attachment preview
function openAttachmentPreview(attachment) {
  attachmentPreview.value = attachment
}

// Close attachment preview
function closeAttachmentPreview() {
  attachmentPreview.value = null
}

// ==================
// Board Share Functions
// ==================

async function fetchBoardShares() {
  if (!selectedBoard.value) return
  loadingShares.value = true
  try {
    const response = await api.get(`/api/v1/kanban/boards/${selectedBoard.value.id}/shares`)
    boardShares.value = response.data.data || []
  } catch (error) {
    uiStore.showError(t('storage.storagefehlerbeimladenderfreigaben'))
    boardShares.value = []
  } finally {
    loadingShares.value = false
  }
}

async function openShareModal() {
  showShareModal.value = true
  newShareEmail.value = ''
  newSharePermission.value = 'view'
  await Promise.all([fetchBoardShares(), fetchPublicShareInfo()])
}

async function addBoardShare() {
  if (!newShareEmail.value.trim()) {
    uiStore.showError(t('kanban.emailRequired'))
    return
  }

  try {
    await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/shares`, {
      email: newShareEmail.value.trim(),
      permission: newSharePermission.value
    })
    uiStore.showSuccess(t('kanbanModule.benutzerHinzugefuegt'))
    newShareEmail.value = ''
    await fetchBoardShares()
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('newsModule.fehlerBeimHinzufuegen'))
  }
}

async function removeBoardShare(userId) {
  if (!await confirm({ message: t('kanbanModule.zugriffWirklichEntfernen'), type: 'danger', confirmText: t('calendarModule.entfernen') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/shares/${userId}`)
    uiStore.showSuccess(t('kanbanModule.zugriffEntfernt'))
    await fetchBoardShares()
  } catch (error) {
    uiStore.showError(t('server.serverfehlerbeimentfernen'))
  }
}

async function updateSharePermission(userId, permission) {
  try {
    await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/shares`, {
      user_id: userId,
      permission
    })
    uiStore.showSuccess(t('projectsModule.berechtigungAktualisiert'))
    await fetchBoardShares()
  } catch (error) {
    uiStore.showError(t('webhooks.bookmarksmodulefehlerbeimaktualisieren'))
  }
}

// ==================
// Public Share Functions
// ==================

async function fetchPublicShareInfo() {
  if (!selectedBoard.value) return
  loadingPublicShare.value = true
  try {
    const response = await api.get(`/api/v1/kanban/boards/${selectedBoard.value.id}/public`)
    publicShareInfo.value = response.data.data
    publicShareUrl.value = publicShareInfo.value.url || ''
  } catch (error) {
    publicShareInfo.value = null
  } finally {
    loadingPublicShare.value = false
  }
}

async function enablePublicShare() {
  const isProtected = publicShareForm.value.mode === 'protected'

  if (isProtected && (!publicShareForm.value.username.trim() || !publicShareForm.value.password.trim())) {
    uiStore.showError(t('kanbanModule.kanbanmodulebenutzernameundpasswortsinderforderlich'))
    return
  }

  loadingPublicShare.value = true
  try {
    const payload = {
      can_edit: publicShareForm.value.can_edit,
    }
    if (isProtected) {
      payload.username = publicShareForm.value.username.trim()
      payload.password = publicShareForm.value.password.trim()
    }

    const response = await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/public`, payload)
    publicShareInfo.value = response.data.data
    publicShareUrl.value = response.data.data.url
    uiStore.showSuccess(t('kanbanModule.oeffentlicherLinkErstellt'))
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('links.bookmarksmodulefehlerbeimerstellen'))
  } finally {
    loadingPublicShare.value = false
  }
}

async function disablePublicShare() {
  if (!await confirm({ message: t('kanbanModule.oeffentlichenZugangWirklichDeaktivieren'), type: 'danger', confirmText: t('kanbanModule.deaktivieren') })) return

  loadingPublicShare.value = true
  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/public`)
    publicShareInfo.value = { active: false }
    publicShareUrl.value = ''
    publicShareForm.value = { username: '', password: '', can_edit: false, mode: 'readonly' }
    uiStore.showSuccess(t('kanbanModule.oeffentlicherZugangDeaktiviert'))
  } catch (error) {
    uiStore.showError(t('contractsModule.fehlerBeimDeaktivieren'))
  } finally {
    loadingPublicShare.value = false
  }
}

async function copyPublicLink() {
  try {
    await navigator.clipboard.writeText(publicShareUrl.value)
    publicShareCopied.value = true
    setTimeout(() => { publicShareCopied.value = false }, 2000)
  } catch (error) {
    uiStore.showError(t('passwordsModule.passwordsmodulefehlerbeimkopieren'))
  }
}

// Format file size
function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// ==================
// Tag Functions
// ==================

function openTagModal(tag = null) {
  editingTag.value = tag
  if (tag) {
    tagForm.value = { name: tag.name, color: tag.color }
  } else {
    tagForm.value = { name: '', color: '#6B7280' }
  }
  showTagModal.value = true
}

async function saveTag() {
  if (!tagForm.value.name.trim()) {
    uiStore.showError(t('kanban.tagNameRequired'))
    return
  }

  try {
    if (editingTag.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/tags/${editingTag.value.id}`,
        tagForm.value
      )
      uiStore.showSuccess(t('system.tagAktualisiert'))
    } else {
      const response = await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/tags`,
        tagForm.value
      )
      selectedBoard.value.tags.push(response.data.data)
      uiStore.showSuccess(t('bookmarksModule.tagErstellt'))
    }
    await fetchBoard(selectedBoard.value.id)
    showTagModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('webhooks.bookmarksmodulefehlerbeimspeichern'))
  }
}

async function deleteTag(tag) {
  if (!await confirm({ message: `${t('kanban.confirmDeleteTag')} \"${tag.name}\"?`, type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/tags/${tag.id}`)
    uiStore.showSuccess(t('bookmarksModule.tagGeloescht'))
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

async function toggleCardTag(tagId) {
  const hasTag = cardForm.value.tags?.some(t => t.id === tagId)
  const tag = selectedBoard.value.tags.find(t => t.id === tagId)

  // For new cards - just update local state
  if (!editingCard.value) {
    if (hasTag) {
      cardForm.value.tags = cardForm.value.tags.filter(t => t.id !== tagId)
    } else {
      if (tag) {
        if (!cardForm.value.tags) cardForm.value.tags = []
        cardForm.value.tags.push(tag)
      }
    }
    return
  }

  // For existing cards - update via API
  try {
    if (hasTag) {
      await api.delete(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/tags/${tagId}`
      )
      cardForm.value.tags = cardForm.value.tags.filter(t => t.id !== tagId)
    } else {
      await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/tags/${tagId}`
      )
      if (tag) {
        if (!cardForm.value.tags) cardForm.value.tags = []
        cardForm.value.tags.push(tag)
      }
    }
  } catch (error) {
    uiStore.showError(t('webhooks.bookmarksmodulefehlerbeimaktualisieren'))
  }
}

// ==================
// Link Functions
// ==================

const linkTypes = [
  { id: 'document', name: t('inboxModule.dokument'), icon: DocumentIcon },
  { id: 'list', name: t('kanban.list'), icon: ListBulletIcon },
  { id: 'snippet', name: t('projectsModule.snippet'), icon: CodeBracketIcon },
  { id: 'bookmark', name: 'Bookmark', icon: BookmarkIcon },
]

function openLinkModal() {
  if (!editingCard.value) {
    uiStore.showError(t('kanbanModule.kanbanmodulebitteerstdiekartespeichern'))
    return
  }
  showLinkModal.value = true
  linkType.value = 'document'
  linkSearchQuery.value = ''
  fetchLinkableItems()
}

async function fetchLinkableItems() {
  isLoadingLinkables.value = true
  try {
    const response = await api.get(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/linkable/${linkType.value}`,
      { params: { search: linkSearchQuery.value } }
    )
    linkableItems.value = response.data.data?.items || []
  } catch (error) {
    console.error('Error fetching linkable items:', error)
    linkableItems.value = []
    uiStore.showError(t('kanbanModule.fehlerBeimLadenDerElemente'))
  } finally {
    isLoadingLinkables.value = false
  }
}

async function addLink(item) {
  if (!editingCard.value) return

  // Check if already linked
  if (cardForm.value.links?.some(l => l.linkable_id === item.id && l.linkable_type === linkType.value)) {
    uiStore.showError(t('kanban.alreadyLinked'))
    return
  }

  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/links`,
      { linkable_type: linkType.value, linkable_id: item.id }
    )
    if (!cardForm.value.links) cardForm.value.links = []
    cardForm.value.links.push(response.data.data)
    uiStore.showSuccess(t('kanban.linked'))
  } catch (error) {
    uiStore.showError(error.response?.data?.message || t('kanbanModule.fehlerBeimVerlinken'))
  }
}

async function removeLink(linkId) {
  if (!editingCard.value) return

  try {
    await api.delete(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/links/${linkId}`
    )
    cardForm.value.links = cardForm.value.links.filter(l => l.id !== linkId)
    uiStore.showSuccess(t('kanban.linkRemoved'))
  } catch (error) {
    uiStore.showError(t('server.serverfehlerbeimentfernen'))
  }
}

function getLinkIcon(type) {
  const typeConfig = linkTypes.find(t => t.id === type)
  return typeConfig?.icon || LinkIcon
}

function getLinkTypeName(type) {
  const typeConfig = linkTypes.find(t => t.id === type)
  return typeConfig?.name || type
}

function navigateToLink(link) {
  const routes = {
    document: `/documents?open=${link.linkable_id}`,
    list: `/lists?open=${link.linkable_id}`,
    snippet: `/snippets?open=${link.linkable_id}`,
    bookmark: link.linkable?.url || '#',
  }
  const route = routes[link.linkable_type]
  if (link.linkable_type === 'bookmark' && link.linkable?.url) {
    window.open(link.linkable.url, '_blank')
  } else if (route) {
    window.location.href = route
  }
}

// Debounced search for linkables
let linkSearchTimeout = null
function onLinkSearchInput() {
  clearTimeout(linkSearchTimeout)
  linkSearchTimeout = setTimeout(() => {
    fetchLinkableItems()
  }, 300)
}

// ==================
// Checklist Functions
// ==================

async function fetchChecklists() {
  if (!editingCard.value) return
  try {
    const response = await api.get(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/checklists`
    )
    checklists.value = response.data.data.checklists || []
  } catch (error) {
    checklists.value = []
  }
}

async function createChecklist() {
  if (!editingCard.value || !newChecklistTitle.value.trim()) return

  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/checklists`,
      { title: newChecklistTitle.value.trim() }
    )
    checklists.value.push(response.data.data)
    newChecklistTitle.value = ''
  } catch (error) {
    uiStore.showError(t('kanbanModule.kanbanmodulefehlerbeimerstellendercheckliste'))
  }
}

async function deleteChecklist(checklistId) {
  if (!await confirm({ message: t('kanbanModule.checklisteWirklichLoeschen'), type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/checklists/${checklistId}`)
    checklists.value = checklists.value.filter(c => c.id !== checklistId)
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

async function addChecklistItem(checklistId) {
  const content = newItemContents.value[checklistId]?.trim()
  if (!content) return

  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/checklists/${checklistId}/items`,
      { content }
    )
    const checklist = checklists.value.find(c => c.id === checklistId)
    if (checklist) {
      checklist.items.push(response.data.data)
    }
    newItemContents.value[checklistId] = ''
  } catch (error) {
    uiStore.showError(t('newsModule.fehlerBeimHinzufuegen'))
  }
}

async function toggleChecklistItem(itemId) {
  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/checklist-items/${itemId}/toggle`
    )
    // Update local state
    for (const checklist of checklists.value) {
      const item = checklist.items.find(i => i.id === itemId)
      if (item) {
        Object.assign(item, response.data.data)
        break
      }
    }
  } catch (error) {
    uiStore.showError(t('webhooks.bookmarksmodulefehlerbeimaktualisieren'))
  }
}

async function deleteChecklistItem(itemId) {
  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/checklist-items/${itemId}`)
    for (const checklist of checklists.value) {
      checklist.items = checklist.items.filter(i => i.id !== itemId)
    }
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

function getChecklistProgress(checklist) {
  if (!checklist.items?.length) return { completed: 0, total: 0, percent: 0 }
  const completed = checklist.items.filter(i => i.is_completed).length
  const total = checklist.items.length
  return { completed, total, percent: Math.round((completed / total) * 100) }
}

// ==================
// Comment Functions
// ==================

async function fetchComments() {
  if (!editingCard.value) return
  try {
    const response = await api.get(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/comments`
    )
    comments.value = response.data.data.comments || []
  } catch (error) {
    comments.value = []
  }
}

async function addCommentAction() {
  if (!editingCard.value || !newComment.value.trim()) return

  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/comments`,
      { content: newComment.value.trim() }
    )
    comments.value.unshift(response.data.data)
    newComment.value = ''
  } catch (error) {
    uiStore.showError(t('newsModule.fehlerBeimHinzufuegen'))
  }
}

function startEditComment(comment) {
  editingComment.value = comment.id
  editCommentContent.value = comment.content
}

async function saveEditComment(commentId) {
  if (!editCommentContent.value.trim()) return

  try {
    await api.put(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/comments/${commentId}`,
      { content: editCommentContent.value.trim() }
    )
    const comment = comments.value.find(c => c.id === commentId)
    if (comment) {
      comment.content = editCommentContent.value.trim()
    }
    editingComment.value = null
    editCommentContent.value = ''
  } catch (error) {
    uiStore.showError(t('webhooks.bookmarksmodulefehlerbeimspeichern'))
  }
}

async function deleteCommentAction(commentId) {
  if (!await confirm({ message: t('tickets.kommentarWirklichLoeschen'), type: 'danger', confirmText: t('common.delete') })) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/comments/${commentId}`)
    comments.value = comments.value.filter(c => c.id !== commentId)
  } catch (error) {
    uiStore.showError(t('bookmarksModule.fehlerBeimLoeschen'))
  }
}

function formatCommentDate(dateStr) {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return t('kanban.justNow')
  if (diffMins < 60) return `${diffMins} ${t('kanban.minutesAgo')}`
  if (diffHours < 24) return `${diffHours} ${t('kanban.hoursAgo')}`
  if (diffDays < 7) return `${diffDays} ${t('kanban.daysAgo')}`
  return date.toLocaleDateString('de-DE')
}

// ==================
// Activity Functions
// ==================

async function fetchActivities() {
  if (!editingCard.value) return
  try {
    const response = await api.get(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/activities`
    )
    activities.value = response.data.data.activities || []
  } catch (error) {
    activities.value = []
  }
}

function getActivityLabel(activity) {
  const labels = {
    'card_created': t('kanban.actCardCreated'),
    'card_moved': `${t('kanban.actCardMoved')} "${activity.details?.from_column}" → "${activity.details?.to_column}"`,
    'card_updated': `hat ${activity.details?.field === 'title' ? 'den Titel' : t('kanbanModule.dieBeschreibung')} geändert`,
    'assignee_added': `${t('kanban.actAssigneeAdded')} ${activity.details?.assignee_name}`,
    'assignee_removed': `${t('kanban.actAssigneeRemoved')} ${activity.details?.assignee_name}`,
    'flag_changed': `${t('kanban.actPriorityChanged')} "${activity.details?.old_flag}" → "${activity.details?.new_flag}"`,
    'tag_added': `${t('kanban.actTagAdded')} "${activity.details?.tag_name}"`,
    'tag_removed': `${t('kanban.actTagRemoved')} "${activity.details?.tag_name}"`,
    'checklist_item_completed': `${t('kanban.actChecked')} "${activity.details?.item_text}"`,
    'checklist_item_uncompleted': `${t('kanban.actUnchecked')} "${activity.details?.item_text}"`,
    'comment_added': t('kanbanModule.hatEinenKommentarHinzugefuegt'),
    'due_date_set': `${t('kanban.actDueDateSet')} ${activity.details?.due_date}`,
    'due_date_removed': t('kanbanModule.hatDasFaelligkeitsdatumEntfernt'),
  }
  return labels[activity.action] || activity.action
}

// Get priority info
function getPriorityInfo(priority) {
  return priorities.find(p => p.value === priority) || priorities[1]
}

// Get label color class
function getLabelColorClass(color) {
  const label = labelColors.find(l => l.name === color)
  return label?.bg || 'bg-gray-500'
}

// Format date
function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
}

// Check if date is overdue
function isOverdue(dateStr) {
  if (!dateStr) return false
  return new Date(dateStr) < new Date()
}

onMounted(async () => {
  await fetchBoards()

  // Check for ?open=id query parameter to auto-open a board
  const openId = route.query.open
  if (openId) {
    await fetchBoard(openId)
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          v-if="selectedBoard"
          @click="backToBoards"
          class="p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedBoard ? selectedBoard.title : $t('kanban.title') }}
          </h1>
          <p v-if="selectedBoard?.description" class="text-gray-400 text-sm mt-1">
            {{ selectedBoard.description }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="selectedBoard && selectedBoard.user_id === authStore.user?.id"
          @click="openShareModal()"
          class="px-4 py-2 bg-white/[0.04] text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
          :title="$t('kanban.shareBoard')"
        >
          <ShareIcon class="w-5 h-5" />
          <span class="hidden sm:inline">{{ $t('kanban.share') }}</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openTagModal()"
          class="px-4 py-2 bg-white/[0.04] text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
          :title="$t('kanban.manageTags')"
        >
          <TagIcon class="w-5 h-5" />
          <span class="hidden sm:inline">Tags</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openColumnModal()"
          class="px-4 py-2 bg-white/[0.04] text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>{{ $t('kanban.column') }}</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openBoardModal(selectedBoard)"
          class="px-4 py-2 bg-white/[0.04] text-white rounded-lg hover:bg-white/[0.04] transition-colors flex items-center gap-2"
        >
          <PencilIcon class="w-5 h-5" />
          <span>{{ $t('common.edit') }}</span>
        </button>
        <button
          v-if="!selectedBoard"
          @click="openBoardModal()"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>{{ $t('kanban.newBoard') }}</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Boards Grid (when no board selected) -->
    <div v-else-if="!selectedBoard" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="board in boards"
        :key="board.id"
        @click="selectBoard(board)"
        class="bg-white/[0.04] border border-white/[0.06] rounded-xl p-4 cursor-pointer hover:border-white/[0.06] transition-all group"
      >
        <div class="flex items-start justify-between mb-3">
          <div
            class="w-10 h-10 rounded-lg flex items-center justify-center"
            :style="{ backgroundColor: board.color || '#6366f1' }"
          >
            <ViewColumnsIconSolid class="w-6 h-6 text-white" />
          </div>
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              @click.stop="openBoardModal(board)"
              class="p-1.5 text-gray-400 hover:text-white hover:bg-white/[0.08] rounded"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click.stop="deleteBoard(board)"
              class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-white/[0.08] rounded"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
        <h3 class="text-white font-semibold mb-1">{{ board.title }}</h3>
        <p v-if="board.description" class="text-gray-400 text-sm line-clamp-2 mb-3">
          {{ board.description }}
        </p>
        <div class="flex items-center gap-4 text-sm text-gray-500">
          <span>{{ board.column_count || 0 }} {{ $t('kanban.columns') }}</span>
          <span>{{ board.card_count || 0 }} {{ $t('kanban.cards') }}</span>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="boards.length === 0"
        @click="openBoardModal()"
        class="bg-white/[0.04] border-2 border-dashed border-white/[0.06] rounded-xl p-8 cursor-pointer hover:border-white/[0.08] transition-colors flex flex-col items-center justify-center text-center col-span-full"
      >
        <ViewColumnsIcon class="w-12 h-12 text-gray-500 mb-3" />
        <p class="text-gray-400">{{ $t('kanbanModule.keinBoardVorhanden') }}</p>
        <p class="text-primary-500 mt-1">{{ $t('kanban.clickToCreate') }}</p>
      </div>
    </div>

    <!-- Board View (columns with cards) -->
    <div v-else class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 200px)">
      <draggable
        v-model="selectedBoard.columns"
        group="columns"
        item-key="id"
        handle=".column-handle"
        class="flex gap-4"
        @end="onColumnDragEnd"
      >
        <template #item="{ element: column }">
          <div class="flex-shrink-0 w-80 bg-white/[0.04] rounded-xl border border-white/[0.06]">
            <!-- Column Header -->
            <div class="p-3 border-b border-white/[0.06] column-handle cursor-move">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div
                    class="w-3 h-3 rounded-full"
                    :style="{ backgroundColor: column.color || '#3B82F6' }"
                  ></div>
                  <h3 class="font-semibold text-white">{{ column.title }}</h3>
                  <CheckCircleIconSolid
                    v-if="column.is_completed == 1 || column.is_completed === true"
                    class="w-4 h-4 text-green-500"
                    :title="$t('kanban.completedColumn')"
                  />
                  <span class="text-xs text-gray-500 bg-white/[0.04] px-2 py-0.5 rounded-full">
                    {{ column.cards?.length || 0 }}
                    <template v-if="column.wip_limit">/ {{ column.wip_limit }}</template>
                  </span>
                </div>
                <div class="flex items-center gap-1">
                  <button
                    @click="openCardModal(column.id)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded"
                  >
                    <PlusIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openColumnModal(column)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteColumn(column)"
                    class="p-1 text-gray-400 hover:text-red-400 hover:bg-white/[0.04] rounded"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Cards -->
            <div class="p-2 space-y-2 max-h-[calc(100vh-300px)] overflow-y-auto">
              <draggable
                :list="column.cards"
                group="cards"
                item-key="id"
                class="space-y-2 min-h-[40px]"
                @change="(evt) => onCardDragEnd(column.id, evt)"
              >
                <template #item="{ element: card }">
                  <div
                    @click="openCardModal(column.id, card)"
                    class="bg-white/[0.04] rounded-lg p-3 cursor-pointer hover:bg-white/[0.04] transition-colors group"
                    :class="{ 'border-l-4': card.color }"
                    :style="card.color ? { borderLeftColor: card.color } : {}"
                  >
                    <!-- Labels -->
                    <div v-if="card.labels?.length" class="flex flex-wrap gap-1 mb-2">
                      <span
                        v-for="label in card.labels"
                        :key="label"
                        class="w-8 h-1.5 rounded-full"
                        :class="getLabelColorClass(label)"
                      ></span>
                    </div>

                    <!-- Tags -->
                    <div v-if="card.tags?.length" class="flex flex-wrap gap-1 mb-2">
                      <span
                        v-for="tag in card.tags"
                        :key="tag.id"
                        class="px-1.5 py-0.5 text-[10px] rounded font-medium"
                        :style="{ backgroundColor: tag.color + '30', color: tag.color }"
                      >
                        {{ tag.name }}
                      </span>
                    </div>

                    <!-- Title -->
                    <p class="text-white text-sm font-medium mb-2">{{ card.title }}</p>

                    <!-- Description preview -->
                    <p v-if="card.description" class="text-gray-400 text-xs line-clamp-2 mb-2">
                      {{ card.description }}
                    </p>

                    <!-- Attachment thumbnails -->
                    <div v-if="card.attachments?.length" class="flex flex-wrap gap-1 mb-2">
                      <div
                        v-for="attachment in card.attachments.slice(0, 3)"
                        :key="attachment.id"
                        class="w-12 h-12 rounded overflow-hidden bg-white/[0.08]"
                      >
                        <img
                          :src="getAttachmentUrl(attachment.filename)"
                          :alt="attachment.original_name"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div
                        v-if="card.attachments.length > 3"
                        class="w-12 h-12 rounded bg-white/[0.08] flex items-center justify-center text-xs text-gray-400"
                      >
                        +{{ card.attachments.length - 3 }}
                      </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <!-- Priority -->
                        <span
                          class="w-2 h-2 rounded-full"
                          :class="getPriorityInfo(card.priority).color"
                          :title="getPriorityInfo(card.priority).label"
                        ></span>
                        <!-- Due date -->
                        <span
                          v-if="card.due_date"
                          class="text-xs flex items-center gap-1"
                          :class="isOverdue(card.due_date) ? 'text-red-400' : 'text-gray-500'"
                        >
                          <CalendarIcon class="w-3 h-3" />
                          {{ formatDate(card.due_date) }}
                        </span>
                        <!-- Assignee -->
                        <span
                          v-if="card.assignee"
                          class="text-xs flex items-center gap-1 text-gray-400"
                          :title="card.assignee.username"
                        >
                          <div class="w-5 h-5 rounded-full bg-primary-600 flex items-center justify-center text-[10px] text-white font-medium">
                            {{ card.assignee.username?.[0]?.toUpperCase() || '?' }}
                          </div>
                        </span>
                        <!-- Attachment count -->
                        <span
                          v-if="card.attachments?.length && !card.attachments.slice(0, 3).length"
                          class="text-xs flex items-center gap-1 text-gray-500"
                        >
                          <PaperClipIcon class="w-3 h-3" />
                          {{ card.attachments.length }}
                        </span>
                        <!-- Link count -->
                        <span
                          v-if="card.links?.length"
                          class="text-xs flex items-center gap-1 text-gray-500"
                        >
                          <LinkIcon class="w-3 h-3" />
                          {{ card.links.length }}
                        </span>
                      </div>

                      <!-- Actions -->
                      <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          @click.stop="deleteCard(card)"
                          class="p-1 text-gray-400 hover:text-red-400 rounded"
                        >
                          <TrashIcon class="w-3 h-3" />
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </draggable>

              <!-- Add card button -->
              <button
                @click="openCardModal(column.id)"
                class="w-full p-2 text-gray-400 hover:text-white hover:bg-white/[0.04] rounded-lg transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <PlusIcon class="w-4 h-4" />
                <span>{{ $t('kanban.addCard') }}</span>
              </button>
            </div>
          </div>
        </template>
      </draggable>

      <!-- Add column button -->
      <div
        @click="openColumnModal()"
        class="flex-shrink-0 w-80 bg-white/[0.02] border-2 border-dashed border-white/[0.06] rounded-xl p-4 cursor-pointer hover:border-white/[0.08] transition-colors flex items-center justify-center"
      >
        <div class="text-center">
          <PlusIcon class="w-8 h-8 text-gray-500 mx-auto mb-2" />
          <p class="text-gray-400">{{ $t('kanban.addColumn') }}</p>
        </div>
      </div>
    </div>

    <!-- Board Modal -->
    <Teleport to="body">
      <div
        v-if="showBoardModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"

      >
        <div class="modal w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">
              {{ editingBoard ? $t('kanban.editBoard') : $t('kanban.newBoard') }}
            </h2>
            <button
              @click="showBoardModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('kanban.titleLabel') }} *</label>
              <input
                v-model="boardForm.title"
                type="text"
                class="input"
                placeholder="Board Name"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('common.description') }}</label>
              <textarea
                v-model="boardForm.description"
                rows="3"
                class="textarea"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('common.color') }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in boardColors"
                  :key="color"
                  @click="boardForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': boardForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-white/[0.06]">
            <button
              @click="showBoardModal = false"
              class="btn-secondary"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="saveBoard"
              class="btn-primary"
            >
              {{ editingBoard ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Column Modal -->
    <Teleport to="body">
      <div
        v-if="showColumnModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"

      >
        <div class="modal w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">
              {{ editingColumn ? $t('kanban.editColumn') : $t('kanban.newColumn') }}
            </h2>
            <button
              @click="showColumnModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('kanban.titleLabel') }} *</label>
              <input
                v-model="columnForm.title"
                type="text"
                class="input"
                placeholder="Spaltenname"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('common.color') }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="color in boardColors"
                  :key="color"
                  @click="columnForm.color = color"
                  class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                  :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': columnForm.color === color }"
                  :style="{ backgroundColor: color }"
                ></button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('kanban.wipLimit') }}</label>
              <input
                v-model.number="columnForm.wip_limit"
                type="number"
                min="0"
                class="input"
                placeholder="Max. Anzahl Karten"
              />
            </div>

            <div class="flex items-center gap-3 p-3 bg-white/[0.04] rounded-lg">
              <input
                v-model="columnForm.is_completed"
                type="checkbox"
                id="is_completed"
                class="w-4 h-4 rounded border-white/[0.08] bg-white/[0.04] text-green-500 focus:ring-green-500 focus:ring-offset-0"
              />
              <label for="is_completed" class="flex-1">
                <span class="block text-sm font-medium text-gray-300">{{ $t('kanban.markCompleted') }}</span>
                <span class="text-xs text-gray-500">{{ $t('kanban.completedDescription') }}</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-white/[0.06]">
            <button
              @click="showColumnModal = false"
              class="btn-secondary"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="saveColumn"
              class="btn-primary"
            >
              {{ editingColumn ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Card Modal -->
    <Teleport to="body">
      <div
        v-if="showCardModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4"

      >
        <div class="modal w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-white/[0.06]">
            <h2 class="text-lg font-semibold text-white">
              {{ editingCard ? $t('kanban.editCard') : $t('kanban.newCard') }}
            </h2>
            <button
              @click="showCardModal = false"
              class="p-1 text-gray-400 hover:text-white rounded"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="flex-1 overflow-hidden flex">
            <!-- Left Column: Main Content -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('kanban.titleLabel') }} *</label>
                <input
                  v-model="cardForm.title"
                  type="text"
                  class="input"
                  placeholder="Kartenname"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('common.description') }}</label>
                <textarea
                  v-model="cardForm.description"
                  rows="3"
                  class="textarea"
                  placeholder="Details zur Karte..."
                ></textarea>
              </div>

              <!-- Checklists Section - directly after description -->
              <div v-if="editingCard">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <ClipboardDocumentListIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.checklists') }}
                </label>

                <div class="space-y-3 mb-3">
                  <div
                    v-for="checklist in checklists"
                    :key="checklist.id"
                    class="bg-white/[0.04] rounded-lg p-3"
                  >
                    <div class="flex items-center justify-between mb-2">
                      <h4 class="text-white font-medium text-sm">{{ checklist.title }}</h4>
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">
                          {{ getChecklistProgress(checklist).completed }}/{{ getChecklistProgress(checklist).total }}
                        </span>
                        <button
                          @click="deleteChecklist(checklist.id)"
                          class="p-1 text-gray-400 hover:text-red-400"
                        >
                          <TrashIcon class="w-3 h-3" />
                        </button>
                      </div>
                    </div>

                    <div v-if="checklist.items?.length" class="h-1 bg-white/[0.08] rounded-full mb-2 overflow-hidden">
                      <div
                        class="h-full bg-green-500 transition-all duration-300"
                        :style="{ width: getChecklistProgress(checklist).percent + '%' }"
                      ></div>
                    </div>

                    <div class="space-y-1">
                      <div
                        v-for="item in checklist.items"
                        :key="item.id"
                        class="flex items-center gap-2 group"
                      >
                        <button
                          @click="toggleChecklistItem(item.id)"
                          class="flex-shrink-0 text-gray-400 hover:text-green-400"
                        >
                          <CheckCircleIconSolid v-if="item.is_completed" class="w-5 h-5 text-green-500" />
                          <CheckCircleIcon v-else class="w-5 h-5" />
                        </button>
                        <span
                          class="flex-1 text-sm"
                          :class="item.is_completed ? 'text-gray-500 line-through' : 'text-gray-300'"
                        >
                          {{ item.content }}
                        </span>
                        <button
                          @click="deleteChecklistItem(item.id)"
                          class="p-1 text-gray-400 hover:text-red-400 opacity-0 group-hover:opacity-100"
                        >
                          <XMarkIcon class="w-3 h-3" />
                        </button>
                      </div>
                    </div>

                    <div class="mt-2 flex gap-2">
                      <input
                        v-model="newItemContents[checklist.id]"
                        type="text"
                        class="flex-1 bg-white/[0.08] border border-white/[0.08] rounded px-2 py-1 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                        :placeholder="$t('kanban.newEntry')"
                        @keydown.enter="addChecklistItem(checklist.id)"
                      />
                      <button
                        @click="addChecklistItem(checklist.id)"
                        class="px-2 py-1 bg-primary-600 text-white rounded text-sm hover:bg-primary-500"
                      >
                        <PlusIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <div class="flex gap-2">
                  <input
                    v-model="newChecklistTitle"
                    type="text"
                    class="flex-1 input text-sm"
                    :placeholder="$t('kanban.newChecklist')"
                    @keydown.enter="createChecklist"
                  />
                  <button
                    @click="createChecklist"
                    class="px-3 py-2 bg-white/[0.08] text-gray-300 rounded-lg hover:bg-white/[0.08] transition-colors"
                    :disabled="!newChecklistTitle.trim()"
                  >
                    <PlusIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">
                    <FlagIcon class="w-4 h-4 inline mr-1" />
                    {{ $t('kanban.priority') }}
                  </label>
                  <select
                    v-model="cardForm.priority"
                    class="select"
                  >
                    <option v-for="p in priorities" :key="p.value" :value="p.value">
                      {{ p.label }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">
                    <CalendarIcon class="w-4 h-4 inline mr-1" />
                    {{ $t('kanban.dueDate') }}
                  </label>
                  <input
                    v-model="cardForm.due_date"
                    type="date"
                    class="input"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  <UserCircleIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.assignedTo') }}
                </label>
                <select
                  v-model="cardForm.assigned_to"
                  class="select"
                >
                  <option :value="null">{{ $t('kanban.nobody') }}</option>
                  <option v-for="user in boardUsers" :key="user.id" :value="user.id">
                    {{ user.username }} ({{ user.email }})
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <TagIcon class="w-4 h-4 inline mr-1" />
                  Labels
                </label>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="label in labelColors"
                    :key="label.name"
                    @click="toggleLabel(label.name)"
                    class="w-10 h-6 rounded transition-all"
                    :class="[
                      label.bg,
                      cardForm.labels.includes(label.name) ? 'ring-2 ring-white' : 'opacity-50 hover:opacity-100'
                    ]"
                  ></button>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('kanban.cardColor') }}</label>
                <div class="flex flex-wrap gap-2">
                  <button
                    @click="cardForm.color = null"
                    class="w-8 h-8 rounded-lg bg-white/[0.08] flex items-center justify-center transition-transform hover:scale-110"
                    :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': !cardForm.color }"
                  >
                    <XMarkIcon class="w-4 h-4 text-gray-400" />
                  </button>
                  <button
                    v-for="color in boardColors"
                    :key="color"
                    @click="cardForm.color = color"
                    class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                    :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': cardForm.color === color }"
                    :style="{ backgroundColor: color }"
                  ></button>
                </div>
              </div>

              <!-- Attachments -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <PhotoIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.images') }}
                </label>

                <div v-if="cardForm.attachments.length" class="grid grid-cols-4 gap-2 mb-3">
                  <div
                    v-for="attachment in cardForm.attachments"
                    :key="attachment.id"
                    class="relative group aspect-square bg-white/[0.04] rounded-lg overflow-hidden"
                  >
                    <img
                      :src="getAttachmentUrl(attachment.filename)"
                      :alt="attachment.original_name"
                      class="w-full h-full object-cover cursor-pointer"
                      @click="openAttachmentPreview(attachment)"
                    />
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                      <button
                        @click="deleteAttachment(attachment.id)"
                        class="p-1.5 bg-red-600 rounded-lg text-white hover:bg-red-500"
                      >
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>

                <div
                  v-if="editingCard"
                  class="border-2 border-dashed border-white/[0.06] rounded-lg p-3 text-center hover:border-primary-500 transition-colors cursor-pointer"
                  @click="$refs.attachmentInput.click()"
                >
                  <input
                    ref="attachmentInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="uploadAttachment"
                  />
                  <div v-if="!isUploadingAttachment" class="text-gray-400 text-sm">
                    <PhotoIcon class="w-6 h-6 mx-auto mb-1" />
                    <p>{{ $t('kanban.uploadImage') }}</p>
                  </div>
                  <div v-else class="text-primary-400">
                    <div class="w-5 h-5 border-2 border-primary-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
                  </div>
                </div>
                <p v-else class="text-xs text-gray-500 italic">
                  $t('kanban.saveCardFirst')
                </p>
              </div>

              <!-- Tags Section -->
              <div v-if="selectedBoard?.tags?.length">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <TagIcon class="w-4 h-4 inline mr-1" />
                  Tags
                </label>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="tag in selectedBoard.tags"
                    :key="tag.id"
                    @click="toggleCardTag(tag.id)"
                    class="px-2 py-1 text-xs rounded-lg transition-all"
                    :class="cardForm.tags?.some(t => t.id === tag.id)
                      ? 'ring-2 ring-white'
                      : 'opacity-60 hover:opacity-100'"
                    :style="{ backgroundColor: tag.color + '40', color: tag.color }"
                  >
                    {{ tag.name }}
                  </button>
                </div>
              </div>

              <!-- Links Section -->
              <div v-if="editingCard">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <LinkIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.linkedElements') }}
                </label>

                <div v-if="cardForm.links?.length" class="space-y-2 mb-3">
                  <div
                    v-for="link in cardForm.links"
                    :key="link.id"
                    class="flex items-center justify-between p-2 bg-white/[0.04] rounded-lg group"
                  >
                    <div
                      class="flex items-center gap-2 flex-1 min-w-0 cursor-pointer hover:text-primary-400"
                      @click="navigateToLink(link)"
                    >
                      <component :is="getLinkIcon(link.linkable_type)" class="w-4 h-4 text-gray-400 flex-shrink-0" />
                      <span class="truncate text-sm">{{ link.linkable?.title || link.linkable?.url || 'Unbekannt' }}</span>
                      <span class="text-xs text-gray-500">({{ getLinkTypeName(link.linkable_type) }})</span>
                    </div>
                    <button
                      @click="removeLink(link.id)"
                      class="p-1 text-gray-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                    >
                      <XMarkIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <button
                  @click="openLinkModal"
                  class="w-full p-2 border-2 border-dashed border-white/[0.06] rounded-lg text-gray-400 hover:border-primary-500 hover:text-primary-400 transition-colors text-sm flex items-center justify-center gap-2"
                >
                  <LinkIcon class="w-4 h-4" />
                  {{ $t('kanban.linkElement') }}
                </button>
              </div>
            </div>

            <!-- Right Column: Comments & Activities -->
            <div v-if="editingCard" class="w-80 border-l border-white/[0.06] flex flex-col bg-white/[0.02]">
              <!-- Tabs -->
              <div class="flex border-b border-white/[0.06]">
                <button
                  @click="showActivities = false"
                  :class="[
                    'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                    !showActivities ? 'text-primary-400 border-b-2 border-primary-500' : 'text-gray-400 hover:text-white'
                  ]"
                >
                  <ChatBubbleLeftIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.comments') }} ({{ comments.length }})
                </button>
                <button
                  @click="showActivities = true; fetchActivities()"
                  :class="[
                    'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                    showActivities ? 'text-primary-400 border-b-2 border-primary-500' : 'text-gray-400 hover:text-white'
                  ]"
                >
                  <ClockIcon class="w-4 h-4 inline mr-1" />
                  {{ $t('kanban.activities') }}
                </button>
              </div>

              <!-- Comments View -->
              <div v-if="!showActivities" class="flex-1 overflow-y-auto p-4">
                <!-- Add comment -->
                <div class="flex gap-2 mb-4">
                  <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-medium flex-shrink-0">
                    {{ authStore.user?.username?.[0]?.toUpperCase() || '?' }}
                  </div>
                  <div class="flex-1">
                    <textarea
                      v-model="newComment"
                      rows="2"
                      class="textarea text-sm"
                      :placeholder="$t('kanban.writeComment')"
                    ></textarea>
                    <button
                      v-if="newComment.trim()"
                      @click="addCommentAction"
                      class="mt-2 px-3 py-1 bg-primary-600 text-white rounded text-sm hover:bg-primary-500"
                    >
                      {{ $t('kanban.send') }}
                    </button>
                  </div>
                </div>

                <!-- Comments list -->
                <div class="space-y-4">
                  <div
                    v-for="comment in comments"
                    :key="comment.id"
                    class="flex gap-2"
                  >
                    <div class="w-8 h-8 rounded-full bg-white/[0.08] flex items-center justify-center text-gray-300 text-xs font-medium flex-shrink-0">
                      {{ comment.username?.[0]?.toUpperCase() || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="text-white text-sm font-medium">{{ comment.username }}</span>
                        <span class="text-xs text-gray-500">{{ formatCommentDate(comment.created_at) }}</span>
                      </div>
                      <template v-if="comment.user_id === authStore.user?.id">
                        <div class="flex gap-2 mb-1">
                          <button
                            @click="startEditComment(comment)"
                            class="text-xs text-gray-400 hover:text-white"
                          >
                            {{ $t('common.edit') }}
                          </button>
                          <button
                            @click="deleteCommentAction(comment.id)"
                            class="text-xs text-gray-400 hover:text-red-400"
                          >
                            {{ $t('common.delete') }}
                          </button>
                        </div>
                      </template>
                      <div v-if="editingComment === comment.id" class="space-y-2">
                        <textarea
                          v-model="editCommentContent"
                          rows="2"
                          class="textarea text-sm"
                        ></textarea>
                        <div class="flex gap-2">
                          <button
                            @click="saveEditComment(comment.id)"
                            class="px-2 py-1 bg-primary-600 text-white rounded text-xs hover:bg-primary-500"
                          >
                            Speichern
                          </button>
                          <button
                            @click="editingComment = null"
                            class="px-2 py-1 text-gray-400 hover:text-white text-xs"
                          >
                            {{ $t('common.cancel') }}
                          </button>
                        </div>
                      </div>
                      <p v-else class="text-gray-300 text-sm whitespace-pre-wrap bg-white/[0.04] rounded-lg p-2">{{ comment.content }}</p>
                    </div>
                  </div>

                  <p v-if="comments.length === 0" class="text-gray-500 text-sm text-center py-8">
                    {{ $t('kanban.noComments') }}
                  </p>
                </div>
              </div>

              <!-- Activities View -->
              <div v-else class="flex-1 overflow-y-auto p-4">
                <div class="space-y-3">
                  <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="flex gap-2"
                  >
                    <div class="w-8 h-8 rounded-full bg-dark-600 flex items-center justify-center text-gray-300 text-xs font-medium flex-shrink-0">
                      {{ activity.username?.[0]?.toUpperCase() || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm">
                        <span class="text-white font-medium">{{ activity.username }}</span>
                        <span class="text-gray-400"> {{ getActivityLabel(activity) }}</span>
                      </p>
                      <span class="text-xs text-gray-500">{{ formatCommentDate(activity.created_at) }}</span>
                    </div>
                  </div>

                  <p v-if="activities.length === 0" class="text-gray-500 text-sm text-center py-8">
                    {{ $t('kanban.noActivities') }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showCardModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              {{ $t('common.cancel') }}
            </button>
            <button
              @click="saveCard"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingCard ? $t('common.save') : $t('common.create') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Attachment Preview Modal -->
    <Teleport to="body">
      <div
        v-if="attachmentPreview"
        class="fixed inset-0 bg-black/90 z-[60] flex items-center justify-center p-4"
        
      >
        <div class="relative max-w-5xl max-h-[90vh] w-full">
          <button
            @click="closeAttachmentPreview"
            class="absolute -top-10 right-0 p-2 text-white hover:text-gray-300 transition-colors"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
          <img
            :src="getAttachmentUrl(attachmentPreview.filename)"
            :alt="attachmentPreview.original_name"
            class="max-w-full max-h-[85vh] mx-auto rounded-lg"
          />
          <div class="text-center mt-3 text-gray-400">
            <p>{{ attachmentPreview.original_name }}</p>
            <p class="text-xs text-gray-500">{{ formatFileSize(attachmentPreview.size) }}</p>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Tag Management Modal -->
    <Teleport to="body">
      <div
        v-if="showTagModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingTag ? $t('kanban.editTag') : $t('kanban.manageTags') }}
            </h2>
            <button @click="showTagModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <!-- Create/Edit Tag Form -->
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">{{ $t('kanban.tagName') }}</label>
                <input
                  v-model="tagForm.name"
                  type="text"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="z.B. Frontend, Backend, Bug..."
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('common.color') }}</label>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="color in boardColors"
                    :key="color"
                    @click="tagForm.color = color"
                    class="w-8 h-8 rounded-lg transition-transform hover:scale-110"
                    :class="{ 'ring-2 ring-white ring-offset-2 ring-offset-dark-800': tagForm.color === color }"
                    :style="{ backgroundColor: color }"
                  ></button>
                </div>
              </div>
              <button
                @click="saveTag"
                class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
              >
                {{ editingTag ? $t('common.save') : $t('kanban.createTag') }}
              </button>
            </div>

            <!-- Existing Tags -->
            <div v-if="selectedBoard?.tags?.length && !editingTag">
              <h3 class="text-sm font-medium text-gray-400 mb-2">{{ $t('kanban.existingTags') }}</h3>
              <div class="space-y-2">
                <div
                  v-for="tag in selectedBoard.tags"
                  :key="tag.id"
                  class="flex items-center justify-between p-2 bg-dark-700 rounded-lg"
                >
                  <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded" :style="{ backgroundColor: tag.color }"></div>
                    <span class="text-white">{{ tag.name }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <button
                      @click="openTagModal(tag)"
                      class="p-1 text-gray-400 hover:text-white rounded"
                    >
                      <PencilIcon class="w-4 h-4" />
                    </button>
                    <button
                      @click="deleteTag(tag)"
                      class="p-1 text-gray-400 hover:text-red-400 rounded"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!selectedBoard?.tags?.length && !editingTag" class="text-center py-4 text-gray-500">
              {{ $t('kanban.noTags') }}
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Link Selection Modal -->
    <Teleport to="body">
      <div
        v-if="showLinkModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[55] flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[80vh] flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">{{ $t('kanban.linkElement') }}</h2>
            <button @click="showLinkModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4 flex-1 overflow-hidden flex flex-col">
            <!-- Type Tabs -->
            <div class="flex gap-2">
              <button
                v-for="lt in linkTypes"
                :key="lt.id"
                @click="linkType = lt.id; fetchLinkableItems()"
                class="px-3 py-1.5 text-sm rounded-lg transition-colors flex items-center gap-1.5"
                :class="linkType === lt.id ? 'bg-primary-600 text-white' : 'bg-dark-700 text-gray-400 hover:text-white'"
              >
                <component :is="lt.icon" class="w-4 h-4" />
                {{ lt.name }}
              </button>
            </div>

            <!-- Search -->
            <input
              v-model="linkSearchQuery"
              type="text"
              @input="onLinkSearchInput"
              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
              :placeholder="$t('kanban.search')"
            />

            <!-- Results -->
            <div class="flex-1 overflow-y-auto space-y-2">
              <div v-if="isLoadingLinkables" class="text-center py-8 text-gray-400">
                <div class="w-6 h-6 border-2 border-primary-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
              </div>

              <div v-else-if="linkableItems.length === 0" class="text-center py-8 text-gray-500">
                {{ $t('kanban.noItemsFound') }}
              </div>

              <button
                v-else
                v-for="item in linkableItems"
                :key="item.id"
                @click="addLink(item)"
                class="w-full flex items-center gap-3 p-3 bg-dark-700 hover:bg-dark-600 rounded-lg transition-colors text-left"
                :disabled="cardForm.links?.some(l => l.linkable_id === item.id && l.linkable_type === linkType)"
                :class="{ 'opacity-50 cursor-not-allowed': cardForm.links?.some(l => l.linkable_id === item.id && l.linkable_type === linkType) }"
              >
                <component :is="getLinkIcon(linkType)" class="w-5 h-5 text-gray-400 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                  <p class="text-white truncate">{{ item.title || item.url }}</p>
                  <p v-if="item.language" class="text-xs text-gray-500">{{ item.language }}</p>
                  <p v-if="item.url" class="text-xs text-gray-500 truncate">{{ item.url }}</p>
                </div>
                <span
                  v-if="cardForm.links?.some(l => l.linkable_id === item.id && l.linkable_type === linkType)"
                  class="text-xs text-green-400"
                >
                  {{ $t('kanban.linked') }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Share Modal -->
    <Teleport to="body">
      <div
        v-if="showShareModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <div>
              <h2 class="text-lg font-semibold text-white">{{ $t('kanban.shareBoard') }}</h2>
              <p class="text-sm text-gray-500 mt-0.5 truncate max-w-[300px]">{{ selectedBoard?.title }}</p>
            </div>
            <button @click="showShareModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4 overflow-y-auto flex-1">
            <!-- Add member form -->
            <div class="bg-dark-700 rounded-lg p-4">
              <h3 class="text-sm font-medium text-gray-300 mb-3">{{ $t('kanban.addUser') }}</h3>
              <div class="flex gap-2">
                <input
                  v-model="newShareEmail"
                  type="email"
                  :placeholder="$t('kanban.emailAddress')"
                  class="flex-1 bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  @keyup.enter="addBoardShare"
                />
                <select
                  v-model="newSharePermission"
                  class="bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option value="view">{{ $t('kanban.read') }}</option>
                  <option value="edit">{{ $t('kanban.editPermission') }}</option>
                </select>
                <button
                  @click="addBoardShare"
                  class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
                >
                  <UserPlusIcon class="w-5 h-5" />
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-2">
                {{ $t('kanban.shareDescription') }}
              </p>
            </div>

            <!-- Members list -->
            <div>
              <h3 class="text-sm font-medium text-gray-300 mb-3">{{ $t('kanban.sharedUsers') }}</h3>
              <div v-if="loadingShares" class="flex items-center justify-center py-8">
                <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>
              <div v-else-if="boardShares.length === 0" class="text-center py-8 text-gray-500">
                {{ $t('kanban.notSharedYet') }}
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="share in boardShares"
                  :key="share.user_id"
                  class="flex items-center justify-between p-3 bg-dark-700 rounded-lg"
                >
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center">
                      <span class="text-xs font-semibold text-white">
                        {{ share.username?.[0]?.toUpperCase() || 'U' }}
                      </span>
                    </div>
                    <div>
                      <p class="text-white text-sm">{{ share.username }}</p>
                      <p class="text-xs text-gray-500">{{ share.email }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <select
                      :value="share.permission"
                      @change="updateSharePermission(share.user_id, $event.target.value)"
                      class="bg-dark-600 border border-dark-500 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-primary-500"
                    >
                      <option value="view">{{ $t('kanban.read') }}</option>
                      <option value="edit">{{ $t('kanban.editPermission') }}</option>
                    </select>
                    <button
                      @click="removeBoardShare(share.user_id)"
                      class="p-1.5 text-gray-400 hover:text-red-400 rounded transition-colors"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Public Share Section -->
            <div class="border-t border-dark-700 pt-4">
              <h3 class="text-sm font-medium text-gray-300 mb-3">{{ $t('kanban.publicLink') }}</h3>

              <div v-if="loadingPublicShare" class="flex items-center justify-center py-4">
                <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
              </div>

              <!-- Active public share -->
              <template v-else-if="publicShareInfo?.active">
                <div class="bg-dark-700 rounded-lg p-4 space-y-3">
                  <div class="flex items-center gap-2 text-sm flex-wrap">
                    <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                    <span class="text-green-400 font-medium">{{ $t('kanban.active') }}</span>
                    <span v-if="publicShareInfo.can_edit" class="px-1.5 py-0.5 bg-blue-500/20 text-blue-400 rounded text-xs">{{ $t('kanban.editable') }}</span>
                    <span v-else class="px-1.5 py-0.5 bg-gray-500/20 text-gray-400 rounded text-xs">{{ $t('kanban.readOnly') }}</span>
                    <span v-if="!publicShareInfo.requires_auth" class="px-1.5 py-0.5 bg-yellow-500/20 text-yellow-400 rounded text-xs">{{ $t('kanban.noLogin') }}</span>
                    <span class="text-gray-500 ml-auto">{{ publicShareInfo.view_count || 0 }} {{ $t('kanban.views') }}</span>
                  </div>

                  <!-- Link -->
                  <div class="flex gap-2">
                    <input
                      :value="publicShareUrl"
                      readonly
                      class="flex-1 bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none"
                    />
                    <button
                      @click="copyPublicLink"
                      class="px-3 py-2 bg-dark-600 hover:bg-dark-500 rounded-lg transition-colors"
                      :title="publicShareCopied ? 'Kopiert!' : 'Link kopieren'"
                    >
                      <svg v-if="!publicShareCopied" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                      </svg>
                      <svg v-else class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                  </div>

                  <!-- Credentials info (only if auth required) -->
                  <div v-if="publicShareInfo.requires_auth" class="text-xs text-gray-500 space-y-1">
                    <p>{{ $t('kanban.username') }}: <span class="text-gray-300">{{ publicShareInfo.username }}</span></p>
                    <p>{{ $t('kanban.password') }}: <span class="text-gray-400">***</span></p>
                  </div>

                  <!-- Disable button -->
                  <button
                    @click="disablePublicShare"
                    class="text-sm text-red-400 hover:text-red-300 transition-colors"
                  >
                    {{ $t('kanban.disableLink') }}
                  </button>
                </div>
              </template>

              <!-- Create public share form -->
              <template v-else>
                <div class="bg-dark-700 rounded-lg p-4 space-y-3">
                  <!-- Mode selection -->
                  <div>
                    <label class="block text-xs text-gray-400 mb-2">{{ $t('kanban.accessMode') }}</label>
                    <div class="flex gap-2">
                      <button
                        @click="publicShareForm.mode = 'readonly'"
                        class="flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors"
                        :class="publicShareForm.mode === 'readonly'
                          ? 'bg-primary-600 text-white'
                          : 'bg-dark-600 text-gray-400 hover:text-white'"
                      >
                        {{ $t('kanban.readOnly') }}
                      </button>
                      <button
                        @click="publicShareForm.mode = 'protected'"
                        class="flex-1 py-2 px-3 rounded-lg text-sm font-medium transition-colors"
                        :class="publicShareForm.mode === 'protected'
                          ? 'bg-primary-600 text-white'
                          : 'bg-dark-600 text-gray-400 hover:text-white'"
                      >
                        {{ $t('kanban.withLogin') }}
                      </button>
                    </div>
                  </div>

                  <!-- Description -->
                  <p class="text-xs text-gray-500">
                    <template v-if="publicShareForm.mode === 'readonly'">
                      {{ $t('kanban.readOnlyDesc') }}
                    </template>
                    <template v-else>
                      {{ $t('kanban.protectedDesc') }}
                    </template>
                  </p>

                  <!-- Username/Password (only for protected mode) -->
                  <template v-if="publicShareForm.mode === 'protected'">
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">{{ $t('kanban.username') }}</label>
                      <input
                        v-model="publicShareForm.username"
                        type="text"
                        placeholder="z.B. kunde-xyz"
                        class="w-full bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-400 mb-1">{{ $t('kanban.password') }}</label>
                      <input
                        v-model="publicShareForm.password"
                        type="text"
                        :placeholder="$t('kanbanModule.passwortFuerDenZugriff')"
                        class="w-full bg-dark-600 border border-dark-500 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                      />
                    </div>

                    <!-- Can edit toggle -->
                    <label class="flex items-center justify-between cursor-pointer">
                      <div>
                        <span class="text-sm text-gray-300">{{ $t('kanban.allowEditing') }}</span>
                        <p class="text-xs text-gray-500">{{ $t('kanban.editingDescription') }}</p>
                      </div>
                      <input
                        v-model="publicShareForm.can_edit"
                        type="checkbox"
                        class="w-4 h-4 rounded border-dark-600 bg-dark-700 text-primary-600 focus:ring-primary-500 focus:ring-offset-0"
                      />
                    </label>
                  </template>

                  <button
                    @click="enablePublicShare"
                    :disabled="publicShareForm.mode === 'protected' && (!publicShareForm.username || !publicShareForm.password)"
                    class="w-full py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {{ $t('kanban.createLink') }}
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
