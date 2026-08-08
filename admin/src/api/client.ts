// Client API gestionale — JWT da zustand store (persist su localStorage)

import { useAuthStore } from '@/store/auth'

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api'

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public fields?: Record<string, string>,
  ) {
    super(message)
  }
}

export async function api<T = unknown>(
  path: string,
  options: { method?: string; body?: unknown; formData?: FormData } = {},
): Promise<T> {
  const { token, logout } = useAuthStore.getState()

  const headers: Record<string, string> = {}
  if (!options.formData) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(`${API_BASE}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.formData ?? (options.body !== undefined ? JSON.stringify(options.body) : undefined),
  })

  // 401 su una route protetta = sessione scaduta → logout.
  // 401 sul login stesso = credenziali sbagliate → va mostrato l'errore reale.
  if (res.status === 401 && path !== '/admin/login') {
    logout()
    throw new ApiError(401, 'unauthorized', 'Sessione scaduta: effettua di nuovo il login.')
  }

  const data = await res.json().catch(() => ({}))

  if (!res.ok) {
    const err = (data as { error?: { code?: string; message?: string; fields?: Record<string, string> } }).error
    throw new ApiError(res.status, err?.code ?? 'error', err?.message ?? 'Errore inatteso.', err?.fields)
  }

  return data as T
}

/** Come api(), ma restituisce il body binario (es. PDF generati dal server). */
export async function apiBlob(
  path: string,
  options: { method?: string; body?: unknown } = {},
): Promise<Blob> {
  const { token, logout } = useAuthStore.getState()

  const headers: Record<string, string> = { 'Content-Type': 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(`${API_BASE}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })

  if (res.status === 401) {
    logout()
    throw new ApiError(401, 'unauthorized', 'Sessione scaduta: effettua di nuovo il login.')
  }
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    const err = (data as { error?: { code?: string; message?: string } }).error
    throw new ApiError(res.status, err?.code ?? 'error', err?.message ?? 'Errore inatteso.')
  }

  return res.blob()
}

export function fileUrl(path: string | null): string | null {
  if (!path) return null
  if (path.startsWith('http')) return path
  return `${API_BASE}/files/${path}`
}
