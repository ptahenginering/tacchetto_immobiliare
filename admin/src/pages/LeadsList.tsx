import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useSearchParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Mail, Phone, UserPlus } from 'lucide-react'
import { api } from '@/api/client'
import {
  LEAD_STATUS_COLORS,
  LEAD_STATUS_LABELS,
  REQUEST_TYPE_LABELS,
  SOURCE_LABELS,
  type Lead,
  type Paginated,
} from '@/api/types'
import { Badge, Field, inputCls, Modal, Pagination } from '@/components/ui'
import { formatDateTime } from '@/lib/utils'

const STATUSES = Object.keys(LEAD_STATUS_LABELS) as Lead['status'][]

export default function LeadsList() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()

  const page = Math.max(1, Number(params.get('page') ?? 1))
  const status = params.get('status') ?? ''
  const source = params.get('source') ?? ''
  const search = params.get('search') ?? ''

  const [selected, setSelected] = useState<Lead | null>(null)
  const [convertLead, setConvertLead] = useState<Lead | null>(null)

  const qs = new URLSearchParams({ page: String(page), per_page: '15' })
  if (status) qs.set('status', status)
  if (source) qs.set('source', source)
  if (search) qs.set('search', search)

  const leads = useQuery({
    queryKey: ['leads', qs.toString()],
    queryFn: () => api<Paginated<Lead>>(`/admin/leads?${qs}`),
  })

  function setFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setParams(next)
  }

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['leads'] })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Lead</h1>
        <div className="flex flex-wrap gap-2">
          <select value={status} onChange={(e) => setFilter('status', e.target.value)} className={inputCls + ' w-auto'}>
            <option value="">Tutti gli stati</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {LEAD_STATUS_LABELS[s]}
              </option>
            ))}
          </select>
          <select value={source} onChange={(e) => setFilter('source', e.target.value)} className={inputCls + ' w-auto'}>
            <option value="">Tutte le fonti</option>
            {Object.entries(SOURCE_LABELS).map(([k, v]) => (
              <option key={k} value={k}>
                {v}
              </option>
            ))}
          </select>
          <input
            defaultValue={search}
            placeholder="Cerca…"
            className={inputCls + ' w-44'}
            onKeyDown={(e) => {
              if (e.key === 'Enter') setFilter('search', (e.target as HTMLInputElement).value)
            }}
          />
        </div>
      </div>

      <div className="rt-card overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-navy/10 bg-ivory-soft text-left text-xs uppercase tracking-wide text-muted">
              <th className="px-4 py-2.5 font-medium">Nome</th>
              <th className="px-4 py-2.5 font-medium">Contatti</th>
              <th className="px-4 py-2.5 font-medium">Richiesta</th>
              <th className="px-4 py-2.5 font-medium">Fonte</th>
              <th className="px-4 py-2.5 font-medium">Stato</th>
              <th className="px-4 py-2.5 font-medium">Arrivato</th>
            </tr>
          </thead>
          <tbody>
            {leads.isLoading && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-muted">
                  Caricamento…
                </td>
              </tr>
            )}
            {(leads.data?.data ?? []).map((l) => (
              <tr
                key={l.id}
                onClick={() => setSelected(l)}
                className="cursor-pointer border-b border-navy/5 last:border-0 hover:bg-ivory-soft/70"
              >
                <td className="px-4 py-3 font-medium text-navy">
                  {l.first_name} {l.last_name}
                </td>
                <td className="px-4 py-3 text-muted">
                  <span className="flex flex-col gap-0.5 text-xs">
                    {l.email && (
                      <span className="flex items-center gap-1">
                        <Mail size={11} /> {l.email}
                      </span>
                    )}
                    {l.phone && (
                      <span className="flex items-center gap-1">
                        <Phone size={11} /> {l.phone}
                      </span>
                    )}
                  </span>
                </td>
                <td className="px-4 py-3 text-muted">{REQUEST_TYPE_LABELS[l.request_type]}</td>
                <td className="px-4 py-3 text-muted">{SOURCE_LABELS[l.source]}</td>
                <td className="px-4 py-3">
                  <Badge className={LEAD_STATUS_COLORS[l.status]}>{LEAD_STATUS_LABELS[l.status]}</Badge>
                </td>
                <td className="px-4 py-3 text-xs text-muted">{formatDateTime(l.created_at)}</td>
              </tr>
            ))}
            {leads.data?.data.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-10 text-center text-muted">
                  Nessun lead trovato con questi filtri.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Pagination page={page} totalPages={leads.data?.meta.total_pages ?? 1} onChange={(p) => setFilter('page', String(p))} />

      {selected && (
        <LeadDrawer
          lead={selected}
          onClose={() => setSelected(null)}
          onChanged={() => {
            invalidate()
            setSelected(null)
          }}
          onConvert={() => {
            setConvertLead(selected)
            setSelected(null)
          }}
        />
      )}

      {convertLead && (
        <ConvertWizard
          lead={convertLead}
          onClose={() => setConvertLead(null)}
          onDone={() => {
            invalidate()
            setConvertLead(null)
          }}
        />
      )}
    </div>
  )
}

/* ---------- Drawer dettaglio ---------- */

function LeadDrawer({
  lead,
  onClose,
  onChanged,
  onConvert,
}: {
  lead: Lead
  onClose: () => void
  onChanged: () => void
  onConvert: () => void
}) {
  const [status, setStatus] = useState<Lead['status']>(lead.status)
  const [notes, setNotes] = useState(lead.notes ?? '')
  const [lostReason, setLostReason] = useState(lead.lost_reason ?? '')

  const update = useMutation({
    mutationFn: () =>
      api(`/admin/leads/${lead.id}`, {
        method: 'PUT',
        body: { status, notes, ...(status === 'perso' ? { lost_reason: lostReason } : {}) },
      }),
    onSuccess: () => {
      toast.success('Lead aggiornato')
      onChanged()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  return (
    <Modal open onClose={onClose} title={`${lead.first_name} ${lead.last_name}`} wide>
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2 text-sm">
          <p className="text-xs uppercase tracking-wide text-muted">Contatti</p>
          {lead.email && (
            <a href={`mailto:${lead.email}`} className="flex items-center gap-2 text-navy hover:text-gold">
              <Mail size={14} className="text-gold" /> {lead.email}
            </a>
          )}
          {lead.phone && (
            <a href={`tel:${lead.phone}`} className="flex items-center gap-2 text-navy hover:text-gold">
              <Phone size={14} className="text-gold" /> {lead.phone}
            </a>
          )}
          <p className="pt-2 text-xs uppercase tracking-wide text-muted">Richiesta</p>
          <p>
            {REQUEST_TYPE_LABELS[lead.request_type]} · {SOURCE_LABELS[lead.source]}
          </p>
          {lead.message && <p className="rounded-lg bg-ivory-soft p-3 text-sm italic">“{lead.message}”</p>}
        </div>

        <div className="space-y-3">
          <Field label="Stato pipeline">
            <select value={status} onChange={(e) => setStatus(e.target.value as Lead['status'])} className={inputCls}>
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {LEAD_STATUS_LABELS[s]}
                </option>
              ))}
            </select>
          </Field>
          {status === 'perso' && (
            <Field label="Motivo perdita">
              <input value={lostReason} onChange={(e) => setLostReason(e.target.value)} className={inputCls} />
            </Field>
          )}
          <Field label="Note interne">
            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={4} className={inputCls} />
          </Field>
        </div>
      </div>

      <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
        {lead.converted_property_id ? (
          <span className="text-sm text-success">✓ Già convertito in incarico</span>
        ) : (
          <button onClick={onConvert} className="rt-btn-outline">
            <UserPlus size={15} /> Converti in incarico
          </button>
        )}
        <button onClick={() => update.mutate()} disabled={update.isPending} className="rt-btn-primary">
          {update.isPending ? 'Salvataggio…' : 'Salva modifiche'}
        </button>
      </div>
    </Modal>
  )
}

/* ---------- Wizard conversione ---------- */

function ConvertWizard({ lead, onClose, onDone }: { lead: Lead; onClose: () => void; onDone: () => void }) {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    owner_first_name: lead.first_name,
    owner_last_name: lead.last_name,
    owner_email: lead.email ?? '',
    owner_phone: lead.phone ?? '',
    title: '',
    address: '',
    city: '',
    province: 'TV',
    type: 'appartamento',
    surface_sqm: '',
    rooms: '',
  })

  const convert = useMutation({
    mutationFn: () =>
      api<{ data: { property_id: number } }>(`/admin/leads/${lead.id}/convert`, {
        method: 'POST',
        body: {
          ...form,
          surface_sqm: form.surface_sqm || undefined,
          rooms: form.rooms || undefined,
          title: form.title || undefined,
        },
      }),
    onSuccess: (res) => {
      toast.success('Incarico creato! Email di benvenuto inviata al proprietario.')
      onDone()
      navigate(`/immobili/${res.data.property_id}`)
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore nella conversione'),
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.value }))
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    convert.mutate()
  }

  return (
    <Modal open onClose={onClose} title="Converti in incarico" wide>
      <form onSubmit={onSubmit} className="space-y-4">
        <p className="rounded-lg bg-gold/10 px-3 py-2 text-xs text-navy">
          Verranno creati l'accesso all'Area Cliente per il proprietario (email di benvenuto con link personale) e
          l'immobile in stato <strong>valutazione</strong> con la checklist burocrazia già pronta.
        </p>

        <p className="text-xs font-semibold uppercase tracking-wide text-muted">1 · Proprietario</p>
        <div className="grid gap-3 md:grid-cols-2">
          <Field label="Nome">
            <input required value={form.owner_first_name} onChange={set('owner_first_name')} className={inputCls} />
          </Field>
          <Field label="Cognome">
            <input required value={form.owner_last_name} onChange={set('owner_last_name')} className={inputCls} />
          </Field>
          <Field label="Email (per l'accesso all'app)">
            <input required type="email" value={form.owner_email} onChange={set('owner_email')} className={inputCls} />
          </Field>
          <Field label="Telefono">
            <input value={form.owner_phone} onChange={set('owner_phone')} className={inputCls} />
          </Field>
        </div>

        <p className="text-xs font-semibold uppercase tracking-wide text-muted">2 · Immobile</p>
        <div className="grid gap-3 md:grid-cols-2">
          <Field label="Titolo (opzionale)">
            <input value={form.title} onChange={set('title')} placeholder="es. Appartamento in centro" className={inputCls} />
          </Field>
          <Field label="Tipologia">
            <select value={form.type} onChange={set('type')} className={inputCls}>
              <option value="appartamento">Appartamento</option>
              <option value="casa">Casa</option>
              <option value="villa">Villa</option>
              <option value="terreno">Terreno</option>
              <option value="commerciale">Commerciale</option>
              <option value="altro">Altro</option>
            </select>
          </Field>
          <Field label="Indirizzo">
            <input value={form.address} onChange={set('address')} className={inputCls} />
          </Field>
          <Field label="Città">
            <input value={form.city} onChange={set('city')} className={inputCls} />
          </Field>
          <Field label="Provincia">
            <input value={form.province} onChange={set('province')} maxLength={4} className={inputCls} />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Mq">
              <input type="number" min="0" value={form.surface_sqm} onChange={set('surface_sqm')} className={inputCls} />
            </Field>
            <Field label="Locali">
              <input type="number" min="0" value={form.rooms} onChange={set('rooms')} className={inputCls} />
            </Field>
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <button type="button" onClick={onClose} className="rt-btn-outline">
            Annulla
          </button>
          <button type="submit" disabled={convert.isPending} className="rt-btn-primary">
            {convert.isPending ? 'Creazione…' : 'Crea incarico e invia benvenuto'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
