import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Copy, Plus, Sparkles, Trash2 } from 'lucide-react'
import { api } from '@/api/client'
import { CHANNEL_LABELS, type MarketingActivity, type Paginated, type Property } from '@/api/types'
import { Badge, Field, inputCls, Modal, VisibilityToggle } from '@/components/ui'
import CampaignStudio from '@/components/CampaignStudio'
import { formatDate } from '@/lib/utils'

interface ScheduledPost {
  id: number
  property_id: number | null
  channel: string
  content: string
  scheduled_at: string | null
  status: 'bozza' | 'programmato' | 'pubblicato' | 'errore'
  property_title?: string | null
}

const AI_KINDS = [
  { value: 'annuncio', label: 'Annuncio portale' },
  { value: 'post_social', label: 'Post social' },
  { value: 'descrizione', label: 'Descrizione breve' },
  { value: 'email', label: 'Email presentazione' },
]

export default function MarketingPage() {
  const [params] = useSearchParams()
  const [propertyId, setPropertyId] = useState(params.get('property_id') ?? '')
  const [editing, setEditing] = useState<MarketingActivity | null | 'new'>(null)
  const queryClient = useQueryClient()

  const properties = useQuery({
    queryKey: ['properties-select'],
    queryFn: () => api<Paginated<Property>>('/admin/properties?per_page=100'),
  })

  const activities = useQuery({
    queryKey: ['marketing', propertyId],
    queryFn: () =>
      api<{ data: MarketingActivity[] }>(`/admin/marketing${propertyId ? `?property_id=${propertyId}` : ''}`).then(
        (r) => r.data,
      ),
  })

  const posts = useQuery({
    queryKey: ['scheduled-posts'],
    queryFn: () => api<{ data: ScheduledPost[] }>('/admin/scheduled-posts').then((r) => r.data),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['marketing'] })
    queryClient.invalidateQueries({ queryKey: ['property-marketing'] })
  }

  async function toggleVisible(m: MarketingActivity) {
    await api(`/admin/marketing/${m.id}`, { method: 'PUT', body: { visible_to_owner: !m.visible_to_owner } })
    invalidate()
  }

  async function removeActivity(m: MarketingActivity) {
    if (!confirm('Eliminare questa attività?')) return
    await api(`/admin/marketing/${m.id}`, { method: 'DELETE' })
    invalidate()
  }

  async function removePost(id: number) {
    if (!confirm('Eliminare questo post?')) return
    await api(`/admin/scheduled-posts/${id}`, { method: 'DELETE' })
    queryClient.invalidateQueries({ queryKey: ['scheduled-posts'] })
  }

  function copyContent(text: string) {
    navigator.clipboard.writeText(text).then(() => toast.success('Contenuto copiato negli appunti'))
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Marketing</h1>
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
            <Plus size={15} /> Nuova attività
          </button>
        </div>
      </div>

      {/* Registro attività */}
      <div className="rt-card divide-y divide-navy/5">
        {(activities.data ?? []).length === 0 && (
          <p className="px-5 py-8 text-center text-sm text-muted">Nessuna attività registrata.</p>
        )}
        {(activities.data ?? []).map((m) => (
          <div key={m.id} className="flex items-center justify-between gap-3 px-5 py-3.5">
            <button onClick={() => setEditing(m)} className="min-w-0 flex-1 text-left">
              <p className="text-xs font-semibold uppercase tracking-wide text-gold">
                {CHANNEL_LABELS[m.channel] ?? m.channel}
              </p>
              <p className="truncate text-sm font-medium text-navy">{m.title}</p>
              <p className="text-xs text-muted">
                {m.property_title} · {m.published_at ? formatDate(m.published_at) : 'non pubblicata'} ·{' '}
                {Object.entries(m.stats)
                  .map(([k, v]) => `${new Intl.NumberFormat('it-IT').format(Number(v))} ${k}`)
                  .join(' · ') || 'nessuna statistica'}
              </p>
            </button>
            <div className="flex shrink-0 items-center gap-1.5">
              <VisibilityToggle visible={m.visible_to_owner} onToggle={() => toggleVisible(m)} />
              <button onClick={() => removeActivity(m)} aria-label="Elimina" className="flex h-8 w-8 items-center justify-center rounded-full text-error hover:bg-error/10">
                <Trash2 size={14} />
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Genera con AI */}
      <AiGenerator properties={properties.data?.data ?? []} onPostSaved={() => queryClient.invalidateQueries({ queryKey: ['scheduled-posts'] })} />

      {/* Campagne di acquisizione: post social JPG + volantino A5 con QR */}
      <CampaignStudio />

      {/* Post programmati */}
      <section>
        <h2 className="font-display text-lg text-navy">Post programmati</h2>
        <p className="text-xs text-muted">
          La pubblicazione automatica sui social arriverà in una fase successiva: per ora copia il contenuto e pubblica manualmente.
        </p>
        <div className="rt-card mt-3 divide-y divide-navy/5">
          {(posts.data ?? []).length === 0 && (
            <p className="px-5 py-6 text-center text-sm text-muted">Nessun post in coda.</p>
          )}
          {(posts.data ?? []).map((sp) => (
            <div key={sp.id} className="flex items-start justify-between gap-3 px-5 py-3.5">
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge className="bg-gold/15 text-gold">{CHANNEL_LABELS[sp.channel] ?? sp.channel}</Badge>
                  <Badge className="bg-navy/8 text-muted">Pubblicazione manuale</Badge>
                  <span className="text-xs text-muted">{sp.property_title}</span>
                </div>
                <p className="mt-1.5 line-clamp-3 whitespace-pre-wrap text-sm text-navy">{sp.content}</p>
              </div>
              <div className="flex shrink-0 gap-1.5">
                <button
                  onClick={() => copyContent(sp.content)}
                  className="flex h-8 w-8 items-center justify-center rounded-full text-navy hover:bg-gold/15"
                  aria-label="Copia contenuto"
                  title="Copia contenuto"
                >
                  <Copy size={14} />
                </button>
                <button onClick={() => removePost(sp.id)} aria-label="Elimina" className="flex h-8 w-8 items-center justify-center rounded-full text-error hover:bg-error/10">
                  <Trash2 size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>

      {editing && (
        <ActivityModal
          activity={editing === 'new' ? null : editing}
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

/* ---------- Sezione AI ---------- */

function AiGenerator({ properties, onPostSaved }: { properties: Property[]; onPostSaved: () => void }) {
  const [propertyId, setPropertyId] = useState('')
  const [kind, setKind] = useState('annuncio')
  const [notes, setNotes] = useState('')
  const [output, setOutput] = useState('')
  const [generationId, setGenerationId] = useState<number | null>(null)

  const generate = useMutation({
    mutationFn: () =>
      api<{ data: { generation_id: number; output: string } }>('/admin/ai/generate', {
        method: 'POST',
        body: { property_id: Number(propertyId), kind, extra_notes: notes || undefined },
      }),
    onSuccess: (res) => {
      setOutput(res.data.output)
      setGenerationId(res.data.generation_id)
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Generazione non riuscita'),
  })

  const saveAsPost = useMutation({
    mutationFn: async () => {
      if (generationId) {
        await api(`/admin/ai/generations/${generationId}`, { method: 'PUT', body: { accepted: true, output } })
      }
      return api('/admin/scheduled-posts', {
        method: 'POST',
        body: {
          property_id: Number(propertyId),
          channel: kind === 'post_social' ? 'facebook' : 'altro',
          content: output,
          status: 'bozza',
        },
      })
    },
    onSuccess: () => {
      toast.success('Salvato come bozza nei post programmati')
      onPostSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  function copy() {
    navigator.clipboard.writeText(output).then(async () => {
      toast.success('Copiato negli appunti')
      if (generationId) {
        await api(`/admin/ai/generations/${generationId}`, { method: 'PUT', body: { accepted: true, output } }).catch(() => undefined)
      }
    })
  }

  return (
    <section className="rt-card border-gold/50 px-5 py-4">
      <h2 className="flex items-center gap-2 font-display text-lg text-navy">
        <Sparkles size={18} className="text-gold" /> Genera con AI
      </h2>
      <div className="mt-3 grid gap-3 md:grid-cols-3">
        <Field label="Immobile">
          <select value={propertyId} onChange={(e) => setPropertyId(e.target.value)} className={inputCls}>
            <option value="">— Seleziona —</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Tipo contenuto">
          <select value={kind} onChange={(e) => setKind(e.target.value)} className={inputCls}>
            {AI_KINDS.map((k) => (
              <option key={k.value} value={k.value}>
                {k.label}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Indicazioni extra (opzionale)">
          <input value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="es. enfatizza il giardino" className={inputCls} />
        </Field>
      </div>
      <button onClick={() => generate.mutate()} disabled={!propertyId || generate.isPending} className="rt-btn-primary mt-3">
        <Sparkles size={14} /> {generate.isPending ? 'Generazione in corso…' : 'Genera'}
      </button>

      {output && (
        <div className="mt-4 space-y-3">
          <Field label="Anteprima — modifica liberamente prima di usare">
            <textarea rows={10} value={output} onChange={(e) => setOutput(e.target.value)} className={inputCls + ' font-mono text-xs leading-relaxed'} />
          </Field>
          <div className="flex flex-wrap gap-2">
            <button onClick={copy} className="rt-btn-outline">
              <Copy size={14} /> Copia negli appunti
            </button>
            <button onClick={() => saveAsPost.mutate()} disabled={saveAsPost.isPending} className="rt-btn-outline">
              Salva come bozza post
            </button>
          </div>
        </div>
      )}
    </section>
  )
}

/* ---------- Modal attività ---------- */

function ActivityModal({
  activity,
  defaultPropertyId,
  properties,
  onClose,
  onSaved,
}: {
  activity: MarketingActivity | null
  defaultPropertyId: string
  properties: Property[]
  onClose: () => void
  onSaved: () => void
}) {
  const [form, setForm] = useState({
    property_id: activity?.property_id?.toString() ?? defaultPropertyId,
    channel: activity?.channel ?? 'immobiliare_it',
    activity_type: activity?.activity_type ?? 'pubblicazione',
    title: activity?.title ?? '',
    url: activity?.url ?? '',
    published_at: activity?.published_at?.slice(0, 10) ?? '',
    views: activity?.stats?.views?.toString() ?? '',
    contacts: activity?.stats?.contacts?.toString() ?? '',
    visible_to_owner: activity?.visible_to_owner ?? true,
  })

  const save = useMutation({
    mutationFn: () => {
      const stats: Record<string, number> = {}
      if (form.views) stats.views = Number(form.views)
      if (form.contacts) stats.contacts = Number(form.contacts)
      return api(activity ? `/admin/marketing/${activity.id}` : '/admin/marketing', {
        method: activity ? 'PUT' : 'POST',
        body: {
          property_id: Number(form.property_id),
          channel: form.channel,
          activity_type: form.activity_type,
          title: form.title,
          url: form.url || null,
          published_at: form.published_at || null,
          stats,
          visible_to_owner: form.visible_to_owner,
        },
      })
    },
    onSuccess: () => {
      toast.success(activity ? 'Attività aggiornata' : 'Attività registrata')
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }))
  }

  return (
    <Modal open onClose={onClose} title={activity ? 'Modifica attività' : 'Nuova attività di promozione'}>
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          save.mutate()
        }}
        className="space-y-3"
      >
        <Field label="Immobile *">
          <select required value={form.property_id} onChange={set('property_id')} className={inputCls} disabled={!!activity}>
            <option value="">— Seleziona —</option>
            {properties.map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Canale">
            <select value={form.channel} onChange={set('channel')} className={inputCls}>
              {Object.entries(CHANNEL_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Tipo">
            <select value={form.activity_type} onChange={set('activity_type')} className={inputCls}>
              <option value="pubblicazione">Pubblicazione</option>
              <option value="campagna">Campagna</option>
              <option value="post">Post</option>
              <option value="open_house">Open house</option>
              <option value="altro">Altro</option>
            </select>
          </Field>
        </div>
        <Field label="Titolo *">
          <input required value={form.title} onChange={set('title')} className={inputCls} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Link (URL)">
            <input type="url" value={form.url} onChange={set('url')} className={inputCls} />
          </Field>
          <Field label="Data pubblicazione">
            <input type="date" value={form.published_at} onChange={set('published_at')} className={inputCls} />
          </Field>
          <Field label="Visualizzazioni">
            <input type="number" min="0" value={form.views} onChange={set('views')} className={inputCls} />
          </Field>
          <Field label="Contatti generati">
            <input type="number" min="0" value={form.contacts} onChange={set('contacts')} className={inputCls} />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={form.visible_to_owner} onChange={set('visible_to_owner')} className="accent-gold" />
          Visibile al proprietario nella pagina "Promozione"
        </label>
        <div className="flex justify-end pt-2">
          <button type="submit" disabled={save.isPending} className="rt-btn-primary">
            {save.isPending ? 'Salvataggio…' : 'Salva'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
