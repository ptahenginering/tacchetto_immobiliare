import { cn } from '@/lib/utils'

/** Skeleton loader brandizzato (shimmer su avorio). */
export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-rt bg-navy/10', className)} />
}

export function PageSkeleton() {
  return (
    <div className="space-y-4" aria-busy="true" aria-label="Caricamento">
      <Skeleton className="h-44 w-full" />
      <div className="grid grid-cols-2 gap-3">
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
      </div>
      <Skeleton className="h-52 w-full" />
    </div>
  )
}
