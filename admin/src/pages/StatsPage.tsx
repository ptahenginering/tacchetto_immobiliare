import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { Download } from 'lucide-react'
import { api } from '@/api/client'
import { SOURCE_LABELS, type Lead } from '@/api/types'
import { formatDate } from '@/lib/utils'

interface WeekRow {
  week_start: string
  leads: number
  visits: number
  proposals: number
}

const PERIODS = [
  { value: 8, label: 'Ultime 8 settimane' },
  { value: 13, label: 'Ultimi 3 mesi' },
  { value: 26, label: 'Ultimi 6 mesi' },
]

export default function StatsPage() {
  const [weeks, setWeeks] = useState(26)

  const performance = useQuery({
    queryKey: ['stats-performance'],
    queryFn: () => api<{ data: WeekRow[] }>('/admin/stats/performance').then((r) => r.data),
  })
  const bySource = useQuery({
    queryKey: ['stats-by-source'],
    queryFn: () =>
      api<{ data: { source: Lead['source']; count: number; converted: number }[] }>('/admin/stats/leads-by-source').then(
        (r) => r.data,
      ),
  })

  const filtered = useMemo(() => {
    const all = performance.data ?? []
    return all.slice(Math.max(0, all.length - weeks))
  }, [performance.data, weeks])

  const chartData = filtered.map((w) => ({
    name: formatDate(w.week_start).slice(0, 5),
    Lead: w.leads,
    Visite: w.visits,
    Proposte: w.proposals,
  }))

  const totals = useMemo(
    () =>
      filtered.reduce(
        (acc, w) => ({
          leads: acc.leads + Number(w.leads),
          visits: acc.visits + Number(w.visits),
          proposals: acc.proposals + Number(w.proposals),
        }),
        { leads: 0, visits: 0, proposals: 0 },
      ),
    [filtered],
  )

  function exportCsv() {
    const header = 'settimana;lead;visite;proposte'
    const rows = filtered.map((w) => `${formatDate(w.week_start)};${w.leads};${w.visits};${w.proposals}`)
    const csv = [header, ...rows].join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = 'rt-casa-live-statistiche.csv'
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Statistiche</h1>
        <div className="flex gap-2">
          <select
            value={weeks}
            onChange={(e) => setWeeks(Number(e.target.value))}
            className="rounded-lg border border-navy/15 bg-white px-3 py-2 text-sm focus:border-gold focus:outline-none"
          >
            {PERIODS.map((p) => (
              <option key={p.value} value={p.value}>
                {p.label}
              </option>
            ))}
          </select>
          <button onClick={exportCsv} className="rt-btn-outline">
            <Download size={14} /> Esporta CSV
          </button>
        </div>
      </div>

      {/* Totali periodo */}
      <div className="grid grid-cols-3 gap-3">
        {[
          { label: 'Lead', value: totals.leads },
          { label: 'Visite', value: totals.visits },
          { label: 'Proposte', value: totals.proposals },
        ].map((t) => (
          <div key={t.label} className="rt-card px-4 py-3.5 text-center">
            <p className="font-display text-3xl text-navy">{t.value}</p>
            <p className="text-xs uppercase tracking-wider text-muted">{t.label} nel periodo</p>
          </div>
        ))}
      </div>

      {/* Andamento */}
      <div className="rt-card px-4 py-4">
        <p className="text-sm font-medium text-navy">Andamento settimanale</p>
        <div className="mt-3 h-72">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={chartData} margin={{ top: 5, right: 10, left: -22, bottom: 0 }}>
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

      {/* Fonti */}
      <div className="rt-card px-4 py-4">
        <p className="text-sm font-medium text-navy">Lead per fonte e conversioni in incarico</p>
        <div className="mt-3 h-64">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart
              data={(bySource.data ?? []).map((s) => ({
                name: SOURCE_LABELS[s.source] ?? s.source,
                Lead: Number(s.count),
                Incarichi: Number(s.converted),
              }))}
              margin={{ top: 5, right: 10, left: -22, bottom: 0 }}
            >
              <CartesianGrid strokeDasharray="3 3" stroke="rgba(22,39,63,.08)" vertical={false} />
              <XAxis dataKey="name" tick={{ fontSize: 11, fill: '#5C6B80' }} axisLine={false} tickLine={false} />
              <YAxis allowDecimals={false} tick={{ fontSize: 10, fill: '#5C6B80' }} axisLine={false} tickLine={false} />
              <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid rgba(194,155,82,.4)', fontSize: 13 }} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="Lead" fill="#C29B52" radius={[6, 6, 0, 0]} />
              <Bar dataKey="Incarichi" fill="#16273F" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>
    </div>
  )
}
