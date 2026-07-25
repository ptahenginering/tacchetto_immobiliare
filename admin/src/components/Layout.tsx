import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import {
  BarChart3,
  CalendarDays,
  HandCoins,
  Home,
  LayoutDashboard,
  LogOut,
  Megaphone,
  Menu,
  Search,
  Settings,
  Users,
  X,
  CalendarCheck,
} from 'lucide-react'
import { useEffect, useState } from 'react'
import { RTMonogram } from '@/components/RTLogo'
import { useAuthStore } from '@/store/auth'
import { useUiStore } from '@/store/ui'
import { cn } from '@/lib/utils'

const NAV = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/leads', label: 'Lead', icon: Users },
  { to: '/immobili', label: 'Immobili', icon: Home },
  { to: '/appuntamenti', label: 'Appuntamenti', icon: CalendarDays },
  { to: '/visite', label: 'Visite', icon: CalendarCheck },
  { to: '/proposte', label: 'Proposte', icon: HandCoins },
  { to: '/marketing', label: 'Marketing', icon: Megaphone },
  { to: '/statistiche', label: 'Statistiche', icon: BarChart3 },
  { to: '/impostazioni', label: 'Impostazioni', icon: Settings },
]

/** Layout gestionale: sidebar navy + topbar con ricerca globale. */
export default function Layout() {
  const { user, logout } = useAuthStore()
  const { sidebarOpen, toggleSidebar, closeSidebar } = useUiStore()
  const navigate = useNavigate()
  const [search, setSearch] = useState('')

  useEffect(() => closeSidebar(), [closeSidebar])

  function onSearch(e: React.FormEvent) {
    e.preventDefault()
    if (search.trim()) {
      navigate(`/leads?search=${encodeURIComponent(search.trim())}`)
      setSearch('')
    }
  }

  return (
    <div className="flex min-h-dvh">
      {/* Sidebar */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-30 flex w-60 flex-col bg-navy-deep transition-transform lg:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <div className="flex items-center justify-between border-b border-white/10 px-5 py-4">
          <div className="flex items-center gap-2.5">
            <RTMonogram size={28} onDark />
            <div className="leading-tight">
              <p className="text-[10px] font-medium uppercase tracking-[0.24em] text-ivory">
                Casa <span className="text-gold">Live</span>
              </p>
              <p className="text-[10px] text-ivory/50">Gestionale</p>
            </div>
          </div>
          <button className="text-ivory/60 lg:hidden" onClick={closeSidebar} aria-label="Chiudi menu">
            <X size={19} />
          </button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
          {NAV.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              onClick={closeSidebar}
              className={({ isActive }) => cn('rt-nav-item', isActive && 'active')}
            >
              <Icon size={17} strokeWidth={1.7} />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-white/10 px-5 py-4">
          <p className="truncate text-sm font-medium text-ivory">
            {user ? `${user.first_name} ${user.last_name}` : ''}
          </p>
          <p className="truncate text-xs text-ivory/50">{user?.email}</p>
          <button
            onClick={() => {
              logout()
              navigate('/login')
            }}
            className="mt-3 flex items-center gap-2 text-xs text-ivory/60 hover:text-error"
          >
            <LogOut size={14} /> Esci
          </button>
        </div>
      </aside>

      {sidebarOpen && (
        <div className="fixed inset-0 z-20 bg-navy-deep/50 lg:hidden" onClick={closeSidebar} aria-hidden="true" />
      )}

      {/* Contenuto */}
      <div className="flex min-w-0 flex-1 flex-col lg:pl-60">
        <header className="sticky top-0 z-10 flex items-center gap-3 border-b border-gold/25 bg-white px-4 py-2.5">
          <button className="text-navy lg:hidden" onClick={toggleSidebar} aria-label="Apri menu">
            <Menu size={21} />
          </button>
          <form onSubmit={onSearch} className="relative max-w-md flex-1">
            <Search size={15} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Cerca lead per nome, email, telefono…"
              className="w-full rounded-full border border-navy/15 bg-ivory-soft py-2 pl-9 pr-4 text-sm focus:border-gold focus:outline-none"
            />
          </form>
          <span className="ml-auto hidden text-[10px] uppercase tracking-[0.18em] text-muted md:block">
            Trasparenza · Controllo · Risultati
          </span>
        </header>

        <main className="flex-1 px-4 py-5 md:px-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
