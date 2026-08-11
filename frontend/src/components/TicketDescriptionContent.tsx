import DOMPurify from 'dompurify'

export function TicketDescriptionContent({ html, className = '' }: { html: string; className?: string }) {
  const containsRichText =
    /<\/?(?:p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|h[1-6]|a|table|thead|tbody|tr|th|td)\b/i.test(html)
  const source = containsRichText
    ? html
    : html
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/\r?\n/g, '<br>')
  const sanitized = DOMPurify.sanitize(source, {
    ALLOWED_TAGS: [
      'p',
      'br',
      'strong',
      'b',
      'em',
      'i',
      'u',
      's',
      'ul',
      'ol',
      'li',
      'blockquote',
      'h1',
      'h2',
      'h3',
      'h4',
      'h5',
      'h6',
      'a',
      'table',
      'thead',
      'tbody',
      'tr',
      'th',
      'td',
    ],
    ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'style', 'colspan', 'rowspan'],
    ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto):|[^a-z]|[a-z+.-]+(?:[^a-z+.-:]|$))/i,
  })
  const document = new DOMParser().parseFromString(sanitized, 'text/html')

  document.body.querySelectorAll<HTMLElement>('[style]').forEach((element) => {
    const alignment = element.tagName === 'P' ? element.style.textAlign : ''
    element.removeAttribute('style')
    if (['left', 'center', 'right', 'justify'].includes(alignment)) element.style.textAlign = alignment
  })
  document.body.querySelectorAll<HTMLAnchorElement>('a[target="_blank"]').forEach((link) => {
    link.rel = 'noopener noreferrer'
  })

  return (
    <div
      className={`ticket-description-content text-sm leading-relaxed text-slate-700 ${className}`}
      dangerouslySetInnerHTML={{ __html: document.body.innerHTML }}
    />
  )
}
