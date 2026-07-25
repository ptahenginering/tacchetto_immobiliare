import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Lock, Mail } from 'lucide-react'
import { api } from '@/api/client'
import { useAuthStore, type AdminUser } from '@/store/auth'
import { RTMonogram } from '@/components/RTLogo'

/** Login gestionale — email + password → JWT 8h. */
export default function LoginPage() {
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const res = await api<{ token: string; user: AdminUser }>('/admin/login', {
        method: 'POST',
        body: { email, password },
      })
      login(res.token, res.user)
      navigate('/', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Credenziali non valide.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-dvh items-center justify-center bg-navy-deep px-6">
      <div className="w-full max-w-sm">
        <div className="text-center">
          <RTMonogram size={60} onDark className="mx-auto" />
          <p className="mt-2 text-xs font-medium uppercase tracking-[0.3em] text-ivory">
            Casa <span className="text-gold">Live</span>
          </p>
          <p className="mt-1 text-xs text-ivory/50">Gestionale Agenzia</p>
        </div>

        <form onSubmit={onSubmit} className="mt-10 space-y-4">
          <div className="relative">
            <Mail size={17} className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gold" />
            <input
              type="email"
              required
              autoComplete="username"
              placeholder="Email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-full border border-gold/40 bg-navy px-12 py-3 text-ivory placeholder:text-ivory/40 focus:border-gold focus:outline-none"
            />
          </div>
          <div className="relative">
            <Lock size={17} className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gold" />
            <input
              type="password"
              required
              autoComplete="current-password"
              placeholder="Password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-full border border-gold/40 bg-navy px-12 py-3 text-ivory placeholder:text-ivory/40 focus:border-gold focus:outline-none"
            />
          </div>
          {error && (
            <p className="text-center text-sm text-[#E8907E]" role="alert">
              {error}
            </p>
          )}
          <button type="submit" disabled={loading} className="rt-btn-primary w-full py-3">
            {loading ? 'Accesso…' : 'Entra nel gestionale'}
          </button>
        </form>

        <p className="mt-8 text-center text-[10px] uppercase tracking-[0.2em] text-ivory/40">
          Trasparenza · Controllo · Risultati
        </p>
      </div>
    </div>
  )
}
