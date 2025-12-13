import { ref, computed, nextTick } from 'vue'

const GRID_COLS = 4
const MIN_CELL_HEIGHT = 120

export function useDashboardGrid(widgets, isEditMode) {
  const draggedWidget = ref(null)
  const resizingWidget = ref(null)
  const resizeDirection = ref(null)
  const ghostPosition = ref(null)
  const ghostSize = ref(null)
  const gridRef = ref(null)

  // Create a grid map to track occupied cells
  const gridMap = computed(() => {
    const map = {}
    widgets.value.forEach(widget => {
      const x = widget.position_x ?? 0
      const y = widget.position_y ?? 0
      const w = widget.width ?? 1
      const h = widget.height ?? 1
      const id = widget.id || `temp-${widget.widget_type}`

      for (let row = y; row < y + h; row++) {
        for (let col = x; col < x + w; col++) {
          map[`${col}-${row}`] = id
        }
      }
    })
    return map
  })

  // Check if a position is valid for a widget
  function canPlaceWidget(widget, newX, newY, newWidth = null, newHeight = null) {
    const w = newWidth ?? widget.width ?? 1
    const h = newHeight ?? widget.height ?? 1
    const widgetId = widget.id || `temp-${widget.widget_type}`

    // Check bounds
    if (newX < 0 || newX + w > GRID_COLS) return false
    if (newY < 0) return false

    // Check for collisions with other widgets
    for (let row = newY; row < newY + h; row++) {
      for (let col = newX; col < newX + w; col++) {
        const occupant = gridMap.value[`${col}-${row}`]
        if (occupant && occupant !== widgetId) {
          return false
        }
      }
    }

    return true
  }

  // Find the next available position for a new widget
  function findNextAvailablePosition(width = 1, height = 1) {
    const maxRows = getMaxRow() + 5
    for (let row = 0; row < maxRows; row++) {
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

  // Get cell dimensions from grid - uses fixed 120px row height
  function getCellDimensions(gridElement) {
    const gap = 24 // 1.5rem gap

    if (!gridElement) return { width: 200, height: MIN_CELL_HEIGHT + gap, gridLeft: 0, gridTop: 0 }

    const gridRect = gridElement.getBoundingClientRect()
    const availableWidth = gridRect.width - (gap * (GRID_COLS - 1))
    const cellWidth = availableWidth / GRID_COLS

    // Fixed row height of 120px + gap for consistent grid behavior
    const cellHeight = MIN_CELL_HEIGHT + gap

    return {
      width: cellWidth + gap,
      height: cellHeight,
      gridLeft: gridRect.left,
      gridTop: gridRect.top
    }
  }

  // Drag handlers
  function startDrag(widget, event) {
    if (!isEditMode.value) return

    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', widget.id || widget.widget_type)

    // Set drag image to be more visible
    if (event.target) {
      event.dataTransfer.setDragImage(event.target, 50, 50)
    }

    draggedWidget.value = widget
    ghostPosition.value = { x: widget.position_x ?? 0, y: widget.position_y ?? 0 }
    ghostSize.value = { w: widget.width ?? 1, h: widget.height ?? 1 }

    // Visual feedback with delay for drag image
    setTimeout(() => {
      if (event.target) {
        event.target.style.opacity = '0.4'
      }
    }, 0)
  }

  function onDrag(event, gridElement) {
    if (!draggedWidget.value || !gridElement || event.clientX === 0) return

    const dims = getCellDimensions(gridElement)
    const widget = draggedWidget.value
    const widgetWidth = widget.width ?? 1

    // Calculate position relative to grid
    const relX = event.clientX - dims.gridLeft
    const relY = event.clientY - dims.gridTop

    // Convert to grid coordinates
    let newX = Math.floor(relX / dims.width)
    let newY = Math.floor(relY / dims.height)

    // Clamp to valid range
    newX = Math.max(0, Math.min(GRID_COLS - widgetWidth, newX))
    newY = Math.max(0, newY)

    // Only update if position changed
    if (!ghostPosition.value || ghostPosition.value.x !== newX || ghostPosition.value.y !== newY) {
      ghostPosition.value = { x: newX, y: newY }
    }
  }

  function endDrag(event) {
    // Reset visual
    if (event && event.target) {
      event.target.style.opacity = '1'
    }

    // Apply position if we have a ghost position
    if (draggedWidget.value && ghostPosition.value) {
      const widget = draggedWidget.value
      const newX = ghostPosition.value.x
      const newY = ghostPosition.value.y

      if (canPlaceWidget(widget, newX, newY)) {
        widget.position_x = newX
        widget.position_y = newY
      }
    }

    // Clear state
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

    // Add listeners
    const onMove = (e) => onResizeMove(e)
    const onEnd = () => {
      endResize()
      document.removeEventListener('mousemove', onMove)
      document.removeEventListener('mouseup', onEnd)
    }

    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onEnd)
  }

  function onResizeMove(event) {
    if (!resizingWidget.value) return

    const gridElement = document.querySelector('.dashboard-grid')
    if (!gridElement) return

    const dims = getCellDimensions(gridElement)
    const widget = resizingWidget.value

    // Use the ORIGINAL position when resize started, not current ghost position
    const origX = widget.position_x ?? 0
    const origY = widget.position_y ?? 0
    const origW = widget.width ?? 1
    const origH = widget.height ?? 1

    // Calculate the right and bottom edges of the original widget
    const origRight = origX + origW
    const origBottom = origY + origH

    // Mouse position in grid coordinates (with some offset for better feel)
    const relX = event.clientX - dims.gridLeft
    const relY = event.clientY - dims.gridTop

    // Convert to cell coordinates
    const mouseCol = Math.round(relX / dims.width)
    const mouseRow = Math.round(relY / dims.height)

    const dir = resizeDirection.value
    let newX = origX
    let newY = origY
    let newW = origW
    let newH = origH

    // Calculate new dimensions based on resize direction
    if (dir.includes('e')) {
      // Resize from right edge - width changes, position stays
      const newRight = Math.max(origX + 1, Math.min(GRID_COLS, mouseCol))
      newW = newRight - origX
    }
    if (dir.includes('w')) {
      // Resize from left edge - both position and width change
      const newLeft = Math.max(0, Math.min(origRight - 1, mouseCol))
      newX = newLeft
      newW = origRight - newLeft
    }
    if (dir.includes('s')) {
      // Resize from bottom edge - height changes, position stays
      const newBottom = Math.max(origY + 1, mouseRow)
      newH = newBottom - origY
    }
    if (dir.includes('n')) {
      // Resize from top edge - both position and height change
      const newTop = Math.max(0, Math.min(origBottom - 1, mouseRow))
      newY = newTop
      newH = origBottom - newTop
    }

    // Ensure minimum size
    newW = Math.max(1, newW)
    newH = Math.max(1, newH)

    // Check if placement is valid (no collision with other widgets)
    if (canPlaceWidget(widget, newX, newY, newW, newH)) {
      ghostSize.value = { w: newW, h: newH }
      ghostPosition.value = { x: newX, y: newY }
    }
  }

  function endResize() {
    if (resizingWidget.value && ghostSize.value && ghostPosition.value) {
      // Apply the new size and position
      resizingWidget.value.width = ghostSize.value.w
      resizingWidget.value.height = ghostSize.value.h
      resizingWidget.value.position_x = ghostPosition.value.x
      resizingWidget.value.position_y = ghostPosition.value.y
    }

    // Clear state
    resizingWidget.value = null
    resizeDirection.value = null
    ghostSize.value = null
    ghostPosition.value = null
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
