import DOMPurify from 'dompurify'

/**
 * Sanitize HTML content to prevent XSS attacks.
 * Uses DOMPurify with the default HTML profile.
 */
export function sanitizeHtml(html: string): string {
  return DOMPurify.sanitize(html, { USE_PROFILES: { html: true } })
}

/**
 * Sanitize HTML content preserving target="_blank" on links.
 * Useful for rendered markdown where external links should open in new tabs.
 */
export function sanitizeHtmlWithLinks(html: string): string {
  return DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true },
    ADD_ATTR: ['target'],
  })
}

/**
 * Strip all HTML tags and return plain text.
 * Safe alternative to innerHTML-based stripping.
 */
export function stripHtml(html: string): string {
  return DOMPurify.sanitize(html, { ALLOWED_TAGS: [] })
}
