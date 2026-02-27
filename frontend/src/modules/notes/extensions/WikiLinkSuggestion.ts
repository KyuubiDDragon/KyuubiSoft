import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'
import type { EditorView } from '@tiptap/pm/view'

/**
 * A suggestion item returned by getSuggestions
 */
export interface WikiLinkSuggestionItem {
  id: string
  title: string
  [key: string]: unknown
}

/**
 * Internal plugin state for the suggestion menu
 */
export interface WikiLinkSuggestionState {
  active: boolean
  range: { from: number; to: number } | null
  query: string
  suggestions: WikiLinkSuggestionItem[]
  selectedIndex: number
  decorationSet: DecorationSet
}

/**
 * Custom event detail for wiki-link-suggestion events
 */
export interface WikiLinkSuggestionEventDetail {
  active: boolean
  query: string
  suggestions: WikiLinkSuggestionItem[]
  selectedIndex: number
  range: { from: number; to: number } | null
  coords: { top: number; left: number; right: number; bottom: number } | null
}

/**
 * Options for the WikiLinkSuggestion extension
 */
export interface WikiLinkSuggestionOptions {
  getSuggestions: (query: string) => Promise<WikiLinkSuggestionItem[]>
  onSelect: ((view: EditorView, range: { from: number; to: number } | null, suggestion: WikiLinkSuggestionItem) => void) | null
  char: string
}

/**
 * WikiLink Suggestion Extension
 * Provides autocomplete suggestions when typing [[ in the editor
 */
export const WikiLinkSuggestion = Extension.create<WikiLinkSuggestionOptions>({
  name: 'wikiLinkSuggestion',

  addOptions() {
    return {
      getSuggestions: async (_query: string) => [], // async function to get suggestions
      onSelect: null, // callback when a suggestion is selected
      char: '[[', // trigger character(s)
    }
  },

  addProseMirrorPlugins() {
    const { getSuggestions, onSelect, char } = this.options
    const pluginKey = new PluginKey('wikiLinkSuggestion')

    return [
      new Plugin({
        key: pluginKey,
        state: {
          init(): WikiLinkSuggestionState {
            return {
              active: false,
              range: null,
              query: '',
              suggestions: [],
              selectedIndex: 0,
              decorationSet: DecorationSet.empty,
            }
          },
          apply(tr, state: WikiLinkSuggestionState, oldState, newState): WikiLinkSuggestionState {
            // Check if the plugin state has been updated via meta
            const meta = tr.getMeta(pluginKey)
            if (meta) {
              return { ...state, ...meta }
            }

            // Reset if document changed in a way that would invalidate the suggestion
            if (tr.docChanged && state.active) {
              // Check if we're still in a valid suggestion context
              const { $from } = newState.selection
              const textBefore = $from.parent.textBetween(0, $from.parentOffset)
              const match = textBefore.match(/\[\[([^\]]*)?$/)

              if (!match) {
                return {
                  ...state,
                  active: false,
                  range: null,
                  query: '',
                  suggestions: [],
                }
              }
            }

            return state
          },
        },
        props: {
          handleKeyDown(view: EditorView, event: KeyboardEvent): boolean {
            const state = pluginKey.getState(view.state) as WikiLinkSuggestionState
            if (!state.active) return false

            if (event.key === 'ArrowDown') {
              event.preventDefault()
              const newIndex = Math.min(state.selectedIndex + 1, state.suggestions.length - 1)
              view.dispatch(view.state.tr.setMeta(pluginKey, { selectedIndex: newIndex }))
              return true
            }

            if (event.key === 'ArrowUp') {
              event.preventDefault()
              const newIndex = Math.max(state.selectedIndex - 1, 0)
              view.dispatch(view.state.tr.setMeta(pluginKey, { selectedIndex: newIndex }))
              return true
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
              event.preventDefault()
              const suggestion = state.suggestions[state.selectedIndex]
              if (suggestion && onSelect) {
                onSelect(view, state.range, suggestion)
              }
              view.dispatch(view.state.tr.setMeta(pluginKey, {
                active: false,
                suggestions: [],
                query: ''
              }))
              return true
            }

            if (event.key === 'Escape') {
              event.preventDefault()
              view.dispatch(view.state.tr.setMeta(pluginKey, {
                active: false,
                suggestions: [],
                query: ''
              }))
              return true
            }

            return false
          },

          handleTextInput(view: EditorView, from: number, to: number, text: string): boolean {
            const state = pluginKey.getState(view.state) as WikiLinkSuggestionState
            const { $from } = view.state.selection
            const textBefore = $from.parent.textBetween(0, $from.parentOffset) + text

            // Check if we just typed [[
            if (textBefore.endsWith('[[')) {
              setTimeout(async () => {
                const suggestions = await getSuggestions('')
                view.dispatch(view.state.tr.setMeta(pluginKey, {
                  active: true,
                  range: { from: from - 1, to: to + 1 },
                  query: '',
                  suggestions,
                  selectedIndex: 0,
                }))
              }, 0)
            }
            // Check if we're in a suggestion context
            else if (state.active) {
              const match = textBefore.match(/\[\[([^\]]*)?$/)
              if (match) {
                const query = match[1] || ''
                setTimeout(async () => {
                  const suggestions = await getSuggestions(query)
                  const start = $from.pos - query.length - 2 // -2 for [[
                  view.dispatch(view.state.tr.setMeta(pluginKey, {
                    query,
                    range: { from: start, to: $from.pos },
                    suggestions,
                    selectedIndex: 0,
                  }))
                }, 0)
              } else {
                view.dispatch(view.state.tr.setMeta(pluginKey, {
                  active: false,
                  suggestions: [],
                  query: '',
                }))
              }
            }

            return false
          },
        },

        view() {
          let component: unknown = null

          return {
            update(view: EditorView, prevState) {
              const state = pluginKey.getState(view.state) as WikiLinkSuggestionState
              const prevPluginState = pluginKey.getState(prevState) as WikiLinkSuggestionState | undefined

              // Emit custom event for the Vue component to handle
              if (state.active !== prevPluginState?.active ||
                  state.suggestions !== prevPluginState?.suggestions ||
                  state.selectedIndex !== prevPluginState?.selectedIndex) {

                const detail: WikiLinkSuggestionEventDetail = {
                  active: state.active,
                  query: state.query,
                  suggestions: state.suggestions,
                  selectedIndex: state.selectedIndex,
                  range: state.range,
                  coords: state.active ? view.coordsAtPos(view.state.selection.from) : null,
                }

                const event = new CustomEvent('wiki-link-suggestion', { detail })
                view.dom.dispatchEvent(event)
              }
            },
            destroy() {
              // Cleanup if needed
            },
          }
        },
      }),
    ]
  },
})

export default WikiLinkSuggestion
