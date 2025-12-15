import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'

/**
 * Collaboration Cursor Extension for TipTap
 * Renders remote user cursors and selections in the editor
 */
export const CollaborationCursor = Extension.create({
  name: 'collaborationCursor',

  addOptions() {
    return {
      // Function that returns current cursor data
      // Returns: [{ userId, position, user, color }]
      getCursors: () => [],

      // Function that returns current selection data
      // Returns: [{ userId, from, to, user, color }]
      getSelections: () => [],

      // Render configuration
      cursorWidth: 2,
      selectionOpacity: 0.2,

      // Update interval in ms
      updateInterval: 100,
    }
  },

  addProseMirrorPlugins() {
    const { getCursors, getSelections, cursorWidth, selectionOpacity, updateInterval } = this.options
    const pluginKey = new PluginKey('collaborationCursor')

    let updateScheduled = false

    return [
      new Plugin({
        key: pluginKey,

        state: {
          init() {
            return DecorationSet.empty
          },

          apply(tr, decorationSet, oldState, newState) {
            // Map existing decorations through the transaction
            decorationSet = decorationSet.map(tr.mapping, tr.doc)

            // Check if we should update
            const meta = tr.getMeta(pluginKey)
            if (meta?.update) {
              return createDecorations(newState.doc, getCursors, getSelections, cursorWidth, selectionOpacity)
            }

            return decorationSet
          },
        },

        props: {
          decorations(state) {
            return this.getState(state)
          },
        },

        view(editorView) {
          // Set up periodic updates
          const intervalId = setInterval(() => {
            if (!updateScheduled) {
              updateScheduled = true
              requestAnimationFrame(() => {
                updateScheduled = false
                editorView.dispatch(
                  editorView.state.tr.setMeta(pluginKey, { update: true })
                )
              })
            }
          }, updateInterval)

          return {
            destroy() {
              clearInterval(intervalId)
            },
          }
        },
      }),
    ]
  },
})

/**
 * Create decorations for cursors and selections
 */
function createDecorations(doc, getCursors, getSelections, cursorWidth, selectionOpacity) {
  const decorations = []

  // Add cursor decorations
  const cursors = getCursors()
  for (const cursor of cursors) {
    if (cursor.position < 0 || cursor.position > doc.content.size) {
      continue
    }

    // Cursor line decoration
    const cursorDecoration = Decoration.widget(cursor.position, () => {
      const cursorElement = document.createElement('span')
      cursorElement.className = 'collaboration-cursor'
      cursorElement.style.cssText = `
        position: relative;
        margin-left: -${cursorWidth / 2}px;
        margin-right: -${cursorWidth / 2}px;
        border-left: ${cursorWidth}px solid ${cursor.color || '#6366F1'};
        pointer-events: none;
      `

      // Cursor flag with user name
      const flagElement = document.createElement('span')
      flagElement.className = 'collaboration-cursor-flag'
      flagElement.textContent = cursor.user?.name || 'Anonymous'
      flagElement.style.cssText = `
        position: absolute;
        left: -${cursorWidth / 2}px;
        top: -1.4em;
        background-color: ${cursor.color || '#6366F1'};
        color: white;
        font-size: 10px;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 3px 3px 3px 0;
        white-space: nowrap;
        pointer-events: none;
        z-index: 10;
        opacity: 0.9;
        transform: translateY(-2px);
      `
      cursorElement.appendChild(flagElement)

      return cursorElement
    }, {
      key: `cursor-${cursor.userId}`,
      side: 1,
    })

    decorations.push(cursorDecoration)
  }

  // Add selection decorations
  const selections = getSelections()
  for (const selection of selections) {
    if (selection.from < 0 || selection.to > doc.content.size || selection.from >= selection.to) {
      continue
    }

    // Create inline decoration for selection
    const selectionDecoration = Decoration.inline(
      Math.max(0, selection.from),
      Math.min(doc.content.size, selection.to),
      {
        class: 'collaboration-selection',
        style: `background-color: ${hexToRgba(selection.color || '#6366F1', selectionOpacity)};`,
      },
      {
        key: `selection-${selection.userId}`,
      }
    )

    decorations.push(selectionDecoration)
  }

  return DecorationSet.create(doc, decorations)
}

/**
 * Convert hex color to rgba
 */
function hexToRgba(hex, alpha) {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  if (!result) return `rgba(99, 102, 241, ${alpha})`

  const r = parseInt(result[1], 16)
  const g = parseInt(result[2], 16)
  const b = parseInt(result[3], 16)

  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

export default CollaborationCursor
