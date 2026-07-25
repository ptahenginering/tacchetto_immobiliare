import { Check, FileText, Loader2 } from 'lucide-react'
import { usePracticeSteps } from '@/api/queries'
import EmptyState from '@/components/EmptyState'
import { PageSkeleton } from '@/components/Skeleton'
import { cn, formatDate } from '@/lib/utils'

/** Pratiche & burocrazia — stepper verticale con avanzamento. */
export default function PracticeStepsPage() {
  const query = usePracticeSteps()

  if (query.isLoading) return <PageSkeleton />

  const steps = query.data?.data ?? []
  const meta = query.data?.meta

  return (
    <div className="space-y-5 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Pratiche</p>
        <h1 className="mt-2 font-display text-xl text-navy">La burocrazia, senza pensieri</h1>
        <p className="mt-1 text-sm text-muted">Ci occupiamo noi di ogni passaggio. Tu guardi i progressi.</p>
      </header>

      {steps.length === 0 ? (
        <EmptyState
          icon={FileText}
          title="Nessuna pratica avviata"
          hint="Gli step burocratici della vendita appariranno qui appena partiranno."
        />
      ) : (
        <>
          {/* Barra avanzamento */}
          <div className="rt-card px-4 py-4">
            <div className="flex items-end justify-between">
              <p className="text-sm font-medium text-navy">Avanzamento pratiche</p>
              <p className="font-display text-2xl text-gold">{meta?.progress_pct ?? 0}%</p>
            </div>
            <div className="mt-2.5 h-2 overflow-hidden rounded-full bg-navy/10" role="progressbar" aria-valuenow={meta?.progress_pct ?? 0} aria-valuemin={0} aria-valuemax={100}>
              <div
                className="h-full rounded-full transition-all"
                style={{
                  width: `${meta?.progress_pct ?? 0}%`,
                  background: 'linear-gradient(135deg, #D3AE66, #B98F45)',
                }}
              />
            </div>
            <p className="mt-1.5 text-xs text-muted">
              {meta?.completed ?? 0} su {meta?.total ?? 0} passaggi completati
            </p>
          </div>

          {/* Stepper verticale */}
          <ol className="relative space-y-0">
            {steps.map((s, i) => {
              const isDone = s.status === 'completato'
              const inProgress = s.status === 'in_corso'
              const isLast = i === steps.length - 1
              return (
                <li key={s.id} className="relative flex gap-4 pb-6">
                  {!isLast && (
                    <span
                      className={cn('absolute left-[15px] top-9 h-[calc(100%-2rem)] w-0.5', isDone ? 'bg-gold' : 'bg-navy/10')}
                      aria-hidden="true"
                    />
                  )}
                  <span
                    className={cn(
                      'z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2',
                      isDone
                        ? 'border-gold bg-gold text-navy-deep'
                        : inProgress
                          ? 'border-gold bg-ivory text-gold'
                          : 'border-navy/20 bg-ivory text-navy/30',
                    )}
                  >
                    {isDone ? (
                      <Check size={15} strokeWidth={2.4} />
                    ) : inProgress ? (
                      <Loader2 size={14} strokeWidth={2} className="animate-spin" />
                    ) : (
                      <span className="text-xs font-semibold">{i + 1}</span>
                    )}
                  </span>
                  <div className="pt-1">
                    <p className={cn('text-sm font-medium leading-snug', isDone ? 'text-navy' : inProgress ? 'text-navy' : 'text-muted')}>
                      {s.label}
                    </p>
                    <p className="mt-0.5 text-xs text-muted">
                      {isDone
                        ? `Completato${s.completed_at ? ` il ${formatDate(s.completed_at)}` : ''}`
                        : inProgress
                          ? 'In corso'
                          : 'Da fare'}
                    </p>
                  </div>
                </li>
              )
            })}
          </ol>
        </>
      )}
    </div>
  )
}
