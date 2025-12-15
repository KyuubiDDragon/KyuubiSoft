import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'

/**
 * SlashCommands Extension for TipTap
 * Provides a command menu when typing / at the start of a line
 */
export const SlashCommands = Extension.create({
  name: 'slashCommands',

  addOptions() {
    return {
      commands: [], // Array of command definitions
      onShow: null, // Callback when menu should be shown
      onHide: null, // Callback when menu should be hidden
    }
  },

  addProseMirrorPlugins() {
    const { commands, onShow, onHide } = this.options
    const pluginKey = new PluginKey('slashCommands')

    return [
      new Plugin({
        key: pluginKey,
        state: {
          init() {
            return {
              active: false,
              query: '',
              range: null,
            }
          },
          apply(tr, state, oldState, newState) {
            const meta = tr.getMeta(pluginKey)
            if (meta) {
              return { ...state, ...meta }
            }

            if (tr.docChanged && state.active) {
              // Check if we're still in a valid slash command context
              const { $from } = newState.selection
              const textBefore = $from.parent.textBetween(0, $from.parentOffset)

              // Must start with / at beginning of block or after whitespace
              const match = textBefore.match(/(?:^|\s)\/([^\s]*)$/)

              if (!match) {
                return {
                  ...state,
                  active: false,
                  query: '',
                  range: null,
                }
              }
            }

            return state
          },
        },
        props: {
          handleKeyDown(view, event) {
            const state = pluginKey.getState(view.state)

            if (!state.active) {
              // Check if we should activate
              if (event.key === '/') {
                const { $from } = view.state.selection

                // Only activate at start of block or after whitespace
                const textBefore = $from.parent.textBetween(0, $from.parentOffset)
                if (textBefore === '' || textBefore.endsWith(' ')) {
                  setTimeout(() => {
                    const coords = view.coordsAtPos(view.state.selection.from)
                    view.dispatch(view.state.tr.setMeta(pluginKey, {
                      active: true,
                      query: '',
                      range: {
                        from: view.state.selection.from,
                        to: view.state.selection.from + 1,
                      },
                    }))
                    if (onShow) {
                      onShow({
                        query: '',
                        commands,
                        coords,
                        execute: (command) => {
                          executeCommand(view, command, pluginKey)
                        },
                      })
                    }
                  }, 0)
                }
              }
              return false
            }

            // Handle navigation in active menu
            if (event.key === 'Escape') {
              event.preventDefault()
              view.dispatch(view.state.tr.setMeta(pluginKey, {
                active: false,
                query: '',
                range: null,
              }))
              if (onHide) onHide()
              return true
            }

            return false
          },

          handleTextInput(view, from, to, text) {
            const state = pluginKey.getState(view.state)

            if (state.active) {
              // Update the query
              setTimeout(() => {
                const { $from } = view.state.selection
                const textBefore = $from.parent.textBetween(0, $from.parentOffset)
                const match = textBefore.match(/(?:^|\s)\/([^\s]*)$/)

                if (match) {
                  const query = match[1]
                  const start = $from.pos - query.length - 1 // -1 for /

                  const coords = view.coordsAtPos(view.state.selection.from)
                  view.dispatch(view.state.tr.setMeta(pluginKey, {
                    query,
                    range: { from: start, to: $from.pos },
                  }))

                  if (onShow) {
                    const filteredCommands = commands.filter(cmd =>
                      cmd.title.toLowerCase().includes(query.toLowerCase()) ||
                      cmd.keywords?.some(k => k.toLowerCase().includes(query.toLowerCase()))
                    )
                    onShow({
                      query,
                      commands: filteredCommands,
                      coords,
                      execute: (command) => {
                        executeCommand(view, command, pluginKey)
                      },
                    })
                  }
                } else {
                  view.dispatch(view.state.tr.setMeta(pluginKey, {
                    active: false,
                    query: '',
                    range: null,
                  }))
                  if (onHide) onHide()
                }
              }, 0)
            }

            return false
          },
        },
      }),
    ]
  },
})

function executeCommand(view, command, pluginKey) {
  const state = pluginKey.getState(view.state)

  if (!state.range) return

  // Delete the slash command text
  view.dispatch(
    view.state.tr
      .delete(state.range.from, view.state.selection.from)
      .setMeta(pluginKey, {
        active: false,
        query: '',
        range: null,
      })
  )

  // Execute the command's action
  if (command.action) {
    // Short delay to ensure the slash is deleted first
    setTimeout(() => {
      command.action(view.state, view.dispatch, view)
    }, 0)
  }
}

// Default commands for notes
export const defaultSlashCommands = [
  {
    title: 'Text',
    description: 'Normaler Absatztext',
    icon: 'Aa',
    keywords: ['paragraph', 'text', 'absatz'],
    action: (state, dispatch, view) => {
      // Already a paragraph by default
    },
  },
  {
    title: 'Überschrift 1',
    description: 'Große Überschrift',
    icon: 'H1',
    keywords: ['heading', 'h1', 'überschrift', 'titel'],
    action: (state, dispatch, view) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 1 }))
    },
  },
  {
    title: 'Überschrift 2',
    description: 'Mittlere Überschrift',
    icon: 'H2',
    keywords: ['heading', 'h2', 'überschrift'],
    action: (state, dispatch, view) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 2 }))
    },
  },
  {
    title: 'Überschrift 3',
    description: 'Kleine Überschrift',
    icon: 'H3',
    keywords: ['heading', 'h3', 'überschrift'],
    action: (state, dispatch, view) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 3 }))
    },
  },
  {
    title: 'Aufzählung',
    description: 'Punktliste erstellen',
    icon: '•',
    keywords: ['bullet', 'list', 'liste', 'aufzählung'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Nummerierung',
    description: 'Nummerierte Liste',
    icon: '1.',
    keywords: ['number', 'ordered', 'list', 'nummeriert'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Checkliste',
    description: 'Aufgabenliste mit Checkboxen',
    icon: '☑',
    keywords: ['todo', 'task', 'checkbox', 'checkliste', 'aufgabe'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Codeblock',
    description: 'Syntaxhervorgehobener Code',
    icon: '</>',
    keywords: ['code', 'syntax', 'programmieren'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Zitat',
    description: 'Zitat hervorheben',
    icon: '"',
    keywords: ['quote', 'blockquote', 'zitat'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Trennlinie',
    description: 'Horizontale Trennlinie',
    icon: '—',
    keywords: ['divider', 'line', 'horizontal', 'trenner'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Info Callout',
    description: 'Informationshinweis',
    icon: 'ℹ️',
    keywords: ['callout', 'info', 'hinweis', 'information'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Warnung Callout',
    description: 'Warnhinweis',
    icon: '⚠️',
    keywords: ['callout', 'warning', 'warnung', 'achtung'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Tipp Callout',
    description: 'Hilfreicher Tipp',
    icon: '💡',
    keywords: ['callout', 'tip', 'tipp', 'hinweis'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Danger Callout',
    description: 'Wichtiger Warnhinweis',
    icon: '❌',
    keywords: ['callout', 'danger', 'error', 'gefahr', 'fehler'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Toggle',
    description: 'Ausklappbarer Bereich',
    icon: '▶',
    keywords: ['toggle', 'collapse', 'ausklappen', 'erweitern'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Tabelle',
    description: 'Tabelle einfügen',
    icon: '▦',
    keywords: ['table', 'tabelle', 'grid'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Bild',
    description: 'Bild einfügen',
    icon: '🖼',
    keywords: ['image', 'bild', 'foto', 'picture'],
    action: (state, dispatch, view) => {
      // This needs to be handled by the editor
    },
  },
]

export default SlashCommands
