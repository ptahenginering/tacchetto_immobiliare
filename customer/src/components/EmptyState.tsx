import type { LucideIcon } from 'lucide-react'

/** Stato vuoto curato: icona line-art oro + testo guida. */
export default function EmptyState({
  icon: Icon,
  title,
  hint,
}: {
  icon: LucideIcon
  title: string
  hint?: string
}) {
  return (
    <div className="rt-card flex flex-col items-center px-6 py-12 text-center animate-fade-in">
      <div className="flex h-16 w-16 items-center justify-center rounded-full border border-gold/40 bg-ivory-soft">
        <Icon size={28} strokeWidth={1.3} className="text-gold" />
      </div>
      <p className="mt-4 font-display text-lg text-navy">{title}</p>
      {hint && <p className="mt-1.5 max-w-xs text-sm text-muted">{hint}</p>}
    </div>
  )
}
