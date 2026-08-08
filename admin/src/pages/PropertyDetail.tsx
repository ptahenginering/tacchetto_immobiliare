import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import {
  ArrowDown,
  ArrowUp,
  Check,
  Home,
  ImagePlus,
  Loader2,
  Star,
  Trash2,
} from 'lucide-react'
import { api, fileUrl } from '@/api/client'
import {
  CHANNEL_LABELS,
  PROPERTY_STATUS_LABELS,
  PROPERTY_TYPE_LABELS,
  PROPOSAL_STATUS_COLORS,
  PROPOSAL_STATUS_LABELS,
  type MarketingActivity,
  type Property,
  type Proposal,
  type Visit,
} from '@/api/types'
import { Badge, Field, inputCls, VisibilityToggle } from '@/components/ui'
import { cn, formatDate, formatDateTime, formatEuro } from '@/lib/utils'
import PropertySheetTab from './PropertySheetTab'

const TABS = ['Dati', 'Foto', 'Scheda', 'Visite', 'Proposte', 'Marketing', 'Pratiche', 'Statistiche'] as const
type Tab = (typeof TABS)[number]

export default function PropertyDetail() {
  const { id } = useParams()
  const propertyId = Number(id)
  const [tab, setTab] = useState<Tab>('Dati')
  const queryClient = useQueryClient()

  const property = useQuery({
    queryKey: ['property', propertyId],
    queryFn: () => api<{ data: Property }>(`/admin/properties/${propertyId}`).then((r) => r.data),
    enabled: Number.isFinite(propertyId),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['property', propertyId] })

  if (property.isLoading) return <p className="py-10 text-center text-muted">Caricamento…</p>
  if (!property.data) return <p className="py-10 text-center text-muted">Immobile non trovato.</p>

  const p = property.data

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/immobili" className="text-xs text-muted hover:text-gold">
            ← Immobili
          </Link>
          <h1 className="font-display text-2xl text-navy">{p.title}</h1>
          <p className="text-sm text-muted">
            {[p.address, p.city, p.province].filter(Boolean).join(', ')}
            {p.owner_first_name && (
              <>
                {' '}
                · Proprietario: <span className="font-medium text-navy">{p.owner_first_name} {p.owner_last_name}</span>
              </>
            )}
          </p>
        </div>
        <Badge className="bg-gold/15 text-gold">{PROPERTY_STATUS_LABELS[p.status]}</Badge>
      </div>

      <div className="flex flex-wrap gap-1 border-b border-navy/10">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={cn(
              'px-4 py-2 text-sm font-medium transition-colors',
              tab === t ? 'border-b-2 border-gold text-navy' : 'text-muted hover:text-navy',
            )}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'Dati' && <DataTab property={p} onSaved={invalidate} />}
      {tab === 'Foto' && <PhotosTab property={p} onChanged={invalidate} />}
      {tab === 'Scheda' && <PropertySheetTab property={p} />}
      {tab === 'Visite' && <VisitsTab propertyId={propertyId} />}
      {tab === 'Proposte' && <ProposalsTab propertyId={propertyId} />}
      {tab === 'Marketing' && <MarketingTab propertyId={propertyId} />}
      {tab === 'Pratiche' && <PracticeTab property={p} onChanged={invalidate} />}
      {tab === 'Statistiche' && <StatsTab propertyId={propertyId} />}
    </div>
  )
}

/* ---------- Dati ---------- */

function DataTab({ property, onSaved }: { property: Property; onSaved: () => void }) {
  const [form, setForm] = useState({
    title: property.title,
    address: property.address ?? '',
    city: property.city ?? '',
    province: property.province ?? '',
    type: property.type,
    status: property.status,
    surface_sqm: property.surface_sqm?.toString() ?? '',
    rooms: property.rooms?.toString() ?? '',
    price: property.price ?? '',
    description: property.description ?? '',
    mandate_start: property.mandate_start ?? '',
    mandate_end: property.mandate_end ?? '',
    owner_user_id: property.owner_id?.toString() ?? '',
  })

  // Proprietari (utenti role=owner): un proprietario può avere più immobili
  // collegati (multiproprietà), ognuno con la propria scheda.
  const owners = useQuery({
    queryKey: ['owner-users'],
    queryFn: () =>
      api<{ data: { id: number; first_name: string; last_name: string; email: string }[] }>(
        '/admin/users?role=owner',
      ).then((r) => r.data),
  })

  const save = useMutation({
    mutationFn: () =>
      api(`/admin/properties/${property.id}`, {
        method: 'PUT',
        body: {
          ...form,
          surface_sqm: form.surface_sqm || null,
          rooms: form.rooms || null,
          price: form.price || null,
          mandate_start: form.mandate_start || null,
          mandate_end: form.mandate_end || null,
          owner_user_id: form.owner_user_id ? Number(form.owner_user_id) : null,
        },
      }),
    onSuccess: () => {
      toast.success('Immobile aggiornato')
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.value }))
  }

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault()
        save.mutate()
      }}
      className="rt-card max-w-3xl space-y-3 px-5 py-4"
    >
      <div className="grid gap-3 md:grid-cols-2">
        <Field label="Titolo">
          <input required value={form.title} onChange={set('title')} className={inputCls} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Tipologia">
            <select value={form.type} onChange={set('type')} className={inputCls}>
              {Object.entries(PROPERTY_TYPE_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Stato">
            <select value={form.status} onChange={set('status')} className={inputCls}>
              {Object.entries(PROPERTY_STATUS_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <Field label="Indirizzo">
          <input value={form.address} onChange={set('address')} className={inputCls} />
        </Field>
        <div className="grid grid-cols-3 gap-3">
          <Field label="Città">
            <input value={form.city} onChange={set('city')} className={inputCls} />
          </Field>
          <Field label="Prov.">
            <input value={form.province} onChange={set('province')} maxLength={4} className={inputCls} />
          </Field>
          <Field label="Prezzo (€)">
            <input type="number" min="0" step="1000" value={form.price} onChange={set('price')} className={inputCls} />
          </Field>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Mq">
            <input type="number" min="0" value={form.surface_sqm} onChange={set('surface_sqm')} className={inputCls} />
          </Field>
          <Field label="Locali">
            <input type="number" min="0" value={form.rooms} onChange={set('rooms')} className={inputCls} />
          </Field>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Inizio incarico">
            <input type="date" value={form.mandate_start} onChange={set('mandate_start')} className={inputCls} />
          </Field>
          <Field label="Fine incarico">
            <input type="date" value={form.mandate_end} onChange={set('mandate_end')} className={inputCls} />
          </Field>
        </div>
        <Field label="Proprietario (accede all'area cliente)">
          <select value={form.owner_user_id} onChange={set('owner_user_id')} className={inputCls}>
            <option value="">— Nessun proprietario collegato —</option>
            {(owners.data ?? []).map((o) => (
              <option key={o.id} value={o.id}>
                {o.first_name} {o.last_name} · {o.email}
              </option>
            ))}
          </select>
        </Field>
      </div>
      <Field label="Descrizione">
        <textarea rows={5} value={form.description} onChange={set('description')} className={inputCls} />
      </Field>
      <div className="flex justify-end">
        <button type="submit" disabled={save.isPending} className="rt-btn-primary">
          {save.isPending ? 'Salvataggio…' : 'Salva'}
        </button>
      </div>
    </form>
  )
}

/* ---------- Foto ---------- */

function PhotosTab({ property, onChanged }: { property: Property; onChanged: () => void }) {
  const fileInput = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [dragOver, setDragOver] = useState(false)
  const images = property.images ?? []

  async function upload(files: FileList | File[]) {
    const fd = new FormData()
    Array.from(files).forEach((f) => fd.append('images[]', f))
    setUploading(true)
    try {
      await api(`/admin/properties/${property.id}/images`, { method: 'POST', formData: fd })
      toast.success('Immagini caricate')
      onChanged()
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Upload non riuscito')
    } finally {
      setUploading(false)
    }
  }

  async function move(index: number, dir: -1 | 1) {
    const order = images.map((i) => i.id)
    const target = index + dir
    if (target < 0 || target >= order.length) return
    ;[order[index], order[target]] = [order[target], order[index]]
    await api(`/admin/properties/${property.id}/images/order`, { method: 'PUT', body: { order } })
    onChanged()
  }

  async function setCover(imageId: number) {
    await api(`/admin/properties/${property.id}/images/${imageId}/cover`, { method: 'PUT' })
    toast.success('Cover aggiornata')
    onChanged()
  }

  async function remove(imageId: number) {
    if (!confirm('Eliminare questa immagine?')) return
    await api(`/admin/properties/${property.id}/images/${imageId}`, { method: 'DELETE' })
    onChanged()
  }

  return (
    <div className="space-y-4">
      <div
        onDragOver={(e) => {
          e.preventDefault()
          setDragOver(true)
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(e) => {
          e.preventDefault()
          setDragOver(false)
          if (e.dataTransfer.files.length) upload(e.dataTransfer.files)
        }}
        onClick={() => fileInput.current?.click()}
        className={cn(
          'flex cursor-pointer flex-col items-center justify-center rounded-rt border-2 border-dashed px-6 py-10 text-center transition-colors',
          dragOver ? 'border-gold bg-gold/10' : 'border-gold/40 bg-ivory-soft hover:bg-gold/5',
        )}
      >
        {uploading ? (
          <Loader2 size={26} className="animate-spin text-gold" />
        ) : (
          <ImagePlus size={26} strokeWidth={1.4} className="text-gold" />
        )}
        <p className="mt-2 text-sm font-medium text-navy">Trascina qui le foto o clicca per scegliere</p>
        <p className="text-xs text-muted">JPG, PNG o WEBP · max 8 MB · ottimizzate automaticamente</p>
        <input
          ref={fileInput}
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(e) => e.target.files?.length && upload(e.target.files)}
        />
      </div>

      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
        {images.map((img, i) => {
          const isCover = property.cover_image_url === img.url
          return (
            <div key={img.id} className="rt-card group relative overflow-hidden">
              <img src={fileUrl(img.url) ?? ''} alt="" className="h-36 w-full object-cover" />
              {isCover && (
                <span className="absolute left-2 top-2 rounded-full bg-gold px-2 py-0.5 text-[10px] font-bold uppercase text-navy-deep">
                  Cover
                </span>
              )}
              <div className="flex items-center justify-between px-2 py-1.5">
                <div className="flex gap-1">
                  <IconBtn onClick={() => move(i, -1)} disabled={i === 0} label="Sposta su">
                    <ArrowUp size={13} />
                  </IconBtn>
                  <IconBtn onClick={() => move(i, 1)} disabled={i === images.length - 1} label="Sposta giù">
                    <ArrowDown size={13} />
                  </IconBtn>
                  <IconBtn onClick={() => setCover(img.id)} disabled={isCover} label="Imposta cover">
                    <Star size={13} />
                  </IconBtn>
                </div>
                <IconBtn onClick={() => remove(img.id)} label="Elimina" danger>
                  <Trash2 size={13} />
                </IconBtn>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function IconBtn({
  children,
  onClick,
  disabled,
  label,
  danger,
}: {
  children: React.ReactNode
  onClick: () => void
  disabled?: boolean
  label: string
  danger?: boolean
}) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      title={label}
      className={cn(
        'flex h-7 w-7 items-center justify-center rounded-full transition-colors disabled:opacity-30',
        danger ? 'text-error hover:bg-error/10' : 'text-navy hover:bg-gold/15',
      )}
    >
      {children}
    </button>
  )
}

/* ---------- Visite / Proposte / Marketing (liste con toggle visibilità) ---------- */

function VisitsTab({ propertyId }: { propertyId: number }) {
  const queryClient = useQueryClient()
  const visits = useQuery({
    queryKey: ['property-visits', propertyId],
    queryFn: () => api<{ data: Visit[] }>(`/admin/visits?property_id=${propertyId}`).then((r) => r.data),
  })

  async function toggle(v: Visit) {
    await api(`/admin/visits/${v.id}`, { method: 'PUT', body: { visible_to_owner: !v.visible_to_owner } })
    queryClient.invalidateQueries({ queryKey: ['property-visits', propertyId] })
  }

  return (
    <ListWithAdd addTo={`/visite?property_id=${propertyId}`} addLabel="Registra visita">
      {(visits.data ?? []).length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">Nessuna visita registrata.</p>
      ) : (
        (visits.data ?? []).map((v) => (
          <div key={v.id} className="flex items-center justify-between gap-3 border-b border-navy/5 py-3 last:border-0">
            <div className="min-w-0">
              <p className="text-sm font-medium text-navy">
                {v.visitor_label} <span className="font-normal text-muted">· {formatDateTime(v.visited_at)}</span>
              </p>
              {v.feedback_text && <p className="truncate text-xs italic text-muted">“{v.feedback_text}”</p>}
            </div>
            <VisibilityToggle visible={v.visible_to_owner} onToggle={() => toggle(v)} />
          </div>
        ))
      )}
    </ListWithAdd>
  )
}

function ProposalsTab({ propertyId }: { propertyId: number }) {
  const queryClient = useQueryClient()
  const proposals = useQuery({
    queryKey: ['property-proposals', propertyId],
    queryFn: () => api<{ data: Proposal[] }>(`/admin/proposals?property_id=${propertyId}`).then((r) => r.data),
  })

  async function toggle(p: Proposal) {
    await api(`/admin/proposals/${p.id}`, { method: 'PUT', body: { visible_to_owner: !p.visible_to_owner } })
    queryClient.invalidateQueries({ queryKey: ['property-proposals', propertyId] })
  }

  return (
    <ListWithAdd addTo={`/proposte?property_id=${propertyId}`} addLabel="Registra proposta">
      {(proposals.data ?? []).length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">Nessuna proposta.</p>
      ) : (
        (proposals.data ?? []).map((p) => (
          <div key={p.id} className="flex items-center justify-between gap-3 border-b border-navy/5 py-3 last:border-0">
            <div>
              <p className="font-display text-lg text-navy">{formatEuro(p.amount)}</p>
              <p className="text-xs text-muted">{formatDate(p.received_at)}</p>
            </div>
            <div className="flex items-center gap-2">
              <Badge className={PROPOSAL_STATUS_COLORS[p.status]}>{PROPOSAL_STATUS_LABELS[p.status]}</Badge>
              <VisibilityToggle visible={p.visible_to_owner} onToggle={() => toggle(p)} />
            </div>
          </div>
        ))
      )}
    </ListWithAdd>
  )
}

function MarketingTab({ propertyId }: { propertyId: number }) {
  const queryClient = useQueryClient()
  const activities = useQuery({
    queryKey: ['property-marketing', propertyId],
    queryFn: () => api<{ data: MarketingActivity[] }>(`/admin/marketing?property_id=${propertyId}`).then((r) => r.data),
  })

  async function toggle(m: MarketingActivity) {
    await api(`/admin/marketing/${m.id}`, { method: 'PUT', body: { visible_to_owner: !m.visible_to_owner } })
    queryClient.invalidateQueries({ queryKey: ['property-marketing', propertyId] })
  }

  return (
    <ListWithAdd addTo={`/marketing?property_id=${propertyId}`} addLabel="Aggiungi attività">
      {(activities.data ?? []).length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">Nessuna attività di promozione.</p>
      ) : (
        (activities.data ?? []).map((m) => (
          <div key={m.id} className="flex items-center justify-between gap-3 border-b border-navy/5 py-3 last:border-0">
            <div className="min-w-0">
              <p className="text-xs font-semibold uppercase tracking-wide text-gold">{CHANNEL_LABELS[m.channel] ?? m.channel}</p>
              <p className="truncate text-sm font-medium text-navy">{m.title}</p>
              <p className="text-xs text-muted">
                {m.published_at ? formatDate(m.published_at) : 'non pubblicata'} ·{' '}
                {Object.entries(m.stats)
                  .map(([k, v]) => `${v} ${k}`)
                  .join(' · ') || 'nessuna statistica'}
              </p>
            </div>
            <VisibilityToggle visible={m.visible_to_owner} onToggle={() => toggle(m)} />
          </div>
        ))
      )}
    </ListWithAdd>
  )
}

function ListWithAdd({ children, addTo, addLabel }: { children: React.ReactNode; addTo: string; addLabel: string }) {
  return (
    <div className="rt-card px-5 py-2">
      {children}
      <div className="border-t border-navy/5 py-3 text-center">
        <Link to={addTo} className="text-sm font-medium text-gold hover:underline">
          + {addLabel}
        </Link>
      </div>
    </div>
  )
}

/* ---------- Pratiche ---------- */

function PracticeTab({ property, onChanged }: { property: Property; onChanged: () => void }) {
  const steps = property.practice_steps ?? []
  const NEXT: Record<string, string> = { da_fare: 'in_corso', in_corso: 'completato', completato: 'da_fare' }

  async function cycle(stepId: number, current: string) {
    await api(`/admin/practice-steps/${stepId}`, { method: 'PUT', body: { status: NEXT[current] } })
    onChanged()
  }

  async function toggleVisible(stepId: number, visible: boolean) {
    await api(`/admin/practice-steps/${stepId}`, { method: 'PUT', body: { visible_to_owner: !visible } })
    onChanged()
  }

  return (
    <div className="rt-card max-w-2xl px-5 py-3">
      {steps.length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">Nessuno step per questo immobile.</p>
      ) : (
        steps.map((s) => (
          <div key={s.id} className="flex items-center justify-between gap-3 border-b border-navy/5 py-3 last:border-0">
            <button
              onClick={() => cycle(s.id, s.status)}
              className="flex items-center gap-3 text-left"
              title="Clicca per cambiare stato (da fare → in corso → completato)"
            >
              <span
                className={cn(
                  'flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-xs',
                  s.status === 'completato'
                    ? 'border-gold bg-gold text-navy-deep'
                    : s.status === 'in_corso'
                      ? 'border-warning text-warning'
                      : 'border-navy/20 text-navy/30',
                )}
              >
                {s.status === 'completato' ? <Check size={13} strokeWidth={2.5} /> : s.status === 'in_corso' ? '…' : ''}
              </span>
              <span>
                <span className="block text-sm font-medium text-navy">{s.label}</span>
                <span className="block text-xs text-muted">
                  {s.status === 'completato'
                    ? `Completato${s.completed_at ? ` il ${formatDate(s.completed_at)}` : ''}`
                    : s.status === 'in_corso'
                      ? 'In corso'
                      : 'Da fare'}
                </span>
              </span>
            </button>
            <VisibilityToggle visible={s.visible_to_owner} onToggle={() => toggleVisible(s.id, s.visible_to_owner)} />
          </div>
        ))
      )}
    </div>
  )
}

/* ---------- Statistiche ---------- */

function StatsTab({ propertyId }: { propertyId: number }) {
  const stats = useQuery({
    queryKey: ['property-stats', propertyId],
    queryFn: () =>
      api<{ data: { funnel: Record<string, string | number> } }>(`/admin/stats/property/${propertyId}`).then(
        (r) => r.data,
      ),
  })

  const f = stats.data?.funnel
  if (stats.isLoading) return <p className="py-8 text-center text-muted">Caricamento…</p>
  if (!f) return null

  const rows = [
    { label: 'Appuntamenti totali', value: f.appointments_total },
    { label: 'Appuntamenti svolti', value: f.appointments_done },
    { label: 'Visite totali', value: f.visits_total },
    { label: 'Visite qualificate', value: f.visits_qualified },
    { label: 'Riscontri positivi (≥4★)', value: f.positive_feedback },
    { label: 'Proposte ricevute', value: f.proposals_total },
    { label: 'Proposte attive', value: f.proposals_active },
    { label: 'Attività di promozione', value: f.marketing_activities },
  ]

  const max = Math.max(1, ...rows.map((r) => Number(r.value)))

  return (
    <div className="rt-card max-w-2xl space-y-3 px-5 py-4">
      {rows.map((r) => (
        <div key={r.label}>
          <div className="flex justify-between text-sm">
            <span className="text-navy">{r.label}</span>
            <span className="font-display text-navy">{String(r.value)}</span>
          </div>
          <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-navy/8">
            <div
              className="h-full rounded-full bg-gold"
              style={{ width: `${(Number(r.value) / max) * 100}%` }}
            />
          </div>
        </div>
      ))}
      {f.best_offer && (
        <p className="pt-2 text-sm text-muted">
          Migliore offerta ricevuta: <span className="font-display text-lg text-navy">{formatEuro(String(f.best_offer))}</span>
        </p>
      )}
    </div>
  )
}
