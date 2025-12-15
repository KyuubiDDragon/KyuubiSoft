import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'

/**
 * WikiLink Suggestion Extension
 * Provides autocomplete suggestions when typing [[ in the editor
 */
export const WikiLinkSuggestion = Extension.create({
  name: 'wikiLinkSuggestion',

  addOptions() {
    return {
      getSuggestions: async () => [], // async function to get suggestions
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
          init() {
            return {
              active: false,
              range: null,
              query: '',
              suggestions: [],
              selectedIndex: 0,
              decorationSet: DecorationSet.empty,
            }
          },
          apply(tr, state, oldState, newState) {
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
          handleKeyDown(view, event) {
            const state = pluginKey.getState(view.state)
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

          handleTextInput(view, from, to, text) {
            const state = pluginKey.getState(view.state)
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
          let component = null

          return {
            update(view, prevState) {
              const state = pluginKey.getState(view.state)
              const prevPluginState = pluginKey.getState(prevState)

              // Emit custom event for the Vue component to handle
              if (state.active !== prevPluginState?.active ||
                  state.suggestions !== prevPluginState?.suggestions ||
                  state.selectedIndex !== prevPluginState?.selectedIndex) {

                const event = new CustomEvent('wiki-link-suggestion', {
                  detail: {
                    active: state.active,
                    query: state.query,
                    suggestions: state.suggestions,
                    selectedIndex: state.selectedIndex,
                    range: state.range,
                    coords: state.active ? view.coordsAtPos(view.state.selection.from) : null,
                  },
                })
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
