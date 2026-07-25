import type { ReactNode } from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

/** Badge di stato colorato. */
export function Badge({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', className)}>
      {children}
    </span>
  )
}

/** Modal semplice (overlay + card). */
export function Modal({
  open,
  onClose,
  title,
  children,
  wide = false,
}: {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  wide?: boolean
}) {
  if (!open) return null
  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-deep/50 p-4 pt-12" onClick={onClose}>
      <div
        className={cn('rt-card w-full bg-white p-5 shadow-xl', wide ? 'max-w-2xl' : 'max-w-md')}
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={title}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="font-display text-lg text-navy">{title}</h2>
          <button onClick={onClose} aria-label="Chiudi" className="text-muted hover:text-navy">
            <X size={19} />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

/** Input/label/select/textarea con stile coerente. */
export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-medium text-navy">{label}</span>
      {children}
    </label>
  )
}

export const inputCls =
  'w-full rounded-lg border border-navy/15 bg-white px-3 py-2 text-sm focus:border-gold focus:outline-none disabled:bg-ivory-soft'

/** Paginazione server-side. */
export function Pagination({
  page,
  totalPages,
  onChange,
}: {
  page: number
  totalPages: number
  onChange: (p: number) => void
}) {
  if (totalPages <= 1) return null
  return (
    <div className="mt-4 flex items-center justify-center gap-3 text-sm">
      <button
        disabled={page <= 1}
        onClick={() => onChange(page - 1)}
        className="rounded-full border border-navy/15 px-4 py-1.5 disabled:opacity-40"
      >
        ← Prec
      </button>
      <span className="text-muted">
        Pagina {page} di {totalPages}
      </span>
      <button
        disabled={page >= totalPages}
        onClick={() => onChange(page + 1)}
        className="rounded-full border border-navy/15 px-4 py-1.5 disabled:opacity-40"
      >
        Succ →
      </button>
    </div>
  )
}

/** Toggle "occhio" per visible_to_owner. */
export function VisibilityToggle({
  visible,
  onToggle,
  title = 'Visibile al proprietario',
}: {
  visible: boolean
  onToggle: () => void
  title?: string
}) {
  return (
    <button
      onClick={onToggle}
      title={visible ? title : 'Nascosto al proprietario'}
      aria-label={visible ? title : 'Nascosto al proprietario'}
      className={cn(
        'flex h-8 w-8 items-center justify-center rounded-full transition-colors',
        visible ? 'bg-gold/15 text-gold' : 'bg-navy/5 text-muted',
      )}
    >
      {visible ? <EyeIcon /> : <EyeOffIcon />}
    </button>
  )
}

function EyeIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7">
      <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  )
}

function EyeOffIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7">
      <path d="m3 3 18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.9M6.6 6.6A16.6 16.6 0 0 0 2 12s3.5 7 10 7a9.9 9.9 0 0 0 4.4-1" />
    </svg>
  )
}
