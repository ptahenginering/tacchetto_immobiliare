import { useState } from 'react'
import { Link } from 'react-router-dom'
import {
  BookOpen,
  CalendarCheck,
  ChevronDown,
  FileText,
  HandCoins,
  Home,
  KeyRound,
  Megaphone,
  MessageCircle,
  ShieldCheck,
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface Section {
  id: string
  icon: typeof Home
  title: string
  body: React.ReactNode
}

/** Guida utente dell'Area Cliente — semplice e rassicurante, una voce per pagina. */
export default function GuidePage() {
  const [open, setOpen] = useState<string | null>('accesso')

  const sections: Section[] = [
    {
      id: 'accesso',
      icon: KeyRound,
      title: 'Come si entra (senza password)',
      body: (
        <>
          <P>
            Niente password da ricordare: l'accesso avviene con un <B>link personale</B> che ti inviamo via email.
          </P>
          <Steps
            items={[
              <>Vai su <B>tacchettoimmobiliare.it/app</B> e inserisci la tua email (quella che hai dato a Roberto).</>,
              <>Apri l'email "Il tuo accesso a RT CASA LIVE" e tocca il pulsante: sei dentro.</>,
              <>Il link vale 30 minuti e si usa una sola volta; la sessione poi resta attiva 30 giorni su questo dispositivo.</>,
              <>Link scaduto o email non arrivata? Richiedine uno nuovo dalla stessa pagina (controlla anche lo spam).</>,
            ]}
          />
          <Tip>Aggiungi l'app alla schermata Home del telefono: dal browser scegli "Aggiungi a schermata Home".</Tip>
        </>
      ),
    },
    {
      id: 'home',
      icon: Home,
      title: 'La tua Home: i numeri della vendita',
      body: (
        <>
          <P>Appena entri trovi lo stato reale della tua vendita, aggiornato in tempo reale:</P>
          <Ul
            items={[
              <><B>La tua casa</B>: foto, indirizzo e lo stato attuale (in valutazione, in vendita, in trattativa…).</>,
              <><B>Appuntamenti e Visite</B>: quante persone hanno visto (o stanno per vedere) la tua casa negli ultimi 30 giorni.</>,
              <><B>Proposte</B>: le offerte attualmente in corso.</>,
              <><B>Interesse %</B>: un indice da 0 a 100 che riassume quanto il mercato si sta muovendo sulla tua casa. Lo calcoliamo con visite recenti, riscontri positivi, proposte attive e appuntamenti in programma (tocca la ⓘ per rivederlo). La freccia indica se sta salendo o scendendo rispetto al mese precedente.</>,
              <><B>Grafico visite</B>: l'andamento settimana per settimana.</>,
              <>Il pulsante con le frecce circolari in alto a destra aggiorna tutti i dati.</>,
            ]}
          />
        </>
      ),
    },
    {
      id: 'visite',
      icon: CalendarCheck,
      title: 'Visite & riscontri',
      body: (
        <>
          <P>
            Qui trovi <B>ogni visita</B> fatta alla tua casa: data, profilo di chi è venuto (in forma anonima, es.
            "Coppia, prima casa"), se era un interesse concreto ("Qualificata") e — soprattutto — <B>il loro parere
            sincero</B>, con le stelle e il commento raccolto da Roberto.
          </P>
          <Tip>
            I riscontri, anche quelli meno positivi, sono preziosi: ci dicono come il mercato percepisce prezzo e
            presentazione. La trasparenza è il nostro metodo di lavoro.
          </Tip>
        </>
      ),
    },
    {
      id: 'proposte',
      icon: HandCoins,
      title: 'Proposte',
      body: (
        <>
          <Ul
            items={[
              <>Quando arriva un'offerta ricevi una email che ti invita ad aprire l'app: <B>l'importo lo vedi solo qui</B>, mai via email, per la tua riservatezza.</>,
              <>Di ogni proposta vedi importo, data e stato: ricevuta, in trattativa, accettata, rifiutata o ritirata.</>,
              <>Nessuna decisione senza di te: ogni proposta viene discussa personalmente con Roberto.</>,
            ]}
          />
        </>
      ),
    },
    {
      id: 'promozione',
      icon: Megaphone,
      title: 'Promozione — dove viene promossa la tua casa',
      body: (
        <>
          <P>
            La pagina della <B>trasparenza totale</B>: per ogni canale (Immobiliare.it, Idealista, Facebook, Instagram,
            vetrina, portale Tecnocasa) vedi cosa è stato pubblicato, quando, con che numeri reali (visualizzazioni,
            contatti) e — dove disponibile — il link per vedere l'annuncio con i tuoi occhi.
          </P>
        </>
      ),
    },
    {
      id: 'pratiche',
      icon: FileText,
      title: 'Pratiche — la burocrazia senza pensieri',
      body: (
        <>
          <Ul
            items={[
              <>La barra in alto mostra la <B>percentuale di avanzamento</B> delle pratiche della tua vendita.</>,
              <>Sotto, tutti i passaggi in ordine: documenti catastali, APE, conformità urbanistica, atto di provenienza, proposta/preliminare, rogito (e, per gli immobili ereditati, anche successione e volture).</>,
              <>Spunta oro ✓ = fatto; rotellina = ci stiamo lavorando; numero = in programma.</>,
              <>Quando completiamo un passaggio ricevi una email. <B>Ci occupiamo noi di tutto</B>: tu devi solo restare informato.</>,
            ]}
          />
        </>
      ),
    },
    {
      id: 'assistente',
      icon: MessageCircle,
      title: 'Assistente digitale',
      body: (
        <>
          <P>
            L'assistente conosce <B>i dati reali della tua vendita</B> (stato, visite, proposte, pratiche) e risponde
            alle tue domande in qualsiasi momento: "come sta andando?", "quali documenti mancano?", "cosa significa
            in trattativa?".
          </P>
          <Ul
            items={[
              <>Scrivi la domanda nel campo in basso e invia: risponde in pochi secondi.</>,
              <>Per questioni legali, fiscali o per decidere su una proposta ti rimanda sempre a Roberto: l'assistente informa, le decisioni si prendono insieme.</>,
              <>Se non è disponibile (manutenzione), trovi comunque i contatti diretti di Roberto qui sotto e nel tuo Profilo.</>,
            ]}
          />
        </>
      ),
    },
    {
      id: 'profilo',
      icon: ShieldCheck,
      title: 'Profilo, privacy e contatti',
      body: (
        <>
          <Ul
            items={[
              <>Nel <Link to="/profilo" className="font-medium text-gold underline-offset-2 hover:underline">Profilo</Link> trovi i tuoi dati e i contatti diretti di Roberto: <B>+39 345 7771822</B> · info@rtimmobiliare.it.</>,
              <>"Esci dall'area riservata" chiude la sessione su questo dispositivo (consigliato su dispositivi condivisi).</>,
              <>Privacy: vedi esclusivamente i dati della TUA casa. Dei visitatori non vedi mai nome o recapiti, solo un profilo anonimo — la stessa tutela vale per te verso gli altri.</>,
              <>Le email che ricevi non contengono mai importi o dati sensibili: sono solo inviti ad aprire l'app.</>,
              <>Hai cambiato email? Comunicala a Roberto: aggiorniamo noi il tuo accesso.</>,
            ]}
          />
        </>
      ),
    },
  ]

  return (
    <div className="space-y-4 animate-fade-in">
      <header>
        <p className="rt-eyebrow">Guida</p>
        <h1 className="mt-2 flex items-center gap-2 font-display text-xl text-navy">
          <BookOpen size={20} className="text-gold" /> Come funziona la tua area
        </h1>
        <p className="mt-1 text-sm text-muted">Tocca una voce per aprirla. Per tutto il resto c'è Roberto: 345 7771822.</p>
      </header>

      <div className="space-y-2.5">
        {sections.map(({ id, icon: Icon, title, body }) => {
          const isOpen = open === id
          return (
            <div key={id} className="rt-card overflow-hidden">
              <button
                onClick={() => setOpen(isOpen ? null : id)}
                className="flex min-h-[52px] w-full items-center justify-between gap-3 px-4 py-3 text-left"
                aria-expanded={isOpen}
              >
                <span className="flex items-center gap-3">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold/15">
                    <Icon size={16} className="text-gold" strokeWidth={1.7} />
                  </span>
                  <span className="text-sm font-medium leading-snug text-navy">{title}</span>
                </span>
                <ChevronDown size={16} className={cn('shrink-0 text-muted transition-transform', isOpen && 'rotate-180')} />
              </button>
              {isOpen && (
                <div className="space-y-3 border-t border-gold/15 px-4 py-4 text-sm leading-relaxed text-navy/90">{body}</div>
              )}
            </div>
          )
        })}
      </div>

      <p className="pb-2 text-center text-[11px] uppercase tracking-[0.2em] text-muted">
        Trasparenza · Controllo · Risultati
      </p>
    </div>
  )
}

function P({ children }: { children: React.ReactNode }) {
  return <p>{children}</p>
}
function B({ children }: { children: React.ReactNode }) {
  return <strong className="font-semibold text-navy">{children}</strong>
}
function Ul({ items }: { items: React.ReactNode[] }) {
  return (
    <ul className="space-y-1.5">
      {items.map((it, i) => (
        <li key={i} className="flex gap-2">
          <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold" />
          <span>{it}</span>
        </li>
      ))}
    </ul>
  )
}
function Steps({ items }: { items: React.ReactNode[] }) {
  return (
    <ol className="space-y-1.5">
      {items.map((it, i) => (
        <li key={i} className="flex gap-2.5">
          <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gold/15 text-[11px] font-semibold text-gold">
            {i + 1}
          </span>
          <span>{it}</span>
        </li>
      ))}
    </ol>
  )
}
function Tip({ children }: { children: React.ReactNode }) {
  return (
    <p className="rounded-lg border border-gold/40 bg-gold/5 px-3 py-2.5 text-[13px]">
      <span className="font-semibold text-gold">💡 </span>
      {children}
    </p>
  )
}
