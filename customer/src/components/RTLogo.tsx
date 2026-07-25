import { cn } from '@/lib/utils'

/**
 * Monogramma RT: due lettere serif sovrapposte — "R" (navy o avorio su fondo
 * scuro) e "T" oro spostata a sinistra (~0.34em) e in basso (~0.10em).
 */
export function RTMonogram({
  size = 44,
  onDark = false,
  className,
}: {
  size?: number
  onDark?: boolean
  className?: string
}) {
  return (
    <span
      className={cn('relative inline-block select-none font-display font-semibold leading-none', className)}
      style={{ fontSize: size, width: size * 1.05, height: size * 1.05 }}
      aria-hidden="true"
    >
      <span className={onDark ? 'text-ivory' : 'text-navy'}>R</span>
      <span
        className="absolute text-gold"
        style={{ left: '0.34em', top: '0.10em' }}
      >
        T
      </span>
    </span>
  )
}

/** Monogramma + wordmark "CASA LIVE" (Jost maiuscolo spaziato, LIVE in oro). */
export function RTLogo({
  size = 40,
  onDark = false,
  className,
}: {
  size?: number
  onDark?: boolean
  className?: string
}) {
  return (
    <span className={cn('inline-flex items-center gap-2.5', className)}>
      <RTMonogram size={size} onDark={onDark} />
      <span
        className={cn(
          'font-sans font-medium uppercase tracking-[0.28em]',
          onDark ? 'text-ivory' : 'text-navy',
        )}
        style={{ fontSize: size * 0.34 }}
      >
        Casa <span className="text-gold">Live</span>
      </span>
    </span>
  )
}
