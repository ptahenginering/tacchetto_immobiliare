import { useState, type FormEvent } from 'react'
import { Mail, CheckCircle2 } from 'lucide-react'
import { api } from '@/api/client'
import { RTMonogram } from '@/components/RTLogo'

/**
 * Splash/login: fondo navy, monogramma grande, input email.
 * L'accesso avviene via magic link inviato per email (nessuna password).
 */
export default function LoginPage() {
  const [email, setEmail] = useState('')
  const [sent, setSent] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await api('/customer/request-access', { method: 'POST', body: { email } })
      setSent(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Si è verificato un errore. Riprova.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-navy-deep px-6">
      <div className="w-full max-w-sm text-center animate-fade-in">
        <RTMonogram size={72} onDark className="mx-auto" />
        <p className="mt-3 text-xs font-medium uppercase tracking-[0.32em] text-ivory">
          Casa <span className="text-gold">Live</span>
        </p>
        <h1 className="mt-8 font-display text-2xl text-ivory">La tua casa, sotto controllo</h1>
        <p className="mt-2 text-sm leading-relaxed text-ivory/70">
          Visite, riscontri, proposte e pratiche: tutto in tempo reale.
          <br />
          <span className="text-gold-light">Trasparenza. Controllo. Risultati.</span>
        </p>

        {sent ? (
          <div className="rt-card mt-10 border-gold/40 bg-navy px-6 py-8 text-ivory shadow-rt">
            <CheckCircle2 className="mx-auto text-gold" size={40} strokeWidth={1.4} />
            <p className="mt-4 font-display text-lg">Controlla la tua email</p>
            <p className="mt-2 text-sm text-ivory/70">
              Ti abbiamo inviato un link di accesso: aprilo da questo dispositivo per entrare nella tua area riservata.
            </p>
            <button className="mt-6 text-sm text-gold underline-offset-4 hover:underline" onClick={() => setSent(false)}>
              Usa un altro indirizzo
            </button>
          </div>
        ) : (
          <form onSubmit={onSubmit} className="mt-10 space-y-4">
            <label htmlFor="email" className="sr-only">
              La tua email
            </label>
            <div className="relative">
              <Mail className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gold" size={18} strokeWidth={1.6} />
              <input
                id="email"
                type="email"
                required
                autoComplete="email"
                placeholder="La tua email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="min-h-[48px] w-full rounded-full border border-gold/40 bg-navy px-12 py-3 text-ivory placeholder:text-ivory/40 focus:border-gold focus:outline-none"
              />
            </div>
            {error && <p className="text-sm text-[#E8907E]" role="alert">{error}</p>}
            <button type="submit" disabled={loading || !email} className="rt-btn-primary w-full">
              {loading ? 'Invio in corso…' : 'Ricevi il link di accesso'}
            </button>
            <p className="text-xs leading-relaxed text-ivory/50">
              Nessuna password da ricordare: ti inviamo un link personale valido 30 minuti.
            </p>
          </form>
        )}

        <p className="mt-10 text-xs text-ivory/40">
          Roberto Tacchetto — Real Estate Advisor ·{' '}
          <a href="tel:+393457771822" className="text-gold/80 hover:text-gold">
            +39 345 7771822
          </a>
        </p>
      </div>
    </div>
  )
}
