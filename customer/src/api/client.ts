// Client API con JWT in localStorage e redirect al login su 401

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api'
const TOKEN_KEY = 'rt_customer_token'
const USER_KEY = 'rt_customer_user'

export interface AuthUser {
  id: number
  role: string
  first_name: string
  last_name: string
  email: string
}

export const auth = {
  getToken: () => localStorage.getItem(TOKEN_KEY),
  setToken: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  getUser: (): AuthUser | null => {
    try {
      const raw = localStorage.getItem(USER_KEY)
      return raw ? (JSON.parse(raw) as AuthUser) : null
    } catch {
      return null
    }
  },
  setUser: (user: AuthUser) => localStorage.setItem(USER_KEY, JSON.stringify(user)),
  clear: () => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  },
  isLoggedIn: () => !!localStorage.getItem(TOKEN_KEY),
}

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
  ) {
    super(message)
  }
}

export async function api<T = unknown>(
  path: string,
  options: { method?: string; body?: unknown } = {},
): Promise<T> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' }
  const token = auth.getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(`${API_BASE}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })

  if (res.status === 401) {
    // Sessione scaduta → torna al login
    auth.clear()
    if (!window.location.pathname.endsWith('/login') && !window.location.pathname.endsWith('/access')) {
      window.location.href = `${import.meta.env.BASE_URL}login`
    }
    throw new ApiError(401, 'unauthorized', 'Sessione scaduta.')
  }

  const data = await res.json().catch(() => ({}))

  if (!res.ok) {
    const err = (data as { error?: { code?: string; message?: string } }).error
    throw new ApiError(res.status, err?.code ?? 'error', err?.message ?? 'Si è verificato un errore.')
  }

  return data as T
}
