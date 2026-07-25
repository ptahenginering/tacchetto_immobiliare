import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Plus, Star, Trash2 } from 'lucide-react'
import { api } from '@/api/client'
import type { Paginated, Property, Visit } from '@/api/types'
import { Field, inputCls, Modal, VisibilityToggle } from '@/components/ui'
import { cn, formatDateTime } from '@/lib/utils'

export default function VisitsPage() {
  const [params] = useSearchParams()
  const propertyFilter = params.get('property_id') ?? ''
  const [propertyId, setPropertyId] = useState(propertyFilter)
  const [editing, setEditing] = useState<Visit | null | 'new'>(propertyFilter ? null : null)
  const queryClient = useQueryClient()

  const properties = useQuery({
    queryKey: ['properties-select'],
    queryFn: () => api<Paginated<Property>>('/admin/properties?per_page=100'),
  })

  const visits = useQuery({
    queryKey: ['visits', propertyId],
    queryFn: () =>
      api<{ data: Visit[] }>(`/admin/visits${propertyId ? `?property_id=${propertyId}` : ''}`).then((r) => r.data),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['visits'] })
    queryClient.invalidateQueries({ queryKey: ['property-visits'] })
  }

  async function toggleVisible(v: Visit) {
    await api(`/admin/visits/${v.id}`, { method: 'PUT', body: { visible_to_owner: !v.visible_to_owner } })
    invalidate()
  }

  async function remove(v: Visit) {
    if (!confirm('Eliminare questa visita?')) return
    await api(`/admin/visits/${v.id}`, { method: 'DELETE' })
    invalidate()
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Visite &amp; riscontri</h1>
        <div className="flex gap-2">
          <select value={propertyId} onChange={(e) => setPropertyId(e.target.value)} className={inputCls + ' w-auto'}>
            <option value="">Tutti gli immobili</option>
            {(properties.data?.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
          <button onClick={() => setEditing('new')} className="rt-btn-primary">
            <Plus size={15} /> Registra visita
          </button>
        </div>
      </div>

      <div className="rt-card divide-y divide-navy/5">
        {(visits.data ?? []).length === 0 && (
          <p className="px-5 py-10 text-center text-sm text-muted">Nessuna visita registrata.</p>
        )}
        {(visits.data ?? []).map((v) => (
          <div key={v.id} className="flex items-start justify-between gap-3 px-5 py-3.5">
            <button onClick={() => setEditing(v)} className="min-w-0 flex-1 text-left">
              <p className="text-sm font-medium text-navy">
                {v.visitor_label}
                <span className="ml-2 font-normal text-muted">{formatDateTime(v.visited_at)}</span>
                {v.qualified && <span className="ml-2 rounded-full bg-success/10 px-2 py-0.5 text-[10px] font-semibold text-success">Qualificata</span>}
              </p>
              <p className="text-xs text-muted">{v.property_title}</p>
              {v.feedback_rating != null && (
                <span className="mt-1 inline-flex gap-0.5">
                  {[1, 2, 3, 4, 5].map((i) => (
                    <Star key={i} size={12} className={i <= (v.feedback_rating ?? 0) ? 'fill-gold text-gold' : 'text-gold/25'} />
                  ))}
                </span>
              )}
              {v.feedback_text && <p className="mt-0.5 truncate text-xs italic text-navy/80">“{v.feedback_text}”</p>}
            </button>
            <div className="flex shrink-0 items-center gap-1.5">
              <VisibilityToggle visible={v.visible_to_owner} onToggle={() => toggleVisible(v)} />
              <button onClick={() => remove(v)} aria-label="Elimina" className="flex h-8 w-8 items-center justify-center rounded-full text-error hover:bg-error/10">
                <Trash2 size={14} />
              </button>
            </div>
          </div>
        ))}
      </div>

      {editing && (
        <VisitModal
          visit={editing === 'new' ? null : editing}
          defaultPropertyId={propertyId}
          properties={properties.data?.data ?? []}
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

function VisitModal({
  visit,
  defaultPropertyId,
  properties,
  onClose,
  onSaved,
}: {
  visit: Visit | null
  defaultPropertyId: string
  properties: Property[]
  onClose: () => void
  onSaved: () => void
}) {
  const [form, setForm] = useState({
    property_id: visit?.property_id?.toString() ?? defaultPropertyId,
    visited_at: visit?.visited_at?.slice(0, 16) ?? '',
    visitor_label: visit?.visitor_label ?? '',
    qualified: visit?.qualified ?? true,
    feedback_rating: visit?.feedback_rating ?? 0,
    feedback_text: visit?.feedback_text ?? '',
    visible_to_owner: visit?.visible_to_owner ?? true,
  })

  const save = useMutation({
    mutationFn: () =>
      api(visit ? `/admin/visits/${visit.id}` : '/admin/visits', {
        method: visit ? 'PUT' : 'POST',
        body: {
          ...form,
          property_id: Number(form.property_id),
          feedback_rating: form.feedback_rating || null,
          feedback_text: form.feedback_text || null,
        },
      }),
    onSuccess: () => {
      toast.success(visit ? 'Visita aggiornata' : 'Visita registrata' + (form.visible_to_owner ? ' — il proprietario riceverà una email' : ''))
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  return (
    <Modal open onClose={onClose} title={visit ? 'Modifica visita' : 'Registra visita'}>
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          save.mutate()
        }}
        className="space-y-3"
      >
        <Field label="Immobile *">
          <select
            required
            value={form.property_id}
            onChange={(e) => setForm((f) => ({ ...f, property_id: e.target.value }))}
            className={inputCls}
            disabled={!!visit}
          >
            <option value="">— Seleziona —</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Data e ora *">
            <input
              required
              type="datetime-local"
              value={form.visited_at}
              onChange={(e) => setForm((f) => ({ ...f, visited_at: e.target.value }))}
              className={inputCls}
            />
          </Field>
          <Field label="Etichetta visitatore *">
            <input
              required
              placeholder='es. "Coppia, prima casa"'
              value={form.visitor_label}
              onChange={(e) => setForm((f) => ({ ...f, visitor_label: e.target.value }))}
              className={inputCls}
            />
          </Field>
        </div>
        <p className="rounded-lg bg-ivory-soft px-3 py-2 text-xs text-muted">
          ⚠️ Mai dati personali del visitatore: il proprietario vedrà questa etichetta.
        </p>

        <Field label="Valutazione">
          <div className="flex gap-1 py-1">
            {[1, 2, 3, 4, 5].map((i) => (
              <button
                key={i}
                type="button"
                onClick={() => setForm((f) => ({ ...f, feedback_rating: f.feedback_rating === i ? 0 : i }))}
                aria-label={`${i} stelle`}
              >
                <Star size={22} className={cn(i <= form.feedback_rating ? 'fill-gold text-gold' : 'text-gold/30')} />
              </button>
            ))}
          </div>
        </Field>
        <Field label="Riscontro del visitatore">
          <textarea
            rows={3}
            value={form.feedback_text}
            onChange={(e) => setForm((f) => ({ ...f, feedback_text: e.target.value }))}
            className={inputCls}
            placeholder="Cosa ha detto il visitatore della casa…"
          />
        </Field>

        <div className="flex flex-col gap-2 text-sm">
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={form.qualified}
              onChange={(e) => setForm((f) => ({ ...f, qualified: e.target.checked }))}
              className="accent-gold"
            />
            Visita qualificata (interesse reale)
          </label>
          <label className="flex items-center gap-2">
            <input
              type="checkbox"
              checked={form.visible_to_owner}
              onChange={(e) => setForm((f) => ({ ...f, visible_to_owner: e.target.checked }))}
              className="accent-gold"
            />
            Visibile al proprietario — se attivo, il feedback sarà mostrato nell'app e notificato via email
          </label>
        </div>

        <div className="flex justify-end pt-2">
          <button type="submit" disabled={save.isPending} className="rt-btn-primary">
            {save.isPending ? 'Salvataggio…' : 'Salva visita'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
