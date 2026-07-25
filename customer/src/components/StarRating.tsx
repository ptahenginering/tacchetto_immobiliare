import { Star } from 'lucide-react'

/** Rating a stelle oro (sola lettura). */
export default function StarRating({ value, size = 15 }: { value: number; size?: number }) {
  return (
    <span className="inline-flex gap-0.5" aria-label={`${value} stelle su 5`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <Star
          key={i}
          size={size}
          strokeWidth={1.4}
          className={i <= value ? 'fill-gold text-gold' : 'text-gold/30'}
        />
      ))}
    </span>
  )
}
