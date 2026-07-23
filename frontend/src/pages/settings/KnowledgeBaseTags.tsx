import { useEffect, useState } from 'react'
import { ApiRequestError } from '../../api/client'
import { knowledgeBaseApi } from '../../api/knowledgeBase'
import { Button, Input, PageHeader, Toast } from '../../components/ui'
import { KnowledgeError, KnowledgeLoading } from '../../components/knowledgeBase/KnowledgeBaseUi'
import type { KnowledgeBaseTag } from '../../types/knowledgeBase'

export default function KnowledgeBaseTags() {
  const [tags, setTags] = useState<KnowledgeBaseTag[]>([])
  const [name, setName] = useState('')
  const [editing, setEditing] = useState<number | null>(null)
  const [editName, setEditName] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const load = () => {
    setLoading(true)
    setError('')
    knowledgeBaseApi
      .tags()
      .then(setTags)
      .catch(() => setError('Daftar tag tidak dapat dimuat.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [])
  const action = async (callback: () => Promise<unknown>, message: string) => {
    setBusy(true)
    setError('')
    try {
      await callback()
      setNotice(message)
      setName('')
      setEditing(null)
      load()
    } catch (cause) {
      setError((cause as ApiRequestError).message)
      setBusy(false)
    }
  }
  if (loading) return <KnowledgeLoading label="Memuat tag..." />
  if (error && tags.length === 0) return <KnowledgeError message={error} retry={load} />
  return (
    <div className="mx-auto max-w-3xl">
      {notice && <Toast message={notice} onClose={() => setNotice('')} />}
      {error && <Toast type="error" message={error} onClose={() => setError('')} />}
      <PageHeader title="Tag Knowledge Base" subtitle="Kelola istilah untuk pencarian dan pengelompokan artikel." />
      <form
        className="mb-5 flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row"
        onSubmit={(event) => {
          event.preventDefault()
          if (name.trim()) void action(() => knowledgeBaseApi.createTag(name.trim()), 'Tag berhasil dibuat.')
        }}
      >
        <Input
          value={name}
          maxLength={50}
          onChange={(event) => setName(event.target.value)}
          placeholder="Nama tag baru"
          aria-label="Nama tag baru"
        />
        <Button type="submit" disabled={busy || !name.trim()}>
          Tambah Tag
        </Button>
      </form>
      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        {tags.length === 0 ? (
          <p className="p-10 text-center text-sm text-gray-500">Belum ada tag.</p>
        ) : (
          <ul className="divide-y divide-gray-100">
            {tags.map((tag) => (
              <li key={tag.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                {editing === tag.id ? (
                  <div className="flex flex-1 gap-2">
                    <Input
                      value={editName}
                      maxLength={50}
                      onChange={(event) => setEditName(event.target.value)}
                      aria-label={`Ubah nama ${tag.name}`}
                    />
                    <Button
                      size="sm"
                      disabled={busy || !editName.trim()}
                      onClick={() =>
                        void action(
                          () => knowledgeBaseApi.updateTag(tag.id, { name: editName.trim() }),
                          'Tag berhasil diperbarui.',
                        )
                      }
                    >
                      Simpan
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setEditing(null)}>
                      Batal
                    </Button>
                  </div>
                ) : (
                  <div>
                    <p className="font-medium text-gray-900">{tag.name}</p>
                    <p className="text-xs text-gray-500">{tag.slug}</p>
                  </div>
                )}
                <div className="flex items-center gap-2">
                  {!tag.is_active && (
                    <span className="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500">Nonaktif</span>
                  )}
                  {editing !== tag.id && (
                    <>
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => {
                          setEditing(tag.id)
                          setEditName(tag.name)
                        }}
                      >
                        Ubah
                      </Button>
                      <Button
                        size="sm"
                        variant={tag.is_active ? 'danger' : 'success'}
                        disabled={busy}
                        onClick={() =>
                          void action(
                            () => knowledgeBaseApi.updateTag(tag.id, { is_active: !tag.is_active }),
                            tag.is_active ? 'Tag dinonaktifkan.' : 'Tag diaktifkan.',
                          )
                        }
                      >
                        {tag.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                      </Button>
                    </>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
