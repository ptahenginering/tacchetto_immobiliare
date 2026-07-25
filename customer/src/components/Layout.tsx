import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { Home, CalendarCheck, HandCoins, Megaphone, MessageCircle, UserRound } from 'lucide-react'
import { RTMonogram } from '@/components/RTLogo'
import { cn } from '@/lib/utils'

const NAV_ITEMS = [
  { to: '/', label: 'Home', icon: Home, end: true },
  { to: '/visite', label: 'Visite', icon: CalendarCheck },
  { to: '/proposte', label: 'Proposte', icon: HandCoins },
  { to: '/promozione', label: 'Promozione', icon: Megaphone },
  { to: '/assistente', label: 'Assistente', icon: MessageCircle },
]

/**
 * Layout mobile-first: header con monogramma + profilo,
 * bottom navigation a 5 voci (touch target ≥ 44px).
 */
export default function Layout() {
  const navigate = useNavigate()

  return (
    <div className="mx-auto flex min-h-dvh w-full max-w-lg flex-col bg-ivory md:max-w-2xl">
      <header className="sticky top-0 z-20 flex items-center justify-between border-b border-gold/25 bg-navy-deep px-4 py-3">
        <button onClick={() => navigate('/')} aria-label="Vai alla home" className="flex items-center gap-2">
          <RTMonogram size={26} onDark />
          <span className="text-[11px] font-medium uppercase tracking-[0.28em] text-ivory">
            Casa <span className="text-gold">Live</span>
          </span>
        </button>
        <NavLink
          to="/profilo"
          aria-label="Profilo"
          className={({ isActive }) =>
            cn(
              'flex h-11 w-11 items-center justify-center rounded-full transition-colors',
              isActive ? 'bg-gold/20 text-gold-light' : 'text-ivory/80 hover:text-ivory',
            )
          }
        >
          <UserRound size={22} strokeWidth={1.6} />
        </NavLink>
      </header>

      <main className="flex-1 px-4 pb-24 pt-4">
        <Outlet />
      </main>

      <nav
        className="fixed inset-x-0 bottom-0 z-20 mx-auto w-full max-w-lg border-t border-gold/25 bg-navy-deep pb-[env(safe-area-inset-bottom)] md:max-w-2xl"
        aria-label="Navigazione principale"
      >
        <div className="grid grid-cols-5">
          {NAV_ITEMS.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                cn(
                  'flex min-h-[56px] flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors',
                  isActive ? 'text-gold-light' : 'text-ivory/60 hover:text-ivory/90',
                )
              }
            >
              <Icon size={21} strokeWidth={1.6} />
              {label}
            </NavLink>
          ))}
        </div>
      </nav>
    </div>
  )
}
