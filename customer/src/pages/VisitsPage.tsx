import { CalendarCheck, BadgeCheck } from 'lucide-react'
import { useVisits } from '@/api/queries'
import EmptyState from '@/components/EmptyState'
import { PageSkeleton } from '@/components/Skeleton'
import StarRating from '@/components/StarRating'
import { formatDate } from '@/lib/utils'

/** Visite & riscontri — lista cronologica. */
export default function VisitsPage() {
  const visits = useVisits()

  if (visits.isLoading) return <PageSkeleton />

  const data = visits.data ?? []

  return (
    <div className="space-y-4 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Visite &amp; riscontri</p>
        <h1 className="mt-2 font-display text-xl text-navy">Chi ha visto la tua casa</h1>
        <p className="mt-1 text-sm text-muted">
          Ogni visita e ogni parere, senza filtri. La trasparenza è la base del nostro lavoro.
        </p>
      </header>

      {data.length === 0 ? (
        <EmptyState
          icon={CalendarCheck}
          title="Le prime visite appariranno qui"
          hint="Appena qualcuno visiterà la tua casa troverai qui data, profilo del visitatore e il suo riscontro."
        />
      ) : (
        <ul className="space-y-3">
          {data.map((v) => (
            <li key={v.id} className="rt-card px-4 py-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wider text-muted">{formatDate(v.visited_at)}</p>
                  <p className="mt-0.5 font-medium text-navy">{v.visitor_label}</p>
                </div>
                {v.qualified && (
                  <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-success/10 px-2.5 py-1 text-[11px] font-medium text-success">
                    <BadgeCheck size={13} strokeWidth={1.8} /> Qualificata
                  </span>
                )}
              </div>
              {v.feedback_rating != null && (
                <div className="mt-2.5">
                  <StarRating value={v.feedback_rating} />
                </div>
              )}
              {v.feedback_text && (
                <p className="mt-2 border-l-2 border-gold/50 pl-3 text-sm italic leading-relaxed text-navy/90">
                  “{v.feedback_text}”
                </p>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
