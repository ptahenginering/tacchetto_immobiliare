import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export interface AdminUser {
  id: number
  role: 'admin' | 'agent'
  first_name: string
  last_name: string
  email: string
}

interface AuthState {
  token: string | null
  user: AdminUser | null
  login: (token: string, user: AdminUser) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      login: (token, user) => set({ token, user }),
      logout: () => set({ token: null, user: null }),
    }),
    { name: 'rt-admin-auth' },
  ),
)
