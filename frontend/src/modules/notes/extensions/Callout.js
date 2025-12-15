import { Node, mergeAttributes } from '@tiptap/core'

/**
 * Callout Extension for TipTap
 * Provides styled info/warning/tip/danger callout blocks
 */
export const Callout = Node.create({
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
        default: 'info',
        parseHTML: (element) => element.getAttribute('data-callout-type') || 'info',
        renderHTML: (attributes) => ({
          'data-callout-type': attributes.type,
        }),
      },
      title: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-callout-title'),
        renderHTML: (attributes) => {
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
    const type = node.attrs.type || 'info'
    const typeClasses = {
      info: 'callout-info border-blue-500 bg-blue-500/10',
      warning: 'callout-warning border-yellow-500 bg-yellow-500/10',
      tip: 'callout-tip border-green-500 bg-green-500/10',
      danger: 'callout-danger border-red-500 bg-red-500/10',
    }

    const icons = {
      info: 'ℹ️',
      warning: '⚠️',
      tip: '💡',
      danger: '❌',
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
        (attributes) =>
        ({ commands }) => {
          return commands.wrapIn(this.name, attributes)
        },
      toggleCallout:
        (attributes) =>
        ({ commands }) => {
          return commands.toggleWrap(this.name, attributes)
        },
      unsetCallout:
        () =>
        ({ commands }) => {
          return commands.lift(this.name)
        },
      setCalloutType:
        (type) =>
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
