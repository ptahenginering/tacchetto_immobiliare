import { HandCoins } from 'lucide-react'
import { useProposals } from '@/api/queries'
import { PROPOSAL_STATUS_LABELS, type Proposal } from '@/api/types'
import EmptyState from '@/components/EmptyState'
import { PageSkeleton } from '@/components/Skeleton'
import { cn, formatDate, formatEuro } from '@/lib/utils'

const STATUS_STYLES: Record<Proposal['status'], string> = {
  ricevuta: 'bg-info/10 text-info',
  in_trattativa: 'bg-warning/10 text-warning',
  accettata: 'bg-success/10 text-success',
  rifiutata: 'bg-error/10 text-error',
  ritirata: 'bg-navy/10 text-muted',
}

/** Proposte d'acquisto ricevute. */
export default function ProposalsPage() {
  const proposals = useProposals()

  if (proposals.isLoading) return <PageSkeleton />

  const data = proposals.data ?? []

  return (
    <div className="space-y-4 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Proposte</p>
        <h1 className="mt-2 font-display text-xl text-navy">Le offerte per la tua casa</h1>
      </header>

      {data.length === 0 ? (
        <EmptyState
          icon={HandCoins}
          title="Nessuna proposta, per ora"
          hint="Quando arriverà un'offerta la troverai qui, con importo e stato della trattativa."
        />
      ) : (
        <ul className="space-y-3">
          {data.map((p) => (
            <li key={p.id} className="rt-card px-4 py-4">
              <div className="flex items-center justify-between gap-3">
                <p className="font-display text-2xl text-navy">{formatEuro(p.amount)}</p>
                <span className={cn('rounded-full px-3 py-1 text-xs font-semibold', STATUS_STYLES[p.status])}>
                  {PROPOSAL_STATUS_LABELS[p.status]}
                </span>
              </div>
              <p className="mt-1.5 text-xs text-muted">Ricevuta il {formatDate(p.received_at)}</p>
            </li>
          ))}
        </ul>
      )}

      <div className="rounded-rt border border-gold/40 bg-ivory-soft px-4 py-3.5 text-sm leading-relaxed text-navy/90">
        <span className="font-medium text-gold">ⓘ</span> Ogni proposta viene discussa personalmente con Roberto:
        nessuna decisione senza di te.
      </div>
    </div>
  )
}
