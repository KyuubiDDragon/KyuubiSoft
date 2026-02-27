import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import type { EditorView } from '@tiptap/pm/view'
import type { EditorState, Transaction } from '@tiptap/pm/state'

/**
 * A slash command definition
 */
export interface SlashCommandDefinition {
  title: string
  description: string
  icon: string
  keywords?: string[]
  action: (state: EditorState, dispatch: EditorView['dispatch'], view: EditorView) => void
}

/**
 * Coordinates for positioning the command menu
 */
export interface MenuCoords {
  top: number
  left: number
  right: number
  bottom: number
}

/**
 * Data passed to the onShow callback
 */
export interface SlashCommandShowData {
  query: string
  commands: SlashCommandDefinition[]
  coords: MenuCoords
  execute: (command: SlashCommandDefinition) => void
}

/**
 * Internal plugin state
 */
export interface SlashCommandPluginState {
  active: boolean
  query: string
  range: { from: number; to: number } | null
}

/**
 * Options for the SlashCommands extension
 */
export interface SlashCommandsOptions {
  commands: SlashCommandDefinition[]
  onShow: ((data: SlashCommandShowData) => void) | null
  onHide: (() => void) | null
}

/**
 * SlashCommands Extension for TipTap
 * Provides a command menu when typing / at the start of a line
 */
export const SlashCommands = Extension.create<SlashCommandsOptions>({
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
          init(): SlashCommandPluginState {
            return {
              active: false,
              query: '',
              range: null,
            }
          },
          apply(tr, state: SlashCommandPluginState, oldState, newState): SlashCommandPluginState {
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
          handleKeyDown(view: EditorView, event: KeyboardEvent): boolean {
            const state = pluginKey.getState(view.state) as SlashCommandPluginState

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
                        execute: (command: SlashCommandDefinition) => {
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

          handleTextInput(view: EditorView, from: number, to: number, text: string): boolean {
            const state = pluginKey.getState(view.state) as SlashCommandPluginState

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
                    const filteredCommands = commands.filter((cmd: SlashCommandDefinition) =>
                      cmd.title.toLowerCase().includes(query.toLowerCase()) ||
                      cmd.keywords?.some((k: string) => k.toLowerCase().includes(query.toLowerCase()))
                    )
                    onShow({
                      query,
                      commands: filteredCommands,
                      coords,
                      execute: (command: SlashCommandDefinition) => {
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

function executeCommand(view: EditorView, command: SlashCommandDefinition, pluginKey: PluginKey): void {
  const state = pluginKey.getState(view.state) as SlashCommandPluginState

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
export const defaultSlashCommands: SlashCommandDefinition[] = [
  {
    title: 'Text',
    description: 'Normaler Absatztext',
    icon: 'Aa',
    keywords: ['paragraph', 'text', 'absatz'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // Already a paragraph by default
    },
  },
  {
    title: '\u00DCberschrift 1',
    description: 'Gro\u00DFe \u00DCberschrift',
    icon: 'H1',
    keywords: ['heading', 'h1', '\u00FCberschrift', 'titel'],
    action: (state: EditorState, dispatch: EditorView['dispatch'], view: EditorView) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 1 }))
    },
  },
  {
    title: '\u00DCberschrift 2',
    description: 'Mittlere \u00DCberschrift',
    icon: 'H2',
    keywords: ['heading', 'h2', '\u00FCberschrift'],
    action: (state: EditorState, dispatch: EditorView['dispatch'], view: EditorView) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 2 }))
    },
  },
  {
    title: '\u00DCberschrift 3',
    description: 'Kleine \u00DCberschrift',
    icon: 'H3',
    keywords: ['heading', 'h3', '\u00FCberschrift'],
    action: (state: EditorState, dispatch: EditorView['dispatch'], view: EditorView) => {
      view.dispatch(state.tr.setBlockType(state.selection.from, state.selection.to, state.schema.nodes.heading, { level: 3 }))
    },
  },
  {
    title: 'Aufz\u00E4hlung',
    description: 'Punktliste erstellen',
    icon: '\u2022',
    keywords: ['bullet', 'list', 'liste', 'aufz\u00E4hlung'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Nummerierung',
    description: 'Nummerierte Liste',
    icon: '1.',
    keywords: ['number', 'ordered', 'list', 'nummeriert'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Checkliste',
    description: 'Aufgabenliste mit Checkboxen',
    icon: '\u2611',
    keywords: ['todo', 'task', 'checkbox', 'checkliste', 'aufgabe'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Codeblock',
    description: 'Syntaxhervorgehobener Code',
    icon: '</>',
    keywords: ['code', 'syntax', 'programmieren'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Zitat',
    description: 'Zitat hervorheben',
    icon: '"',
    keywords: ['quote', 'blockquote', 'zitat'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Trennlinie',
    description: 'Horizontale Trennlinie',
    icon: '\u2014',
    keywords: ['divider', 'line', 'horizontal', 'trenner'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Info Callout',
    description: 'Informationshinweis',
    icon: '\u2139\uFE0F',
    keywords: ['callout', 'info', 'hinweis', 'information'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Warnung Callout',
    description: 'Warnhinweis',
    icon: '\u26A0\uFE0F',
    keywords: ['callout', 'warning', 'warnung', 'achtung'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Tipp Callout',
    description: 'Hilfreicher Tipp',
    icon: '\uD83D\uDCA1',
    keywords: ['callout', 'tip', 'tipp', 'hinweis'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Danger Callout',
    description: 'Wichtiger Warnhinweis',
    icon: '\u274C',
    keywords: ['callout', 'danger', 'error', 'gefahr', 'fehler'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Toggle',
    description: 'Ausklappbarer Bereich',
    icon: '\u25B6',
    keywords: ['toggle', 'collapse', 'ausklappen', 'erweitern'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Tabelle',
    description: 'Tabelle einf\u00FCgen',
    icon: '\u25A6',
    keywords: ['table', 'tabelle', 'grid'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
  {
    title: 'Bild',
    description: 'Bild einf\u00FCgen',
    icon: '\uD83D\uDDBC',
    keywords: ['image', 'bild', 'foto', 'picture'],
    action: (_state: EditorState, _dispatch: EditorView['dispatch'], _view: EditorView) => {
      // This needs to be handled by the editor
    },
  },
]

export default SlashCommands
