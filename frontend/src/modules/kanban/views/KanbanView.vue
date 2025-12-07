<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/core/api/axios'
import { useUiStore } from '@/stores/ui'
import { useProjectStore } from '@/stores/project'
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
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleIconSolid } from '@heroicons/vue/24/solid'
import { useAuthStore } from '@/stores/auth'
import { ViewColumnsIcon as ViewColumnsIconSolid } from '@heroicons/vue/24/solid'

const route = useRoute()
const uiStore = useUiStore()
const projectStore = useProjectStore()
const authStore = useAuthStore()

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

// Colors for boards
const boardColors = [
  '#6366f1', '#8B5CF6', '#EC4899', '#EF4444', '#F97316',
  '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
]

// Priority options
const priorities = [
  { value: 'low', label: 'Niedrig', color: 'bg-gray-500' },
  { value: 'medium', label: 'Mittel', color: 'bg-blue-500' },
  { value: 'high', label: 'Hoch', color: 'bg-orange-500' },
  { value: 'urgent', label: 'Dringend', color: 'bg-red-500' },
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
    uiStore.showError('Fehler beim Laden der Boards')
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
    uiStore.showError('Fehler beim Laden des Boards')
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
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingBoard.value) {
      await api.put(`/api/v1/kanban/boards/${editingBoard.value.id}`, boardForm.value)
      uiStore.showSuccess('Board aktualisiert')
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

      uiStore.showSuccess('Board erstellt')
      // Select the new board
      await fetchBoard(newBoard.id)
    }
    await fetchBoards()
    showBoardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete board
async function deleteBoard(board) {
  if (!confirm(`Board "${board.title}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${board.id}`)
    uiStore.showSuccess('Board gelöscht')
    if (selectedBoard.value?.id === board.id) {
      selectedBoard.value = null
    }
    await fetchBoards()
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingColumn.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${editingColumn.value.id}`,
        columnForm.value
      )
      uiStore.showSuccess('Spalte aktualisiert')
    } else {
      await api.post(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns`, columnForm.value)
      uiStore.showSuccess('Spalte erstellt')
    }
    await fetchBoard(selectedBoard.value.id)
    showColumnModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete column
async function deleteColumn(column) {
  if (!confirm(`Spalte "${column.title}" wirklich löschen? Alle Karten werden ebenfalls gelöscht.`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${column.id}`)
    uiStore.showSuccess('Spalte gelöscht')
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Titel ist erforderlich')
    return
  }

  try {
    if (editingCard.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}`,
        cardForm.value
      )
      uiStore.showSuccess('Karte aktualisiert')
    } else {
      await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/columns/${targetColumnId.value}/cards`,
        cardForm.value
      )
      uiStore.showSuccess('Karte erstellt')
    }
    await fetchBoard(selectedBoard.value.id)
    showCardModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

// Delete card
async function deleteCard(card) {
  if (!confirm(`Karte "${card.title}" wirklich löschen?`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${card.id}`)
    uiStore.showSuccess('Karte gelöscht')
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Fehler beim Verschieben')
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
    uiStore.showError('Fehler beim Sortieren')
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
    uiStore.showError('Bitte erst die Karte speichern, dann Bilder hinzufügen')
    event.target.value = ''
    return
  }

  // Validate file type
  if (!file.type.startsWith('image/')) {
    uiStore.showError('Nur Bilder erlaubt (JPEG, PNG, GIF, WebP)')
    event.target.value = ''
    return
  }

  // Validate file size (5MB)
  if (file.size > 5 * 1024 * 1024) {
    uiStore.showError('Datei zu groß (max 5MB)')
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
    uiStore.showSuccess('Bild hochgeladen')
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Hochladen')
  } finally {
    isUploadingAttachment.value = false
    event.target.value = ''
  }
}

// Delete attachment
async function deleteAttachment(attachmentId) {
  if (!editingCard.value) return

  if (!confirm('Bild wirklich löschen?')) return

  try {
    await api.delete(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/attachments/${attachmentId}`
    )

    // Remove from local attachments
    cardForm.value.attachments = cardForm.value.attachments.filter(a => a.id !== attachmentId)
    uiStore.showSuccess('Bild gelöscht')
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Tag-Name ist erforderlich')
    return
  }

  try {
    if (editingTag.value) {
      await api.put(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/tags/${editingTag.value.id}`,
        tagForm.value
      )
      uiStore.showSuccess('Tag aktualisiert')
    } else {
      const response = await api.post(
        `/api/v1/kanban/boards/${selectedBoard.value.id}/tags`,
        tagForm.value
      )
      selectedBoard.value.tags.push(response.data.data)
      uiStore.showSuccess('Tag erstellt')
    }
    await fetchBoard(selectedBoard.value.id)
    showTagModal.value = false
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Speichern')
  }
}

async function deleteTag(tag) {
  if (!confirm(`Tag "${tag.name}" wirklich löschen? Er wird von allen Karten entfernt.`)) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/tags/${tag.id}`)
    uiStore.showSuccess('Tag gelöscht')
    await fetchBoard(selectedBoard.value.id)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

// ==================
// Link Functions
// ==================

const linkTypes = [
  { id: 'document', name: 'Dokument', icon: DocumentIcon },
  { id: 'list', name: 'Liste', icon: ListBulletIcon },
  { id: 'snippet', name: 'Snippet', icon: CodeBracketIcon },
  { id: 'bookmark', name: 'Bookmark', icon: BookmarkIcon },
]

function openLinkModal() {
  if (!editingCard.value) {
    uiStore.showError('Bitte erst die Karte speichern')
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
    uiStore.showError('Fehler beim Laden der Elemente')
  } finally {
    isLoadingLinkables.value = false
  }
}

async function addLink(item) {
  if (!editingCard.value) return

  // Check if already linked
  if (cardForm.value.links?.some(l => l.linkable_id === item.id && l.linkable_type === linkType.value)) {
    uiStore.showError('Bereits verlinkt')
    return
  }

  try {
    const response = await api.post(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/links`,
      { linkable_type: linkType.value, linkable_id: item.id }
    )
    if (!cardForm.value.links) cardForm.value.links = []
    cardForm.value.links.push(response.data.data)
    uiStore.showSuccess('Verlinkt')
  } catch (error) {
    uiStore.showError(error.response?.data?.message || 'Fehler beim Verlinken')
  }
}

async function removeLink(linkId) {
  if (!editingCard.value) return

  try {
    await api.delete(
      `/api/v1/kanban/boards/${selectedBoard.value.id}/cards/${editingCard.value.id}/links/${linkId}`
    )
    cardForm.value.links = cardForm.value.links.filter(l => l.id !== linkId)
    uiStore.showSuccess('Link entfernt')
  } catch (error) {
    uiStore.showError('Fehler beim Entfernen')
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
    uiStore.showError('Fehler beim Erstellen der Checkliste')
  }
}

async function deleteChecklist(checklistId) {
  if (!confirm('Checkliste wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/checklists/${checklistId}`)
    checklists.value = checklists.value.filter(c => c.id !== checklistId)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Fehler beim Hinzufügen')
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
    uiStore.showError('Fehler beim Aktualisieren')
  }
}

async function deleteChecklistItem(itemId) {
  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/checklist-items/${itemId}`)
    for (const checklist of checklists.value) {
      checklist.items = checklist.items.filter(i => i.id !== itemId)
    }
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
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
    uiStore.showError('Fehler beim Hinzufügen')
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
    uiStore.showError('Fehler beim Speichern')
  }
}

async function deleteCommentAction(commentId) {
  if (!confirm('Kommentar wirklich löschen?')) return

  try {
    await api.delete(`/api/v1/kanban/boards/${selectedBoard.value.id}/comments/${commentId}`)
    comments.value = comments.value.filter(c => c.id !== commentId)
  } catch (error) {
    uiStore.showError('Fehler beim Löschen')
  }
}

function formatCommentDate(dateStr) {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'gerade eben'
  if (diffMins < 60) return `vor ${diffMins} Min.`
  if (diffHours < 24) return `vor ${diffHours} Std.`
  if (diffDays < 7) return `vor ${diffDays} Tagen`
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
    'card_created': 'hat die Karte erstellt',
    'card_moved': `hat die Karte verschoben von "${activity.details?.from_column}" nach "${activity.details?.to_column}"`,
    'card_updated': `hat ${activity.details?.field === 'title' ? 'den Titel' : 'die Beschreibung'} geändert`,
    'assignee_added': `hat ${activity.details?.assignee_name} zugewiesen`,
    'assignee_removed': `hat ${activity.details?.assignee_name} entfernt`,
    'flag_changed': `hat die Priorität geändert von "${activity.details?.old_flag}" auf "${activity.details?.new_flag}"`,
    'tag_added': `hat Tag "${activity.details?.tag_name}" hinzugefügt`,
    'tag_removed': `hat Tag "${activity.details?.tag_name}" entfernt`,
    'checklist_item_completed': `hat "${activity.details?.item_text}" abgehakt`,
    'checklist_item_uncompleted': `hat "${activity.details?.item_text}" wieder geöffnet`,
    'comment_added': 'hat einen Kommentar hinzugefügt',
    'due_date_set': `hat Fälligkeitsdatum auf ${activity.details?.due_date} gesetzt`,
    'due_date_removed': 'hat das Fälligkeitsdatum entfernt',
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
          class="p-2 text-gray-400 hover:text-white hover:bg-dark-700 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-2xl font-bold text-white">
            {{ selectedBoard ? selectedBoard.title : 'Kanban Boards' }}
          </h1>
          <p v-if="selectedBoard?.description" class="text-gray-400 text-sm mt-1">
            {{ selectedBoard.description }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="selectedBoard"
          @click="openTagModal()"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
          title="Tags verwalten"
        >
          <TagIcon class="w-5 h-5" />
          <span class="hidden sm:inline">Tags</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openColumnModal()"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Spalte</span>
        </button>
        <button
          v-if="selectedBoard"
          @click="openBoardModal(selectedBoard)"
          class="px-4 py-2 bg-dark-700 text-white rounded-lg hover:bg-dark-600 transition-colors flex items-center gap-2"
        >
          <PencilIcon class="w-5 h-5" />
          <span>Bearbeiten</span>
        </button>
        <button
          v-if="!selectedBoard"
          @click="openBoardModal()"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors flex items-center gap-2"
        >
          <PlusIcon class="w-5 h-5" />
          <span>Neues Board</span>
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
        class="bg-dark-800 border border-dark-700 rounded-xl p-4 cursor-pointer hover:border-dark-600 transition-all group"
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
              class="p-1.5 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              @click.stop="deleteBoard(board)"
              class="p-1.5 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
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
          <span>{{ board.column_count || 0 }} Spalten</span>
          <span>{{ board.card_count || 0 }} Karten</span>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-if="boards.length === 0"
        @click="openBoardModal()"
        class="bg-dark-800 border-2 border-dashed border-dark-600 rounded-xl p-8 cursor-pointer hover:border-dark-500 transition-colors flex flex-col items-center justify-center text-center col-span-full"
      >
        <ViewColumnsIcon class="w-12 h-12 text-gray-500 mb-3" />
        <p class="text-gray-400">Kein Board vorhanden</p>
        <p class="text-primary-500 mt-1">Klicken um ein Board zu erstellen</p>
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
          <div class="flex-shrink-0 w-80 bg-dark-800 rounded-xl border border-dark-700">
            <!-- Column Header -->
            <div class="p-3 border-b border-dark-700 column-handle cursor-move">
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
                    title="Abgeschlossen-Spalte"
                  />
                  <span class="text-xs text-gray-500 bg-dark-700 px-2 py-0.5 rounded-full">
                    {{ column.cards?.length || 0 }}
                    <template v-if="column.wip_limit">/ {{ column.wip_limit }}</template>
                  </span>
                </div>
                <div class="flex items-center gap-1">
                  <button
                    @click="openCardModal(column.id)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PlusIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="openColumnModal(column)"
                    class="p-1 text-gray-400 hover:text-white hover:bg-dark-600 rounded"
                  >
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteColumn(column)"
                    class="p-1 text-gray-400 hover:text-red-400 hover:bg-dark-600 rounded"
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
                    class="bg-dark-700 rounded-lg p-3 cursor-pointer hover:bg-dark-600 transition-colors group"
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
                        class="w-12 h-12 rounded overflow-hidden bg-dark-600"
                      >
                        <img
                          :src="getAttachmentUrl(attachment.filename)"
                          :alt="attachment.original_name"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div
                        v-if="card.attachments.length > 3"
                        class="w-12 h-12 rounded bg-dark-600 flex items-center justify-center text-xs text-gray-400"
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
                class="w-full p-2 text-gray-400 hover:text-white hover:bg-dark-600 rounded-lg transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <PlusIcon class="w-4 h-4" />
                <span>Karte hinzufügen</span>
              </button>
            </div>
          </div>
        </template>
      </draggable>

      <!-- Add column button -->
      <div
        @click="openColumnModal()"
        class="flex-shrink-0 w-80 bg-dark-800/50 border-2 border-dashed border-dark-600 rounded-xl p-4 cursor-pointer hover:border-dark-500 transition-colors flex items-center justify-center"
      >
        <div class="text-center">
          <PlusIcon class="w-8 h-8 text-gray-500 mx-auto mb-2" />
          <p class="text-gray-400">Spalte hinzufügen</p>
        </div>
      </div>
    </div>

    <!-- Board Modal -->
    <Teleport to="body">
      <div
        v-if="showBoardModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingBoard ? 'Board bearbeiten' : 'Neues Board' }}
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
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="boardForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Board Name"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
              <textarea
                v-model="boardForm.description"
                rows="3"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                placeholder="Optionale Beschreibung..."
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
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

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showBoardModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveBoard"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingBoard ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Column Modal -->
    <Teleport to="body">
      <div
        v-if="showColumnModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-md">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingColumn ? 'Spalte bearbeiten' : 'Neue Spalte' }}
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
              <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
              <input
                v-model="columnForm.title"
                type="text"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Spaltenname"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
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
              <label class="block text-sm font-medium text-gray-300 mb-1">WIP Limit (optional)</label>
              <input
                v-model.number="columnForm.wip_limit"
                type="number"
                min="0"
                class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                placeholder="Max. Anzahl Karten"
              />
            </div>

            <div class="flex items-center gap-3 p-3 bg-dark-700/50 rounded-lg">
              <input
                v-model="columnForm.is_completed"
                type="checkbox"
                id="is_completed"
                class="w-4 h-4 rounded border-dark-500 bg-dark-700 text-green-500 focus:ring-green-500 focus:ring-offset-0"
              />
              <label for="is_completed" class="flex-1">
                <span class="block text-sm font-medium text-gray-300">Als "Abgeschlossen" markieren</span>
                <span class="text-xs text-gray-500">Karten in dieser Spalte gelten als erledigt und werden nicht mehr als offene Aufgaben angezeigt</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 p-4 border-t border-dark-700">
            <button
              @click="showColumnModal = false"
              class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
            >
              Abbrechen
            </button>
            <button
              @click="saveColumn"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingColumn ? 'Speichern' : 'Erstellen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Card Modal -->
    <Teleport to="body">
      <div
        v-if="showCardModal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        
      >
        <div class="bg-dark-800 border border-dark-700 rounded-xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="flex items-center justify-between p-4 border-b border-dark-700">
            <h2 class="text-lg font-semibold text-white">
              {{ editingCard ? 'Karte bearbeiten' : 'Neue Karte' }}
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
                <label class="block text-sm font-medium text-gray-300 mb-1">Titel *</label>
                <input
                  v-model="cardForm.title"
                  type="text"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="Kartenname"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Beschreibung</label>
                <textarea
                  v-model="cardForm.description"
                  rows="3"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                  placeholder="Details zur Karte..."
                ></textarea>
              </div>

              <!-- Checklists Section - directly after description -->
              <div v-if="editingCard">
                <label class="block text-sm font-medium text-gray-300 mb-2">
                  <ClipboardDocumentListIcon class="w-4 h-4 inline mr-1" />
                  Checklisten
                </label>

                <div class="space-y-3 mb-3">
                  <div
                    v-for="checklist in checklists"
                    :key="checklist.id"
                    class="bg-dark-700 rounded-lg p-3"
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

                    <div v-if="checklist.items?.length" class="h-1 bg-dark-600 rounded-full mb-2 overflow-hidden">
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
                        class="flex-1 bg-dark-600 border border-dark-500 rounded px-2 py-1 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                        placeholder="Neuer Eintrag..."
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
                    class="flex-1 bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                    placeholder="Neue Checkliste..."
                    @keydown.enter="createChecklist"
                  />
                  <button
                    @click="createChecklist"
                    class="px-3 py-2 bg-dark-600 text-gray-300 rounded-lg hover:bg-dark-500 transition-colors"
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
                    Priorität
                  </label>
                  <select
                    v-model="cardForm.priority"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                  >
                    <option v-for="p in priorities" :key="p.value" :value="p.value">
                      {{ p.label }}
                    </option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-1">
                    <CalendarIcon class="w-4 h-4 inline mr-1" />
                    Fälligkeitsdatum
                  </label>
                  <input
                    v-model="cardForm.due_date"
                    type="date"
                    class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">
                  <UserCircleIcon class="w-4 h-4 inline mr-1" />
                  Zugewiesen an
                </label>
                <select
                  v-model="cardForm.assigned_to"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-primary-500"
                >
                  <option :value="null">Niemand</option>
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
                <label class="block text-sm font-medium text-gray-300 mb-2">Kartenfarbe</label>
                <div class="flex flex-wrap gap-2">
                  <button
                    @click="cardForm.color = null"
                    class="w-8 h-8 rounded-lg bg-dark-600 flex items-center justify-center transition-transform hover:scale-110"
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
                  Bilder / Screenshots
                </label>

                <div v-if="cardForm.attachments.length" class="grid grid-cols-4 gap-2 mb-3">
                  <div
                    v-for="attachment in cardForm.attachments"
                    :key="attachment.id"
                    class="relative group aspect-square bg-dark-700 rounded-lg overflow-hidden"
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
                  class="border-2 border-dashed border-dark-600 rounded-lg p-3 text-center hover:border-primary-500 transition-colors cursor-pointer"
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
                    <p>Bild hochladen</p>
                  </div>
                  <div v-else class="text-primary-400">
                    <div class="w-5 h-5 border-2 border-primary-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
                  </div>
                </div>
                <p v-else class="text-xs text-gray-500 italic">
                  Bitte erst die Karte speichern, um Bilder hinzuzufügen.
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
                  Verknüpfte Elemente
                </label>

                <div v-if="cardForm.links?.length" class="space-y-2 mb-3">
                  <div
                    v-for="link in cardForm.links"
                    :key="link.id"
                    class="flex items-center justify-between p-2 bg-dark-700 rounded-lg group"
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
                  class="w-full p-2 border-2 border-dashed border-dark-600 rounded-lg text-gray-400 hover:border-primary-500 hover:text-primary-400 transition-colors text-sm flex items-center justify-center gap-2"
                >
                  <LinkIcon class="w-4 h-4" />
                  Element verknüpfen
                </button>
              </div>
            </div>

            <!-- Right Column: Comments & Activities -->
            <div v-if="editingCard" class="w-80 border-l border-dark-700 flex flex-col bg-dark-850">
              <!-- Tabs -->
              <div class="flex border-b border-dark-700">
                <button
                  @click="showActivities = false"
                  :class="[
                    'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                    !showActivities ? 'text-primary-400 border-b-2 border-primary-500' : 'text-gray-400 hover:text-white'
                  ]"
                >
                  <ChatBubbleLeftIcon class="w-4 h-4 inline mr-1" />
                  Kommentare ({{ comments.length }})
                </button>
                <button
                  @click="showActivities = true; fetchActivities()"
                  :class="[
                    'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                    showActivities ? 'text-primary-400 border-b-2 border-primary-500' : 'text-gray-400 hover:text-white'
                  ]"
                >
                  <ClockIcon class="w-4 h-4 inline mr-1" />
                  Aktivitäten
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
                      class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-primary-500 resize-none"
                      placeholder="Kommentar schreiben..."
                    ></textarea>
                    <button
                      v-if="newComment.trim()"
                      @click="addCommentAction"
                      class="mt-2 px-3 py-1 bg-primary-600 text-white rounded text-sm hover:bg-primary-500"
                    >
                      Senden
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
                    <div class="w-8 h-8 rounded-full bg-dark-600 flex items-center justify-center text-gray-300 text-xs font-medium flex-shrink-0">
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
                            Bearbeiten
                          </button>
                          <button
                            @click="deleteCommentAction(comment.id)"
                            class="text-xs text-gray-400 hover:text-red-400"
                          >
                            Löschen
                          </button>
                        </div>
                      </template>
                      <div v-if="editingComment === comment.id" class="space-y-2">
                        <textarea
                          v-model="editCommentContent"
                          rows="2"
                          class="w-full bg-dark-700 border border-dark-600 rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-primary-500 resize-none"
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
                            Abbrechen
                          </button>
                        </div>
                      </div>
                      <p v-else class="text-gray-300 text-sm whitespace-pre-wrap bg-dark-700 rounded-lg p-2">{{ comment.content }}</p>
                    </div>
                  </div>

                  <p v-if="comments.length === 0" class="text-gray-500 text-sm text-center py-8">
                    Noch keine Kommentare
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
                    Noch keine Aktivitäten
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
              Abbrechen
            </button>
            <button
              @click="saveCard"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
            >
              {{ editingCard ? 'Speichern' : 'Erstellen' }}
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
              {{ editingTag ? 'Tag bearbeiten' : 'Tags verwalten' }}
            </h2>
            <button @click="showTagModal = false" class="p-1 text-gray-400 hover:text-white rounded">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-4 space-y-4">
            <!-- Create/Edit Tag Form -->
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Tag-Name</label>
                <input
                  v-model="tagForm.name"
                  type="text"
                  class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-primary-500"
                  placeholder="z.B. Frontend, Backend, Bug..."
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Farbe</label>
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
                {{ editingTag ? 'Speichern' : 'Tag erstellen' }}
              </button>
            </div>

            <!-- Existing Tags -->
            <div v-if="selectedBoard?.tags?.length && !editingTag">
              <h3 class="text-sm font-medium text-gray-400 mb-2">Bestehende Tags</h3>
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
              Keine Tags vorhanden
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
            <h2 class="text-lg font-semibold text-white">Element verknüpfen</h2>
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
              placeholder="Suchen..."
            />

            <!-- Results -->
            <div class="flex-1 overflow-y-auto space-y-2">
              <div v-if="isLoadingLinkables" class="text-center py-8 text-gray-400">
                <div class="w-6 h-6 border-2 border-primary-400 border-t-transparent rounded-full animate-spin mx-auto"></div>
              </div>

              <div v-else-if="linkableItems.length === 0" class="text-center py-8 text-gray-500">
                Keine {{ getLinkTypeName(linkType) }}e gefunden
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
                  Verknüpft
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
