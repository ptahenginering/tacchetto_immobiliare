import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import toast from 'react-hot-toast'
import { ChevronLeft, ChevronRight, Download, Mail, Sparkles } from 'lucide-react'
import { api, apiBlob, fileUrl } from '@/api/client'
import { PROPERTY_TYPE_LABELS, type Property } from '@/api/types'
import { Field, inputCls, Modal } from '@/components/ui'
import { cn, formatEuro } from '@/lib/utils'

/**
 * Tab "Scheda" del dettaglio immobile: presentazione professionale con
 * carosello foto, riepilogo dati e testo generato con l'AI (kind "scheda").
 * La scheda si scarica in PDF (dompdf lato server) o si invia via email
 * a un potenziale acquirente con il PDF in allegato.
 */
export default function PropertySheetTab({ property }: { property: Property }) {
  const [text, setText] = useState(property.description ?? '')
  const [generated, setGenerated] = useState(false)
  const [sendOpen, setSendOpen] = useState(false)

  const generate = useMutation({
    mutationFn: () =>
      api<{ data: { output: string } }>('/admin/ai/generate', {
        method: 'POST',
        body: { property_id: property.id, kind: 'scheda' },
      }),
    onSuccess: (res) => {
      setText(res.data.output)
      setGenerated(true)
      toast.success('Scheda generata: rileggi e modifica liberamente il testo')
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Generazione non riuscita'),
  })

  const downloadPdf = useMutation({
    mutationFn: () =>
      apiBlob(`/admin/properties/${property.id}/brochure/pdf`, {
        method: 'POST',
        body: { presentation_text: text || undefined },
      }),
    onSuccess: (blob) => {
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `scheda-${property.id}.pdf`
      a.click()
      URL.revokeObjectURL(url)
      toast.success('Scheda PDF scaricata')
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Download non riuscito'),
  })

  return (
    <div className="max-w-3xl space-y-4">
      <PhotoCarousel property={property} />

      {/* Riepilogo dati */}
      <div className="rt-card grid grid-cols-2 gap-px overflow-hidden bg-navy/5 sm:grid-cols-4">
        {[
          { label: 'Tipologia', value: PROPERTY_TYPE_LABELS[property.type] ?? property.type },
          { label: 'Superficie', value: property.surface_sqm ? `${property.surface_sqm} mq` : '—' },
          { label: 'Locali', value: property.rooms ?? '—' },
          { label: 'Prezzo', value: property.price ? formatEuro(property.price) : 'Su richiesta' },
        ].map((d) => (
          <div key={d.label} className="bg-white px-4 py-3 text-center">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">{d.label}</p>
            <p className="font-display text-navy">{String(d.value)}</p>
          </div>
        ))}
      </div>

      {/* Testo di presentazione */}
      <div className="rt-card space-y-3 px-5 py-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h3 className="flex items-center gap-2 font-display text-lg text-navy">
            <Sparkles size={17} className="text-gold" /> Presentazione
          </h3>
          <button onClick={() => generate.mutate()} disabled={generate.isPending} className="rt-btn-outline">
            <Sparkles size={14} />
            {generate.isPending ? 'Generazione…' : generated ? 'Rigenera con AI' : 'Genera scheda con AI'}
          </button>
        </div>
        <textarea
          rows={12}
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Genera il testo con l'AI oppure scrivilo qui: paragrafo introduttivo, punti di forza (uno per riga con «- »), chiusura con invito alla visita."
          className={inputCls + ' leading-relaxed'}
        />
        <div className="flex flex-wrap justify-end gap-2">
          <button onClick={() => downloadPdf.mutate()} disabled={downloadPdf.isPending} className="rt-btn-outline">
            <Download size={14} /> {downloadPdf.isPending ? 'Preparazione…' : 'Scarica PDF'}
          </button>
          <button onClick={() => setSendOpen(true)} className="rt-btn-primary">
            <Mail size={14} /> Invia a un acquirente
          </button>
        </div>
      </div>

      {sendOpen && (
        <SendSheetModal property={property} presentationText={text} onClose={() => setSendOpen(false)} />
      )}
    </div>
  )
}

function PhotoCarousel({ property }: { property: Property }) {
  const images = property.images ?? []
  const [index, setIndex] = useState(0)

  if (images.length === 0) {
    return (
      <div className="rt-card flex h-48 items-center justify-center text-sm text-muted">
        Nessuna foto: caricale nel tab «Foto» per una scheda più efficace.
      </div>
    )
  }

  const go = (dir: -1 | 1) => setIndex((i) => (i + dir + images.length) % images.length)

  return (
    <div className="space-y-2">
      <div className="rt-card relative overflow-hidden">
        <img src={fileUrl(images[index].url) ?? ''} alt="" className="h-72 w-full object-cover" />
        {images.length > 1 && (
          <>
            <button
              onClick={() => go(-1)}
              aria-label="Foto precedente"
              className="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-navy-deep/60 text-ivory hover:bg-navy-deep/80"
            >
              <ChevronLeft size={18} />
            </button>
            <button
              onClick={() => go(1)}
              aria-label="Foto successiva"
              className="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-navy-deep/60 text-ivory hover:bg-navy-deep/80"
            >
              <ChevronRight size={18} />
            </button>
            <span className="absolute bottom-2 right-3 rounded-full bg-navy-deep/70 px-2 py-0.5 text-xs text-ivory">
              {index + 1} / {images.length}
            </span>
          </>
        )}
      </div>
      {images.length > 1 && (
        <div className="flex gap-2 overflow-x-auto pb-1">
          {images.map((img, i) => (
            <button key={img.id} onClick={() => setIndex(i)} aria-label={`Foto ${i + 1}`} className="shrink-0">
              <img
                src={fileUrl(img.url) ?? ''}
                alt=""
                className={cn(
                  'h-14 w-20 rounded object-cover transition-opacity',
                  i === index ? 'ring-2 ring-gold' : 'opacity-60 hover:opacity-100',
                )}
              />
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function SendSheetModal({
  property,
  presentationText,
  onClose,
}: {
  property: Property
  presentationText: string
  onClose: () => void
}) {
  const [form, setForm] = useState({ email: '', recipient_name: '', message: '' })

  const send = useMutation({
    mutationFn: () =>
      api<{ message: string }>(`/admin/properties/${property.id}/brochure/send`, {
        method: 'POST',
        body: {
          email: form.email,
          recipient_name: form.recipient_name || undefined,
          message: form.message || undefined,
          presentation_text: presentationText || undefined,
        },
      }),
    onSuccess: (res) => {
      toast.success(res.message ?? 'Scheda inviata')
      onClose()
    },
    onError: (e) => toast.error(e instanceof Error ? e.message : 'Invio non riuscito'),
  })

  return (
    <Modal open onClose={onClose} title="Invia la scheda a un acquirente">
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          send.mutate()
        }}
        className="space-y-3"
      >
        <p className="text-xs text-muted">
          Il destinatario riceve una email brand RT con la scheda PDF di «{property.title}» in allegato.
        </p>
        <Field label="Email destinatario *">
          <input
            required
            type="email"
            value={form.email}
            onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
            className={inputCls}
          />
        </Field>
        <Field label="Nome destinatario">
          <input
            value={form.recipient_name}
            onChange={(e) => setForm((f) => ({ ...f, recipient_name: e.target.value }))}
            className={inputCls}
          />
        </Field>
        <Field label="Messaggio personale (opzionale)">
          <textarea
            rows={3}
            value={form.message}
            onChange={(e) => setForm((f) => ({ ...f, message: e.target.value }))}
            placeholder="es. Come da accordi telefonici, le invio la scheda dell'immobile…"
            className={inputCls}
          />
        </Field>
        <div className="flex justify-end pt-1">
          <button type="submit" disabled={send.isPending} className="rt-btn-primary">
            <Mail size={14} /> {send.isPending ? 'Invio…' : 'Invia scheda'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
