import { Mark, mergeAttributes } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import type { EditorView } from '@tiptap/pm/view'

/**
 * Options for the WikiLink extension
 */
export interface WikiLinkOptions {
  HTMLAttributes: Record<string, unknown>
  onNavigate: ((href: string) => void) | null
  validateLink: ((href: string) => boolean) | null
}

/**
 * WikiLink mark attributes
 */
export interface WikiLinkAttributes {
  href: string | null
  title: string | null
  exists: boolean
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    wikiLink: {
      setWikiLink: (attributes: Partial<WikiLinkAttributes>) => ReturnType
      toggleWikiLink: (attributes: Partial<WikiLinkAttributes>) => ReturnType
      unsetWikiLink: () => ReturnType
    }
  }
}

/**
 * WikiLink Extension for TipTap
 * Handles [[wiki links]] syntax in notes
 */
export const WikiLink = Mark.create<WikiLinkOptions>({
  name: 'wikiLink',

  addOptions() {
    return {
      HTMLAttributes: {
        class: 'wiki-link',
      },
      onNavigate: null, // callback for navigation
      validateLink: null, // callback to check if link target exists
    }
  },

  addAttributes() {
    return {
      href: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-href'),
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!attributes.href) return {}
          return { 'data-href': attributes.href }
        },
      },
      title: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-title'),
        renderHTML: (attributes: Record<string, unknown>) => {
          if (!attributes.title) return {}
          return { 'data-title': attributes.title }
        },
      },
      exists: {
        default: true,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-exists') !== 'false',
        renderHTML: (attributes: Record<string, unknown>) => {
          return { 'data-exists': attributes.exists ? 'true' : 'false' }
        },
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'span[data-wiki-link]',
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    const attrs = mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
      'data-wiki-link': '',
    })

    // Add broken class if link doesn't exist
    if (HTMLAttributes.exists === false) {
      attrs.class = `${attrs.class || ''} wiki-link-broken`.trim()
    }

    return ['span', attrs, 0]
  },

  addCommands() {
    return {
      setWikiLink:
        (attributes: Partial<WikiLinkAttributes>) =>
        ({ commands }) => {
          return commands.setMark(this.name, attributes)
        },
      toggleWikiLink:
        (attributes: Partial<WikiLinkAttributes>) =>
        ({ commands }) => {
          return commands.toggleMark(this.name, attributes)
        },
      unsetWikiLink:
        () =>
        ({ commands }) => {
          return commands.unsetMark(this.name)
        },
    }
  },

  addProseMirrorPlugins() {
    const { onNavigate } = this.options

    return [
      new Plugin({
        key: new PluginKey('wikiLinkClick'),
        props: {
          handleClick(view: EditorView, pos: number, event: MouseEvent): boolean {
            const target = event.target as HTMLElement
            if (target.hasAttribute('data-wiki-link')) {
              const href = target.getAttribute('data-href')
              if (href && onNavigate) {
                event.preventDefault()
                onNavigate(href)
                return true
              }
            }
            return false
          },
        },
      }),
    ]
  },

  addInputRules() {
    // Input rule to convert [[text]] to wiki link
    return [
      {
        find: /\[\[([^\]]+)\]\]/g,
        handler: ({ state, range, match }: { state: any; range: { from: number; to: number }; match: RegExpMatchArray }) => {
          const text = match[1]
          const { tr } = state

          if (text) {
            // Delete the matched text including brackets
            tr.delete(range.from, range.to)

            // Insert the text with wiki link mark
            const node = state.schema.text(text)
            tr.insert(range.from, node)

            // Apply wiki link mark
            tr.addMark(
              range.from,
              range.from + text.length,
              this.type.create({
                href: text.toLowerCase().replace(/\s+/g, '-'),
                title: text,
                exists: true, // Will be validated later
              })
            )
          }
        },
      },
    ]
  },
})

export default WikiLink
