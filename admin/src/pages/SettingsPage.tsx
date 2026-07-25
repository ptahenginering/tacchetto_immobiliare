import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { CheckCircle2, Plus, Send, XCircle } from 'lucide-react'
import { api } from '@/api/client'
import { useAuthStore } from '@/store/auth'
import { Badge, Field, inputCls, Modal } from '@/components/ui'
import { formatDateTime } from '@/lib/utils'

interface Health {
  database: boolean
  brevo: boolean
  smtp_fallback: boolean
  email_delivery: boolean
  anthropic: boolean
  anthropic_model: string | null
  app_env: string
}

interface Agency {
  id: number
  name: string
  email: string | null
  phone: string | null
}

interface StaffUser {
  id: number
  role: 'admin' | 'agent' | 'owner'
  first_name: string
  last_name: string
  email: string
  phone: string | null
  is_active: boolean
  last_login_at: string | null
}

export default function SettingsPage() {
  const me = useAuthStore((s) => s.user)
  const queryClient = useQueryClient()
  const [showNewUser, setShowNewUser] = useState(false)
  const [testTo, setTestTo] = useState('')

  const health = useQuery({
    queryKey: ['health'],
    queryFn: () => api<{ data: Health }>('/admin/system/health').then((r) => r.data),
  })
  const settings = useQuery({
    queryKey: ['settings'],
    queryFn: () => api<{ data: Agency }>('/admin/settings').then((r) => r.data),
  })
  const users = useQuery({
    queryKey: ['staff-users'],
    queryFn: () => api<{ data: StaffUser[] }>('/admin/users').then((r) => r.data),
  })

  const testEmail = useMutation({
    mutationFn: () =>
      api<{ data: { sent: boolean; message: string } }>('/admin/system/test-email', {
        method: 'POST',
        body: { to: testTo },
      }),
    onSuccess: (res) => (res.data.sent ? toast.success(res.data.message) : toast.error(res.data.message)),
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  async function toggleUser(u: StaffUser) {
    try {
      await api(`/admin/users/${u.id}`, { method: 'PUT', body: { is_active: !u.is_active } })
      queryClient.invalidateQueries({ queryKey: ['staff-users'] })
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Errore')
    }
  }

  const staff = (users.data ?? []).filter((u) => u.role !== 'owner')
  const owners = (users.data ?? []).filter((u) => u.role === 'owner')

  return (
    <div className="max-w-4xl space-y-6">
      <h1 className="font-display text-2xl text-navy">Impostazioni</h1>

      {/* Stato integrazioni */}
      <section className="rt-card px-5 py-4">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">Stato integrazioni</h2>
        <div className="mt-3 grid gap-2 sm:grid-cols-2">
          <IntegrationRow label="Database PostgreSQL" ok={health.data?.database ?? false} />
          <IntegrationRow
            label="Email — Brevo"
            ok={health.data?.brevo ?? false}
            hint={!health.data?.brevo ? 'BREVO_API_KEY mancante' : undefined}
          />
          <IntegrationRow
            label="Email — SMTP fallback"
            ok={health.data?.smtp_fallback ?? false}
            hint={!health.data?.smtp_fallback ? 'SMTP_* non configurato' : undefined}
          />
          <IntegrationRow
            label={`AI Anthropic${health.data?.anthropic_model ? ` (${health.data.anthropic_model})` : ''}`}
            ok={health.data?.anthropic ?? false}
            hint={!health.data?.anthropic ? 'ANTHROPIC_API_KEY mancante' : undefined}
          />
        </div>
      </section>

      {/* Dati agenzia */}
      <AgencyForm agency={settings.data} onSaved={() => queryClient.invalidateQueries({ queryKey: ['settings'] })} />

      {/* Test email */}
      <section className="rt-card px-5 py-4">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">Test invio email</h2>
        <form
          onSubmit={(e: FormEvent) => {
            e.preventDefault()
            testEmail.mutate()
          }}
          className="mt-3 flex flex-wrap items-end gap-2"
        >
          <div className="min-w-64 flex-1">
            <Field label="Invia una email di prova a">
              <input type="email" required value={testTo} onChange={(e) => setTestTo(e.target.value)} className={inputCls} placeholder="tu@esempio.it" />
            </Field>
          </div>
          <button type="submit" disabled={testEmail.isPending} className="rt-btn-primary">
            <Send size={14} /> {testEmail.isPending ? 'Invio…' : 'Invia test'}
          </button>
        </form>
      </section>

      {/* Utenti staff */}
      <section className="rt-card px-5 py-4">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">Utenti staff</h2>
          {me?.role === 'admin' && (
            <button onClick={() => setShowNewUser(true)} className="rt-btn-outline">
              <Plus size={14} /> Nuovo utente
            </button>
          )}
        </div>
        <div className="mt-3 divide-y divide-navy/5">
          {staff.map((u) => (
            <div key={u.id} className="flex items-center justify-between gap-3 py-3">
              <div>
                <p className="text-sm font-medium text-navy">
                  {u.first_name} {u.last_name}{' '}
                  <Badge className={u.role === 'admin' ? 'bg-gold/15 text-gold' : 'bg-info/10 text-info'}>{u.role}</Badge>
                </p>
                <p className="text-xs text-muted">
                  {u.email} · ultimo accesso: {u.last_login_at ? formatDateTime(u.last_login_at) : 'mai'}
                </p>
              </div>
              {me?.role === 'admin' && me.id !== u.id && (
                <button
                  onClick={() => toggleUser(u)}
                  className={`rounded-full px-3 py-1 text-xs font-medium ${u.is_active ? 'bg-success/10 text-success' : 'bg-navy/10 text-muted'}`}
                >
                  {u.is_active ? 'Attivo' : 'Disattivato'}
                </button>
              )}
            </div>
          ))}
        </div>
        <p className="mt-3 border-t border-navy/5 pt-3 text-xs text-muted">
          Proprietari registrati (accesso via magic link): <strong className="text-navy">{owners.length}</strong>
        </p>
      </section>

      {showNewUser && (
        <NewUserModal
          onClose={() => setShowNewUser(false)}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['staff-users'] })
            setShowNewUser(false)
          }}
        />
      )}
    </div>
  )
}

function IntegrationRow({ label, ok, hint }: { label: string; ok: boolean; hint?: string }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-navy/10 px-3.5 py-2.5">
      <span className="text-sm text-navy">{label}</span>
      <span className="flex items-center gap-1.5 text-xs font-medium">
        {ok ? (
          <>
            <CheckCircle2 size={15} className="text-success" /> <span className="text-success">Configurata</span>
          </>
        ) : (
          <>
            <XCircle size={15} className="text-error" />
            <span className="text-error">{hint ?? 'Mancante'}</span>
          </>
        )}
      </span>
    </div>
  )
}

function AgencyForm({ agency, onSaved }: { agency?: Agency; onSaved: () => void }) {
  const [form, setForm] = useState({ name: '', email: '', phone: '' })

  useEffect(() => {
    if (agency) setForm({ name: agency.name ?? '', email: agency.email ?? '', phone: agency.phone ?? '' })
  }, [agency])

  const save = useMutation({
    mutationFn: () => api('/admin/settings', { method: 'PUT', body: form }),
    onSuccess: () => {
      toast.success('Dati agenzia aggiornati')
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  return (
    <section className="rt-card px-5 py-4">
      <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">Dati agenzia</h2>
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          save.mutate()
        }}
        className="mt-3 grid gap-3 sm:grid-cols-3"
      >
        <Field label="Nome">
          <input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} className={inputCls} />
        </Field>
        <Field label="Email">
          <input type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} className={inputCls} />
        </Field>
        <Field label="Telefono">
          <input value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} className={inputCls} />
        </Field>
        <div className="sm:col-span-3">
          <button type="submit" disabled={save.isPending} className="rt-btn-primary">
            {save.isPending ? 'Salvataggio…' : 'Salva dati agenzia'}
          </button>
        </div>
      </form>
    </section>
  )
}

function NewUserModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: 'agent',
    password: '',
  })

  const create = useMutation({
    mutationFn: () => api('/admin/users', { method: 'POST', body: form }),
    onSuccess: () => {
      toast.success('Utente creato')
      onSaved()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Errore'),
  })

  function set(key: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
      setForm((f) => ({ ...f, [key]: e.target.value }))
  }

  return (
    <Modal open onClose={onClose} title="Nuovo utente staff">
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          create.mutate()
        }}
        className="space-y-3"
      >
        <div className="grid grid-cols-2 gap-3">
          <Field label="Nome *">
            <input required value={form.first_name} onChange={set('first_name')} className={inputCls} />
          </Field>
          <Field label="Cognome *">
            <input required value={form.last_name} onChange={set('last_name')} className={inputCls} />
          </Field>
          <Field label="Email *">
            <input required type="email" value={form.email} onChange={set('email')} className={inputCls} />
          </Field>
          <Field label="Telefono">
            <input value={form.phone} onChange={set('phone')} className={inputCls} />
          </Field>
          <Field label="Ruolo">
            <select value={form.role} onChange={set('role')} className={inputCls}>
              <option value="agent">Agente</option>
              <option value="admin">Amministratore</option>
            </select>
          </Field>
          <Field label="Password * (min 8)">
            <input required type="password" minLength={8} value={form.password} onChange={set('password')} className={inputCls} />
          </Field>
        </div>
        <div className="flex justify-end pt-2">
          <button type="submit" disabled={create.isPending} className="rt-btn-primary">
            {create.isPending ? 'Creazione…' : 'Crea utente'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
