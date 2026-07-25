import { useEffect, useRef, useState } from 'react'
import { useNavigate, useSearchParams, Link } from 'react-router-dom'
import { api, auth, type AuthUser } from '@/api/client'
import { RTMonogram } from '@/components/RTLogo'

/**
 * /access?token=... — verifica il magic link e apre la sessione (JWT 30 giorni).
 */
export default function AccessPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [state, setState] = useState<'loading' | 'error'>('loading')
  const [message, setMessage] = useState('')
  const verified = useRef(false)

  useEffect(() => {
    const token = params.get('token')
    if (!token) {
      setState('error')
      setMessage('Link non valido: token mancante.')
      return
    }
    if (verified.current) return
    verified.current = true // il token è single-use: mai verificare due volte (StrictMode)

    api<{ token: string; user: AuthUser }>('/customer/verify', { method: 'POST', body: { token } })
      .then((res) => {
        auth.setToken(res.token)
        auth.setUser(res.user)
        navigate('/', { replace: true })
      })
      .catch((err) => {
        setState('error')
        setMessage(err instanceof Error ? err.message : 'Link non valido o scaduto.')
      })
  }, [params, navigate])

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-navy-deep px-6 text-center">
      <RTMonogram size={64} onDark />
      {state === 'loading' ? (
        <p className="mt-8 animate-pulse text-ivory/80">Accesso in corso…</p>
      ) : (
        <div className="mt-8 max-w-sm animate-fade-in">
          <p className="font-display text-xl text-ivory">Ops, qualcosa non va</p>
          <p className="mt-3 text-sm text-ivory/70">{message}</p>
          <Link to="/login" className="rt-btn-primary mt-8 inline-flex">
            Richiedi un nuovo link
          </Link>
        </div>
      )}
    </div>
  )
}
