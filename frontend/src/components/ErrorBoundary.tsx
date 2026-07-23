import { Component, type ErrorInfo, type ReactNode } from 'react'

interface ErrorBoundaryState {
  failed: boolean
}

export class ErrorBoundary extends Component<{ children: ReactNode }, ErrorBoundaryState> {
  state: ErrorBoundaryState = { failed: false }

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    if (import.meta.env.DEV) console.error('Application render failed', error, info)
  }

  render() {
    if (!this.state.failed) return this.props.children

    return (
      <main className="min-h-dvh bg-slate-100 p-6 flex items-center justify-center">
        <section role="alert" className="w-full max-w-lg rounded-2xl bg-white p-8 shadow-lg">
          <h1 className="text-xl font-bold text-slate-900">Halaman tidak dapat ditampilkan</h1>
          <p className="mt-2 text-sm text-slate-600">Muat ulang halaman. Jika masalah berlanjut, hubungi tim IT.</p>
          <button
            type="button"
            className="mt-5 rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white"
            onClick={() => window.location.reload()}
          >
            Muat ulang
          </button>
        </section>
      </main>
    )
  }
}
