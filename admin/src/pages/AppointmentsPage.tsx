import { useMemo, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react'
import { api } from '@/api/client'
import {
  APPOINTMENT_STATUS_LABELS,
  APPOINTMENT_TYPE_LABELS,
  type Appointment,
  type Paginated,
  type Property,
} from '@/api/types'
import { Badge, Field, inputCls, Modal } from '@/components/ui'
import { cn, formatDateTime } from '@/lib/utils'

function startOfWeek(d: Date): Date {
  const date = new Date(d)
  const day = (date.getDay() + 6) % 7 // lunedì = 0
  date.setDate(date.getDate() - day)
  date.setHours(0, 0, 0, 0)
  return date
}

function iso(d: Date): string {
  return d.toISOString().slice(0, 10)
}

const STATUS_COLORS: Record<Appointment['status'], string> = {
  programmato: 'bg-info/10 text-info',
  svolto: 'bg-success/10 text-success',
  annullato: 'bg-navy/10 text-muted',
}

export default function AppointmentsPage() {
  const [weekStart, setWeekStart] = useState(() => startOfWeek(new Date()))
  const [editing, setEditing] = useState<Appointment | null | 'new'>(null)
  const queryClient = useQueryClient()

  const weekEnd = useMemo(() => {
    const d = new Date(weekStart)
    d.setDate(d.getDate() + 6)
    return d
  }, [weekStart])

  const appts = useQuery({
    queryKey: ['appointments', iso(weekStart)],
    queryFn: () =>
      api<{ data: Appointment[] }>(`/admin/appointments?from=${iso(weekStart)}&to=${iso(weekEnd)}`).then((r) => r.data),
  })

  const days = useMemo(
    () =>
      Array.from({ length: 7 }, (_, i) => {
        const d = new Date(weekStart)
        d.setDate(d.getDate() + i)
        return d
      }),
    [weekStart],
  )

  function shiftWeek(delta: number) {
    const d = new Date(weekStart)
    d.setDate(d.getDate() + delta * 7)
    setWeekStart(d)
  }

  const byDay = useMemo(() => {
    const map: Record<string, Appointment[]> = {}
    for (const a of appts.data ?? []) {
      const key = a.starts_at.slice(0, 10)
      ;(map[key] ??= []).push(a)
    }
    return map
  }, [appts.data])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['appointments'] })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Appuntamenti</h1>
        <div className="flex items-center gap-2">
          <button onClick={() => shiftWeek(-1)} className="rt-btn-outline px-3" aria-label="Settimana precedente">
            <ChevronLeft size={16} />
          </button>
          <button onClick={() => setWeekStart(startOfWeek(new Date()))} className="rt-btn-outline">
            Oggi
          </button>
          <button onClick={() => shiftWeek(1)} className="rt-btn-outline px-3" aria-label="Settimana successiva">
            <ChevronRight size={16} />
          </button>
          <button onClick={() => setEditing('new')} className="rt-btn-primary">
            <Plus size={15} /> Nuovo
          </button>
        </div>
      </div>

      <p className="text-sm text-muted">
        Settimana dal{' '}
        <span className="font-medium text-navy">
          {weekStart.toLocaleDateString('it-IT', { day: '2-digit', month: 'long' })}
        </span>{' '}
        al{' '}
        <span className="font-medium text-navy">
          {weekEnd.toLocaleDateString('it-IT', { day: '2-digit', month: 'long', year: 'numeric' })}
        </span>
      </p>

      <div className="grid gap-2 md:grid-cols-7">
        {days.map((d) => {
          const key = iso(d)
          const isToday = key === iso(new Date())
          const dayAppts = (byDay[key] ?? []).sort((a, b) => a.starts_at.localeCompare(b.starts_at))
          return (
            <div key={key} className={cn('rt-card min-h-[130px] px-2.5 py-2', isToday && 'border-gold shadow-sm')}>
              <p className={cn('text-xs font-semibold uppercase', isToday ? 'text-gold' : 'text-muted')}>
                {d.toLocaleDateString('it-IT', { weekday: 'short', day: '2-digit' })}
              </p>
              <div className="mt-1.5 space-y-1.5">
                {dayAppts.map((a) => (
                  <button
                    key={a.id}
                    onClick={() => setEditing(a)}
                    className={cn(
                      'block w-full rounded-lg px-2 py-1.5 text-left text-xs transition-colors',
                      a.status === 'annullato' ? 'bg-navy/5 text-muted line-through' : 'bg-gold/10 text-navy hover:bg-gold/20',
                    )}
                  >
                    <span className="font-semibold">
                      {new Date(a.starts_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}
                    </span>{' '}
                    {APPOINTMENT_TYPE_LABELS[a.type]}
                    {a.contact_name && <span className="block truncate text-muted">{a.contact_name}</span>}
                  </button>
                ))}
              </div>
            </div>
          )
        })}
      </div>

      {/* Lista completa della settimana */}
      <div className="rt-card overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-navy/10 bg-ivory-soft text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-2.5 font-medium">Quando</th>
              <th className="px-4 py-2.5 font-medium">Tipo</th>
              <th className="px-4 py-2.5 font-medium">Contatto</th>
              <th className="px-4 py-2.5 font-medium">Immobile / Lead</th>
              <th className="px-4 py-2.5 font-medium">Stato</th>
            </tr>
          </thead>
          <tbody>
            {(appts.data ?? []).length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-muted">
                  Nessun appuntamento questa settimana.
                </td>
              </tr>
            )}
            {(appts.data ?? [])
              .sort((a, b) => a.starts_at.localeCompare(b.starts_at))
              .map((a) => (
                <tr key={a.id} onClick={() => setEditing(a)} className="cursor-pointer border-b border-navy/5 last:border-0 hover:bg-ivory-soft/70">
                  <td className="px-4 py-2.5 font-medium text-navy">{formatDateTime(a.starts_at)}</td>
                  <td className="px-4 py-2.5">{APPOINTMENT_TYPE_LABELS[a.type]}</td>
                  <td className="px-4 py-2.5 text-muted">{a.contact_name ?? '—'}</td>
                  <td className="px-4 py-2.5 text-muted">
                    {a.property_title ?? (a.lead_first_name ? `${a.lead_first_name} ${a.lead_last_name}` : '—')}
                  </td>
                  <td className="px-4 py-2.5">
                    <Badge className={STATUS_COLORS[a.status]}>{APPOINTMENT_STATUS_LABELS[a.status]}</Badge>
                  </td>
                </tr>
              ))}
          </tbody>
        </table>
      </div>

      {editing && (
        <AppointmentModal
          appointment={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            invalidate()
            setEditing(null)
          }}
        />
      )}
    </div>
  )
}

function AppointmentModal({
  appointment,
  onClose,
  onSaved,
}: {
  appointment: Appointment | null
  onClose: () => void
  onSaved: () => void
}) {
  const properties = useQuery({
    queryKey: ['properties-select'],
    queryFn: () => api<Paginated<Property>>('/admin/properties?per_page=100'),
  })

  const [form, setForm] = useState({
    type: appointment?.type ?? 'visita',
    starts_at: appointment?.starts_at?.slice(0, 16) ?? '',
    ends_at: appointment?.ends_at?.slice(0, 16) ?? '',
    contact_name: appointment?.contact_name ?? '',
    contact_phone: appointment?.contact_phone ?? '',
    property_id: appointment?.property_id?.toString() ?? '',
    status: appointment?.status ?? 'programmato',
    notes: appointment?.notes ?? '',
  })

  const save = useMutation({
    mutationFn: () =>
      api(appointment ? `/admin/appointments/${appointment.id}` : '/admin/appointments', {
        method: appointment ? 'PUT' : 'POST',
        body: {
          ...form,
          property_id: form.property_id || null,
          ends_at: form.ends_at || null,
        },
      }),
    onSuccess: () => {
      toast.success(appointment ? 'Appuntamento aggiornato' : 'Appuntamento creato')
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  const remove = useMutation({
    mutationFn: () => api(`/admin/appointments/${appointment!.id}`, { method: 'DELETE' }),
    onSuccess: () => {
      toast.success('Appuntamento eliminato')
      onSaved()
    },
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.value }))
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal open onClose={onClose} title={appointment ? 'Modifica appuntamento' : 'Nuovo appuntamento'}>
      <form onSubmit={onSubmit} className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Tipo">
            <select value={form.type} onChange={set('type')} className={inputCls}>
              {Object.entries(APPOINTMENT_TYPE_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Stato">
            <select value={form.status} onChange={set('status')} className={inputCls}>
              {Object.entries(APPOINTMENT_STATUS_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Inizio *">
            <input required type="datetime-local" value={form.starts_at} onChange={set('starts_at')} className={inputCls} />
          </Field>
          <Field label="Fine">
            <input type="datetime-local" value={form.ends_at} onChange={set('ends_at')} className={inputCls} />
          </Field>
          <Field label="Nome contatto">
            <input value={form.contact_name} onChange={set('contact_name')} className={inputCls} />
          </Field>
          <Field label="Telefono contatto">
            <input value={form.contact_phone} onChange={set('contact_phone')} className={inputCls} />
          </Field>
        </div>
        <Field label="Immobile">
          <select value={form.property_id} onChange={set('property_id')} className={inputCls}>
            <option value="">— Nessun immobile —</option>
            {(properties.data?.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Note">
          <textarea rows={2} value={form.notes} onChange={set('notes')} className={inputCls} />
        </Field>
        <div className="flex items-center justify-between pt-2">
          {appointment ? (
            <button
              type="button"
              onClick={() => confirm('Eliminare questo appuntamento?') && remove.mutate()}
              className="flex items-center gap-1.5 text-sm text-error hover:underline"
            >
              <Trash2 size={14} /> Elimina
            </button>
          ) : (
            <span />
          )}
          <button type="submit" disabled={save.isPending} className="rt-btn-primary">
            {save.isPending ? 'Salvataggio…' : 'Salva'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
