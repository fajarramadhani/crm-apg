import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import { Loader2 } from 'lucide-react'

interface LoadingContextValue {
  active: boolean
  message: string
  begin(message?: string): void
  end(): void
}

const LoadingContext = createContext<LoadingContextValue | null>(null)

export function LoadingProvider({ children }: { children: React.ReactNode }) {
  const [active, setActive] = useState(false)
  const [message, setMessage] = useState('')

  const begin = useCallback((nextMessage = 'Memproses permintaan...') => {
    setMessage(nextMessage)
    setActive(true)
  }, [])

  const end = useCallback(() => {
    setActive(false)
    setMessage('')
  }, [])

  const value = useMemo(() => ({ active, message, begin, end }), [active, begin, end, message])

  return <LoadingContext.Provider value={value}>{children}{active && <GlobalLoadingOverlay message={message} />}</LoadingContext.Provider>
}

export function useLoading() {
  const context = useContext(LoadingContext)
  if (!context) throw new Error('useLoading must be used inside LoadingProvider')
  return context
}

function GlobalLoadingOverlay({ message }: { message: string }) {
  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 backdrop-blur-sm text-white" role="status" aria-live="polite">
      <div className="relative w-full max-w-md overflow-hidden rounded-[2rem] border border-white/10 bg-white/10 px-8 py-8 text-center shadow-2xl backdrop-blur-xl">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(96,165,250,0.28),_transparent_60%)]" />
        <div className="relative flex flex-col items-center gap-5">
          <div className="relative flex h-20 w-20 items-center justify-center">
            <span className="absolute h-20 w-20 animate-ping rounded-full bg-cyan-300/10" />
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-[#0F2554] shadow-lg">
              <span className="text-xl font-black">A</span>
            </div>
          </div>
          <Loader2 className="h-5 w-5 animate-spin text-cyan-300" />
          <p className="text-lg font-semibold">Tic Hub</p>
          <p className="text-sm text-blue-100/80">{message}</p>
        </div>
      </div>
    </div>
  )
}
