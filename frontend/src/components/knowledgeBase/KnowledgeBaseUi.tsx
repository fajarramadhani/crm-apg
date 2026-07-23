import { Link } from 'react-router-dom'
import type { KnowledgeBaseArticle, KnowledgeBaseStatus, KnowledgeBaseVisibility } from '../../types/knowledgeBase'

const STATUS_LABELS: Record<KnowledgeBaseStatus, string> = {
  draft: 'Draf',
  in_review: 'Dalam Review',
  published: 'Terbit',
  rejected: 'Ditolak',
  archived: 'Diarsipkan',
}

const STATUS_COLORS: Record<KnowledgeBaseStatus, string> = {
  draft: 'bg-gray-100 text-gray-700',
  in_review: 'bg-amber-100 text-amber-800',
  published: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-red-100 text-red-700',
  archived: 'bg-slate-200 text-slate-700',
}

export const VISIBILITY_LABELS: Record<KnowledgeBaseVisibility, string> = {
  it_internal: 'Internal IT',
  business_internal: 'Internal Bisnis',
  all_authenticated: 'Semua Pengguna',
}

export function KnowledgeStatusBadge({ status }: { status: KnowledgeBaseStatus }) {
  return (
    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${STATUS_COLORS[status]}`}>
      {STATUS_LABELS[status]}
    </span>
  )
}

export function KnowledgeTags({ article }: { article: KnowledgeBaseArticle }) {
  if (!article.tags.length) return null
  return (
    <div className="flex flex-wrap gap-1.5">
      {article.tags.map((tag) => (
        <span key={tag.id} className="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
          {tag.name}
        </span>
      ))}
    </div>
  )
}

export function KnowledgeArticleCard({ article, manage = false }: { article: KnowledgeBaseArticle; manage?: boolean }) {
  return (
    <article className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-md sm:p-5">
      <div className="flex flex-wrap items-center gap-2">
        <span className="font-mono text-xs font-semibold text-blue-700">{article.article_number}</span>
        <KnowledgeStatusBadge status={article.status} />
        <span className="text-xs text-gray-500">{VISIBILITY_LABELS[article.visibility]}</span>
      </div>
      <Link
        to={`/knowledge-base/${article.slug}`}
        className="mt-3 block text-base font-bold text-gray-900 hover:text-blue-700"
      >
        {article.title}
      </Link>
      <p className="mt-1 line-clamp-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-600">{article.summary}</p>
      <div className="mt-3">
        <KnowledgeTags article={article} />
      </div>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
        <span>
          {article.author.name} · {article.view_count} kali dilihat
        </span>
        <div className="flex items-center gap-3">
          {article.helpful_ratio !== null && <span>{Math.round(article.helpful_ratio * 100)}% membantu</span>}
          {manage && (
            <Link className="font-semibold text-blue-700 hover:underline" to={`/knowledge-base/${article.id}/edit`}>
              Edit
            </Link>
          )}
          {manage && article.status === 'in_review' && (
            <Link className="font-semibold text-amber-700 hover:underline" to={`/knowledge-base/${article.id}/review`}>
              Review
            </Link>
          )}
        </div>
      </div>
    </article>
  )
}

export function KnowledgeError({ message, retry }: { message: string; retry: () => void }) {
  return (
    <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
      <p className="text-sm text-red-700">{message}</p>
      <button className="mt-3 text-sm font-semibold text-red-700 underline" onClick={retry}>
        Coba lagi
      </button>
    </div>
  )
}

export function KnowledgeLoading({ label = 'Memuat knowledge base...' }: { label?: string }) {
  return (
    <div role="status" className="py-16 text-center text-sm text-gray-500">
      {label}
    </div>
  )
}
