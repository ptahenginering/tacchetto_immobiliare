import { useCallback, useEffect, useRef, useState } from 'react'
import toast from 'react-hot-toast'
import QRCode from 'qrcode'
import { jsPDF } from 'jspdf'
import { Download, ImageDown, ImagePlus, Megaphone, RefreshCw } from 'lucide-react'
import { Field, inputCls } from '@/components/ui'

/**
 * Studio campagne di acquisizione: genera la grafica del post social (JPG)
 * e il volantino A5 (JPG/PDF) in stile sito vetrina, con QR code che porta
 * al form contatti (?src=qr / ?src=social per tracciare la fonte dei lead).
 * Tutto lato client: canvas + qrcode + jspdf, nessuna chiamata server.
 */

const SITE_URL = 'https://tacchettoimmobiliare.it'
const QR_LANDING = `${SITE_URL}/?src=qr#contatti`

// Palette e font della vetrina (public_html/index.html)
const NAVY_DEEP = '#0E1B2E'
const GOLD = '#C29B52'
const GOLD_LIGHT = '#DCC28C'
const IVORY = '#F6F2EA'
const GREY_ON_DARK = '#C6CFDC'

interface CreativeTexts {
  eyebrow: string
  headline: string
  subline: string
  bullets: string[]
  phone: string
  claim: string
}

const DEFAULTS: CreativeTexts = {
  eyebrow: 'RT — Roberto Tacchetto · Real Estate Advisor',
  headline: 'Vuoi vendere casa?',
  subline: 'Valutazione gratuita e senza impegno del tuo immobile, con un metodo chiaro in 5 passi.',
  bullets: [
    'Valutazione professionale gratuita',
    'Aggiornamenti in tempo reale nella tua area riservata',
    'Un solo referente, dal primo incontro al rogito',
  ],
  phone: '+39 345 7771822',
  claim: 'Trasparenza. Controllo. Risultati.',
}

function wrapText(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] {
  const words = text.split(/\s+/).filter(Boolean)
  const lines: string[] = []
  let line = ''
  for (const w of words) {
    const probe = line ? `${line} ${w}` : w
    if (ctx.measureText(probe).width > maxWidth && line) {
      lines.push(line)
      line = w
    } else {
      line = probe
    }
  }
  if (line) lines.push(line)
  return lines
}

export type CampaignTemplate = 'classico' | 'foto'

/** Disegna un'immagine a copertura (cover) con crop centrato. */
function drawCover(ctx: CanvasRenderingContext2D, img: HTMLImageElement, W: number, H: number) {
  const scale = Math.max(W / img.naturalWidth, H / img.naturalHeight)
  const w = img.naturalWidth * scale
  const h = img.naturalHeight * scale
  ctx.drawImage(img, (W - w) / 2, (H - h) / 2, w, h)
}

/** Disegna la creatività su canvas. Layout parametrico: stesse proporzioni per social e volantino. */
async function drawCreative(
  canvas: HTMLCanvasElement,
  W: number,
  H: number,
  t: CreativeTexts,
  withBullets: boolean,
  template: CampaignTemplate = 'classico',
  bgImage: HTMLImageElement | null = null,
): Promise<void> {
  await document.fonts.ready
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Canvas non disponibile')

  const u = W / 1080 // unità di scala (design a 1080 di larghezza)

  // Sfondo: navy pieno (classico) oppure foto caricata + velatura navy
  // per garantire la leggibilità di testi e QR (fotografico)
  if (template === 'foto' && bgImage) {
    drawCover(ctx, bgImage, W, H)
    const grad = ctx.createLinearGradient(0, 0, 0, H)
    grad.addColorStop(0, 'rgba(14,27,46,.55)')
    grad.addColorStop(0.45, 'rgba(14,27,46,.35)')
    grad.addColorStop(1, 'rgba(14,27,46,.9)')
    ctx.fillStyle = grad
    ctx.fillRect(0, 0, W, H)
  } else {
    ctx.fillStyle = NAVY_DEEP
    ctx.fillRect(0, 0, W, H)
  }

  // Cornice oro
  ctx.strokeStyle = 'rgba(194,155,82,.5)'
  ctx.lineWidth = 3 * u
  ctx.strokeRect(36 * u, 36 * u, W - 72 * u, H - 72 * u)

  // Sul template foto un'ombra leggera aiuta i testi sopra l'immagine
  if (template === 'foto' && bgImage) {
    ctx.shadowColor = 'rgba(14,27,46,.75)'
    ctx.shadowBlur = 14 * u
    ctx.shadowOffsetY = 2 * u
  }

  ctx.textAlign = 'center'
  let y = 150 * u

  // Eyebrow
  ctx.fillStyle = GOLD_LIGHT
  ctx.font = `500 ${26 * u}px Jost, sans-serif`
  ctx.fillText(t.eyebrow.toUpperCase(), W / 2, y)
  y += 60 * u

  // Headline (Playfair)
  ctx.fillStyle = IVORY
  ctx.font = `600 ${92 * u}px "Playfair Display", serif`
  for (const line of wrapText(ctx, t.headline, W - 200 * u)) {
    y += 100 * u
    ctx.fillText(line, W / 2, y)
  }

  // Divider oro
  y += 56 * u
  ctx.fillStyle = GOLD
  ctx.fillRect(W / 2 - 60 * u, y, 120 * u, 5 * u)
  y += 30 * u

  // Subline
  ctx.fillStyle = GREY_ON_DARK
  ctx.font = `300 ${38 * u}px Jost, sans-serif`
  for (const line of wrapText(ctx, t.subline, W - 240 * u)) {
    y += 52 * u
    ctx.fillText(line, W / 2, y)
  }

  // Bullet (solo volantino)
  if (withBullets) {
    y += 60 * u
    ctx.textAlign = 'left'
    ctx.font = `400 ${36 * u}px Jost, sans-serif`
    const bulletX = 150 * u
    for (const b of t.bullets.filter(Boolean)) {
      y += 62 * u
      ctx.fillStyle = GOLD
      ctx.fillText('•', bulletX, y)
      ctx.fillStyle = IVORY
      ctx.fillText(b, bulletX + 40 * u, y)
    }
    ctx.textAlign = 'center'
  }

  // Card bianca con QR in basso (senza ombra: il QR deve restare nitido e scansionabile)
  ctx.shadowColor = 'transparent'
  ctx.shadowBlur = 0
  ctx.shadowOffsetY = 0
  const qrData = await QRCode.toDataURL(QR_LANDING, {
    errorCorrectionLevel: 'M',
    margin: 1,
    width: 480,
    color: { dark: NAVY_DEEP, light: '#FFFFFF' },
  })
  const qrImg = new Image()
  await new Promise<void>((resolve, reject) => {
    qrImg.onload = () => resolve()
    qrImg.onerror = () => reject(new Error('QR non generato'))
    qrImg.src = qrData
  })

  const cardW = 560 * u
  const cardH = 340 * u
  const cardX = (W - cardW) / 2
  const cardY = H - cardH - 220 * u
  ctx.fillStyle = '#FFFFFF'
  ctx.beginPath()
  ctx.roundRect(cardX, cardY, cardW, cardH, 20 * u)
  ctx.fill()

  const qrSize = 240 * u
  ctx.drawImage(qrImg, cardX + 40 * u, cardY + (cardH - qrSize) / 2, qrSize, qrSize)

  ctx.textAlign = 'left'
  const textX = cardX + 40 * u + qrSize + 36 * u
  ctx.fillStyle = NAVY_DEEP
  ctx.font = `600 ${34 * u}px Jost, sans-serif`
  ctx.fillText('Inquadra il', textX, cardY + 120 * u)
  ctx.fillText('QR code', textX, cardY + 164 * u)
  ctx.font = `300 ${26 * u}px Jost, sans-serif`
  ctx.fillStyle = '#5C6B80'
  ctx.fillText('e richiedi la tua', textX, cardY + 214 * u)
  ctx.fillText('valutazione gratuita', textX, cardY + 248 * u)
  ctx.textAlign = 'center'

  // Contatti + claim
  ctx.fillStyle = GOLD_LIGHT
  ctx.font = `500 ${44 * u}px Jost, sans-serif`
  ctx.fillText(t.phone, W / 2, H - 130 * u)
  ctx.fillStyle = GREY_ON_DARK
  ctx.font = `300 ${28 * u}px Jost, sans-serif`
  ctx.fillText(`tacchettoimmobiliare.it — ${t.claim}`, W / 2, H - 78 * u)
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

export default function CampaignStudio() {
  const [texts, setTexts] = useState<CreativeTexts>(DEFAULTS)
  const [template, setTemplate] = useState<CampaignTemplate>('classico')
  const [bgImage, setBgImage] = useState<HTMLImageElement | null>(null)
  const [bgName, setBgName] = useState('')
  const [busy, setBusy] = useState(false)
  const socialRef = useRef<HTMLCanvasElement>(null)
  const flyerRef = useRef<HTMLCanvasElement>(null)
  const bgInputRef = useRef<HTMLInputElement>(null)

  const render = useCallback(async () => {
    setBusy(true)
    try {
      // Post social 1080×1350 (portrait Instagram/Facebook)
      if (socialRef.current) await drawCreative(socialRef.current, 1080, 1350, texts, false, template, bgImage)
      // Volantino A5 a 300 dpi (148×210 mm → 1748×2480 px)
      if (flyerRef.current) await drawCreative(flyerRef.current, 1748, 2480, texts, true, template, bgImage)
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Anteprima non generata')
    } finally {
      setBusy(false)
    }
  }, [texts, template, bgImage])

  // Prima anteprima + rigenerazione automatica al cambio template/sfondo
  // (per le modifiche ai testi c'è il pulsante "Aggiorna anteprime")
  useEffect(() => {
    void render()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [template, bgImage])

  function loadBackground(file: File) {
    if (!file.type.startsWith('image/')) {
      toast.error('Carica un file immagine (JPG, PNG o WEBP)')
      return
    }
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      setBgImage(img)
      setBgName(file.name)
      setTemplate('foto')
      toast.success('Sfondo caricato: anteprime aggiornate')
      // NB: niente revoke dell'objectURL finché l'immagine serve per il re-render
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      toast.error('Immagine non leggibile')
    }
    img.src = url
  }

  function downloadJpg(canvas: HTMLCanvasElement | null, filename: string) {
    canvas?.toBlob(
      (blob) => {
        if (blob) {
          downloadBlob(blob, filename)
          toast.success(`${filename} scaricato`)
        }
      },
      'image/jpeg',
      0.92,
    )
  }

  function downloadFlyerPdf() {
    const canvas = flyerRef.current
    if (!canvas) return
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a5' })
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, 148, 210)
    pdf.save('volantino-rt-a5.pdf')
    toast.success('volantino-rt-a5.pdf scaricato')
  }

  function set(key: keyof CreativeTexts) {
    return (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
      setTexts((t) => ({ ...t, [key]: e.target.value }))
  }

  return (
    <section className="rt-card border-gold/50 px-5 py-4">
      <h2 className="flex items-center gap-2 font-display text-lg text-navy">
        <Megaphone size={18} className="text-gold" /> Campagne di acquisizione — post social & volantino
      </h2>
      <p className="mt-1 text-xs text-muted">
        Grafiche in stile sito vetrina con QR code integrato: chi lo inquadra atterra sul form contatti e il lead
        arriva nel gestionale con fonte «QR code». Scarica il JPG per i social o il PDF A5 per la stampa.
      </p>

      {/* Scelta template + sfondo personalizzato */}
      <div className="mt-3 flex flex-wrap items-end gap-3">
        <Field label="Template">
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setTemplate('classico')}
              className={template === 'classico' ? 'rt-btn-primary' : 'rt-btn-outline'}
            >
              Classico navy
            </button>
            <button
              type="button"
              onClick={() => (bgImage ? setTemplate('foto') : bgInputRef.current?.click())}
              className={template === 'foto' ? 'rt-btn-primary' : 'rt-btn-outline'}
            >
              Fotografico
            </button>
          </div>
        </Field>
        <Field label="Immagine di sfondo (template fotografico)">
          <div className="flex items-center gap-2">
            <button type="button" onClick={() => bgInputRef.current?.click()} className="rt-btn-outline">
              <ImagePlus size={14} /> {bgImage ? 'Cambia immagine' : 'Carica immagine'}
            </button>
            {bgName && <span className="max-w-[180px] truncate text-xs text-muted">{bgName}</span>}
            <input
              ref={bgInputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={(e) => {
                const f = e.target.files?.[0]
                if (f) loadBackground(f)
                e.target.value = ''
              }}
            />
          </div>
        </Field>
      </div>
      {template === 'foto' && !bgImage && (
        <p className="mt-1 text-xs text-warning">
          Carica un'immagine di sfondo per il template fotografico: finché manca, l'anteprima usa lo sfondo navy.
        </p>
      )}

      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <Field label="Titolo">
          <input value={texts.headline} onChange={set('headline')} className={inputCls} />
        </Field>
        <Field label="Sottotitolo">
          <input value={texts.subline} onChange={set('subline')} className={inputCls} />
        </Field>
        <Field label="Punti chiave del volantino (uno per riga)">
          <textarea
            rows={3}
            value={texts.bullets.join('\n')}
            onChange={(e) => setTexts((t) => ({ ...t, bullets: e.target.value.split('\n') }))}
            className={inputCls}
          />
        </Field>
        <Field label="Telefono">
          <input value={texts.phone} onChange={set('phone')} className={inputCls} />
        </Field>
      </div>

      <button onClick={() => void render()} disabled={busy} className="rt-btn-primary mt-3">
        <RefreshCw size={14} className={busy ? 'animate-spin' : ''} />
        {busy ? 'Generazione…' : 'Aggiorna anteprime'}
      </button>

      <div className="mt-4 grid gap-4 md:grid-cols-2">
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Post social (1080×1350)</p>
          <canvas ref={socialRef} className="w-full max-w-xs rounded-rt border border-navy/10 shadow-sm" />
          <div className="mt-2">
            <button onClick={() => downloadJpg(socialRef.current, 'post-social-rt.jpg')} className="rt-btn-outline">
              <ImageDown size={14} /> Scarica JPG social
            </button>
          </div>
        </div>
        <div>
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Volantino A5 (stampa 300 dpi)</p>
          <canvas ref={flyerRef} className="w-full max-w-xs rounded-rt border border-navy/10 shadow-sm" />
          <div className="mt-2 flex flex-wrap gap-2">
            <button onClick={downloadFlyerPdf} className="rt-btn-outline">
              <Download size={14} /> Scarica PDF A5
            </button>
            <button onClick={() => downloadJpg(flyerRef.current, 'volantino-rt-a5.jpg')} className="rt-btn-outline">
              <ImageDown size={14} /> Scarica JPG
            </button>
          </div>
        </div>
      </div>
    </section>
  )
}
