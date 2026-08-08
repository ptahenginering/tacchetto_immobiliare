import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import { useSelectedProperty } from '@/hooks/useSelectedProperty'
import type {
  MarketingActivity,
  PracticeStep,
  Property,
  PropertyKpi,
  Proposal,
  TimelineEvent,
  Visit,
} from './types'

/**
 * Tutte le query sono scoped sull'immobile selezionato (multiproprietà):
 * il param ?property_id= viene aggiunto quando il proprietario ha scelto
 * un immobile; il backend valida sempre l'appartenenza.
 */
function usePropertyScope() {
  const { selectedId, propertyParam } = useSelectedProperty()
  return { key: selectedId, qs: propertyParam ? `?${propertyParam}` : '' }
}

export function useProperty() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['property', key],
    queryFn: () => api<{ data: Property }>(`/customer/property${qs}`).then((r) => r.data),
  })
}

export function usePropertyKpi() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['property-kpi', key],
    queryFn: () => api<{ data: PropertyKpi }>(`/customer/property/kpi${qs}`).then((r) => r.data),
  })
}

export function useVisits() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['visits', key],
    queryFn: () => api<{ data: Visit[] }>(`/customer/visits${qs}`).then((r) => r.data),
  })
}

export function useProposals() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['proposals', key],
    queryFn: () => api<{ data: Proposal[] }>(`/customer/proposals${qs}`).then((r) => r.data),
  })
}

export function useMarketing() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['marketing', key],
    queryFn: () => api<{ data: MarketingActivity[] }>(`/customer/marketing${qs}`).then((r) => r.data),
  })
}

export function usePracticeSteps() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['practice-steps', key],
    queryFn: () =>
      api<{ data: PracticeStep[]; meta: { total: number; completed: number; progress_pct: number } }>(
        `/customer/practice-steps${qs}`,
      ),
  })
}

export function useTimeline() {
  const { key, qs } = usePropertyScope()
  return useQuery({
    queryKey: ['timeline', key],
    queryFn: () => api<{ data: TimelineEvent[] }>(`/customer/timeline${qs}`).then((r) => r.data),
  })
}
