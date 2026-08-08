import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Home, Plus } from 'lucide-react'
import { api, fileUrl } from '@/api/client'
import {
  PROPERTY_STATUS_COLORS,
  PROPERTY_STATUS_LABELS,
  PROPERTY_TYPE_LABELS,
  type Paginated,
  type Property,
} from '@/api/types'
import { Badge, Field, inputCls, Modal, Pagination } from '@/components/ui'
import { formatEuro } from '@/lib/utils'

export default function PropertiesList() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [showCreate, setShowCreate] = useState(false)

  const qs = new URLSearchParams({ page: String(page), per_page: '12' })
  if (status) qs.set('status', status)
  if (search) qs.set('search', search)

  const properties = useQuery({
    queryKey: ['properties', qs.toString()],
    queryFn: () => api<Paginated<Property>>(`/admin/properties?${qs}`),
  })

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl text-navy">Immobili</h1>
        <div className="flex flex-wrap gap-2">
          <select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value)
              setPage(1)
            }}
            className={inputCls + ' w-auto'}
          >
            <option value="">Tutti gli stati</option>
            {Object.entries(PROPERTY_STATUS_LABELS).map(([k, v]) => (
              <option key={k} value={k}>
                {v}
              </option>
            ))}
          </select>
          <input
            placeholder="Cerca titolo, indirizzo…"
            className={inputCls + ' w-48'}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                setSearch((e.target as HTMLInputElement).value)
                setPage(1)
              }
            }}
          />
          <button onClick={() => setShowCreate(true)} className="rt-btn-primary">
            <Plus size={15} /> Nuovo immobile
          </button>
        </div>
      </div>

      {properties.isLoading ? (
        <p className="py-10 text-center text-muted">Caricamento…</p>
      ) : (properties.data?.data ?? []).length === 0 ? (
        <div className="rt-card px-6 py-14 text-center">
          <Home size={36} strokeWidth={1.2} className="mx-auto text-gold" />
          <p className="mt-3 font-display text-lg text-navy">Nessun immobile</p>
          <p className="mt-1 text-sm text-muted">Crea il primo incarico o converti un lead.</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {(properties.data?.data ?? []).map((p) => {
            const cover = fileUrl(p.cover_image_url)
            return (
              <Link key={p.id} to={`/immobili/${p.id}`} className="rt-card group overflow-hidden transition-shadow hover:shadow-rt">
                <div className="relative h-40 bg-navy">
                  {cover ? (
                    <img src={cover} alt={p.title} className="h-full w-full object-cover transition-transform group-hover:scale-[1.02]" />
                  ) : (
                    <div className="flex h-full items-center justify-center">
                      <Home size={34} strokeWidth={1} className="text-gold/60" />
                    </div>
                  )}
                  <Badge className={`absolute right-2.5 top-2.5 ${PROPERTY_STATUS_COLORS[p.status]} bg-white/95`}>
                    {PROPERTY_STATUS_LABELS[p.status]}
                  </Badge>
                </div>
                <div className="px-4 py-3">
                  <p className="truncate font-medium text-navy">{p.title}</p>
                  <p className="truncate text-xs text-muted">
                    {[p.address, p.city].filter(Boolean).join(', ') || PROPERTY_TYPE_LABELS[p.type]}
                  </p>
                  <div className="mt-2 flex items-center justify-between text-xs text-muted">
                    <span>
                      {p.visits_count ?? 0} visite · {p.active_proposals ?? 0} proposte
                    </span>
                    {p.price && <span className="font-semibold text-navy">{formatEuro(p.price)}</span>}
                  </div>
                </div>
              </Link>
            )
          })}
        </div>
      )}

      <Pagination page={page} totalPages={properties.data?.meta.total_pages ?? 1} onChange={setPage} />

      {showCreate && <CreatePropertyModal onClose={() => setShowCreate(false)} />}
    </div>
  )
}

function CreatePropertyModal({ onClose }: { onClose: () => void }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    title: '',
    address: '',
    city: '',
    province: 'TV',
    type: 'appartamento',
    surface_sqm: '',
    rooms: '',
    price: '',
    inherited: false,
    owner_user_id: '',
  })

  // Proprietari esistenti: collegando lo stesso proprietario a più immobili
  // si gestisce la multiproprietà (es. più unità nella stessa palazzina).
  const owners = useQuery({
    queryKey: ['owner-users'],
    queryFn: () =>
      api<{ data: { id: number; first_name: string; last_name: string; email: string }[] }>(
        '/admin/users?role=owner',
      ).then((r) => r.data),
  })

  const create = useMutation({
    mutationFn: () =>
      api<{ data: Property }>('/admin/properties', {
        method: 'POST',
        body: {
          ...form,
          surface_sqm: form.surface_sqm || undefined,
          rooms: form.rooms || undefined,
          price: form.price || undefined,
          owner_user_id: form.owner_user_id ? Number(form.owner_user_id) : undefined,
        },
      }),
    onSuccess: (res) => {
      toast.success('Immobile creato con checklist burocrazia')
      queryClient.invalidateQueries({ queryKey: ['properties'] })
      navigate(`/immobili/${res.data.id}`)
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value }))
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    create.mutate()
  }

  return (
    <Modal open onClose={onClose} title="Nuovo immobile" wide>
      <form onSubmit={onSubmit} className="space-y-3">
        <div className="grid gap-3 md:grid-cols-2">
          <Field label="Titolo *">
            <input required value={form.title} onChange={set('title')} className={inputCls} />
          </Field>
          <Field label="Tipologia">
            <select value={form.type} onChange={set('type')} className={inputCls}>
              {Object.entries(PROPERTY_TYPE_LABELS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Indirizzo">
            <input value={form.address} onChange={set('address')} className={inputCls} />
          </Field>
          <Field label="Città">
            <input value={form.city} onChange={set('city')} className={inputCls} />
          </Field>
          <div className="grid grid-cols-3 gap-3">
            <Field label="Prov.">
              <input value={form.province} onChange={set('province')} maxLength={4} className={inputCls} />
            </Field>
            <Field label="Mq">
              <input type="number" min="0" value={form.surface_sqm} onChange={set('surface_sqm')} className={inputCls} />
            </Field>
            <Field label="Locali">
              <input type="number" min="0" value={form.rooms} onChange={set('rooms')} className={inputCls} />
            </Field>
          </div>
          <Field label="Prezzo richiesto (€)">
            <input type="number" min="0" step="1000" value={form.price} onChange={set('price')} className={inputCls} />
          </Field>
          <Field label="Proprietario (opzionale)">
            <select value={form.owner_user_id} onChange={set('owner_user_id')} className={inputCls}>
              <option value="">— Da collegare in seguito —</option>
              {(owners.data ?? []).map((o) => (
                <option key={o.id} value={o.id}>
                  {o.first_name} {o.last_name} · {o.email}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm text-navy">
          <input type="checkbox" checked={form.inherited} onChange={set('inherited')} className="accent-gold" />
          Immobile ereditato (aggiunge gli step di successione alla checklist)
        </label>
        <div className="flex justify-end gap-3 pt-2">
          <button type="button" onClick={onClose} className="rt-btn-outline">
            Annulla
          </button>
          <button type="submit" disabled={create.isPending} className="rt-btn-primary">
            {create.isPending ? 'Creazione…' : 'Crea immobile'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
