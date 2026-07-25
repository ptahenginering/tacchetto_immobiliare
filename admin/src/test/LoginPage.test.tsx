import { describe, expect, it, vi, beforeEach } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import LoginPage from '@/pages/LoginPage'
import { useAuthStore } from '@/store/auth'

describe('LoginPage (gestionale)', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    useAuthStore.getState().logout()
  })

  it('mostra il form di login brandizzato', () => {
    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>,
    )
    expect(screen.getByText('Gestionale Agenzia')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Email')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Password')).toBeInTheDocument()
  })

  it('mostra errore su credenziali sbagliate', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ error: { code: 'invalid_credentials', message: 'Credenziali non valide.' } }), {
        status: 401,
      }),
    )

    render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>,
    )
    fireEvent.change(screen.getByPlaceholderText('Email'), { target: { value: 'x@y.it' } })
    fireEvent.change(screen.getByPlaceholderText('Password'), { target: { value: 'sbagliata' } })
    fireEvent.click(screen.getByRole('button', { name: /entra/i }))

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/sessione scaduta|credenziali/i)
    })
    expect(useAuthStore.getState().token).toBeNull()
  })
})

describe('auth store', () => {
  it('login e logout aggiornano lo stato', () => {
    const user = { id: 1, role: 'admin' as const, first_name: 'R', last_name: 'T', email: 'a@b.it' }
    useAuthStore.getState().login('tok', user)
    expect(useAuthStore.getState().token).toBe('tok')
    expect(useAuthStore.getState().user?.email).toBe('a@b.it')

    useAuthStore.getState().logout()
    expect(useAuthStore.getState().token).toBeNull()
  })
})
