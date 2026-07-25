import { cn } from '@/lib/utils'

/** Monogramma RT (R avorio/navy + T oro sovrapposta). */
export function RTMonogram({
  size = 40,
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
      <span className="absolute text-gold" style={{ left: '0.34em', top: '0.10em' }}>
        T
      </span>
    </span>
  )
}
