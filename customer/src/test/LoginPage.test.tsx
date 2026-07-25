import { describe, expect, it, vi, beforeEach } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import LoginPage from '@/pages/LoginPage'

describe('LoginPage (area cliente)', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    localStorage.clear()
  })

  it('mostra il brand e il form email', () => {
    render(<LoginPage />)
    expect(screen.getByText('La tua casa, sotto controllo')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('La tua email')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /ricevi il link/i })).toBeInTheDocument()
  })

  it('invia la richiesta di accesso e mostra la conferma', async () => {
    const fetchMock = vi.spyOn(global, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'ok' }), { status: 200 }),
    )

    render(<LoginPage />)
    fireEvent.change(screen.getByPlaceholderText('La tua email'), {
      target: { value: 'marco@example.it' },
    })
    fireEvent.click(screen.getByRole('button', { name: /ricevi il link/i }))

    await waitFor(() => {
      expect(screen.getByText('Controlla la tua email')).toBeInTheDocument()
    })

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/customer/request-access'),
      expect.objectContaining({ method: 'POST' }),
    )
  })
})
