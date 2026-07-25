// Tipi delle risposte API area cliente

export interface Property {
  id: number
  title: string
  address: string | null
  city: string | null
  province: string | null
  type: string
  surface_sqm: number | null
  rooms: number | null
  price: string | null
  status: 'valutazione' | 'in_vendita' | 'in_trattativa' | 'venduto' | 'sospeso'
  cover_image_url: string | null
  description: string | null
  mandate_start: string | null
  mandate_end: string | null
  created_at: string
  images: { url: string; sort_order: number }[]
}

export interface PropertyKpi {
  appointments_30d: number
  appointments_upcoming: number
  visits_30d: number
  proposals_active: number
  interest: {
    score: number
    trend: 'up' | 'down' | 'flat'
    explanation: string
  }
  visits_series: { week_start: string; visits: number }[]
}

export interface Visit {
  id: number
  visited_at: string
  visitor_label: string
  qualified: boolean
  feedback_text: string | null
  feedback_rating: number | null
}

export interface Proposal {
  id: number
  amount: string
  status: 'ricevuta' | 'in_trattativa' | 'accettata' | 'rifiutata' | 'ritirata'
  received_at: string
  created_at: string
}

export interface MarketingActivity {
  id: number
  channel: string
  activity_type: string
  title: string
  url: string | null
  published_at: string | null
  stats: Record<string, number>
}

export interface PracticeStep {
  id: number
  step_key: string
  label: string
  status: 'da_fare' | 'in_corso' | 'completato'
  sort_order: number
  completed_at: string | null
}

export interface TimelineEvent {
  event_type: 'visita' | 'proposta' | 'promozione' | 'pratica' | 'appuntamento'
  id: number
  happened_at: string
  title: string
  detail: string
  rating: number | null
}

export const STATUS_LABELS: Record<Property['status'], string> = {
  valutazione: 'In valutazione',
  in_vendita: 'In vendita',
  in_trattativa: 'In trattativa',
  venduto: 'Venduto',
  sospeso: 'Sospeso',
}

export const PROPOSAL_STATUS_LABELS: Record<Proposal['status'], string> = {
  ricevuta: 'Ricevuta',
  in_trattativa: 'In trattativa',
  accettata: 'Accettata',
  rifiutata: 'Rifiutata',
  ritirata: 'Ritirata',
}

export const CHANNEL_LABELS: Record<string, string> = {
  immobiliare_it: 'Immobiliare.it',
  idealista: 'Idealista',
  facebook: 'Facebook',
  instagram: 'Instagram',
  linkedin: 'LinkedIn',
  vetrina: 'Vetrina agenzia',
  portale_tecnocasa: 'Portale Tecnocasa',
  altro: 'Altro',
}

/** URL immagine servita dal backend */
export function fileUrl(path: string | null): string | null {
  if (!path) return null
  if (path.startsWith('http')) return path
  const base = import.meta.env.VITE_API_BASE_URL || '/api'
  return `${base}/files/${path}`
}
