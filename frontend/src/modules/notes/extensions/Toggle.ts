import { Node, mergeAttributes } from '@tiptap/core'

/**
 * Options for the Toggle extension
 */
export interface ToggleOptions {
  HTMLAttributes: Record<string, unknown>
}

/**
 * Toggle node attributes
 */
export interface ToggleAttributes {
  open: boolean
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    toggle: {
      setToggle: () => ReturnType
      toggleToggle: () => ReturnType
    }
  }
}

/**
 * Toggle Extension for TipTap
 * Provides collapsible content sections (like Notion's toggle blocks)
 */
export const Toggle = Node.create<ToggleOptions>({
  name: 'toggle',

  addOptions() {
    return {
      HTMLAttributes: {},
    }
  },

  group: 'block',

  content: 'toggleTitle toggleContent',

  defining: true,

  addAttributes() {
    return {
      open: {
        default: true,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-open') !== 'false',
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-open': attributes.open ? 'true' : 'false',
        }),
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-toggle]',
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        'data-toggle': '',
        class: 'toggle-block my-2',
      }),
      0,
    ]
  },

  addCommands() {
    return {
      setToggle:
        () =>
        ({ commands, state }) => {
          // Create a toggle with default content
          return commands.insertContent({
            type: this.name,
            attrs: { open: true },
            content: [
              {
                type: 'toggleTitle',
                content: [{ type: 'text', text: 'Toggle' }],
              },
              {
                type: 'toggleContent',
                content: [{ type: 'paragraph' }],
              },
            ],
          })
        },
      toggleToggle:
        () =>
        ({ commands, state, tr }) => {
          // Toggle the open state
          const { selection } = state
          const node = selection.$anchor.node(-1)

          if (node?.type.name === this.name || node?.type.name === 'toggleTitle' || node?.type.name === 'toggleContent') {
            const pos = selection.$anchor.before(-1)
            const toggleNode = state.doc.nodeAt(pos)

            if (toggleNode?.type.name === this.name) {
              return commands.updateAttributes(this.name, { open: !toggleNode.attrs.open })
            }
          }

          return false
        },
    }
  },
})

/**
 * Toggle Title - The clickable header of a toggle block
 */
export const ToggleTitle = Node.create({
  name: 'toggleTitle',

  group: 'block',

  content: 'inline*',

  defining: true,

  parseHTML() {
    return [
      {
        tag: 'div[data-toggle-title]',
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(HTMLAttributes, {
        'data-toggle-title': '',
        class: 'toggle-title flex items-center gap-2 cursor-pointer py-1 font-medium',
      }),
      [
        'span',
        { class: 'toggle-icon transition-transform' },
        '\u25B6',
      ],
      [
        'span',
        { class: 'toggle-title-text' },
        0,
      ],
    ]
  },

  addKeyboardShortcuts() {
    return {
      Enter: ({ editor }) => {
        const { state } = editor
        const { selection } = state
        const { $from } = selection

        // If we're in a toggle title, move to toggle content
        if ($from.parent.type.name === 'toggleTitle') {
          // Find the toggle content and focus it
          const togglePos = $from.before(-1)
          const toggle = state.doc.nodeAt(togglePos)

          if (toggle) {
            // Get position of toggle content (after title)
            const contentPos = togglePos + $from.parent.nodeSize + 2

            return editor.chain()
              .setTextSelection(contentPos)
              .run()
          }
        }

        return false
      },
    }
  },
})

/**
 * Toggle Content - The collapsible content of a toggle block
 */
export const ToggleContent = Node.create({
  name: 'toggleContent',

  group: 'block',

  content: 'block+',

  defining: true,

  parseHTML() {
    return [
      {
        tag: 'div[data-toggle-content]',
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(HTMLAttributes, {
        'data-toggle-content': '',
        class: 'toggle-content ml-6 mt-1',
      }),
      0,
    ]
  },
})

export default Toggle
