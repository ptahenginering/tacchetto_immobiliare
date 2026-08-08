import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Plus } from 'lucide-react'
import { api } from '@/api/client'
import {
  PROPOSAL_STATUS_COLORS,
  PROPOSAL_STATUS_LABELS,
  type Paginated,
  type Property,
  type Proposal,
} from '@/api/types'
import { Badge, Field, inputCls, Modal, VisibilityToggle } from '@/components/ui'
import { formatDate, formatEuro } from '@/lib/utils'

const STATUSES = Object.keys(PROPOSAL_STATUS_LABELS) as Proposal['status'][]

export default function ProposalsPage() {
  const [params] = useSearchParams()
  const [propertyId, setPropertyId] = useState(params.get('property_id') ?? '')
  const [status, setStatus] = useState('')
  const [editing, setEditing] = useState<Proposal | null | 'new'>(null)
  const queryClient = useQueryClient()

  const properties = useQuery({
    queryKey: ['properties-select'],
    queryFn: () => api<Paginated<Property>>('/admin/properties?per_page=100'),
  })

  const qs = new URLSearchParams()
  if (propertyId) qs.set('property_id', propertyId)
  if (status) qs.set('status', status)

  const proposals = useQuery({
    queryKey: ['proposals', qs.toString()],
    queryFn: () => api<{ data: Proposal[] }>(`/admin/proposals?${qs}`).then((r) => r.data),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['proposals'] })
    queryClient.invalidateQueries({ queryKey: ['property-proposals'] })
  }

  async function toggleVisible(p: Proposal) {
    await api(`/admin/proposals/${p.id}`, { method: 'PUT', body: { visible_to_owner: !p.visible_to_owner } })
    invalidate()
  }

  async function changeStatus(p: Proposal, newStatus: Proposal['status']) {
    await api(`/admin/proposals/${p.id}`, { method: 'PUT', body: { status: newStatus } })
    toast.success(`Proposta → ${PROPOSAL_STATUS_LABELS[newStatus]}`)
    invalidate()
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Proposte</h1>
        <div className="flex flex-wrap gap-2">
          <select value={propertyId} onChange={(e) => setPropertyId(e.target.value)} className={inputCls + ' w-auto'}>
            <option value="">Tutti gli immobili</option>
            {(properties.data?.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
          <select value={status} onChange={(e) => setStatus(e.target.value)} className={inputCls + ' w-auto'}>
            <option value="">Tutti gli stati</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {PROPOSAL_STATUS_LABELS[s]}
              </option>
            ))}
          </select>
          <button onClick={() => setEditing('new')} className="rt-btn-primary">
            <Plus size={15} /> Registra proposta
          </button>
        </div>
      </div>

      <div className="rt-card divide-y divide-navy/5">
        {(proposals.data ?? []).length === 0 && (
          <p className="px-5 py-10 text-center text-sm text-muted">Nessuna proposta con questi filtri.</p>
        )}
        {(proposals.data ?? []).map((p) => (
          <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
            <button onClick={() => setEditing(p)} className="min-w-0 text-left">
              <p className="font-display text-xl text-navy">{formatEuro(p.amount)}</p>
              <p className="text-xs text-muted">
                {p.property_title} · ricevuta il {formatDate(p.received_at)}
              </p>
            </button>
            <div className="flex items-center gap-2">
              {/* Pipeline stato rapida */}
              <select
                value={p.status}
                onChange={(e) => changeStatus(p, e.target.value as Proposal['status'])}
                className="rounded-full border border-navy/15 bg-white px-2.5 py-1 text-xs focus:border-gold focus:outline-none"
              >
                {STATUSES.map((s) => (
                  <option key={s} value={s}>
                    {PROPOSAL_STATUS_LABELS[s]}
                  </option>
                ))}
              </select>
              <Badge className={PROPOSAL_STATUS_COLORS[p.status]}>{PROPOSAL_STATUS_LABELS[p.status]}</Badge>
              <VisibilityToggle visible={p.visible_to_owner} onToggle={() => toggleVisible(p)} />
            </div>
          </div>
        ))}
      </div>

      {editing && (
        <ProposalModal
          proposal={editing === 'new' ? null : editing}
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

function ProposalModal({
  proposal,
  defaultPropertyId,
  properties,
  onClose,
  onSaved,
}: {
  proposal: Proposal | null
  defaultPropertyId: string
  properties: Property[]
  onClose: () => void
  onSaved: () => void
}) {
  const [form, setForm] = useState({
    property_id: proposal?.property_id?.toString() ?? defaultPropertyId,
    amount: proposal?.amount ?? '',
    status: proposal?.status ?? 'ricevuta',
    received_at: proposal?.received_at?.slice(0, 16) ?? '',
    notes: proposal?.notes ?? '',
    visible_to_owner: proposal?.visible_to_owner ?? true,
  })

  const save = useMutation({
    mutationFn: () =>
      api(proposal ? `/admin/proposals/${proposal.id}` : '/admin/proposals', {
        method: proposal ? 'PUT' : 'POST',
        body: {
          ...form,
          property_id: Number(form.property_id),
          received_at: form.received_at || undefined,
          notes: form.notes || null,
        },
      }),
    onSuccess: () => {
      toast.success(
        proposal
          ? 'Proposta aggiornata'
          : 'Proposta registrata' + (form.visible_to_owner ? ' — il proprietario riceverà l\'invito ad aprire l\'app (senza importo)' : ''),
      )
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  return (
    <Modal open onClose={onClose} title={proposal ? 'Modifica proposta' : 'Registra proposta'}>
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
            disabled={!!proposal}
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
          <Field label="Importo (€) *">
            <input
              required
              type="number"
              min="1"
              step="any"
              value={form.amount}
              onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))}
              className={inputCls}
            />
          </Field>
          <Field label="Stato">
            <select
              value={form.status}
              onChange={(e) => setForm((f) => ({ ...f, status: e.target.value as Proposal['status'] }))}
              className={inputCls}
            >
              {STATUSES.map((s) => (
                <option key={s} value={s}>
                  {PROPOSAL_STATUS_LABELS[s]}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <Field label="Ricevuta il">
          <input
            type="datetime-local"
            value={form.received_at}
            onChange={(e) => setForm((f) => ({ ...f, received_at: e.target.value }))}
            className={inputCls}
          />
        </Field>
        <Field label="Note interne">
          <textarea rows={2} value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} className={inputCls} />
        </Field>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.visible_to_owner}
            onChange={(e) => setForm((f) => ({ ...f, visible_to_owner: e.target.checked }))}
            className="accent-gold"
          />
          Visibile al proprietario (riceverà l'email di notifica, senza importo)
        </label>
        <div className="flex justify-end pt-2">
          <button type="submit" disabled={save.isPending} className="rt-btn-primary">
            {save.isPending ? 'Salvataggio…' : 'Salva proposta'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
