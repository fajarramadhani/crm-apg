import { useEffect, useState } from 'react'
import { Editor } from '@tinymce/tinymce-react'
import type { IAllProps } from '@tinymce/tinymce-react'

type TinyMceEditor = Parameters<NonNullable<IAllProps['onEditorChange']>>[1]

const tinyMceApiKey = import.meta.env.VITE_TINYMCE_API_KEY?.trim()

if (import.meta.env.DEV && !tinyMceApiKey) {
  console.warn('TinyMCE API key belum tersedia. Isi VITE_TINYMCE_API_KEY pada file environment frontend.')
}

export function ticketDescriptionText(html: string): string {
  const document = new DOMParser().parseFromString(html, 'text/html')
  return (
    document.body.textContent
      ?.replace(/[\u00a0\u200B-\u200D\u2060\uFEFF]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim() ?? ''
  )
}

export function TicketDescriptionEditor({
  value,
  onChange,
  disabled = false,
  error,
  id = 'ticket-description',
  mode = 'authenticated',
}: {
  value: string
  onChange: (value: string) => void
  disabled?: boolean
  error?: string
  id?: string
  mode?: 'authenticated' | 'public'
}) {
  const errorId = `${id}-error`
  const [editorFailed, setEditorFailed] = useState(!tinyMceApiKey)
  const [editorReady, setEditorReady] = useState(false)

  useEffect(() => {
    if (!tinyMceApiKey || editorReady || editorFailed) return
    const timeout = window.setTimeout(() => setEditorFailed(true), 12000)
    return () => window.clearTimeout(timeout)
  }, [editorFailed, editorReady])

  const publicMode = mode === 'public'

  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-gray-700">
        Deskripsi Pengajuan <span className="text-red-500">*</span>
      </label>
      <div
        className={`overflow-hidden rounded-xl border bg-white transition focus-within:ring-2 focus-within:ring-[#1E3A8A]/30 ${error ? 'border-red-400' : 'border-gray-300'}`}
        aria-describedby={error ? errorId : undefined}
      >
        {editorFailed ? (
          <textarea
            id={id}
            name="description"
            value={value}
            disabled={disabled}
            rows={publicMode ? 10 : 14}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? errorId : undefined}
            onChange={(event) => onChange(event.target.value)}
            className="block min-h-64 w-full resize-y border-0 px-4 py-3 text-sm leading-6 text-slate-800 outline-none disabled:cursor-not-allowed disabled:bg-slate-100"
          />
        ) : (
          <Editor
            apiKey={tinyMceApiKey}
            cloudChannel="8"
            scriptLoading={{ async: true }}
            id={id}
            textareaName="description"
            value={value}
            disabled={disabled}
            onInit={() => setEditorReady(true)}
            onScriptsLoadError={() => setEditorFailed(true)}
            onEditorChange={onChange}
            init={{
              height: publicMode ? 300 : 350,
              menubar: false,
              branding: false,
              promotion: false,
              resize: false,
              plugins: publicMode
                ? ['advlist', 'autolink', 'link', 'lists']
                : [
                    'advlist',
                    'autolink',
                    'autosave',
                    'code',
                    'fullscreen',
                    'help',
                    'link',
                    'lists',
                    'preview',
                    'searchreplace',
                    'table',
                    'visualblocks',
                    'wordcount',
                  ],
              toolbar: publicMode
                ? 'undo redo | bold italic underline | bullist numlist | alignleft aligncenter alignright alignjustify | link | removeformat'
                : 'undo redo | blocks | bold italic underline strikethrough | ' +
                  'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | ' +
                  'link table | removeformat searchreplace | preview fullscreen code',
              toolbar_mode: 'sliding',
              statusbar: !publicMode,
              elementpath: false,
              content_style:
                'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.65; color: #1e293b; } ' +
                'table { width: 100%; border-collapse: collapse; } ' +
                'th, td { border: 1px solid #d1d5db; padding: 8px; } ' +
                'a { color: #1d4ed8; }',
              valid_elements:
                'p[style],br,strong/b,em/i,u,s,ul,ol,li,blockquote,h1,h2,h3,h4,h5,h6,' +
                'a[href|title|target|rel],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]',
              valid_styles: { p: 'text-align' },
              link_default_target: '_blank',
              link_assume_external_targets: 'https',
              target_list: false,
              rel_list: [{ title: 'No opener', value: 'noopener noreferrer' }],
              setup: (editor: TinyMceEditor) => {
                editor.on('change input undo redo', () => editor.save())
              },
              mobile: {
                toolbar_mode: 'sliding',
              },
            }}
          />
        )}
      </div>
      {editorFailed && (
        <p className="text-xs text-amber-700" role="status">
          Editor sederhana digunakan karena editor teks kaya tidak tersedia. Isi Anda tetap dapat dikirim.
        </p>
      )}
      {error && (
        <p id={errorId} className="text-xs text-red-600">
          {error}
        </p>
      )}
    </div>
  )
}
