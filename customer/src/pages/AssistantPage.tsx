import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Send, Sparkles } from 'lucide-react'
import { api } from '@/api/client'
import { cn } from '@/lib/utils'

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
}

const WELCOME =
  'Ciao! Sono l\'assistente digitale di RT CASA LIVE. Posso aiutarti con domande sulla vendita della tua casa: visite, proposte, promozione e pratiche. Per parlare direttamente con Roberto: +39 345 7771822.'

/** Assistente AI — chat con bolle navy/avorio e indicatore "sta scrivendo". */
export default function AssistantPage() {
  const [messages, setMessages] = useState<ChatMessage[]>([{ role: 'assistant', content: WELCOME }])
  const [input, setInput] = useState('')
  const [sessionId, setSessionId] = useState<number | null>(null)
  const [typing, setTyping] = useState(false)
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [messages, typing])

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    const text = input.trim()
    if (!text || typing) return

    setMessages((m) => [...m, { role: 'user', content: text }])
    setInput('')
    setTyping(true)

    try {
      const res = await api<{ data: { session_id: number; reply: string } }>('/customer/chat', {
        method: 'POST',
        body: { message: text, session_id: sessionId ?? undefined },
      })
      setSessionId(res.data.session_id)
      setMessages((m) => [...m, { role: 'assistant', content: res.data.reply }])
    } catch (err) {
      setMessages((m) => [
        ...m,
        {
          role: 'assistant',
          content:
            err instanceof Error && err.message !== 'Sessione scaduta.'
              ? err.message
              : 'Si è verificato un problema. Riprova tra poco oppure contatta Roberto al +39 345 7771822.',
        },
      ])
    } finally {
      setTyping(false)
    }
  }

  return (
    <div className="flex h-[calc(100dvh-190px)] flex-col animate-fade-in">
      <header className="pb-3">
        <p className="rt-eyebrow">Assistente</p>
        <h1 className="mt-2 flex items-center gap-2 font-display text-xl text-navy">
          <Sparkles size={19} className="text-gold" strokeWidth={1.6} /> Chiedimi della tua vendita
        </h1>
        <p className="mt-1 text-xs text-muted">
          Assistente digitale di RT — per parlare con Roberto:{' '}
          <a href="tel:+393457771822" className="text-gold">345 7771822</a> ·{' '}
          <a href="mailto:info@rtimmobiliare.it" className="text-gold">email</a>
        </p>
      </header>

      <div className="flex-1 space-y-3 overflow-y-auto pb-3" role="log" aria-live="polite">
        {messages.map((m, i) => (
          <div key={i} className={cn('flex', m.role === 'user' ? 'justify-end' : 'justify-start')}>
            <div
              className={cn(
                'max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-2.5 text-sm leading-relaxed',
                m.role === 'user'
                  ? 'rounded-br-md bg-navy text-ivory'
                  : 'rounded-bl-md border border-gold/30 bg-ivory-soft text-navy',
              )}
            >
              {m.content}
            </div>
          </div>
        ))}
        {typing && (
          <div className="flex justify-start">
            <div className="flex items-center gap-1.5 rounded-2xl rounded-bl-md border border-gold/30 bg-ivory-soft px-4 py-3" aria-label="L'assistente sta scrivendo">
              {[0, 1, 2].map((i) => (
                <span
                  key={i}
                  className="h-1.5 w-1.5 animate-typing rounded-full bg-gold"
                  style={{ animationDelay: `${i * 0.18}s` }}
                />
              ))}
            </div>
          </div>
        )}
        <div ref={endRef} />
      </div>

      <form onSubmit={onSubmit} className="flex items-center gap-2 border-t border-gold/25 pt-3">
        <label htmlFor="chat-input" className="sr-only">Scrivi un messaggio</label>
        <input
          id="chat-input"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Scrivi qui la tua domanda…"
          maxLength={2000}
          className="min-h-[46px] flex-1 rounded-full border border-gold/40 bg-white px-4 text-sm text-navy placeholder:text-muted focus:border-gold focus:outline-none"
        />
        <button
          type="submit"
          disabled={!input.trim() || typing}
          aria-label="Invia"
          className="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-full text-navy-deep disabled:opacity-40"
          style={{ background: 'linear-gradient(135deg, #D3AE66, #B98F45)' }}
        >
          <Send size={18} strokeWidth={1.8} />
        </button>
      </form>
    </div>
  )
}
