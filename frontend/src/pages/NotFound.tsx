import { useNavigate } from 'react-router-dom'
import { Button, Card } from '../components/ui'

export default function NotFound() {
  const navigate = useNavigate()

  return (
    <Card className="mx-auto max-w-lg p-8 text-center">
      <p className="text-sm font-semibold text-[#1E3A8A]">404</p>
      <h1 className="mt-2 text-2xl font-bold text-gray-900">Halaman tidak ditemukan</h1>
      <p className="mt-2 text-sm text-gray-500">Alamat yang Anda buka tidak tersedia di APG CRM.</p>
      <Button className="mt-6" onClick={() => navigate('/')}>
        Kembali ke dashboard
      </Button>
    </Card>
  )
}
