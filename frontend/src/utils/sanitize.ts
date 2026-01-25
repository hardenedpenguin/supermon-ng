/**
 * Sanitize HTML for safe use with v-html / innerHTML.
 * Allows tags and attributes used by ALERT, header, welcome message, node title, etc.
 */
import DOMPurify from 'dompurify'

export function sanitizeHtml(html: string | null | undefined): string {
  if (html == null || typeof html !== 'string') return ''
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['span', 'a', 'b', 'u', 'br', 'div', 'i'],
    ALLOWED_ATTR: ['href', 'target', 'style', 'class', 'data-node-id'],
  })
}
