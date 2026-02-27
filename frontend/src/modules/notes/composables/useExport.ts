import { ref, type Ref } from 'vue'
import TurndownService from 'turndown'
import { gfm } from 'turndown-plugin-gfm'
import { stripHtml, sanitizeHtml } from '@/core/services/sanitize'

/**
 * Note data structure used for exports
 */
export interface ExportableNote {
  id?: string
  title?: string
  content?: string
  icon?: string
  cover?: string
  parent_id?: string | null
  created_at?: string
  updated_at?: string
  tags?: string[]
  properties?: Record<string, unknown>
}

/**
 * JSON export envelope
 */
export interface NoteExportData {
  version: string
  exported_at: string
  note: {
    id?: string
    title?: string
    content?: string
    icon?: string
    cover?: string
    parent_id?: string | null
    created_at?: string
    updated_at?: string
    tags: string[]
    properties: Record<string, unknown>
  }
}

/**
 * Return type of the useExport composable
 */
export interface UseExportReturn {
  isExporting: Ref<boolean>
  exportError: Ref<string | null>
  exportMarkdown: (note: ExportableNote) => boolean
  exportHTML: (note: ExportableNote) => boolean
  exportPDF: (note: ExportableNote) => Promise<boolean>
  exportText: (note: ExportableNote) => boolean
  exportJSON: (note: ExportableNote) => boolean
}

/**
 * Composable for exporting notes in various formats
 */
export function useExport(): UseExportReturn {
  const isExporting = ref<boolean>(false)
  const exportError = ref<string | null>(null)

  // Initialize Turndown for HTML to Markdown conversion
  const turndownService = new TurndownService({
    headingStyle: 'atx',
    codeBlockStyle: 'fenced',
    bulletListMarker: '-',
    emDelimiter: '*',
    strongDelimiter: '**',
    linkStyle: 'inlined',
  })

  // Use GitHub-flavored Markdown plugin
  turndownService.use(gfm)

  // Custom rules for wiki links
  turndownService.addRule('wikiLink', {
    filter: (node: HTMLElement) => {
      return node.nodeName === 'SPAN' && node.hasAttribute('data-wiki-link')
    },
    replacement: (content: string, node: HTMLElement) => {
      const href = node.getAttribute('data-wiki-link') || content
      return `[[${content}]]`
    },
  })

  // Custom rules for callouts
  turndownService.addRule('callout', {
    filter: (node: HTMLElement) => {
      return node.nodeName === 'DIV' && node.hasAttribute('data-callout')
    },
    replacement: (content: string, node: HTMLElement) => {
      const type = node.getAttribute('data-callout') || 'info'
      const typeEmoji: Record<string, string> = {
        info: '\u2139\uFE0F',
        warning: '\u26A0\uFE0F',
        tip: '\uD83D\uDCA1',
        danger: '\u274C',
      }
      return `\n> ${typeEmoji[type] || '\uD83D\uDCDD'} **${type.charAt(0).toUpperCase() + type.slice(1)}**\n> ${content.trim().replace(/\n/g, '\n> ')}\n\n`
    },
  })

  // Custom rules for toggles/collapsibles
  turndownService.addRule('toggle', {
    filter: (node: HTMLElement) => {
      return node.nodeName === 'DIV' && node.hasAttribute('data-toggle')
    },
    replacement: (content: string, node: HTMLElement) => {
      const titleNode = node.querySelector('[data-toggle-title]')
      const contentNode = node.querySelector('[data-toggle-content]')
      const title = titleNode ? titleNode.textContent : 'Toggle'
      const toggleContent = contentNode ? contentNode.innerHTML : ''

      return `\n<details>\n<summary>${title}</summary>\n\n${turndownService.turndown(toggleContent)}\n</details>\n\n`
    },
  })

  // Custom rules for task lists
  turndownService.addRule('taskListItem', {
    filter: (node: HTMLElement) => {
      return node.nodeName === 'LI' && node.hasAttribute('data-checked')
    },
    replacement: (content: string, node: HTMLElement) => {
      const isChecked = node.getAttribute('data-checked') === 'true'
      return `- [${isChecked ? 'x' : ' '}] ${content.trim()}\n`
    },
  })

  /**
   * Export note as Markdown
   */
  function exportMarkdown(note: ExportableNote): boolean {
    try {
      isExporting.value = true
      exportError.value = null

      // Create frontmatter
      const frontmatter = [
        '---',
        `title: "${note.title || 'Untitled'}"`,
        `created: ${note.created_at || new Date().toISOString()}`,
        `updated: ${note.updated_at || new Date().toISOString()}`,
        note.tags?.length ? `tags: [${note.tags.map((t: string) => `"${t}"`).join(', ')}]` : null,
        '---',
        '',
      ].filter(Boolean).join('\n')

      // Convert HTML to Markdown
      const content = note.content || ''
      const markdown = turndownService.turndown(content)

      // Combine frontmatter and content
      const fullContent = `${frontmatter}\n# ${note.title || 'Untitled'}\n\n${markdown}`

      // Download file
      downloadFile(fullContent, `${sanitizeFilename(note.title)}.md`, 'text/markdown')

      return true
    } catch (error: unknown) {
      console.error('Export Markdown error:', error)
      exportError.value = error instanceof Error ? error.message : String(error)
      return false
    } finally {
      isExporting.value = false
    }
  }

  /**
   * Export note as HTML
   */
  function exportHTML(note: ExportableNote): boolean {
    try {
      isExporting.value = true
      exportError.value = null

      const htmlContent = `<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${escapeHtml(note.title || 'Untitled')}</title>
  <style>
    :root {
      --bg-primary: #1a1b26;
      --bg-secondary: #24283b;
      --text-primary: #c0caf5;
      --text-secondary: #9aa5ce;
      --accent: #7aa2f7;
      --success: #9ece6a;
      --warning: #e0af68;
      --danger: #f7768e;
      --info: #7dcfff;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      line-height: 1.6;
      padding: 2rem;
      max-width: 800px;
      margin: 0 auto;
    }

    h1 { font-size: 2rem; color: white; margin-bottom: 1.5rem; border-bottom: 2px solid var(--bg-secondary); padding-bottom: 0.5rem; }
    h2 { font-size: 1.5rem; color: white; margin-top: 2rem; }
    h3 { font-size: 1.25rem; color: white; margin-top: 1.5rem; }

    p { margin-bottom: 1rem; }

    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    code {
      background: var(--bg-secondary);
      padding: 0.125rem 0.375rem;
      border-radius: 4px;
      font-family: 'Fira Code', 'Monaco', monospace;
      font-size: 0.875rem;
    }

    pre {
      background: var(--bg-secondary);
      padding: 1rem;
      border-radius: 8px;
      overflow-x: auto;
    }

    pre code {
      background: none;
      padding: 0;
    }

    blockquote {
      border-left: 4px solid var(--accent);
      margin: 1rem 0;
      padding-left: 1rem;
      color: var(--text-secondary);
      font-style: italic;
    }

    ul, ol {
      margin-bottom: 1rem;
      padding-left: 1.5rem;
    }

    li { margin-bottom: 0.25rem; }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 1rem 0;
    }

    th, td {
      border: 1px solid var(--bg-secondary);
      padding: 0.75rem;
      text-align: left;
    }

    th {
      background: var(--bg-secondary);
      font-weight: 600;
    }

    img {
      max-width: 100%;
      border-radius: 8px;
    }

    hr {
      border: none;
      border-top: 1px solid var(--bg-secondary);
      margin: 2rem 0;
    }

    /* Callout styles */
    .callout {
      padding: 1rem;
      border-radius: 8px;
      margin: 1rem 0;
      border-left: 4px solid;
    }

    .callout-info { background: rgba(125, 207, 255, 0.1); border-color: var(--info); }
    .callout-warning { background: rgba(224, 175, 104, 0.1); border-color: var(--warning); }
    .callout-tip { background: rgba(158, 206, 106, 0.1); border-color: var(--success); }
    .callout-danger { background: rgba(247, 118, 142, 0.1); border-color: var(--danger); }

    /* Task list styles */
    ul[data-type="taskList"] {
      list-style: none;
      padding-left: 0;
    }

    ul[data-type="taskList"] li {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }

    ul[data-type="taskList"] li[data-checked="true"] {
      text-decoration: line-through;
      color: var(--text-secondary);
    }

    /* Toggle styles */
    details {
      background: var(--bg-secondary);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      margin: 1rem 0;
    }

    summary {
      cursor: pointer;
      font-weight: 500;
    }

    details[open] summary {
      margin-bottom: 0.5rem;
    }

    /* Wiki link styles */
    [data-wiki-link] {
      color: var(--accent);
      text-decoration: underline dotted;
    }

    .meta {
      color: var(--text-secondary);
      font-size: 0.875rem;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <article>
    <h1>${escapeHtml(note.title || 'Untitled')}</h1>
    <div class="meta">
      <span>Erstellt: ${formatDate(note.created_at)}</span>
      ${note.updated_at ? `<span> | Aktualisiert: ${formatDate(note.updated_at)}</span>` : ''}
    </div>
    <div class="content">
      ${sanitizeHtml(note.content || '<p>Keine Inhalte</p>')}
    </div>
  </article>
</body>
</html>`

      downloadFile(htmlContent, `${sanitizeFilename(note.title)}.html`, 'text/html')

      return true
    } catch (error: unknown) {
      console.error('Export HTML error:', error)
      exportError.value = error instanceof Error ? error.message : String(error)
      return false
    } finally {
      isExporting.value = false
    }
  }

  /**
   * Export note as PDF
   * Uses html2pdf.js library for client-side PDF generation
   */
  async function exportPDF(note: ExportableNote): Promise<boolean> {
    try {
      isExporting.value = true
      exportError.value = null

      // Dynamically import html2pdf
      const html2pdf = (await import('html2pdf.js')).default

      // Create temporary container
      const container = document.createElement('div')
      container.innerHTML = `
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; max-width: 800px;">
          <h1 style="font-size: 24px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e5e5e5;">${escapeHtml(note.title || 'Untitled')}</h1>
          <div style="color: #666; font-size: 12px; margin-bottom: 24px;">
            Erstellt: ${formatDate(note.created_at)}
            ${note.updated_at ? ` | Aktualisiert: ${formatDate(note.updated_at)}` : ''}
          </div>
          <div style="line-height: 1.6;">
            ${sanitizeHtml(note.content || '<p>Keine Inhalte</p>')}
          </div>
        </div>
      `

      // Apply print-friendly styles
      const style = document.createElement('style')
      style.textContent = `
        h1, h2, h3 { color: #111; }
        a { color: #0066cc; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 12px; border-radius: 6px; overflow-x: auto; }
        blockquote { border-left: 3px solid #0066cc; padding-left: 12px; color: #666; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        img { max-width: 100%; }
      `
      container.prepend(style)

      // PDF options
      const options = {
        margin: [10, 10] as [number, number],
        filename: `${sanitizeFilename(note.title)}.pdf`,
        image: { type: 'jpeg' as const, quality: 0.98 },
        html2canvas: {
          scale: 2,
          useCORS: true,
          logging: false,
        },
        jsPDF: {
          unit: 'mm' as const,
          format: 'a4' as const,
          orientation: 'portrait' as const,
        },
      }

      // Generate and download PDF
      await html2pdf().set(options).from(container).save()

      return true
    } catch (error: unknown) {
      console.error('Export PDF error:', error)
      exportError.value = error instanceof Error ? error.message : String(error)
      return false
    } finally {
      isExporting.value = false
    }
  }

  /**
   * Export note as plain text
   */
  function exportText(note: ExportableNote): boolean {
    try {
      isExporting.value = true
      exportError.value = null

      // Strip HTML safely using DOMPurify-based helper
      const textContent = stripHtml(note.content || '') || 'Keine Inhalte'

      // Get text content with basic formatting
      const text = [
        note.title || 'Untitled',
        '='.repeat((note.title || 'Untitled').length),
        '',
        `Erstellt: ${formatDate(note.created_at)}`,
        note.updated_at ? `Aktualisiert: ${formatDate(note.updated_at)}` : null,
        '',
        '---',
        '',
        textContent,
      ].filter(line => line !== null).join('\n')

      downloadFile(text, `${sanitizeFilename(note.title)}.txt`, 'text/plain')

      return true
    } catch (error: unknown) {
      console.error('Export Text error:', error)
      exportError.value = error instanceof Error ? error.message : String(error)
      return false
    } finally {
      isExporting.value = false
    }
  }

  /**
   * Export note as JSON (for backup/import)
   */
  function exportJSON(note: ExportableNote): boolean {
    try {
      isExporting.value = true
      exportError.value = null

      const exportData: NoteExportData = {
        version: '1.0',
        exported_at: new Date().toISOString(),
        note: {
          id: note.id,
          title: note.title,
          content: note.content,
          icon: note.icon,
          cover: note.cover,
          parent_id: note.parent_id,
          created_at: note.created_at,
          updated_at: note.updated_at,
          tags: note.tags || [],
          properties: note.properties || {},
        },
      }

      const json = JSON.stringify(exportData, null, 2)
      downloadFile(json, `${sanitizeFilename(note.title)}.json`, 'application/json')

      return true
    } catch (error: unknown) {
      console.error('Export JSON error:', error)
      exportError.value = error instanceof Error ? error.message : String(error)
      return false
    } finally {
      isExporting.value = false
    }
  }

  /**
   * Helper: Download file
   */
  function downloadFile(content: string, filename: string, mimeType: string): void {
    const blob = new Blob([content], { type: mimeType })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  }

  /**
   * Helper: Sanitize filename
   */
  function sanitizeFilename(name: string | undefined): string {
    if (!name) return 'untitled'
    return name
      .toLowerCase()
      .replace(/[^a-z0-9\u00e4\u00f6\u00fc\u00df\s-]/g, '')
      .replace(/\s+/g, '-')
      .slice(0, 100)
  }

  /**
   * Helper: Escape HTML
   */
  function escapeHtml(text: string | undefined): string {
    if (!text) return ''
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')
  }

  /**
   * Helper: Format date
   */
  function formatDate(dateString: string | undefined): string {
    if (!dateString) return '-'
    try {
      return new Date(dateString).toLocaleDateString('de-DE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    } catch {
      return dateString
    }
  }

  return {
    isExporting,
    exportError,
    exportMarkdown,
    exportHTML,
    exportPDF,
    exportText,
    exportJSON,
  }
}
