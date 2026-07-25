import { describe, expect, it } from 'vitest'
import { formatDate, formatEuro } from '@/lib/utils'

describe('formatEuro', () => {
  it('formatta in stile italiano', () => {
    expect(formatEuro('265000')).toMatch(/265\.000,00/)
    expect(formatEuro(1234.56)).toMatch(/1\.234,56/)
  })

  it('gestisce valori nulli', () => {
    expect(formatEuro(null)).toBe('—')
    expect(formatEuro('')).toBe('—')
  })
})

describe('formatDate', () => {
  it('formatta in dd/mm/yyyy', () => {
    expect(formatDate('2026-07-25T10:00:00Z')).toBe('25/07/2026')
  })

  it('gestisce valori invalidi', () => {
    expect(formatDate(null)).toBe('—')
    expect(formatDate('non-una-data')).toBe('—')
  })
})
