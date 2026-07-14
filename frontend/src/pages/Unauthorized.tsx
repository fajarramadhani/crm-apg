import { Link } from 'react-router-dom'

export default function Unauthorized({ dashboardPath }: { dashboardPath: string }) {
  return (
    <div className="min-h-[70vh] flex items-center justify-center px-4">
      <div className="max-w-md text-center">
        <p className="text-sm font-semibold uppercase tracking-widest text-amber-600">403</p>
        <h1 className="mt-2 text-2xl font-bold text-gray-900">Akses tidak diizinkan</h1>
        <p className="mt-3 text-sm text-gray-500">
          Akun Anda tidak memiliki role yang diperlukan untuk membuka halaman ini.
        </p>
        <Link
          to={dashboardPath}
          className="mt-6 inline-flex rounded-lg bg-[#1E3A8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#1e40af]"
        >
          Kembali ke halaman utama
        </Link>
      </div>
    </div>
  )
}
