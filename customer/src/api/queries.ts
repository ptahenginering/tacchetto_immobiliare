import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type {
  MarketingActivity,
  PracticeStep,
  Property,
  PropertyKpi,
  Proposal,
  TimelineEvent,
  Visit,
} from './types'

export function useProperty() {
  return useQuery({
    queryKey: ['property'],
    queryFn: () => api<{ data: Property }>('/customer/property').then((r) => r.data),
  })
}

export function usePropertyKpi() {
  return useQuery({
    queryKey: ['property-kpi'],
    queryFn: () => api<{ data: PropertyKpi }>('/customer/property/kpi').then((r) => r.data),
  })
}

export function useVisits() {
  return useQuery({
    queryKey: ['visits'],
    queryFn: () => api<{ data: Visit[] }>('/customer/visits').then((r) => r.data),
  })
}

export function useProposals() {
  return useQuery({
    queryKey: ['proposals'],
    queryFn: () => api<{ data: Proposal[] }>('/customer/proposals').then((r) => r.data),
  })
}

export function useMarketing() {
  return useQuery({
    queryKey: ['marketing'],
    queryFn: () => api<{ data: MarketingActivity[] }>('/customer/marketing').then((r) => r.data),
  })
}

export function usePracticeSteps() {
  return useQuery({
    queryKey: ['practice-steps'],
    queryFn: () =>
      api<{ data: PracticeStep[]; meta: { total: number; completed: number; progress_pct: number } }>(
        '/customer/practice-steps',
      ),
  })
}

export function useTimeline() {
  return useQuery({
    queryKey: ['timeline'],
    queryFn: () => api<{ data: TimelineEvent[] }>('/customer/timeline').then((r) => r.data),
  })
}
