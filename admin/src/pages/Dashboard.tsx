import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  CartesianGrid,
} from 'recharts'
import { ArrowDownRight, ArrowUpRight, CalendarClock, Minus } from 'lucide-react'
import { api } from '@/api/client'
import {
  LEAD_STATUS_COLORS,
  LEAD_STATUS_LABELS,
  SOURCE_LABELS,
  type Appointment,
  type Lead,
  type Paginated,
} from '@/api/types'
import { Badge } from '@/components/ui'
import { formatDate, formatDateTime } from '@/lib/utils'

interface Overview {
  new_leads: Kpi
  handled_leads: Kpi
  mandates: Kpi
  appointments: Kpi
}
interface Kpi {
  value: number
  previous: number
  delta_pct: number | null
}

const PIE_COLORS = ['#C29B52', '#16273F', '#3E6B9E', '#2E7D5B', '#C98A2B']

export default function Dashboard() {
  const overview = useQuery({
    queryKey: ['stats-overview'],
    queryFn: () => api<{ data: Overview }>('/admin/stats/overview').then((r) => r.data),
  })
  const bySource = useQuery({
    queryKey: ['stats-by-source'],
    queryFn: () =>
      api<{ data: { source: Lead['source']; count: number }[] }>('/admin/stats/leads-by-source').then((r) => r.data),
  })
  const performance = useQuery({
    queryKey: ['stats-performance'],
    queryFn: () =>
      api<{ data: { week_start: string; leads: number; visits: number; proposals: number }[] }>(
        '/admin/stats/performance',
      ).then((r) => r.data),
  })
  const lastLeads = useQuery({
    queryKey: ['dash-leads'],
    queryFn: () => api<Paginated<Lead>>('/admin/leads?per_page=6'),
  })
  const todayIso = new Date().toISOString().slice(0, 10)
  const tomorrowIso = new Date(Date.now() + 86400_000).toISOString().slice(0, 10)
  const appts = useQuery({
    queryKey: ['dash-appts'],
    queryFn: () => api<{ data: Appointment[] }>(`/admin/appointments?from=${todayIso}&to=${tomorrowIso}`).then((r) => r.data),
  })

  const perf = (performance.data ?? []).map((w) => ({
    name: formatDate(w.week_start).slice(0, 5),
    Lead: w.leads,
    Visite: w.visits,
    Proposte: w.proposals,
  }))

  const pieData = (bySource.data ?? []).map((s) => ({
    name: SOURCE_LABELS[s.source] ?? s.source,
    value: Number(s.count),
  }))

  const o = overview.data

  return (
    <div className="space-y-5">
      <h1 className="font-display text-2xl text-navy">Dashboard</h1>

      {/* KPI cards */}
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <KpiCard title="Nuovi lead" kpi={o?.new_leads} />
        <KpiCard title="Contatti gestiti" kpi={o?.handled_leads} />
        <KpiCard title="Incarichi" kpi={o?.mandates} />
        <KpiCard title="Appuntamenti" kpi={o?.appointments} />
      </div>

      <div className="grid gap-4 xl:grid-cols-3">
        {/* Performance 6 mesi */}
        <div className="rt-card px-4 py-4 xl:col-span-2">
          <p className="text-sm font-medium text-navy">Performance ultimi 6 mesi</p>
          <div className="mt-3 h-64">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={perf} margin={{ top: 5, right: 10, left: -22, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="rgba(22,39,63,.08)" vertical={false} />
                <XAxis dataKey="name" tick={{ fontSize: 10, fill: '#5C6B80' }} axisLine={false} tickLine={false} interval="preserveStartEnd" />
                <YAxis allowDecimals={false} tick={{ fontSize: 10, fill: '#5C6B80' }} axisLine={false} tickLine={false} />
                <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid rgba(194,155,82,.4)', fontSize: 13 }} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Line type="monotone" dataKey="Lead" stroke="#C29B52" strokeWidth={2} dot={false} />
                <Line type="monotone" dataKey="Visite" stroke="#16273F" strokeWidth={2} dot={false} />
                <Line type="monotone" dataKey="Proposte" stroke="#3E6B9E" strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Donut lead per fonte */}
        <div className="rt-card px-4 py-4">
          <p className="text-sm font-medium text-navy">Lead per fonte</p>
          <div className="mt-3 h-64">
            {pieData.length === 0 ? (
              <p className="flex h-full items-center justify-center text-sm text-muted">Nessun dato ancora</p>
            ) : (
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={pieData} dataKey="value" nameKey="name" innerRadius="55%" outerRadius="80%" paddingAngle={3}>
                    {pieData.map((_, i) => (
                      <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid rgba(194,155,82,.4)', fontSize: 13 }} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                </PieChart>
              </ResponsiveContainer>
            )}
          </div>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-3">
        {/* Ultimi lead */}
        <div className="rt-card overflow-hidden xl:col-span-2">
          <div className="flex items-center justify-between px-4 py-3">
            <p className="text-sm font-medium text-navy">Ultimi lead</p>
            <Link to="/leads" className="text-xs font-medium text-gold hover:underline">
              Vedi tutti →
            </Link>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-y border-navy/10 bg-ivory-soft text-left text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-2 font-medium">Nome</th>
                  <th className="px-4 py-2 font-medium">Fonte</th>
                  <th className="px-4 py-2 font-medium">Stato</th>
                  <th className="px-4 py-2 font-medium">Data</th>
                </tr>
              </thead>
              <tbody>
                {(lastLeads.data?.data ?? []).map((l) => (
                  <tr key={l.id} className="border-b border-navy/5 last:border-0 hover:bg-ivory-soft/60">
                    <td className="px-4 py-2.5">
                      <Link to={`/leads?search=${encodeURIComponent(l.last_name)}`} className="font-medium text-navy hover:text-gold">
                        {l.first_name} {l.last_name}
                      </Link>
                    </td>
                    <td className="px-4 py-2.5 text-muted">{SOURCE_LABELS[l.source]}</td>
                    <td className="px-4 py-2.5">
                      <Badge className={LEAD_STATUS_COLORS[l.status]}>{LEAD_STATUS_LABELS[l.status]}</Badge>
                    </td>
                    <td className="px-4 py-2.5 text-muted">{formatDate(l.created_at)}</td>
                  </tr>
                ))}
                {lastLeads.data?.data.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-4 py-8 text-center text-muted">
                      Nessun lead ancora: appariranno qui appena arrivano dal sito.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Appuntamenti oggi/domani */}
        <div className="rt-card px-4 py-4">
          <p className="text-sm font-medium text-navy">Oggi e domani</p>
          <div className="mt-3 space-y-2.5">
            {(appts.data ?? []).length === 0 ? (
              <p className="text-sm text-muted">Nessun appuntamento nelle prossime 48 ore.</p>
            ) : (
              (appts.data ?? []).map((a) => (
                <div key={a.id} className="flex items-center gap-3 rounded-lg border border-navy/10 px-3 py-2.5">
                  <CalendarClock size={17} className="shrink-0 text-gold" strokeWidth={1.7} />
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium capitalize text-navy">
                      {a.type} {a.contact_name ? `· ${a.contact_name}` : ''}
                    </p>
                    <p className="truncate text-xs text-muted">
                      {formatDateTime(a.starts_at)}
                      {a.property_title ? ` — ${a.property_title}` : ''}
                    </p>
                  </div>
                </div>
              ))
            )}
            <Link to="/appuntamenti" className="block pt-1 text-center text-xs font-medium text-gold hover:underline">
              Vai al calendario →
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}

function KpiCard({ title, kpi }: { title: string; kpi?: Kpi }) {
  const delta = kpi?.delta_pct ?? null
  const DeltaIcon = delta === null || delta === 0 ? Minus : delta > 0 ? ArrowUpRight : ArrowDownRight
  const deltaColor = delta === null || delta === 0 ? 'text-muted' : delta > 0 ? 'text-success' : 'text-error'
  return (
    <div className="rt-card px-4 py-3.5">
      <p className="text-xs font-medium uppercase tracking-wider text-muted">{title}</p>
      <div className="mt-1 flex items-end justify-between">
        <p className="font-display text-3xl text-navy">{kpi?.value ?? '—'}</p>
        <span className={`flex items-center gap-0.5 text-xs font-semibold ${deltaColor}`}>
          <DeltaIcon size={14} strokeWidth={2} />
          {delta === null ? 'n.d.' : `${delta > 0 ? '+' : ''}${delta}%`}
        </span>
      </div>
      <p className="text-[11px] text-muted">vs 30 giorni precedenti</p>
    </div>
  )
}
