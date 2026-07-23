import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, Input, PageHeader, Select } from '../../components/ui'
import {
  KnowledgeArticleCard,
  KnowledgeError,
  KnowledgeLoading,
  VISIBILITY_LABELS,
} from '../../components/knowledgeBase/KnowledgeBaseUi'
import { useAuth } from '../../context/AuthContext'
import type { KnowledgeBaseArticle, KnowledgeBaseStatus, KnowledgeBaseTag } from '../../types/knowledgeBase'

export default function KnowledgeBaseList({ manage = false }: { manage?: boolean }) {
  const { hasPermission } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [articles, setArticles] = useState<KnowledgeBaseArticle[]>([])
  const [tags, setTags] = useState<KnowledgeBaseTag[]>([])
  const [search, setSearch] = useState(searchParams.get('search') || '')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const status = (searchParams.get('status') || '') as KnowledgeBaseStatus | ''
  const visibility = searchParams.get('visibility') || ''
  const tag = searchParams.get('tag') || ''

  const load = () => {
    setLoading(true)
    setError('')
    Promise.all([
      knowledgeBaseApi.list({
        search: searchParams.get('search') || '',
        status: manage && hasPermission('knowledge_base.review') ? status : '',
        visibility: visibility as 'it_internal' | 'business_internal' | 'all_authenticated' | '',
        tag: tag ? Number(tag) : '',
        page,
        per_page: 12,
      }),
      knowledgeBaseApi.tags(),
    ])
      .then(([result, tagResult]) => {
        setArticles(result.data)
        setTags(tagResult)
        if (result.meta) setMeta(result.meta)
      })
      .catch(() => setError('Daftar artikel tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }

  useEffect(load, [page, status, tag, visibility, manage])

  const updateFilter = (key: string, value: string) => {
    const next = new URLSearchParams(searchParams)
    value ? next.set(key, value) : next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  const submitSearch = (event: React.FormEvent) => {
    event.preventDefault()
    updateFilter('search', search.trim())
  }

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title={manage ? 'Kelola Knowledge Base' : 'Knowledge Base'}
        subtitle={
          manage
            ? 'Kelola draf, review, dan artikel yang telah diterbitkan.'
            : 'Temukan panduan dan resolusi yang sudah teruji.'
        }
        actions={
          <div className="flex flex-wrap gap-2">
            {manage && hasPermission('knowledge_base.review') && (
              <Button variant="secondary" onClick={() => updateFilter('status', 'in_review')}>
                Antrean Review
              </Button>
            )}
            {hasPermission('knowledge_base.create') && (
              <Link to="/knowledge-base/new">
                <Button>+ Artikel Baru</Button>
              </Link>
            )}
          </div>
        }
      />

      <div className="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form
          onSubmit={submitSearch}
          className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(240px,1fr)_180px_180px_auto]"
        >
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Cari judul, isi, aplikasi, atau tag..."
            aria-label="Cari artikel"
          />
          <Select
            aria-label="Filter visibilitas"
            value={visibility}
            onChange={(event) => updateFilter('visibility', event.target.value)}
            options={[
              { value: '', label: 'Semua visibilitas' },
              ...Object.entries(VISIBILITY_LABELS).map(([value, label]) => ({ value, label })),
            ]}
          />
          <Select
            aria-label="Filter tag"
            value={tag}
            onChange={(event) => updateFilter('tag', event.target.value)}
            options={[
              { value: '', label: 'Semua tag' },
              ...tags.map((item) => ({ value: String(item.id), label: item.name })),
            ]}
          />
          <Button type="submit">Cari</Button>
        </form>
        {manage && hasPermission('knowledge_base.review') && (
          <div className="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
            {[
              ['', 'Semua status'],
              ['draft', 'Draf'],
              ['in_review', 'Dalam Review'],
              ['published', 'Terbit'],
              ['rejected', 'Ditolak'],
              ['archived', 'Diarsipkan'],
            ].map(([value, label]) => (
              <button
                key={value}
                onClick={() => updateFilter('status', value)}
                className={`rounded-full px-3 py-1 text-xs font-semibold ${status === value ? 'bg-[#1E3A8A] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
              >
                {label}
              </button>
            ))}
          </div>
        )}
      </div>

      {loading ? (
        <KnowledgeLoading />
      ) : error ? (
        <KnowledgeError message={error} retry={load} />
      ) : articles.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-white py-16 text-center">
          <p className="font-medium text-gray-700">Tidak ada artikel yang sesuai.</p>
          <p className="mt-1 text-sm text-gray-500">Ubah kata kunci atau filter pencarian.</p>
        </div>
      ) : (
        <>
          <p className="mb-3 text-sm text-gray-500">{meta.total} artikel ditemukan</p>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {articles.map((article) => (
              <KnowledgeArticleCard key={article.id} article={article} manage={manage} />
            ))}
          </div>
          {meta.last_page > 1 && (
            <div className="mt-6 flex items-center justify-center gap-3">
              <Button variant="secondary" disabled={page <= 1} onClick={() => updateFilter('page', String(page - 1))}>
                Sebelumnya
              </Button>
              <span className="text-sm text-gray-600">
                Halaman {page} dari {meta.last_page}
              </span>
              <Button
                variant="secondary"
                disabled={page >= meta.last_page}
                onClick={() => updateFilter('page', String(page + 1))}
              >
                Berikutnya
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
