import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, PageHeader, SectionCard, Textarea, Toast } from '../../components/ui'
import {
  KnowledgeArticleCard,
  KnowledgeError,
  KnowledgeLoading,
  KnowledgeStatusBadge,
  KnowledgeTags,
  VISIBILITY_LABELS,
} from '../../components/knowledgeBase/KnowledgeBaseUi'
import { useAuth } from '../../context/AuthContext'
import type { KnowledgeBaseArticle, KnowledgeBaseArticleDetail as ArticleDetail } from '../../types/knowledgeBase'

export default function KnowledgeBaseDetail() {
  const { slug = '' } = useParams()
  const navigate = useNavigate()
  const { user, hasPermission } = useAuth()
  const [article, setArticle] = useState<ArticleDetail | null>(null)
  const [related, setRelated] = useState<KnowledgeBaseArticle[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState<boolean | null>(null)
  const [comment, setComment] = useState('')
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')

  const load = () => {
    setLoading(true)
    setError('')
    knowledgeBaseApi
      .get(slug)
      .then(async (result) => {
        setArticle(result)
        if (result.status === 'published') setRelated(await knowledgeBaseApi.related(result.id).catch(() => []))
      })
      .catch(() => setError('Artikel tidak ditemukan atau Anda tidak memiliki akses.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [slug])

  const sendFeedback = async () => {
    if (!article || feedback === null) return
    setBusy(true)
    try {
      await knowledgeBaseApi.feedback(article.id, feedback, comment.trim() || undefined)
      setNotice('Terima kasih. Masukan Anda telah disimpan.')
      setComment('')
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  const lifecycle = async (action: 'submit' | 'archive' | 'restore') => {
    if (!article) return
    setBusy(true)
    setError('')
    try {
      const result =
        action === 'submit'
          ? await knowledgeBaseApi.submitReview(article.id)
          : action === 'archive'
            ? await knowledgeBaseApi.archive(article.id)
            : await knowledgeBaseApi.restore(article.id)
      setArticle(result)
      setNotice(
        action === 'submit'
          ? 'Artikel dikirim untuk review.'
          : action === 'archive'
            ? 'Artikel diarsipkan.'
            : 'Artikel dipulihkan.',
      )
    } catch (cause) {
      setError((cause as ApiRequestError).message)
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <KnowledgeLoading label="Memuat artikel..." />
  if (!article) return <KnowledgeError message={error} retry={load} />
  const canEdit =
    hasPermission('knowledge_base.edit_any') ||
    (article.author.id === user?.id && hasPermission('knowledge_base.edit_own'))

  return (
    <div className="mx-auto max-w-5xl">
      {notice && <Toast message={notice} onClose={() => setNotice('')} />}
      {error && <Toast message={error} type="error" onClose={() => setError('')} />}
      <PageHeader
        title={article.title}
        subtitle={`${article.article_number} · Versi ${article.current_version}`}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="ghost" onClick={() => navigate('/knowledge-base')}>
              ← Knowledge Base
            </Button>
            {canEdit && (
              <Link to={`/knowledge-base/${article.id}/edit`}>
                <Button variant="secondary">Edit</Button>
              </Link>
            )}
            {article.status === 'in_review' && hasPermission('knowledge_base.review') && (
              <Link to={`/knowledge-base/${article.id}/review`}>
                <Button variant="warning">Review</Button>
              </Link>
            )}
            {['draft', 'rejected'].includes(article.status) && canEdit && (
              <Button loading={busy} onClick={() => void lifecycle('submit')}>
                Kirim Review
              </Button>
            )}
            {article.status === 'published' && hasPermission('knowledge_base.archive') && (
              <Button variant="danger" loading={busy} onClick={() => void lifecycle('archive')}>
                Arsipkan
              </Button>
            )}
            {article.status === 'archived' && hasPermission('knowledge_base.archive') && (
              <Button loading={busy} onClick={() => void lifecycle('restore')}>
                Pulihkan
              </Button>
            )}
          </div>
        }
      />
      {article.rejection_reason && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4">
          <p className="text-sm font-semibold text-red-800">Alasan penolakan</p>
          <p className="mt-1 whitespace-pre-wrap text-sm text-red-700">{article.rejection_reason}</p>
        </div>
      )}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div className="space-y-5">
          <SectionCard title="Ringkasan">
            <p className="whitespace-pre-wrap text-sm leading-7 text-gray-700">{article.summary}</p>
          </SectionCard>
          <SectionCard title="Isi Artikel">
            <div className="whitespace-pre-wrap break-words text-sm leading-7 text-gray-800">{article.content}</div>
          </SectionCard>
          {article.status === 'published' && hasPermission('knowledge_base.feedback') && (
            <SectionCard title="Apakah artikel ini membantu?">
              <div className="flex gap-2">
                <Button variant={feedback === true ? 'success' : 'secondary'} onClick={() => setFeedback(true)}>
                  Ya, membantu
                </Button>
                <Button variant={feedback === false ? 'danger' : 'secondary'} onClick={() => setFeedback(false)}>
                  Belum membantu
                </Button>
              </div>
              {feedback !== null && (
                <div className="mt-4 space-y-3">
                  <Textarea
                    label="Catatan (opsional)"
                    maxLength={500}
                    rows={3}
                    value={comment}
                    onChange={(event) => setComment(event.target.value)}
                  />
                  <Button loading={busy} onClick={() => void sendFeedback()}>
                    Kirim Masukan
                  </Button>
                </div>
              )}
            </SectionCard>
          )}
        </div>
        <aside className="space-y-5">
          <SectionCard title="Informasi">
            <div className="space-y-3 text-sm">
              <div className="flex flex-wrap gap-2">
                <KnowledgeStatusBadge status={article.status} />
                <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs">
                  {VISIBILITY_LABELS[article.visibility]}
                </span>
              </div>
              <Info label="Penulis" value={article.author.name} />
              <Info label="Reviewer" value={article.reviewer?.name || '—'} />
              <Info label="Kategori" value={article.category?.name || '—'} />
              <Info label="Aplikasi" value={article.application?.name || '—'} />
              <Info
                label="Diterbitkan"
                value={article.published_at ? new Date(article.published_at).toLocaleDateString('id-ID') : '—'}
              />
              <Info label="Dilihat" value={String(article.view_count)} />
              <KnowledgeTags article={article} />
            </div>
          </SectionCard>
          {article.tickets && article.tickets.length > 0 && (
            <SectionCard title="Resolusi Tiket">
              <div className="space-y-2">
                {article.tickets.map((ticket) => (
                  <div key={ticket.id} className="rounded-lg bg-gray-50 p-3">
                    <p className="font-mono text-xs text-blue-700">{ticket.ticket_number}</p>
                    <p className="mt-1 text-sm font-medium">{ticket.title}</p>
                    <p className="mt-1 text-xs text-gray-500">{ticket.relation_type.replace(/_/g, ' ')}</p>
                  </div>
                ))}
              </div>
            </SectionCard>
          )}
        </aside>
      </div>
      {related.length > 0 && (
        <section className="mt-7">
          <h2 className="mb-3 text-lg font-bold text-gray-900">Artikel Terkait</h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {related.map((item) => (
              <KnowledgeArticleCard key={item.id} article={item} />
            ))}
          </div>
        </section>
      )}
    </div>
  )
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="font-medium text-gray-800">{value}</p>
    </div>
  )
}
