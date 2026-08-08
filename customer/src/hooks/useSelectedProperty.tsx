import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'

/**
 * Selezione immobile per proprietari con più immobili (multiproprietà).
 * L'id scelto è persistito in localStorage e aggiunto come ?property_id=
 * a tutte le chiamate; il backend valida comunque che l'immobile
 * appartenga al proprietario loggato.
 */

export interface PropertySummary {
  id: number
  title: string
  address: string | null
  city: string | null
  province: string | null
  type: string
  status: string
  cover_image_url: string | null
  created_at: string
}

const STORAGE_KEY = 'rt_customer_property_id'

interface PropertySelection {
  properties: PropertySummary[]
  isLoading: boolean
  selectedId: number | null
  setSelectedId: (id: number) => void
  /** Suffisso query string da appendere alle chiamate API ("" se selezione di default). */
  propertyParam: string
}

const Ctx = createContext<PropertySelection>({
  properties: [],
  isLoading: true,
  selectedId: null,
  setSelectedId: () => undefined,
  propertyParam: '',
})

export function PropertySelectionProvider({ children }: { children: React.ReactNode }) {
  const [selectedId, setSelected] = useState<number | null>(() => {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? Number(raw) || null : null
  })

  const list = useQuery({
    queryKey: ['my-properties'],
    queryFn: () => api<{ data: PropertySummary[] }>('/customer/properties').then((r) => r.data),
  })

  const properties = list.data ?? []

  // Se l'id salvato non è (più) tra gli immobili del proprietario, torna al default
  useEffect(() => {
    if (list.data && selectedId !== null && !list.data.some((p) => p.id === selectedId)) {
      setSelected(null)
      localStorage.removeItem(STORAGE_KEY)
    }
  }, [list.data, selectedId])

  const setSelectedId = useCallback((id: number) => {
    setSelected(id)
    localStorage.setItem(STORAGE_KEY, String(id))
  }, [])

  const effectiveId = selectedId ?? properties[0]?.id ?? null
  const propertyParam = effectiveId !== null ? `property_id=${effectiveId}` : ''

  return (
    <Ctx.Provider
      value={{ properties, isLoading: list.isLoading, selectedId: effectiveId, setSelectedId, propertyParam }}
    >
      {children}
    </Ctx.Provider>
  )
}

export function useSelectedProperty(): PropertySelection {
  return useContext(Ctx)
}
