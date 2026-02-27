import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import EmbedNode from '../components/embeds/EmbedNode.vue'

/**
 * Embed provider configuration
 */
export interface EmbedProvider {
  name: string
  regex: RegExp
  embedUrl: ((idOrMatch: string | RegExpMatchArray, url?: string) => string) | string
  aspectRatio: string | null
  isOEmbed?: boolean
  height?: string
}

/**
 * Parsed embed result
 */
export interface ParsedEmbed {
  provider: string
  src: string
  embedId?: string
  embedUrl: string
  title: string
  aspectRatio: string
  height?: string | null
}

/**
 * Options for the Embed extension
 */
export interface EmbedOptions {
  HTMLAttributes: Record<string, unknown>
  providers: Record<string, EmbedProvider>
}

/**
 * Embed node attributes
 */
export interface EmbedAttributes {
  src: string | null
  provider: string
  embedId: string | null
  embedUrl: string | null
  title: string
  aspectRatio: string
  width: string
  height: string | null
}

/**
 * Options passed to the setEmbed command
 */
export interface SetEmbedOptions {
  url: string
  [key: string]: unknown
}

declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    embed: {
      setEmbed: (options: SetEmbedOptions) => ReturnType
    }
  }
}

/**
 * Embed Extension for TipTap
 * Supports YouTube, Vimeo, Twitter, CodePen, Figma, Loom, and generic iframes
 */
export const Embed = Node.create<EmbedOptions>({
  name: 'embed',

  addOptions() {
    return {
      HTMLAttributes: {},
      // Supported embed providers
      providers: {
        youtube: {
          name: 'YouTube',
          regex: /(?:youtube\.com\/(?:[^/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?/\s]{11})/i,
          embedUrl: (id: string | RegExpMatchArray) => `https://www.youtube.com/embed/${typeof id === 'string' ? id : id[1]}`,
          aspectRatio: '16/9',
        },
        vimeo: {
          name: 'Vimeo',
          regex: /(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/i,
          embedUrl: (id: string | RegExpMatchArray) => `https://player.vimeo.com/video/${typeof id === 'string' ? id : id[1]}`,
          aspectRatio: '16/9',
        },
        twitter: {
          name: 'Twitter/X',
          regex: /(?:twitter\.com|x\.com)\/(?:#!\/)?(\w+)\/status(?:es)?\/(\d+)/i,
          embedUrl: (match: string | RegExpMatchArray) => `https://platform.twitter.com/embed/Tweet.html?id=${typeof match === 'string' ? match : match[2]}`,
          aspectRatio: null, // Dynamic height
          isOEmbed: true,
        },
        codepen: {
          name: 'CodePen',
          regex: /codepen\.io\/([^/]+)\/(?:pen|embed)\/([^?/]+)/i,
          embedUrl: (match: string | RegExpMatchArray) => `https://codepen.io/${typeof match === 'string' ? match : match[1]}/embed/${typeof match === 'string' ? match : match[2]}?default-tab=result`,
          aspectRatio: '4/3',
        },
        figma: {
          name: 'Figma',
          regex: /figma\.com\/(file|proto)\/([^/]+)/i,
          embedUrl: (match: string | RegExpMatchArray) => `https://www.figma.com/embed?embed_host=share&url=https://www.figma.com/${typeof match === 'string' ? match : match[1]}/${typeof match === 'string' ? match : match[2]}`,
          aspectRatio: '16/9',
        },
        loom: {
          name: 'Loom',
          regex: /loom\.com\/share\/([a-zA-Z0-9]+)/i,
          embedUrl: (id: string | RegExpMatchArray) => `https://www.loom.com/embed/${typeof id === 'string' ? id : id[1]}`,
          aspectRatio: '16/9',
        },
        spotify: {
          name: 'Spotify',
          regex: /open\.spotify\.com\/(track|album|playlist|episode)\/([a-zA-Z0-9]+)/i,
          embedUrl: (match: string | RegExpMatchArray) => `https://open.spotify.com/embed/${typeof match === 'string' ? match : match[1]}/${typeof match === 'string' ? match : match[2]}`,
          aspectRatio: null,
          height: '152px',
        },
        googleMaps: {
          name: 'Google Maps',
          regex: /google\.com\/maps\/(?:embed\?pb=|place\/|@)([^&\s]+)/i,
          embedUrl: (match: string | RegExpMatchArray, url?: string) => {
            if (url && url.includes('embed?pb=')) return url
            return `https://www.google.com/maps/embed?pb=${typeof match === 'string' ? match : match[1]}`
          },
          aspectRatio: '16/9',
        },
        miro: {
          name: 'Miro',
          regex: /miro\.com\/app\/board\/([^/?]+)/i,
          embedUrl: (id: string | RegExpMatchArray) => `https://miro.com/app/embed/${typeof id === 'string' ? id : id[1]}/`,
          aspectRatio: '16/9',
        },
        excalidraw: {
          name: 'Excalidraw',
          regex: /excalidraw\.com\/#json=([^&]+)/i,
          embedUrl: (_match: string | RegExpMatchArray, url?: string) => url || '',
          aspectRatio: '16/9',
        },
      },
    }
  },

  group: 'block',

  atom: true,

  draggable: true,

  addAttributes() {
    return {
      src: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-embed-src'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-embed-src': attributes.src,
        }),
      },
      provider: {
        default: 'generic',
        parseHTML: (element: HTMLElement) => element.getAttribute('data-embed-provider'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-embed-provider': attributes.provider,
        }),
      },
      embedId: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-embed-id'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-embed-id': attributes.embedId,
        }),
      },
      embedUrl: {
        default: null,
        parseHTML: (element: HTMLElement) => element.getAttribute('data-embed-url'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-embed-url': attributes.embedUrl,
        }),
      },
      title: {
        default: '',
        parseHTML: (element: HTMLElement) => element.getAttribute('data-embed-title'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-embed-title': attributes.title,
        }),
      },
      aspectRatio: {
        default: '16/9',
        parseHTML: (element: HTMLElement) => element.getAttribute('data-aspect-ratio'),
        renderHTML: (attributes: Record<string, unknown>) => ({
          'data-aspect-ratio': attributes.aspectRatio,
        }),
      },
      width: {
        default: '100%',
      },
      height: {
        default: null,
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-embed]',
      },
    ]
  },

  renderHTML({ node, HTMLAttributes }) {
    return [
      'div',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        'data-embed': '',
        class: 'embed-wrapper my-4',
      }),
      [
        'div',
        { class: 'embed-placeholder bg-dark-700 rounded-lg p-4 text-center text-gray-500' },
        `[${node.attrs.provider}: ${node.attrs.title || node.attrs.src}]`,
      ],
    ]
  },

  addNodeView() {
    return VueNodeViewRenderer(EmbedNode)
  },

  addCommands() {
    return {
      setEmbed:
        (options: SetEmbedOptions) =>
        ({ commands }) => {
          const { url, ...attrs } = options
          const parsed = this.options.providers
            ? parseEmbedUrl(url, this.options.providers)
            : { provider: 'generic', embedUrl: url, src: url, title: 'Embed', aspectRatio: '16/9' }

          return commands.insertContent({
            type: this.name,
            attrs: {
              ...parsed,
              ...attrs,
            },
          })
        },
    }
  },
})

/**
 * Parse URL and detect embed provider
 */
function parseEmbedUrl(url: string, providers: Record<string, EmbedProvider>): ParsedEmbed {
  for (const [key, provider] of Object.entries(providers)) {
    const match = url.match(provider.regex)
    if (match) {
      let embedUrl: string
      if (typeof provider.embedUrl === 'function') {
        embedUrl = provider.embedUrl(match, url)
      } else {
        embedUrl = provider.embedUrl
      }

      return {
        provider: key,
        src: url,
        embedId: match[1],
        embedUrl: embedUrl,
        title: provider.name,
        aspectRatio: provider.aspectRatio || '16/9',
        height: provider.height || null,
      }
    }
  }

  // Generic iframe
  return {
    provider: 'generic',
    src: url,
    embedUrl: url,
    title: 'Embed',
    aspectRatio: '16/9',
  }
}

export default Embed
