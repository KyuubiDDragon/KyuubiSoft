import { Node, mergeAttributes } from '@tiptap/core'
import type { NodeSpec } from '@tiptap/pm/model'

/**
 * Callout type options
 */
export type CalloutType = 'info' | 'warning' | 'tip' | 'danger'

/**
 * Options for the Callout extension
 */
export interface CalloutOptions {
  HTMLAttributes: Record<string, unknown>
  types: CalloutType[]
}

/**
 * Callout node attributes
 */
export interface CalloutAttributes {
  type: CalloutType
  title: string | null
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    callout: {
      setCallout: (attributes?: Partial<CalloutAttributes>) => ReturnType
      toggleCallout: (attributes?: Partial<CalloutAttributes>) => ReturnType
      unsetCallout: () => ReturnType
      setCalloutType: (type: CalloutType) => ReturnType
    }
  }
}

/**
 * Callout Extension for TipTap
 * Provides styled info/warning/tip/danger callout blocks
 */
export const Callout = Node.create<CalloutOptions>({
  name: 'callout',

  addOptions() {
    return {
      HTMLAttributes: {},
      types: ['info', 'warning', 'tip', 'danger'],
    }
  },

  group: 'block',

  content: 'block+',

  defining: true,

  addAttributes() {
    return {
      type: {
        default: 'info' as CalloutType,
        parseHTML: (element: HTMLElement) => (element.getAttribute('data-callout-type') || 'info') as CalloutType,
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-callout-type': attributes.type,
        }),
      },
      title: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-callout-title'),
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!attributes.title) return {}
          return { 'data-callout-title': attributes.title }
        },
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-callout]',
      },
    ]
  },

  renderHTML({ node, HTMLAttributes }) {
    const type = (node.attrs.type || 'info') as CalloutType
    const typeClasses: Record<CalloutType, string> = {
      info: 'callout-info border-blue-500 bg-blue-500/10',
      warning: 'callout-warning border-yellow-500 bg-yellow-500/10',
      tip: 'callout-tip border-green-500 bg-green-500/10',
      danger: 'callout-danger border-red-500 bg-red-500/10',
    }

    const icons: Record<CalloutType, string> = {
      info: '\u2139\uFE0F',
      warning: '\u26A0\uFE0F',
      tip: '\uD83D\uDCA1',
      danger: '\u274C',
    }

    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        'data-callout': '',
        class: `callout ${typeClasses[type]} my-4 rounded-lg border-l-4 p-4`,
      }),
      [
        'div',
        { class: 'callout-header flex items-center gap-2 mb-2 font-semibold' },
        [
          'span',
          { class: 'callout-icon' },
          icons[type],
        ],
        [
          'span',
          { class: 'callout-title' },
          node.attrs.title || type.charAt(0).toUpperCase() + type.slice(1),
        ],
      ],
      [
        'div',
        { class: 'callout-content' },
        0, // Content placeholder
      ],
    ]
  },

  addCommands() {
    return {
      setCallout:
        (attributes?: Partial<CalloutAttributes>) =>
        ({ commands }) => {
          return commands.wrapIn(this.name, attributes)
        },
      toggleCallout:
        (attributes?: Partial<CalloutAttributes>) =>
        ({ commands }) => {
          return commands.toggleWrap(this.name, attributes)
        },
      unsetCallout:
        () =>
        ({ commands }) => {
          return commands.lift(this.name)
        },
      setCalloutType:
        (type: CalloutType) =>
        ({ commands }) => {
          return commands.updateAttributes(this.name, { type })
        },
    }
  },

  addKeyboardShortcuts() {
    return {
      // Enter at the end of a callout should exit the callout
      Enter: ({ editor }) => {
        const { state } = editor
        const { selection } = state
        const { $from, empty } = selection

        if (!empty) return false

        // Check if we're in a callout
        const callout = $from.node(-1)?.type.name === 'callout'
          ? $from.node(-1)
          : null

        if (!callout) return false

        // If at the end of the callout and the last block is empty
        const isAtEnd = $from.parentOffset === $from.parent.content.size
        const parentIsEmpty = $from.parent.content.size === 0

        if (isAtEnd && parentIsEmpty) {
          // Exit the callout
          return editor.chain()
            .deleteNode($from.parent.type)
            .insertContentAt(editor.state.selection.from, { type: 'paragraph' })
            .run()
        }

        return false
      },
    }
  },
})

export default Callout
