import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, SectionCard } from '../ui'
import type { KnowledgeBaseArticle } from '../../types/knowledgeBase'

export function TicketKnowledgePanel({ ticketId }: { ticketId: number }) {
  const [linked, setLinked] = useState<KnowledgeBaseArticle[]>([])
  const [recommended, setRecommended] = useState<KnowledgeBaseArticle[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const load = () => {
    setLoading(true)
    setError('')
    Promise.all([knowledgeBaseApi.ticketArticles(ticketId), knowledgeBaseApi.ticketRecommendations(ticketId)])
      .then(([linkedResult, recommendationResult]) => {
        setLinked(linkedResult)
        setRecommended(
          recommendationResult.filter((item) => !linkedResult.some((linkedItem) => linkedItem.id === item.id)),
        )
      })
      .catch(() => setError('Pengetahuan terkait tiket tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }

  useEffect(load, [ticketId])

  return (
    <SectionCard title="Knowledge & Previous Resolutions">
      {loading ? (
        <p role="status" className="py-5 text-center text-sm text-gray-500">
          Mencari resolusi terkait...
        </p>
      ) : error ? (
        <div role="alert" className="rounded-lg bg-red-50 p-3 text-sm text-red-700">
          <p>{error}</p>
          <button onClick={load} className="mt-2 font-semibold underline">
            Coba lagi
          </button>
        </div>
      ) : linked.length === 0 && recommended.length === 0 ? (
        <div className="py-4 text-center">
          <p className="text-sm text-gray-600">Belum ada resolusi sebelumnya yang cocok.</p>
          <Link to="/knowledge-base" className="mt-2 inline-block text-sm font-semibold text-blue-700 hover:underline">
            Cari di Knowledge Base
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {linked.length > 0 && (
            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Resolusi terhubung</p>
              <div className="space-y-2">
                {linked.map((article) => (
                  <CompactArticle key={article.id} article={article} />
                ))}
              </div>
            </div>
          )}
          {recommended.length > 0 && (
            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Mungkin membantu</p>
              <div className="space-y-2">
                {recommended.slice(0, 3).map((article) => (
                  <CompactArticle key={article.id} article={article} />
                ))}
              </div>
            </div>
          )}
          <Link to={`/knowledge-base?search=${encodeURIComponent('')}`}>
            <Button variant="secondary" size="sm" className="w-full justify-center">
              Buka Knowledge Base
            </Button>
          </Link>
        </div>
      )}
    </SectionCard>
  )
}

function CompactArticle({ article }: { article: KnowledgeBaseArticle }) {
  return (
    <Link
      to={`/knowledge-base/${article.slug}`}
      className="block rounded-lg border border-gray-200 p-3 hover:border-blue-300 hover:bg-blue-50/40"
    >
      <p className="font-mono text-[11px] font-semibold text-blue-700">{article.article_number}</p>
      <p className="mt-1 text-sm font-semibold leading-snug text-gray-900">{article.title}</p>
      <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-500">{article.summary}</p>
    </Link>
  )
}
