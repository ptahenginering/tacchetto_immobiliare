import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import {
  CalendarCheck,
  CalendarClock,
  HandCoins,
  Home,
  Info,
  RefreshCw,
  TrendingDown,
  TrendingUp,
  Minus,
} from 'lucide-react'
import { useProperty, usePropertyKpi, useTimeline, useVisits } from '@/api/queries'
import { auth } from '@/api/client'
import { fileUrl, STATUS_LABELS } from '@/api/types'
import EmptyState from '@/components/EmptyState'
import { PageSkeleton } from '@/components/Skeleton'
import StarRating from '@/components/StarRating'
import { formatDate, formatDateTime } from '@/lib/utils'

export default function HomePage() {
  const queryClient = useQueryClient()
  const property = useProperty()
  const kpi = usePropertyKpi()
  const visits = useVisits()
  const timeline = useTimeline()

  const user = auth.getUser()

  if (property.isLoading || kpi.isLoading) return <PageSkeleton />

  if (property.isError || !property.data) {
    return (
      <EmptyState
        icon={Home}
        title="Nessun immobile associato"
        hint="Il tuo immobile apparirà qui appena l'incarico sarà attivo. Per informazioni contatta Roberto."
      />
    )
  }

  const p = property.data
  const k = kpi.data
  const cover = fileUrl(p.cover_image_url)
  const lastFeedback = (visits.data ?? []).filter((v) => v.feedback_text).slice(0, 3)
  const upcoming = (timeline.data ?? [])
    .filter((e) => e.event_type === 'appuntamento' && new Date(e.happened_at) > new Date())
    .sort((a, b) => a.happened_at.localeCompare(b.happened_at))
    .slice(0, 3)

  const TrendIcon = k?.interest.trend === 'up' ? TrendingUp : k?.interest.trend === 'down' ? TrendingDown : Minus
  const trendColor =
    k?.interest.trend === 'up' ? 'text-success' : k?.interest.trend === 'down' ? 'text-error' : 'text-muted'

  const series = (k?.visits_series ?? []).map((w) => ({
    name: formatDate(w.week_start).slice(0, 5),
    visite: w.visits,
  }))

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-muted">Ciao {user?.first_name ?? ''} 👋</p>
          <h1 className="font-display text-xl text-navy">La tua vendita, in tempo reale</h1>
        </div>
        <button
          aria-label="Aggiorna dati"
          onClick={() => queryClient.invalidateQueries()}
          className="flex h-11 w-11 items-center justify-center rounded-full border border-gold/40 text-gold transition-transform active:rotate-180"
        >
          <RefreshCw size={18} strokeWidth={1.7} />
        </button>
      </div>

      {/* Hero card immobile */}
      <div className="rt-card overflow-hidden shadow-rt">
        <div className="relative h-44 bg-navy">
          {cover ? (
            <img src={cover} alt={p.title} className="h-full w-full object-cover" />
          ) : (
            <div className="flex h-full items-center justify-center">
              <Home size={44} strokeWidth={1} className="text-gold/60" />
            </div>
          )}
          <span className="absolute right-3 top-3 rounded-full bg-gold px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider text-navy-deep">
            {STATUS_LABELS[p.status]}
          </span>
        </div>
        <div className="px-4 py-3.5">
          <p className="font-display text-lg leading-snug text-navy">{p.title}</p>
          <p className="mt-0.5 text-sm text-muted">
            {[p.address, p.city].filter(Boolean).join(', ')}
            {p.surface_sqm ? ` · ${p.surface_sqm} mq` : ''}
            {p.rooms ? ` · ${p.rooms} locali` : ''}
          </p>
        </div>
      </div>

      {/* KPI grid */}
      <div className="grid grid-cols-2 gap-3">
        <KpiCard icon={CalendarClock} label="Appuntamenti" value={k?.appointments_30d ?? 0} note="ultimi 30 giorni" />
        <KpiCard icon={CalendarCheck} label="Visite" value={k?.visits_30d ?? 0} note="ultimi 30 giorni" />
        <KpiCard icon={HandCoins} label="Proposte" value={k?.proposals_active ?? 0} note="in corso" />
        <div className="rt-card px-4 py-3.5">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium uppercase tracking-wider text-muted">Interesse</span>
            <span title={k?.interest.explanation}>
              <Info size={14} className="text-gold" aria-label={k?.interest.explanation} />
            </span>
          </div>
          <div className="mt-1 flex items-end gap-2">
            <span className="font-display text-3xl text-navy">{k?.interest.score ?? 0}%</span>
            <TrendIcon size={20} className={`${trendColor} mb-1.5`} strokeWidth={1.8} />
          </div>
          <p className="text-[11px] text-muted">come lo calcoliamo ⓘ</p>
        </div>
      </div>

      {/* Andamento visite */}
      <div className="rt-card px-4 py-4">
        <p className="rt-eyebrow">Andamento visite</p>
        <div className="mt-3 h-44">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={series} margin={{ top: 5, right: 5, bottom: 0, left: -28 }}>
              <defs>
                <linearGradient id="goldFade" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#C29B52" stopOpacity={0.35} />
                  <stop offset="100%" stopColor="#C29B52" stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="rgba(22,39,63,.08)" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 10, fill: '#5C6B80' }} axisLine={false} tickLine={false} />
              <YAxis allowDecimals={false} tick={{ fontSize: 10, fill: '#5C6B80' }} axisLine={false} tickLine={false} />
              <Tooltip
                contentStyle={{ borderRadius: 12, border: '1px solid rgba(194,155,82,.4)', fontSize: 13 }}
                labelFormatter={(l) => `Settimana del ${l}`}
              />
              <Area
                type="monotone"
                dataKey="visite"
                stroke="#16273F"
                strokeWidth={2}
                fill="url(#goldFade)"
                dot={{ fill: '#C29B52', stroke: '#C29B52', r: 3 }}
                activeDot={{ r: 5, fill: '#C29B52' }}
              />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Ultimi feedback */}
      <section>
        <p className="rt-eyebrow">Ultimi riscontri</p>
        <div className="mt-3 space-y-3">
          {lastFeedback.length === 0 ? (
            <p className="text-sm text-muted">I riscontri delle visite appariranno qui.</p>
          ) : (
            lastFeedback.map((v) => (
              <blockquote key={v.id} className="rt-card px-4 py-3.5">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-muted">{v.visitor_label}</span>
                  {v.feedback_rating && <StarRating value={v.feedback_rating} />}
                </div>
                <p className="mt-1.5 text-sm italic leading-relaxed text-navy">“{v.feedback_text}”</p>
              </blockquote>
            ))
          )}
          {lastFeedback.length > 0 && (
            <Link to="/visite" className="block text-center text-sm font-medium text-gold underline-offset-4 hover:underline">
              Vedi tutti i riscontri →
            </Link>
          )}
        </div>
      </section>

      {/* Prossimi appuntamenti */}
      <section className="pb-2">
        <p className="rt-eyebrow">Prossimi appuntamenti</p>
        <div className="mt-3 space-y-2.5">
          {upcoming.length === 0 ? (
            <p className="text-sm text-muted">Nessun appuntamento in programma al momento.</p>
          ) : (
            upcoming.map((e) => (
              <div key={`${e.event_type}-${e.id}`} className="rt-card flex items-center gap-3 px-4 py-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gold/15">
                  <CalendarClock size={18} className="text-gold" strokeWidth={1.6} />
                </div>
                <div>
                  <p className="text-sm font-medium capitalize text-navy">{e.title.replace('Appuntamento: ', '')}</p>
                  <p className="text-xs text-muted">{formatDateTime(e.happened_at)}</p>
                </div>
              </div>
            ))
          )}
        </div>
      </section>
    </div>
  )
}

function KpiCard({
  icon: Icon,
  label,
  value,
  note,
}: {
  icon: typeof CalendarCheck
  label: string
  value: number
  note: string
}) {
  return (
    <div className="rt-card px-4 py-3.5">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium uppercase tracking-wider text-muted">{label}</span>
        <Icon size={15} className="text-gold" strokeWidth={1.6} />
      </div>
      <p className="mt-1 font-display text-3xl text-navy">{value}</p>
      <p className="text-[11px] text-muted">{note}</p>
    </div>
  )
}
