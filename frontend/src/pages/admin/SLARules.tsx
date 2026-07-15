import { useCallback, useEffect, useState } from 'react'
import { EmptyState, PageHeader, SectionCard } from '../../components/ui'
import { adminMasterDataService, type SlaPolicy } from '../../services/masterDataService'

const colors: Record<string, string> = {
  critical: 'border-red-200 bg-red-50 text-red-700',
  high: 'border-orange-200 bg-orange-50 text-orange-700',
  medium: 'border-yellow-200 bg-yellow-50 text-yellow-700',
  low: 'border-green-200 bg-green-50 text-green-700',
}

export default function SLARules() {
  const [policies, setPolicies] = useState<SlaPolicy[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const load = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      setPolicies((await adminMasterDataService.getSlaPolicies()).data)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'SLA policy tidak dapat dimuat.')
    } finally {
      setLoading(false)
    }
  }, [])
  useEffect(() => {
    void load()
  }, [load])

  return (
    <div>
      <PageHeader
        title="Aturan SLA"
        subtitle="Durasi resolusi disimpan dalam menit kerja; engine perhitungan dijadwalkan untuk fase berikutnya"
      />
      {error && (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {error}{' '}
          <button className="font-semibold underline" onClick={() => void load()}>
            Coba lagi
          </button>
        </div>
      )}
      <SectionCard title="SLA Default Berdasarkan Prioritas">
        {loading ? (
          <div role="status" className="py-12 text-center text-sm text-gray-500">
            Memuat SLA policy...
          </div>
        ) : policies.length === 0 ? (
          <EmptyState title="Belum ada SLA policy" message="Tambahkan policy melalui API Admin." />
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {policies.map((policy) => {
              const key = policy.priority?.key ?? 'priority'
              return (
                <div
                  key={policy.id}
                  className={`rounded-xl border p-4 ${colors[key] ?? 'border-gray-200 bg-gray-50 text-gray-700'} ${policy.is_active ? '' : 'opacity-60'}`}
                >
                  <p className="text-sm font-bold capitalize">{policy.priority?.name ?? key}</p>
                  <p className="mt-2 text-3xl font-black">{policy.resolution_minutes}</p>
                  <p className="text-xs">menit kerja resolusi</p>
                  <p className="mt-3 border-t border-current/10 pt-2 text-xs">
                    {policy.working_calendar?.name ?? 'Kalender tidak tersedia'}
                  </p>
                  {!policy.is_active && <p className="mt-1 text-xs font-semibold">Nonaktif</p>}
                </div>
              )
            })}
          </div>
        )}
      </SectionCard>
    </div>
  )
}
