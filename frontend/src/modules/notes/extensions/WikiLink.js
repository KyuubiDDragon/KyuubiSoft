import { Mark, mergeAttributes } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'

/**
 * WikiLink Extension for TipTap
 * Handles [[wiki links]] syntax in notes
 */
export const WikiLink = Mark.create({
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
        parseHTML: (element) => element.getAttribute('data-href'),
        renderHTML: (attributes) => {
          if (!attributes.href) return {}
          return { 'data-href': attributes.href }
        },
      },
      title: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-title'),
        renderHTML: (attributes) => {
          if (!attributes.title) return {}
          return { 'data-title': attributes.title }
        },
      },
      exists: {
        default: true,
        parseHTML: (element) => element.getAttribute('data-exists') !== 'false',
        renderHTML: (attributes) => {
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
        (attributes) =>
        ({ commands }) => {
          return commands.setMark(this.name, attributes)
        },
      toggleWikiLink:
        (attributes) =>
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
          handleClick(view, pos, event) {
            const target = event.target
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
        handler: ({ state, range, match }) => {
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
