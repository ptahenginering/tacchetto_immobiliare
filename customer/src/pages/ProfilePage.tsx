import { useNavigate } from 'react-router-dom'
import { LogOut, Mail, Phone, UserRound } from 'lucide-react'
import { auth } from '@/api/client'
import { RTMonogram } from '@/components/RTLogo'

/** Profilo: dati utente, contatti diretti di Roberto, logout. */
export default function ProfilePage() {
  const navigate = useNavigate()
  const user = auth.getUser()

  function logout() {
    auth.clear()
    navigate('/login', { replace: true })
  }

  return (
    <div className="space-y-5 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Profilo</p>
      </header>

      {/* Dati utente */}
      <div className="rt-card flex items-center gap-4 px-4 py-5">
        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-navy">
          <UserRound size={26} className="text-gold" strokeWidth={1.4} />
        </div>
        <div>
          <p className="font-display text-lg text-navy">
            {user ? `${user.first_name} ${user.last_name}` : 'Ospite'}
          </p>
          <p className="text-sm text-muted">{user?.email}</p>
        </div>
      </div>

      {/* Contatti Roberto */}
      <div className="rt-card overflow-hidden">
        <div className="bg-navy-deep px-4 py-5 text-center">
          <RTMonogram size={40} onDark className="mx-auto" />
          <p className="mt-2 font-display text-lg text-ivory">Roberto Tacchetto</p>
          <p className="text-xs uppercase tracking-[0.2em] text-gold-light">Real Estate Advisor</p>
          <p className="mt-2 text-xs italic text-ivory/60">"La tua casa. Il tuo futuro. Il mio impegno."</p>
        </div>
        <div className="divide-y divide-gold/20">
          <a href="tel:+393457771822" className="flex min-h-[52px] items-center gap-3 px-4 py-3 text-navy hover:bg-ivory-soft">
            <Phone size={19} className="text-gold" strokeWidth={1.6} />
            <span className="text-sm font-medium">+39 345 7771822</span>
          </a>
          <a href="mailto:info@rtimmobiliare.it" className="flex min-h-[52px] items-center gap-3 px-4 py-3 text-navy hover:bg-ivory-soft">
            <Mail size={19} className="text-gold" strokeWidth={1.6} />
            <span className="text-sm font-medium">info@rtimmobiliare.it</span>
          </a>
        </div>
      </div>

      <button
        onClick={logout}
        className="flex min-h-[48px] w-full items-center justify-center gap-2 rounded-full border border-error/40 py-3 text-sm font-medium text-error hover:bg-error/5"
      >
        <LogOut size={17} strokeWidth={1.7} /> Esci dall'area riservata
      </button>

      <p className="pb-2 text-center text-[11px] uppercase tracking-[0.2em] text-muted">
        Trasparenza · Controllo · Risultati
      </p>
    </div>
  )
}
