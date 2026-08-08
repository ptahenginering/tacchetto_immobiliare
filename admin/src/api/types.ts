// Tipi e label condivise del gestionale

export interface Paginated<T> {
  data: T[]
  meta: { page: number; per_page: number; total: number; total_pages: number }
}

export interface Lead {
  id: number
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  request_type: 'vendere' | 'ereditato' | 'perizia' | 'altro'
  message: string | null
  source: 'sito' | 'qr' | 'social' | 'referral' | 'altro'
  status: 'nuovo' | 'contattato' | 'appuntamento' | 'incarico' | 'perso'
  assigned_to: number | null
  notes: string | null
  converted_property_id: number | null
  lost_reason: string | null
  created_at: string
  updated_at: string
  assigned_first_name?: string | null
  assigned_last_name?: string | null
}

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
  owner_first_name?: string | null
  owner_last_name?: string | null
  owner_id?: number
  owner_email?: string | null
  owner_phone?: string | null
  visits_count?: number
  active_proposals?: number
  images?: PropertyImage[]
  practice_steps?: PracticeStep[]
}

export interface PropertyImage {
  id: number
  url: string
  sort_order: number
  is_ai_generated: boolean
}

export interface PracticeStep {
  id: number
  step_key: string
  label: string
  status: 'da_fare' | 'in_corso' | 'completato'
  sort_order: number
  completed_at: string | null
  visible_to_owner: boolean
}

export interface Appointment {
  id: number
  property_id: number | null
  lead_id: number | null
  type: 'valutazione' | 'visita' | 'firma' | 'altro'
  starts_at: string
  ends_at: string | null
  contact_name: string | null
  contact_phone: string | null
  status: 'programmato' | 'svolto' | 'annullato'
  notes: string | null
  property_title?: string | null
  lead_first_name?: string | null
  lead_last_name?: string | null
}

export interface Visit {
  id: number
  property_id: number
  appointment_id: number | null
  visited_at: string
  visitor_label: string
  qualified: boolean
  feedback_text: string | null
  feedback_rating: number | null
  visible_to_owner: boolean
  property_title?: string
}

export interface Proposal {
  id: number
  property_id: number
  amount: string
  status: 'ricevuta' | 'in_trattativa' | 'accettata' | 'rifiutata' | 'ritirata'
  received_at: string
  notes: string | null
  visible_to_owner: boolean
  property_title?: string
}

export interface MarketingActivity {
  id: number
  property_id: number
  channel: string
  activity_type: string
  title: string
  url: string | null
  published_at: string | null
  stats: Record<string, number>
  visible_to_owner: boolean
  property_title?: string
}

export const LEAD_STATUS_LABELS: Record<Lead['status'], string> = {
  nuovo: 'Nuovo',
  contattato: 'Contattato',
  appuntamento: 'Appuntamento',
  incarico: 'Incarico',
  perso: 'Perso',
}

export const LEAD_STATUS_COLORS: Record<Lead['status'], string> = {
  nuovo: 'bg-info/10 text-info',
  contattato: 'bg-warning/10 text-warning',
  appuntamento: 'bg-gold/15 text-gold',
  incarico: 'bg-success/10 text-success',
  perso: 'bg-navy/10 text-muted',
}

export const SOURCE_LABELS: Record<Lead['source'], string> = {
  sito: 'Sito',
  qr: 'QR code',
  social: 'Social',
  referral: 'Passaparola',
  altro: 'Altro',
}

export const REQUEST_TYPE_LABELS: Record<Lead['request_type'], string> = {
  vendere: 'Vuole vendere',
  ereditato: 'Immobile ereditato',
  perizia: 'Perizia',
  altro: 'Altro',
}

export const PROPERTY_STATUS_LABELS: Record<Property['status'], string> = {
  valutazione: 'In valutazione',
  in_vendita: 'In vendita',
  in_trattativa: 'In trattativa',
  venduto: 'Venduto',
  sospeso: 'Sospeso',
}

export const PROPERTY_STATUS_COLORS: Record<Property['status'], string> = {
  valutazione: 'bg-info/10 text-info',
  in_vendita: 'bg-gold/15 text-gold',
  in_trattativa: 'bg-warning/10 text-warning',
  venduto: 'bg-success/10 text-success',
  sospeso: 'bg-navy/10 text-muted',
}

export const PROPERTY_TYPE_LABELS: Record<string, string> = {
  appartamento: 'Appartamento',
  casa: 'Casa',
  villa: 'Villa',
  terreno: 'Terreno',
  commerciale: 'Commerciale',
  altro: 'Altro',
}

export const PROPOSAL_STATUS_LABELS: Record<Proposal['status'], string> = {
  ricevuta: 'Ricevuta',
  in_trattativa: 'In trattativa',
  accettata: 'Accettata',
  rifiutata: 'Rifiutata',
  ritirata: 'Ritirata',
}

export const PROPOSAL_STATUS_COLORS: Record<Proposal['status'], string> = {
  ricevuta: 'bg-info/10 text-info',
  in_trattativa: 'bg-warning/10 text-warning',
  accettata: 'bg-success/10 text-success',
  rifiutata: 'bg-error/10 text-error',
  ritirata: 'bg-navy/10 text-muted',
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

export const APPOINTMENT_TYPE_LABELS: Record<Appointment['type'], string> = {
  valutazione: 'Valutazione',
  visita: 'Visita',
  firma: 'Firma',
  altro: 'Altro',
}

export const APPOINTMENT_STATUS_LABELS: Record<Appointment['status'], string> = {
  programmato: 'Programmato',
  svolto: 'Svolto',
  annullato: 'Annullato',
}
