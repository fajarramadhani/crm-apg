import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ApiRequestError } from '../../api/client'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, Input, PageHeader, Select, Textarea } from '../../components/ui'
import { KnowledgeError, KnowledgeLoading } from '../../components/knowledgeBase/KnowledgeBaseUi'
import { useAuth } from '../../context/AuthContext'
import { masterDataService, type Application, type TicketCategory } from '../../services/masterDataService'
import type { KnowledgeBaseArticleDetail, KnowledgeBaseTag, KnowledgeBaseVisibility } from '../../types/knowledgeBase'

const emptyForm = {
  title: '',
  summary: '',
  content: '',
  visibility: 'all_authenticated' as KnowledgeBaseVisibility,
  categoryId: '',
  applicationId: '',
  tagIds: [] as number[],
  changeSummary: '',
}

export default function KnowledgeBaseForm() {
  const id = Number(useParams().id)
  const editing = Number.isFinite(id) && id > 0
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const [article, setArticle] = useState<KnowledgeBaseArticleDetail | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [tags, setTags] = useState<KnowledgeBaseTag[]>([])
  const [categories, setCategories] = useState<TicketCategory[]>([])
  const [applications, setApplications] = useState<Application[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  const load = () => {
    setLoading(true)
    setError('')
    Promise.all([
      knowledgeBaseApi.tags(),
      masterDataService.getTicketCategories(),
      masterDataService.getApplications(),
      editing ? knowledgeBaseApi.get(id) : Promise.resolve(null),
    ])
      .then(([tagResult, categoryResult, applicationResult, articleResult]) => {
        setTags(tagResult.filter((tag) => tag.is_active))
        setCategories(categoryResult.filter((item) => item.is_active))
        setApplications(applicationResult.filter((item) => item.is_active))
        if (articleResult) {
          setArticle(articleResult)
          setForm({
            title: articleResult.title,
            summary: articleResult.summary,
            content: articleResult.content,
            visibility: articleResult.visibility,
            categoryId: articleResult.category ? String(articleResult.category.id) : '',
            applicationId: articleResult.application ? String(articleResult.application.id) : '',
            tagIds: articleResult.tags.map((tag) => tag.id),
            changeSummary: '',
          })
        }
      })
      .catch(() => setError('Data artikel dan pilihan formulir tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [id, editing])

  const save = async (status: 'draft' | 'in_review') => {
    setBusy(true)
    setError('')
    setFieldErrors({})
    const payload = {
      title: form.title.trim(),
      summary: form.summary.trim(),
      content: form.content.trim(),
      visibility: form.visibility,
      category_id: form.categoryId ? Number(form.categoryId) : null,
      application_id: form.applicationId ? Number(form.applicationId) : null,
      tags: form.tagIds,
      status,
      ...(article?.status === 'published' && { change_summary: form.changeSummary.trim() }),
    }
    try {
      const result = editing ? await knowledgeBaseApi.update(id, payload) : await knowledgeBaseApi.create(payload)
      navigate(`/knowledge-base/${result.slug}`, { replace: true })
    } catch (cause) {
      const apiError = cause as ApiRequestError
      setFieldErrors(apiError.errors || {})
      setError(apiError.message)
    } finally {
      setBusy(false)
    }
  }

  if (loading) return <KnowledgeLoading label="Menyiapkan formulir artikel..." />
  if (editing && !article) return <KnowledgeError message={error} retry={load} />
  const valid =
    form.title.trim() &&
    form.summary.trim() &&
    form.content.trim() &&
    (article?.status !== 'published' || form.changeSummary.trim())

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        title={editing ? 'Edit Artikel' : 'Artikel Knowledge Base Baru'}
        subtitle={
          editing
            ? `${article?.article_number} · ${article?.title}`
            : 'Dokumentasikan pengetahuan tanpa data pribadi, kredensial, atau rahasia.'
        }
        actions={
          <Button
            variant="ghost"
            onClick={() => navigate(editing && article ? `/knowledge-base/${article.slug}` : '/knowledge-base/manage')}
          >
            Batal
          </Button>
        }
      />
      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}
        </div>
      )}
      {article?.rejection_reason && (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4">
          <p className="text-sm font-semibold text-red-800">Perbaikan diminta reviewer</p>
          <p className="mt-1 whitespace-pre-wrap text-sm text-red-700">{article.rejection_reason}</p>
        </div>
      )}
      <div className="space-y-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
        <Input
          label="Judul *"
          maxLength={255}
          value={form.title}
          error={fieldErrors.title?.[0]}
          onChange={(event) => setForm({ ...form, title: event.target.value })}
        />
        <Textarea
          label="Ringkasan *"
          rows={4}
          maxLength={1000}
          value={form.summary}
          error={fieldErrors.summary?.[0]}
          hint={`${form.summary.length}/1000 karakter`}
          onChange={(event) => setForm({ ...form, summary: event.target.value })}
        />
        <Textarea
          label="Isi Artikel *"
          rows={16}
          value={form.content}
          error={fieldErrors.content?.[0]}
          hint="Teks biasa akan ditampilkan sesuai baris yang Anda tulis. Jangan sertakan data pribadi atau kredensial."
          onChange={(event) => setForm({ ...form, content: event.target.value })}
        />
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Select
            label="Visibilitas *"
            value={form.visibility}
            onChange={(event) => setForm({ ...form, visibility: event.target.value as KnowledgeBaseVisibility })}
            options={[
              ...(hasPermission('knowledge_base.view_it_internal')
                ? [{ value: 'it_internal', label: 'Internal IT' }]
                : []),
              { value: 'business_internal', label: 'Internal Bisnis' },
              { value: 'all_authenticated', label: 'Semua Pengguna' },
            ]}
          />
          <Select
            label="Kategori"
            value={form.categoryId}
            onChange={(event) => setForm({ ...form, categoryId: event.target.value })}
            options={[
              { value: '', label: 'Tanpa kategori' },
              ...categories.map((item) => ({ value: String(item.id), label: item.name })),
            ]}
          />
          <Select
            label="Aplikasi"
            value={form.applicationId}
            onChange={(event) => setForm({ ...form, applicationId: event.target.value })}
            options={[
              { value: '', label: 'Tanpa aplikasi' },
              ...applications.map((item) => ({ value: String(item.id), label: item.name })),
            ]}
          />
        </div>
        <fieldset>
          <legend className="mb-2 text-sm font-medium text-gray-700">Tag</legend>
          {tags.length === 0 ? (
            <p className="text-sm text-gray-500">Belum ada tag aktif.</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {tags.map((tag) => (
                <label
                  key={tag.id}
                  className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium ${form.tagIds.includes(tag.id) ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600'}`}
                >
                  <input
                    type="checkbox"
                    className="sr-only"
                    checked={form.tagIds.includes(tag.id)}
                    onChange={() =>
                      setForm({
                        ...form,
                        tagIds: form.tagIds.includes(tag.id)
                          ? form.tagIds.filter((tagId) => tagId !== tag.id)
                          : [...form.tagIds, tag.id],
                      })
                    }
                  />
                  {tag.name}
                </label>
              ))}
            </div>
          )}
        </fieldset>
        {article?.status === 'published' && (
          <Textarea
            label="Ringkasan perubahan *"
            rows={3}
            maxLength={500}
            value={form.changeSummary}
            error={fieldErrors.change_summary?.[0]}
            onChange={(event) => setForm({ ...form, changeSummary: event.target.value })}
          />
        )}
        <div className="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
          <Button variant="secondary" disabled={busy || !valid} onClick={() => void save('draft')}>
            {busy ? 'Menyimpan...' : 'Simpan Draf'}
          </Button>
          <Button disabled={busy || !valid} onClick={() => void save('in_review')}>
            {busy ? 'Mengirim...' : 'Simpan & Kirim Review'}
          </Button>
        </div>
      </div>
    </div>
  )
}
