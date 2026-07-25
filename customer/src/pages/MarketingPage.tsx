import { ExternalLink, Eye, Megaphone, MessageSquare, MousePointerClick } from 'lucide-react'
import { useMarketing } from '@/api/queries'
import { CHANNEL_LABELS } from '@/api/types'
import EmptyState from '@/components/EmptyState'
import { PageSkeleton } from '@/components/Skeleton'
import { formatDate } from '@/lib/utils'

const ACTIVITY_LABELS: Record<string, string> = {
  pubblicazione: 'Pubblicazione',
  campagna: 'Campagna',
  post: 'Post',
  open_house: 'Open house',
  altro: 'Attività',
}

const STAT_LABELS: Record<string, { label: string; icon: typeof Eye }> = {
  views: { label: 'visualizzazioni', icon: Eye },
  contacts: { label: 'contatti', icon: MessageSquare },
  clicks: { label: 'click', icon: MousePointerClick },
}

function formatNumber(n: number): string {
  return new Intl.NumberFormat('it-IT').format(n)
}

/** "Dove viene promossa la tua casa" — trasparenza totale sulle attività. */
export default function MarketingPage() {
  const marketing = useMarketing()

  if (marketing.isLoading) return <PageSkeleton />

  const data = marketing.data ?? []

  return (
    <div className="space-y-4 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Promozione</p>
        <h1 className="mt-2 font-display text-xl text-navy">Dove viene promossa la tua casa</h1>
        <p className="mt-1 text-sm text-muted">
          Portali, social, vetrina: qui vedi ogni canale attivo e i numeri reali.
        </p>
      </header>

      {data.length === 0 ? (
        <EmptyState
          icon={Megaphone}
          title="La promozione sta per partire"
          hint="Appena la tua casa sarà pubblicata sui portali e sui social, troverai qui ogni attività con i suoi numeri."
        />
      ) : (
        <ul className="space-y-3">
          {data.map((m) => (
            <li key={m.id} className="rt-card px-4 py-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wider text-gold">
                    {CHANNEL_LABELS[m.channel] ?? m.channel}
                  </p>
                  <p className="mt-1 font-medium leading-snug text-navy">{m.title}</p>
                  <p className="mt-0.5 text-xs text-muted">
                    {ACTIVITY_LABELS[m.activity_type] ?? m.activity_type}
                    {m.published_at ? ` · ${formatDate(m.published_at)}` : ''}
                  </p>
                </div>
                {m.url && (
                  <a
                    href={m.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`Apri ${CHANNEL_LABELS[m.channel] ?? m.channel}`}
                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gold/40 text-gold hover:bg-gold/10"
                  >
                    <ExternalLink size={17} strokeWidth={1.7} />
                  </a>
                )}
              </div>

              {Object.keys(m.stats).length > 0 && (
                <div className="mt-3 flex flex-wrap gap-2">
                  {Object.entries(m.stats).map(([key, value]) => {
                    const def = STAT_LABELS[key]
                    const Icon = def?.icon ?? Eye
                    return (
                      <span
                        key={key}
                        className="inline-flex items-center gap-1.5 rounded-full bg-navy/5 px-3 py-1.5 text-xs font-medium text-navy"
                      >
                        <Icon size={13} strokeWidth={1.7} className="text-gold" />
                        {formatNumber(Number(value))} {def?.label ?? key}
                      </span>
                    )
                  })}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
