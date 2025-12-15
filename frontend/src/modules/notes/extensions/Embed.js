import { Node, mergeAttributes } from '@tiptap/core'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import EmbedNode from '../components/embeds/EmbedNode.vue'

/**
 * Embed Extension for TipTap
 * Supports YouTube, Vimeo, Twitter, CodePen, Figma, Loom, and generic iframes
 */
export const Embed = Node.create({
  name: 'embed',

  addOptions() {
    return {
      HTMLAttributes: {},
      // Supported embed providers
      providers: {
        youtube: {
          name: 'YouTube',
          regex: /(?:youtube\.com\/(?:[^/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?/\s]{11})/i,
          embedUrl: (id) => `https://www.youtube.com/embed/${id}`,
          aspectRatio: '16/9',
        },
        vimeo: {
          name: 'Vimeo',
          regex: /(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/i,
          embedUrl: (id) => `https://player.vimeo.com/video/${id}`,
          aspectRatio: '16/9',
        },
        twitter: {
          name: 'Twitter/X',
          regex: /(?:twitter\.com|x\.com)\/(?:#!\/)?(\w+)\/status(?:es)?\/(\d+)/i,
          embedUrl: (match) => `https://platform.twitter.com/embed/Tweet.html?id=${match[2]}`,
          aspectRatio: null, // Dynamic height
          isOEmbed: true,
        },
        codepen: {
          name: 'CodePen',
          regex: /codepen\.io\/([^/]+)\/(?:pen|embed)\/([^?/]+)/i,
          embedUrl: (match) => `https://codepen.io/${match[1]}/embed/${match[2]}?default-tab=result`,
          aspectRatio: '4/3',
        },
        figma: {
          name: 'Figma',
          regex: /figma\.com\/(file|proto)\/([^/]+)/i,
          embedUrl: (match) => `https://www.figma.com/embed?embed_host=share&url=https://www.figma.com/${match[1]}/${match[2]}`,
          aspectRatio: '16/9',
        },
        loom: {
          name: 'Loom',
          regex: /loom\.com\/share\/([a-zA-Z0-9]+)/i,
          embedUrl: (id) => `https://www.loom.com/embed/${id}`,
          aspectRatio: '16/9',
        },
        spotify: {
          name: 'Spotify',
          regex: /open\.spotify\.com\/(track|album|playlist|episode)\/([a-zA-Z0-9]+)/i,
          embedUrl: (match) => `https://open.spotify.com/embed/${match[1]}/${match[2]}`,
          aspectRatio: null,
          height: '152px',
        },
        googleMaps: {
          name: 'Google Maps',
          regex: /google\.com\/maps\/(?:embed\?pb=|place\/|@)([^&\s]+)/i,
          embedUrl: (match, url) => {
            if (url.includes('embed?pb=')) return url
            return `https://www.google.com/maps/embed?pb=${match[1]}`
          },
          aspectRatio: '16/9',
        },
        miro: {
          name: 'Miro',
          regex: /miro\.com\/app\/board\/([^/?]+)/i,
          embedUrl: (id) => `https://miro.com/app/embed/${id}/`,
          aspectRatio: '16/9',
        },
        excalidraw: {
          name: 'Excalidraw',
          regex: /excalidraw\.com\/#json=([^&]+)/i,
          embedUrl: (match, url) => url,
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
        parseHTML: (element) => element.getAttribute('data-embed-src'),
        renderHTML: (attributes) => ({
          'data-embed-src': attributes.src,
        }),
      },
      provider: {
        default: 'generic',
        parseHTML: (element) => element.getAttribute('data-embed-provider'),
        renderHTML: (attributes) => ({
          'data-embed-provider': attributes.provider,
        }),
      },
      embedId: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-embed-id'),
        renderHTML: (attributes) => ({
          'data-embed-id': attributes.embedId,
        }),
      },
      embedUrl: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-embed-url'),
        renderHTML: (attributes) => ({
          'data-embed-url': attributes.embedUrl,
        }),
      },
      title: {
        default: '',
        parseHTML: (element) => element.getAttribute('data-embed-title'),
        renderHTML: (attributes) => ({
          'data-embed-title': attributes.title,
        }),
      },
      aspectRatio: {
        default: '16/9',
        parseHTML: (element) => element.getAttribute('data-aspect-ratio'),
        renderHTML: (attributes) => ({
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
        (options) =>
        ({ commands }) => {
          const { url, ...attrs } = options
          const parsed = this.options.providers
            ? parseEmbedUrl(url, this.options.providers)
            : { provider: 'generic', embedUrl: url, src: url }

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
function parseEmbedUrl(url, providers) {
  for (const [key, provider] of Object.entries(providers)) {
    const match = url.match(provider.regex)
    if (match) {
      let embedUrl
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
