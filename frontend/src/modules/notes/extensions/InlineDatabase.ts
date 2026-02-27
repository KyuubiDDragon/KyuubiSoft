import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import InlineDatabaseNode from '../components/database/InlineDatabaseNode.vue'

/**
 * Database view types
 */
export type DatabaseView = 'table' | 'board' | 'gallery' | 'list' | 'calendar'

/**
 * Options for the InlineDatabase extension
 */
export interface InlineDatabaseOptions {
  HTMLAttributes: Record<string, unknown>
}

/**
 * InlineDatabase node attributes
 */
export interface InlineDatabaseAttributes {
  databaseId: string | null
  name: string
  view: DatabaseView
  noteId: string | null
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    inlineDatabase: {
      insertDatabase: (attributes?: Partial<InlineDatabaseAttributes>) => ReturnType
      updateDatabase: (databaseId: string, attributes: Partial<InlineDatabaseAttributes>) => ReturnType
      deleteDatabase: (databaseId: string) => ReturnType
    }
  }
}

/**
 * InlineDatabase Extension for TipTap
 * Allows embedding Notion-style databases inline within notes
 */
export const InlineDatabase = Node.create<InlineDatabaseOptions>({
  name: 'inlineDatabase',

  addOptions() {
    return {
      HTMLAttributes: {},
    }
  },

  group: 'block',

  atom: true,

  draggable: true,

  addAttributes() {
    return {
      databaseId: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-database-id'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-database-id': attributes.databaseId,
        }),
      },
      name: {
        default: 'Neue Datenbank',
        parseHTML: (element: HTMLElement) => element.getAttribute('data-database-name'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-database-name': attributes.name,
        }),
      },
      view: {
        default: 'table' as DatabaseView,
        parseHTML: (element: HTMLElement) => (element.getAttribute('data-database-view') || 'table') as DatabaseView,
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-database-view': attributes.view,
        }),
      },
      noteId: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-note-id'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-note-id': attributes.noteId,
        }),
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-inline-database]',
      },
    ]
  },

  renderHTML({ node, HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        'data-inline-database': '',
        class: 'inline-database-wrapper my-4',
      }),
      // Placeholder content for SSR/non-Vue rendering
      [
        'div',
        { class: 'inline-database-placeholder bg-dark-700 rounded-lg p-4' },
        `[Datenbank: ${node.attrs.name || 'Ohne Titel'}]`,
      ],
    ]
  },

  addNodeView() {
    return VueNodeViewRenderer(InlineDatabaseNode)
  },

  addCommands() {
    return {
      insertDatabase:
        (attributes: Partial<InlineDatabaseAttributes> = {}) =>
        ({ commands, editor }) => {
          // Get the current note ID from editor options or state
          const noteId = (editor.options as Record<string, unknown>)?.editorProps?.noteId || attributes.noteId

          return commands.insertContent({
            type: this.name,
            attrs: {
              ...attributes,
              noteId,
              name: attributes.name || 'Neue Datenbank',
              view: attributes.view || 'table',
            },
          })
        },

      updateDatabase:
        (databaseId: string, attributes: Partial<InlineDatabaseAttributes>) =>
        ({ commands, tr, state }) => {
          // Find and update the database node
          let found = false
          state.doc.descendants((node, pos) => {
            if (node.type.name === this.name && node.attrs.databaseId === databaseId) {
              found = true
              commands.command(({ tr }) => {
                tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...attributes })
                return true
              })
            }
          })
          return found
        },

      deleteDatabase:
        (databaseId: string) =>
        ({ commands, state }) => {
          let found = false
          state.doc.descendants((node, pos) => {
            if (node.type.name === this.name && node.attrs.databaseId === databaseId) {
              found = true
              commands.deleteRange({ from: pos, to: pos + node.nodeSize })
            }
          })
          return found
        },
    }
  },
})

export default InlineDatabase
