import { ref, computed } from 'vue'

const GRID_COLS = 4
const GRID_ROWS = 20 // Max rows, can grow

export function useDashboardGrid(widgets, isEditMode) {
  const draggedWidget = ref(null)
  const dragOffset = ref({ x: 0, y: 0 })
  const resizingWidget = ref(null)
  const resizeDirection = ref(null)
  const ghostPosition = ref(null)
  const ghostSize = ref(null)

  // Create a grid map to track occupied cells
  const gridMap = computed(() => {
    const map = {}
    widgets.value.forEach(widget => {
      const x = widget.position_x ?? 0
      const y = widget.position_y ?? 0
      const w = widget.width ?? 1
      const h = widget.height ?? 1

      for (let row = y; row < y + h; row++) {
        for (let col = x; col < x + w; col++) {
          map[`${col}-${row}`] = widget.id || widget.widget_type
        }
      }
    })
    return map
  })

  // Check if a position is valid for a widget
  function canPlaceWidget(widget, newX, newY, newWidth = null, newHeight = null) {
    const w = newWidth ?? widget.width ?? 1
    const h = newHeight ?? widget.height ?? 1

    // Check bounds
    if (newX < 0 || newX + w > GRID_COLS) return false
    if (newY < 0) return false

    // Check for collisions with other widgets
    for (let row = newY; row < newY + h; row++) {
      for (let col = newX; col < newX + w; col++) {
        const occupant = gridMap.value[`${col}-${row}`]
        if (occupant && occupant !== (widget.id || widget.widget_type)) {
          return false
        }
      }
    }

    return true
  }

  // Find the next available position for a new widget
  function findNextAvailablePosition(width = 1, height = 1) {
    for (let row = 0; row < GRID_ROWS; row++) {
      for (let col = 0; col <= GRID_COLS - width; col++) {
        let canPlace = true
        for (let r = row; r < row + height && canPlace; r++) {
          for (let c = col; c < col + width && canPlace; c++) {
            if (gridMap.value[`${c}-${r}`]) {
              canPlace = false
            }
          }
        }
        if (canPlace) {
          return { x: col, y: row }
        }
      }
    }
    // If no space found, put at the bottom
    return { x: 0, y: getMaxRow() + 1 }
  }

  // Get the maximum row used
  function getMaxRow() {
    let maxRow = 0
    widgets.value.forEach(widget => {
      const y = widget.position_y ?? 0
      const h = widget.height ?? 1
      maxRow = Math.max(maxRow, y + h)
    })
    return maxRow
  }

  // Calculate grid rows needed
  const gridRows = computed(() => {
    return Math.max(getMaxRow() + (isEditMode.value ? 2 : 0), 4)
  })

  // Drag handlers
  function startDrag(widget, event) {
    if (!isEditMode.value) return

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', widget.id || widget.widget_type)

    draggedWidget.value = widget
    ghostPosition.value = { x: widget.position_x ?? 0, y: widget.position_y ?? 0 }
    ghostSize.value = { w: widget.width ?? 1, h: widget.height ?? 1 }

    // Calculate offset from mouse to widget top-left
    const rect = event.target.getBoundingClientRect()
    dragOffset.value = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top
    }

    // Visual feedback
    setTimeout(() => {
      event.target.style.opacity = '0.5'
    }, 0)
  }

  function onDrag(event, gridElement) {
    if (!draggedWidget.value || !gridElement) return

    const gridRect = gridElement.getBoundingClientRect()
    const cellWidth = gridRect.width / GRID_COLS
    const cellHeight = 120 // Approximate cell height

    const relX = event.clientX - gridRect.left - dragOffset.value.x + (cellWidth / 2)
    const relY = event.clientY - gridRect.top - dragOffset.value.y + (cellHeight / 2)

    const newX = Math.max(0, Math.min(GRID_COLS - (draggedWidget.value.width ?? 1), Math.floor(relX / cellWidth)))
    const newY = Math.max(0, Math.floor(relY / cellHeight))

    ghostPosition.value = { x: newX, y: newY }
  }

  function endDrag(event) {
    if (event.target) {
      event.target.style.opacity = '1'
    }
    draggedWidget.value = null
    ghostPosition.value = null
    ghostSize.value = null
  }

  function dropOnCell(widget, newX, newY) {
    if (!widget) return false

    if (canPlaceWidget(widget, newX, newY)) {
      widget.position_x = newX
      widget.position_y = newY
      return true
    }
    return false
  }

  // Resize handlers
  function startResize(widget, direction, event) {
    if (!isEditMode.value) return

    event.preventDefault()
    event.stopPropagation()

    resizingWidget.value = widget
    resizeDirection.value = direction
    ghostSize.value = { w: widget.width ?? 1, h: widget.height ?? 1 }
    ghostPosition.value = { x: widget.position_x ?? 0, y: widget.position_y ?? 0 }

    document.addEventListener('mousemove', onResizeMove)
    document.addEventListener('mouseup', endResize)
  }

  function onResizeMove(event) {
    if (!resizingWidget.value) return

    const gridElement = document.querySelector('.dashboard-grid')
    if (!gridElement) return

    const gridRect = gridElement.getBoundingClientRect()
    const cellWidth = gridRect.width / GRID_COLS
    const cellHeight = 120

    const widget = resizingWidget.value
    const startX = widget.position_x ?? 0
    const startY = widget.position_y ?? 0

    const relX = event.clientX - gridRect.left
    const relY = event.clientY - gridRect.top

    const dir = resizeDirection.value
    let newW = widget.width ?? 1
    let newH = widget.height ?? 1
    let newX = startX
    let newY = startY

    if (dir.includes('e')) {
      newW = Math.max(1, Math.min(GRID_COLS - startX, Math.ceil((relX - startX * cellWidth) / cellWidth)))
    }
    if (dir.includes('w')) {
      const endX = startX + (widget.width ?? 1)
      newX = Math.max(0, Math.min(startX + (widget.width ?? 1) - 1, Math.floor(relX / cellWidth)))
      newW = endX - newX
    }
    if (dir.includes('s')) {
      newH = Math.max(1, Math.ceil((relY - startY * cellHeight) / cellHeight))
    }
    if (dir.includes('n')) {
      const endY = startY + (widget.height ?? 1)
      newY = Math.max(0, Math.floor(relY / cellHeight))
      newH = endY - newY
    }

    // Validate
    if (newW >= 1 && newH >= 1 && canPlaceWidget(widget, newX, newY, newW, newH)) {
      ghostSize.value = { w: newW, h: newH }
      ghostPosition.value = { x: newX, y: newY }
    }
  }

  function endResize() {
    if (resizingWidget.value && ghostSize.value && ghostPosition.value) {
      resizingWidget.value.width = ghostSize.value.w
      resizingWidget.value.height = ghostSize.value.h
      resizingWidget.value.position_x = ghostPosition.value.x
      resizingWidget.value.position_y = ghostPosition.value.y
    }

    resizingWidget.value = null
    resizeDirection.value = null
    ghostSize.value = null
    ghostPosition.value = null

    document.removeEventListener('mousemove', onResizeMove)
    document.removeEventListener('mouseup', endResize)
  }

  // Get widget style for grid positioning
  function getWidgetStyle(widget) {
    const x = widget.position_x ?? 0
    const y = widget.position_y ?? 0
    const w = widget.width ?? 1
    const h = widget.height ?? 1

    return {
      gridColumn: `${x + 1} / span ${w}`,
      gridRow: `${y + 1} / span ${h}`,
    }
  }

  // Get ghost preview style
  function getGhostStyle() {
    if (!ghostPosition.value || !ghostSize.value) return null

    return {
      gridColumn: `${ghostPosition.value.x + 1} / span ${ghostSize.value.w}`,
      gridRow: `${ghostPosition.value.y + 1} / span ${ghostSize.value.h}`,
    }
  }

  // Generate empty cell placeholders for edit mode
  const emptyCells = computed(() => {
    if (!isEditMode.value) return []

    const cells = []
    const rows = gridRows.value

    for (let row = 0; row < rows; row++) {
      for (let col = 0; col < GRID_COLS; col++) {
        if (!gridMap.value[`${col}-${row}`]) {
          cells.push({ x: col, y: row })
        }
      }
    }
    return cells
  })

  return {
    GRID_COLS,
    draggedWidget,
    resizingWidget,
    ghostPosition,
    ghostSize,
    gridRows,
    gridMap,
    emptyCells,
    canPlaceWidget,
    findNextAvailablePosition,
    startDrag,
    onDrag,
    endDrag,
    dropOnCell,
    startResize,
    getWidgetStyle,
    getGhostStyle,
  }
}
