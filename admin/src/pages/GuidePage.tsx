import { useState } from 'react'
import {
  BarChart3,
  BookOpen,
  CalendarDays,
  CalendarCheck,
  ChevronDown,
  HandCoins,
  Home,
  KeyRound,
  LayoutDashboard,
  LifeBuoy,
  Megaphone,
  Settings,
  ShieldCheck,
  Users,
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface Section {
  id: string
  icon: typeof Users
  title: string
  body: React.ReactNode
}

/** Guida utente completa del gestionale — una sezione per funzionalità. */
export default function GuidePage() {
  const [open, setOpen] = useState<string | null>('primo-accesso')

  const sections: Section[] = [
    {
      id: 'primo-accesso',
      icon: KeyRound,
      title: 'Primo accesso e account',
      body: (
        <>
          <P>
            Il gestionale si trova su <B>tacchettoimmobiliare.it/admin/</B>. Si accede con <B>email e password</B>:
            l'account amministratore iniziale è creato automaticamente al primo deploy con l'email e la password
            configurate nei secrets (<Code>ADMIN_EMAIL</Code> / <Code>ADMIN_DEFAULT_PASSWORD</Code>).
          </P>
          <Ul
            items={[
              'La sessione dura 8 ore: alla scadenza basta rifare il login.',
              'Dopo 10 tentativi falliti in 15 minuti l\'accesso viene bloccato temporaneamente per sicurezza.',
              'Per cambiare la tua password: Impostazioni → Utenti staff (un admin può reimpostarla), oppure chiedi al tecnico di usare il reset remoto (vedi sezione "Chiavi e configurazione").',
              'Esci sempre con "Esci" in fondo alla barra laterale se usi un computer condiviso.',
            ]}
          />
        </>
      ),
    },
    {
      id: 'dashboard',
      icon: LayoutDashboard,
      title: 'Dashboard',
      body: (
        <>
          <P>La Dashboard è il colpo d'occhio quotidiano sull'agenzia:</P>
          <Ul
            items={[
              '4 indicatori con confronto sui 30 giorni precedenti (freccia verde = in crescita): nuovi lead, contatti gestiti, incarichi, appuntamenti.',
              'Grafico "Performance 6 mesi": andamento settimanale di lead, visite e proposte.',
              'Grafico a ciambella "Lead per fonte": da dove arrivano i contatti (sito, QR, social, passaparola).',
              'Tabella "Ultimi lead": clic su un nome per aprirlo nel modulo Lead.',
              '"Oggi e domani": gli appuntamenti delle prossime 48 ore.',
            ]}
          />
        </>
      ),
    },
    {
      id: 'lead',
      icon: Users,
      title: 'Lead — dal contatto all\'incarico',
      body: (
        <>
          <P>
            Ogni richiesta dal form del sito arriva qui automaticamente (con notifica email). I lead si possono anche
            registrare a mano dopo una telefonata.
          </P>
          <Steps
            items={[
              'Filtra per stato, fonte o cerca per nome/email/telefono. La ricerca globale in alto porta sempre qui.',
              'Clicca su una riga per aprire il dettaglio: contatti cliccabili (telefono/email), messaggio originale, note interne.',
              'Aggiorna lo stato della pipeline: Nuovo → Contattato → Appuntamento → Incarico (o Perso, indicando il motivo).',
              'Quando il cliente firma: pulsante "Converti in incarico". Il wizard crea in un colpo solo il profilo del proprietario (con accesso all\'Area Cliente), l\'immobile in stato "valutazione" e la checklist burocrazia. Il proprietario riceve l\'email di benvenuto con il suo link personale.',
            ]}
          />
          <Tip>Per un lead di tipo "ereditato" la checklist includerà anche successione, accettazione eredità e volture.</Tip>
        </>
      ),
    },
    {
      id: 'immobili',
      icon: Home,
      title: 'Immobili — la scheda completa',
      body: (
        <>
          <P>Ogni immobile ha una scheda a schede (tab):</P>
          <Ul
            items={[
              'Dati: titolo, indirizzo, tipologia, mq, locali, prezzo, stato vendita, date incarico, descrizione.',
              'Foto: trascina i file nell\'area tratteggiata (JPG/PNG/WEBP, max 8 MB — vengono ottimizzate in automatico). Frecce per riordinare, stella per scegliere la copertina, cestino per eliminare.',
              'Visite / Proposte / Marketing: lo storico dell\'immobile con la possibilità di aggiungere nuovi elementi.',
              'Pratiche: clicca sul cerchio di uno step per farlo avanzare (da fare → in corso → completato). Al completamento il proprietario riceve una email.',
              'Statistiche: il funnel del singolo immobile (appuntamenti → visite → proposte) e la migliore offerta.',
            ]}
          />
          <Tip>
            L'icona <B>occhio</B> accanto a visite, proposte, attività marketing e step decide cosa vede il proprietario
            nella sua app. Occhio spento = elemento interno, invisibile al cliente e senza email.
          </Tip>
        </>
      ),
    },
    {
      id: 'appuntamenti',
      icon: CalendarDays,
      title: 'Appuntamenti',
      body: (
        <>
          <Ul
            items={[
              'Vista calendario settimanale + elenco: frecce per cambiare settimana, "Oggi" per tornare alla corrente.',
              '"Nuovo" per creare: tipo (valutazione/visita/firma), data e ora, contatto, immobile collegato, note.',
              'Clicca su un appuntamento per modificarlo, segnarlo svolto/annullato o eliminarlo.',
              'Promemoria automatico: il giorno prima il proprietario dell\'immobile riceve una email di promemoria (gestito dal sistema, nessuna azione richiesta).',
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
          <P>Dopo ogni visita, registrala qui: è il cuore della trasparenza verso il proprietario.</P>
          <Steps
            items={[
              '"Registra visita": scegli immobile, data/ora e una etichetta del visitatore.',
              'IMPORTANTE — privacy: l\'etichetta è ciò che vedrà il proprietario. Usa descrizioni generiche ("Coppia, prima casa", "Famiglia con bambini"), MAI nome e cognome del visitatore.',
              'Aggiungi il riscontro: stelle (1–5) e il commento raccolto.',
              'Se "Visibile al proprietario" è attivo, il cliente vede la visita nell\'app e riceve una email di notifica (senza dati sensibili).',
            ]}
          />
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
              '"Registra proposta": immobile, importo, data, note interne.',
              'Pipeline di stato direttamente dall\'elenco: Ricevuta → In trattativa → Accettata / Rifiutata / Ritirata.',
              'Il proprietario riceve una email che lo invita ad aprire l\'app: per policy l\'importo non viaggia MAI via email, lo vede solo dentro l\'area riservata.',
              'Nell\'app cliente ogni proposta riporta la nota "viene discussa personalmente con Roberto".',
            ]}
          />
        </>
      ),
    },
    {
      id: 'marketing',
      icon: Megaphone,
      title: 'Marketing + Genera con AI',
      body: (
        <>
          <P>
            <B>Registro attività</B>: traccia ogni pubblicazione (Immobiliare.it, Idealista, Facebook, Instagram,
            vetrina…) con visualizzazioni e contatti. È ciò che alimenta la pagina "Promozione" dell'app del cliente —
            aggiorna i numeri periodicamente, sono il tuo argomento di trasparenza.
          </P>
          <P>
            <B>Genera con AI</B> (richiede la chiave Anthropic configurata):
          </P>
          <Steps
            items={[
              'Scegli immobile e tipo di contenuto: annuncio portale, post social, descrizione breve o email di presentazione.',
              'Aggiungi eventuali indicazioni ("enfatizza il giardino") e premi Genera.',
              'Modifica liberamente l\'anteprima, poi "Copia negli appunti" per incollarla sul portale, oppure "Salva come bozza post".',
              'I post salvati compaiono in "Post programmati" con il badge "pubblicazione manuale": la pubblicazione automatica sui social arriverà in una fase successiva.',
            ]}
          />
        </>
      ),
    },
    {
      id: 'statistiche',
      icon: BarChart3,
      title: 'Statistiche',
      body: (
        <Ul
          items={[
            'Andamento settimanale di lead, visite e proposte con filtro periodo (8 settimane / 3 mesi / 6 mesi).',
            'Lead per fonte con conversioni in incarico: capisci quale canale rende di più.',
            '"Esporta CSV" scarica i dati del periodo per Excel.',
            'Il funnel del singolo immobile è nella sua scheda, tab Statistiche.',
          ]}
        />
      ),
    },
    {
      id: 'impostazioni',
      icon: Settings,
      title: 'Impostazioni: agenzia, utenti, test email',
      body: (
        <>
          <Ul
            items={[
              'Stato integrazioni: verde ✓ = configurata, rosso ✗ = chiave mancante (vedi sezione successiva per aggiungerle).',
              'Dati agenzia: nome, email e telefono usati nella piattaforma.',
              'Test invio email: inserisci il tuo indirizzo e verifica in un clic che le email partano davvero.',
              'Utenti staff (solo admin): crea collaboratori con ruolo "agent" (operano su tutto) o "admin" (gestiscono anche gli utenti). Attiva/disattiva un account con il pulsante di stato; la password si può reimpostare in modifica.',
            ]}
          />
        </>
      ),
    },
    {
      id: 'chiavi',
      icon: ShieldCheck,
      title: 'Chiavi e configurazione (Brevo, AI, email)',
      body: (
        <>
          <P>
            Le chiavi dei servizi esterni <B>non si inseriscono nel gestionale</B>: vivono nei{' '}
            <B>GitHub Secrets</B> del repository e vengono applicate al server ad ogni deploy (push su <Code>main</Code>).
            Percorso: <Code>GitHub → repository → Settings → Secrets and variables → Actions</Code>.
          </P>
          <Table
            rows={[
              ['BREVO_API_KEY', 'Email transazionali (magic link, notifiche, report). Si ottiene da app.brevo.com → SMTP & API → API Keys.'],
              ['ANTHROPIC_API_KEY', 'Chatbot dell\'area cliente + "Genera con AI". Si ottiene da console.anthropic.com → API Keys.'],
              ['SMTP_HOST/USER/PASS', 'Fallback email opzionale (es. SMTP SiteGround) se Brevo non è disponibile.'],
              ['ADMIN_DEFAULT_PASSWORD', 'Password dell\'admin iniziale. Cambiala qui e usa il reset remoto per applicarla.'],
            ]}
          />
          <Steps
            items={[
              'Aggiungi o aggiorna il secret su GitHub.',
              'Esegui un nuovo deploy: push su main oppure GitHub → Actions → "Deploy su SiteGround" → Run workflow.',
              'Verifica in Impostazioni → Stato integrazioni che la voce sia diventata ✓, e fai un "Test invio email".',
            ]}
          />
          <Tip>
            Senza chiavi la piattaforma non si rompe mai: le email vengono solo registrate (tabella email_log) e
            l'assistente AI risponde con un messaggio di cortesia. Reset password admin d'emergenza:{' '}
            <Code>/api/public/run-migrations.php?key=MIGRATION_KEY&amp;reset-admin=1</Code>.
          </Tip>
        </>
      ),
    },
    {
      id: 'assistenza',
      icon: LifeBuoy,
      title: 'Domande frequenti e assistenza',
      body: (
        <>
          <Ul
            items={[
              'Il proprietario non riceve il link di accesso → controlla che BREVO_API_KEY sia configurata (Impostazioni → Integrazioni) e che l\'email del proprietario sia corretta nella scheda immobile.',
              'Un elemento non compare nell\'app del cliente → controlla l\'icona occhio (visibilità) su quell\'elemento.',
              'Il form del sito non arriva nei Lead → verifica che il sito usi l\'endpoint /api/public/leads (vedi docs/integrazione-sito.md nel repository).',
              'I dati demo (utenti e immobile di esempio) si possono eliminare dai singoli moduli quando non servono più.',
            ]}
          />
        </>
      ),
    },
  ]

  return (
    <div className="max-w-3xl space-y-4">
      <div>
        <h1 className="flex items-center gap-2 font-display text-2xl text-navy">
          <BookOpen size={22} className="text-gold" /> Guida al gestionale
        </h1>
        <p className="mt-1 text-sm text-muted">
          Tutto quello che serve per usare RT CASA LIVE, funzione per funzione. Clicca una sezione per aprirla.
        </p>
      </div>

      <div className="space-y-2">
        {sections.map(({ id, icon: Icon, title, body }) => {
          const isOpen = open === id
          return (
            <div key={id} className="rt-card overflow-hidden">
              <button
                onClick={() => setOpen(isOpen ? null : id)}
                className="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left"
                aria-expanded={isOpen}
              >
                <span className="flex items-center gap-3">
                  <span className="flex h-9 w-9 items-center justify-center rounded-full bg-gold/15">
                    <Icon size={17} className="text-gold" strokeWidth={1.7} />
                  </span>
                  <span className="font-medium text-navy">{title}</span>
                </span>
                <ChevronDown size={17} className={cn('shrink-0 text-muted transition-transform', isOpen && 'rotate-180')} />
              </button>
              {isOpen && <div className="space-y-3 border-t border-navy/5 px-4 py-4 text-sm leading-relaxed text-navy/90">{body}</div>}
            </div>
          )
        })}
      </div>
    </div>
  )
}

/* ---- piccoli helper tipografici ---- */

function P({ children }: { children: React.ReactNode }) {
  return <p>{children}</p>
}
function B({ children }: { children: React.ReactNode }) {
  return <strong className="font-semibold text-navy">{children}</strong>
}
function Code({ children }: { children: React.ReactNode }) {
  return <code className="rounded bg-navy/5 px-1.5 py-0.5 text-[12px] text-navy">{children}</code>
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
function Table({ rows }: { rows: [string, string][] }) {
  return (
    <table className="w-full text-[13px]">
      <tbody>
        {rows.map(([key, desc]) => (
          <tr key={key} className="border-b border-navy/5 last:border-0">
            <td className="py-2 pr-3 align-top">
              <Code>{key}</Code>
            </td>
            <td className="py-2 text-navy/85">{desc}</td>
          </tr>
        ))}
      </tbody>
    </table>
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
