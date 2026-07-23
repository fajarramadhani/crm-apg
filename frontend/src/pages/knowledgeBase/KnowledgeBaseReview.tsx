import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, PageHeader, SectionCard, Textarea, Toast } from '../../components/ui'
import {
  KnowledgeError,
  KnowledgeLoading,
  KnowledgeStatusBadge,
  VISIBILITY_LABELS,
} from '../../components/knowledgeBase/KnowledgeBaseUi'
import type { KnowledgeBaseActivity, KnowledgeBaseArticleDetail, KnowledgeBaseVersion } from '../../types/knowledgeBase'

export default function KnowledgeBaseReview() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const [article, setArticle] = useState<KnowledgeBaseArticleDetail | null>(null)
  const [versions, setVersions] = useState<KnowledgeBaseVersion[]>([])
  const [activity, setActivity] = useState<KnowledgeBaseActivity[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reason, setReason] = useState('')
  const [changeSummary, setChangeSummary] = useState('')
  const [busy, setBusy] = useState(false)

  const load = () => {
    setLoading(true)
    setError('')
    Promise.all([knowledgeBaseApi.get(id), knowledgeBaseApi.versions(id), knowledgeBaseApi.activity(id)])
      .then(([articleResult, versionResult, activityResult]) => {
        setArticle(articleResult)
        setVersions(versionResult)
        setActivity(activityResult)
      })
      .catch(() => setError('Data review tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [id])

  const decide = async (decision: 'publish' | 'reject') => {
    if (!article) return
    setBusy(true)
    setError('')
    try {
      const result =
        decision === 'publish'
          ? await knowledgeBaseApi.publish(article.id, changeSummary.trim() || undefined)
          : await knowledgeBaseApi.reject(article.id, reason.trim())
      navigate(`/knowledge-base/${result.slug}`, { replace: true })
    } catch (cause) {
      setError((cause as ApiRequestError).message)
      setBusy(false)
    }
  }

  if (loading) return <KnowledgeLoading label="Memuat bahan review..." />
  if (!article) return <KnowledgeError message={error} retry={load} />
  return (
    <div className="mx-auto max-w-5xl">
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      <PageHeader
        title="Review Artikel"
        subtitle={`${article.article_number} · Versi ${article.current_version}`}
        actions={
          <Button variant="ghost" onClick={() => navigate('/knowledge-base/manage?status=in_review')}>
            ← Antrean Review
          </Button>
        }
      />
      {article.status !== 'in_review' && (
        <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          Artikel ini berstatus <strong>{article.status}</strong>, bukan dalam antrean review. Keputusan publikasi hanya
          dapat diproses backend untuk status yang valid.
        </div>
      )}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="space-y-5">
          <SectionCard title={article.title}>
            <div className="mb-4 flex flex-wrap gap-2">
              <KnowledgeStatusBadge status={article.status} />
              <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs">
                {VISIBILITY_LABELS[article.visibility]}
              </span>
            </div>
            <p className="whitespace-pre-wrap text-sm font-medium leading-7 text-gray-700">{article.summary}</p>
            <div className="my-5 border-t border-gray-200" />
            <div className="whitespace-pre-wrap break-words text-sm leading-7 text-gray-800">{article.content}</div>
          </SectionCard>
          <SectionCard title="Keputusan Review">
            <Textarea
              label="Ringkasan publikasi (opsional)"
              maxLength={500}
              rows={3}
              value={changeSummary}
              onChange={(event) => setChangeSummary(event.target.value)}
            />
            <div className="my-4 border-t border-gray-100" />
            <Textarea
              label="Alasan penolakan"
              maxLength={1000}
              rows={4}
              value={reason}
              hint="Wajib minimal 3 karakter jika artikel ditolak."
              onChange={(event) => setReason(event.target.value)}
            />
            <div className="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button
                variant="danger"
                disabled={busy || reason.trim().length < 3 || article.status !== 'in_review'}
                onClick={() => void decide('reject')}
              >
                Tolak & Kembalikan
              </Button>
              <Button
                variant="success"
                disabled={busy || article.status !== 'in_review'}
                onClick={() => void decide('publish')}
              >
                {busy ? 'Memproses...' : 'Setujui & Terbitkan'}
              </Button>
            </div>
          </SectionCard>
        </div>
        <aside className="space-y-5">
          <SectionCard title="Metadata">
            <div className="space-y-2 text-sm">
              <p>
                <span className="text-gray-500">Penulis:</span> {article.author.name}
              </p>
              <p>
                <span className="text-gray-500">Kategori:</span> {article.category?.name || '—'}
              </p>
              <p>
                <span className="text-gray-500">Aplikasi:</span> {article.application?.name || '—'}
              </p>
              <p>
                <span className="text-gray-500">Diperbarui:</span>{' '}
                {new Date(article.updated_at).toLocaleString('id-ID')}
              </p>
            </div>
          </SectionCard>
          <SectionCard title={`Versi (${versions.length})`}>
            <div className="space-y-3">
              {versions.map((version) => (
                <div key={version.id} className="rounded-lg bg-gray-50 p-3">
                  <p className="text-sm font-semibold">Versi {version.version_number}</p>
                  <p className="mt-1 text-xs text-gray-500">
                    {version.creator.name} · {new Date(version.created_at).toLocaleString('id-ID')}
                  </p>
                  {version.change_summary && (
                    <p className="mt-2 whitespace-pre-wrap text-xs text-gray-700">{version.change_summary}</p>
                  )}
                </div>
              ))}
            </div>
          </SectionCard>
          <SectionCard title="Aktivitas">
            <div className="space-y-3">
              {activity.length === 0 && <p className="text-sm text-gray-500">Belum ada aktivitas.</p>}
              {activity.map((item) => (
                <div key={item.id} className="border-l-2 border-blue-200 pl-3">
                  <p className="text-sm font-medium capitalize">{item.action.replace(/_/g, ' ')}</p>
                  <p className="text-xs text-gray-500">
                    {item.actor.name} · {new Date(item.created_at).toLocaleString('id-ID')}
                  </p>
                </div>
              ))}
            </div>
          </SectionCard>
        </aside>
      </div>
    </div>
  )
}
