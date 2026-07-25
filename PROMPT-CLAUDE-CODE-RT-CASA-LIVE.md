# PROMPT PER CLAUDE CODE — PIATTAFORMA "RT CASA LIVE"

> Copia tutto il contenuto da qui in giù e incollalo in Claude Code.

---

## MODALITÀ OPERATIVA (LEGGI PRIMA DI TUTTO)

Lavori in **modalità completamente autonoma**: NON chiedere mai conferme o autorizzazioni tra un task e l'altro. Esegui i task nell'ordine indicato (T01 → T40), uno alla volta, fino al completamento. Per ogni task:

1. Implementa il codice completo (niente TODO, niente stub vuoti, niente "da completare").
2. Esegui/verifica quello che puoi in locale (sintassi PHP, `npm run build`, migrazioni su DB locale se disponibile).
3. Fai un commit atomico con messaggio `feat(TXX): descrizione` (oppure `fix`/`chore`).
4. Aggiorna il file `PROGRESS.md` in root con lo stato del task (✅/⏳) e note.
5. Passa al task successivo senza fermarti.

Se un servizio esterno richiede una chiave non ancora disponibile (Brevo, Anthropic, social), implementa comunque tutto il codice funzionante, leggi la chiave da env var e gestisci con grazia la sua assenza (feature disattivata + log chiaro, mai crash). A fine sviluppo produci l'elenco delle chiavi da inserire (task T40).

Se incontri una decisione ambigua, scegli tu la soluzione più semplice e coerente con questo documento, annotala in `DECISIONS.md` e prosegui. Non fermarti mai per chiedere.

---

## 1. CONTESTO E OBIETTIVO

**RT CASA LIVE** è la piattaforma di Roberto Tacchetto, Real Estate Advisor (network Tecnocasa, Treviso e provincia, sito vetrina statico già online su www.rtimmobiliare.it). Motto: *"Trasparenza. Controllo. Risultati."*

La piattaforma ha **due lati sullo stesso backend**:

- **Area Cliente** (`customer/`): webapp **mobile-first** riservata al proprietario che vende casa. Gli dà controllo in tempo reale sulla vendita del suo immobile: KPI, visite, feedback, proposte, promozione, pratiche burocratiche. È la "versione app" del sito, stessa identità visiva.
- **Gestionale Agenzia** (`admin/`): CRM completo per Roberto e i suoi collaboratori: lead, immobili, appuntamenti, visite, proposte, marketing con AI, statistiche.

**Vincoli confermati dal committente:**
- Single-tenant (solo Roberto) ma schema DB predisposto al multi-tenant: tabella `agencies` con un record e colonna `agency_id` su tutte le entità.
- Notifiche **solo via email** (Brevo). Nessuna integrazione WhatsApp di messaggistica.
- Il form pubblico del sito vetrina invierà i lead a questo backend (endpoint pubblico).
- Chatbot AI (Claude via API Anthropic) nell'area cliente.

---

## 2. STACK VINCOLANTE (NON DEROGARE)

Replica l'architettura del template consolidato del team (progetto "fantic-store-replica"):

### Database — PostgreSQL
- Connessione **PDO singleton** in `backend/src/Infrastructure/Database/Connection.php`.
- **Migrazioni SQL pure** in `backend/migrations/` numerate (`001_...sql`, `002_...sql`), **idempotenti** (`CREATE TABLE IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS`, ecc.) + runner PHP `backend/bin/migrate.php` che le esegue in ordine e registra quelle applicate in tabella `schema_migrations`.
- Config via env: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`.

### Backend PHP — Slim 4 (PHP ≥ 8.1)
- Cartella `backend/`, entry point `backend/public/index.php`, **basePath `/api`**.
- Slim 4 + PHP-DI + `firebase/php-jwt` + PHPMailer + Dompdf (per PDF report). Niente PayPal in questo progetto.
- Route in `backend/config/routes.php`, divise in 3 gruppi: **pubbliche**, **customer** (JWT ruolo `owner`, `CustomerMiddleware`), **admin** (JWT ruolo `admin`/`agent`, `AdminMiddleware`).
- Struttura a layer: `src/Application/Actions/...` (una classe per endpoint), `src/Domain/...` (entità + repository interface), `src/Infrastructure/...` (repository PDO, servizi esterni: `BrevoService`, `AnthropicService`, `MailService`, `PdfService`).
- Integrazioni: **Brevo** (email transazionali + contatti CRM), **Anthropic API** (chatbot + generazione testi AI).

### Frontend Area Cliente — `customer/` (React 18 + Vite 5 + TypeScript)
- Tailwind + shadcn-ui/Radix, React Router v6, TanStack Query, react-hook-form + zod, recharts.
- Build `npm run build` → `customer/dist/`, base path `/app/`.
- Env: `VITE_API_BASE_URL`.
- **Mobile-first**: progettata per smartphone, gradevole anche su desktop.

### Frontend Gestionale — `admin/` (React 18 + Vite 5 + TypeScript)
- SPA distinta: Zustand per lo stato, recharts per i grafici, Tailwind + shadcn-ui, base path `/admin/`, build → `admin/dist/`.
- Login `POST /api/admin/login` (JWT). Desktop-first ma responsive.

### Repo
Monorepo `rt-casa-live/` con `backend/`, `customer/`, `admin/`, `docs/`, `PROGRESS.md`, `DECISIONS.md`, `README.md`, `.env.example` completo.

---

## 3. DESIGN SYSTEM (COERENTE COL SITO VETRINA ESISTENTE)

Il sito vetrina usa questa identità: replicala fedelmente nell'Area Cliente; nel Gestionale usala in versione più sobria/funzionale.

**Palette (CSS custom properties / Tailwind theme):**
- `--navy-deep: #0E1B2E` (sfondi scuri, header)
- `--navy: #16273F` (pannelli, testo su chiaro)
- `--navy-panel: #1C3050`
- `--gold: #C29B52` (accento primario, CTA, icone)
- `--gold-light: #DCC28C`
- `--ivory: #F6F2EA` (sfondo chiaro principale) e `--ivory-soft: #FBF9F4`
- Grigio testo secondario `#5C6B80`; radius `14px`; ombra `0 24px 60px rgba(14,27,46,.18)`.
- Semantici aggiuntivi per la piattaforma: successo `#2E7D5B`, errore `#B3402E`, warning `#C98A2B`, info `#3E6B9E`.

**Tipografia (Google Fonts):** `Playfair Display` (titoli, numeri KPI grandi), `Jost` (testi UI, pesi 300–600), `Allura` (solo micro-accenti decorativi tipo firma — usala pochissimo, mai nel gestionale).

**Logo/monogramma RT:** generalo tu come componente React `<RTLogo/>` e come SVG statico: due lettere serif sovrapposte, "R" navy (bianca su fondo scuro) e "T" oro spostata a sinistra di ~0.34em e in basso di ~0.10em. Variante "CASA LIVE": monogramma + wordmark "CASA <span oro>LIVE</span>" in Jost maiuscolo spaziato. Genera anche `favicon.svg` (badge navy arrotondato con RT).

**Pattern UI ricorrenti:** eyebrow maiuscole oro con hairline laterali (`— TESTO —`), card con bordo `rgba(194,155,82,.3)`, bottoni pill (gradient oro `#D3AE66→#B98F45` testo navy per primari; outline oro per secondari), stati vuoti curati con icona line-art oro e testo guida. Icone: `lucide-react`, stroke sottile.

**Accessibilità:** focus visibile oro, contrasto AA, `prefers-reduced-motion` rispettato, touch target ≥ 44px nell'Area Cliente.

---

## 4. SCHEMA DATABASE (implementalo in migrazioni ordinate)

Tutte le tabelle con `id BIGSERIAL PK`, `agency_id BIGINT NOT NULL REFERENCES agencies(id)`, `created_at`/`updated_at TIMESTAMPTZ DEFAULT now()`. Indici su tutte le FK e sui campi di ricerca. Enum come `VARCHAR` + CHECK constraint.

1. **agencies** — `name`, `email`, `phone`, `logo_url`, `primary_color`, `settings JSONB`. Seed: 1 record "RT — Roberto Tacchetto Real Estate Advisor".
2. **users** — `role` CHECK in (`admin`,`agent`,`owner`), `first_name`, `last_name`, `email UNIQUE`, `phone`, `password_hash NULLABLE` (i proprietari non hanno password), `is_active`, `last_login_at`.
3. **magic_links** — `user_id`, `token_hash`, `expires_at`, `used_at`. (Login proprietari senza password.)
4. **leads** — `first_name`, `last_name`, `email`, `phone`, `request_type` CHECK in (`vendere`,`ereditato`,`altro`), `message`, `source` CHECK in (`sito`,`qr`,`social`,`referral`,`altro`), `status` CHECK in (`nuovo`,`contattato`,`appuntamento`,`incarico`,`perso`), `assigned_to (users)`, `notes`, `converted_property_id NULLABLE`, `lost_reason NULLABLE`.
5. **properties** — `owner_user_id (users)`, `title`, `address`, `city`, `province`, `type` (`appartamento`,`casa`,`villa`,`terreno`,`commerciale`,`altro`), `surface_sqm`, `rooms`, `price NUMERIC(12,2) NULLABLE`, `status` CHECK in (`valutazione`,`in_vendita`,`in_trattativa`,`venduto`,`sospeso`), `cover_image_url`, `description`, `mandate_start DATE`, `mandate_end DATE`.
6. **property_images** — `property_id`, `url`, `sort_order`, `is_ai_generated BOOL`.
7. **appointments** — `property_id NULLABLE`, `lead_id NULLABLE`, `type` (`valutazione`,`visita`,`firma`,`altro`), `starts_at`, `ends_at`, `contact_name`, `contact_phone`, `status` (`programmato`,`svolto`,`annullato`), `notes`.
8. **visits** — `property_id`, `appointment_id NULLABLE`, `visited_at`, `visitor_label` (es. "Coppia, prima casa" — MAI dati personali completi del visitatore verso il proprietario), `qualified BOOL DEFAULT true`, `feedback_text`, `feedback_rating SMALLINT 1–5 NULLABLE`, `visible_to_owner BOOL DEFAULT true`.
9. **proposals** — `property_id`, `amount NUMERIC(12,2)`, `status` (`ricevuta`,`in_trattativa`,`accettata`,`rifiutata`,`ritirata`), `received_at`, `notes`, `visible_to_owner BOOL DEFAULT true`.
10. **marketing_activities** — `property_id`, `channel` (`immobiliare_it`,`idealista`,`facebook`,`instagram`,`linkedin`,`vetrina`,`portale_tecnocasa`,`altro`), `activity_type` (`pubblicazione`,`campagna`,`post`,`open_house`,`altro`), `title`, `url NULLABLE`, `published_at`, `stats JSONB` (es. `{"views":1234,"contacts":8}`), `visible_to_owner BOOL DEFAULT true`.
11. **practice_steps** — checklist burocrazia per immobile: `property_id`, `step_key`, `label`, `status` (`da_fare`,`in_corso`,`completato`), `sort_order`, `completed_at`, `visible_to_owner BOOL DEFAULT true`. Seed automatico alla creazione immobile: documenti catastali, APE, conformità urbanistica, atto di provenienza, (se ereditato: dichiarazione di successione, accettazione eredità, volture), proposta/preliminare, rogito.
12. **email_log** — `to_email`, `template_key`, `subject`, `status` (`inviata`,`errore`,`disattivata`), `error_text`, `related_type`/`related_id`.
13. **chat_sessions** / **chat_messages** — sessioni chatbot: `user_id NULLABLE`, `property_id NULLABLE`; messaggi con `role` (`user`,`assistant`), `content`, `tokens_used`.
14. **ai_generations** — log generazioni AI: `kind` (`annuncio`,`post_social`,`descrizione`,`email`), `property_id NULLABLE`, `prompt`, `output`, `model`, `accepted BOOL`.
15. **social_accounts** — predisposizione fase social: `channel`, `account_name`, `credentials JSONB`, `is_active BOOL DEFAULT false`.
16. **scheduled_posts** — `property_id`, `channel`, `content`, `image_url`, `scheduled_at`, `status` (`bozza`,`programmato`,`pubblicato`,`errore`).
17. **schema_migrations** — gestita dal runner.

**Indice di interesse (%)** — calcolato, non salvato: funzione backend `InterestScoreService` documentata nel codice:
`score = min(100, round( visite_30gg*6 + feedback_positivi(rating>=4)*8 + proposte_attive*25 + appuntamenti_futuri*5 ))`, con trend = confronto vs 30 giorni precedenti (`up`/`down`/`flat`).

---

## 5. ELENCO COMPLETO DEI TASK

### FASE A — Fondamenta (T01–T07)

- **T01 — Scaffolding monorepo.** Crea struttura `backend/`, `customer/`, `admin/`, `docs/`, `PROGRESS.md`, `DECISIONS.md`, `README.md`, `.gitignore`, `.env.example` (TUTTE le env di §7). Backend: composer con slim/slim, slim/psr7, php-di/php-di, firebase/php-jwt, phpmailer/phpmailer, dompdf/dompdf, vlucas/phpdotenv. Frontend: due app Vite React-TS con Tailwind, shadcn-ui inizializzato, tema colori/font di §3 configurato nel `tailwind.config`.
- **T02 — Connection + migration runner.** `Connection.php` PDO singleton (identico pattern del template), `bin/migrate.php`, tabella `schema_migrations`.
- **T03 — Migrazioni complete** dello schema §4 + seed: agenzia RT, utente admin (email `admin@rtimmobiliare.it`, password da env `ADMIN_DEFAULT_PASSWORD`, hash bcrypt), step burocrazia template.
- **T04 — Kernel Slim.** `public/index.php`, container PHP-DI, error handler JSON uniforme (`{"error":{"code","message"}}`), CORS middleware configurabile via env `CORS_ALLOWED_ORIGINS` (default: `https://www.rtimmobiliare.it,https://rtimmobiliare.it,http://localhost:5173,http://localhost:5174`), logging su file `backend/logs/app.log`.
- **T05 — Autenticazione.** `POST /api/admin/login` (email+password → JWT 8h con `role`, `uid`, `agency_id`); `AdminMiddleware` (ruoli admin/agent) e `CustomerMiddleware` (ruolo owner). Refresh: `POST /api/auth/refresh`. Rate limiting semplice su login (max 10 tentativi/15min per IP, storage su tabella `login_attempts` — aggiungi migrazione).
- **T06 — Magic link proprietari.** `POST /api/customer/request-access` (email → se esiste owner attivo, genera token 30min, invia email Brevo con link `https://app.rtimmobiliare.it/app/access?token=...`); `POST /api/customer/verify` (token → JWT owner 30 giorni). Risposta identica anche se l'email non esiste (no user enumeration).
- **T07 — MailService + BrevoService.** Astrazione `MailService`: usa API Brevo se `BREVO_API_KEY` presente, altrimenti fallback SMTP PHPMailer (`SMTP_*` env), altrimenti logga in `email_log` con status `disattivata`. Template email HTML brandizzati RT (layout base navy/oro/avorio con monogramma, responsive): `magic_link`, `nuovo_lead_admin`, `benvenuto_proprietario`, `nuova_visita`, `nuovo_feedback`, `nuova_proposta`, `report_settimanale`, `promemoria_appuntamento`. Ogni invio registrato in `email_log`.

### FASE B — API Core CRM (T08–T16)

- **T08 — Endpoint pubblico lead.** `POST /api/public/leads`: validazione server-side, honeypot field `website` (se compilato → 200 fake e scarta), rate limit per IP, salva lead con `source`, invia email `nuovo_lead_admin` a Roberto + (opz. env) autoresponder al lead. Questo endpoint sostituirà il mailto del form del sito vetrina: documenta in `docs/integrazione-sito.md` lo snippet fetch() da incollare nel sito.
- **T09 — CRUD Leads (admin).** List con filtri (status, source, ricerca testo, date), dettaglio, update status/assegnazione/note, conversione lead→incarico (`POST /api/admin/leads/{id}/convert`: crea/collega user owner + property in stato `valutazione`, invia email `benvenuto_proprietario` con magic link).
- **T10 — CRUD Properties (admin).** CRUD completo + upload immagini (multipart, salvataggio su `backend/storage/uploads/properties/{id}/`, resize/ottimizzazione con GD a max 1600px, servite via route statica `/api/files/...`), gestione stato, associazione proprietario, auto-seed `practice_steps`.
- **T11 — CRUD Appointments (admin).** CRUD + vista calendario dati (`GET /api/admin/appointments?from&to`), promemoria email automatico il giorno prima (vedi scheduler T15).
- **T12 — CRUD Visits + feedback (admin).** CRUD; alla creazione con `feedback_text` → email `nuovo_feedback` al proprietario; flag `visible_to_owner`.
- **T13 — CRUD Proposals (admin).** CRUD; alla creazione → email `nuova_proposta` al proprietario (senza importo nell'email, solo invito ad accedere all'app).
- **T14 — Marketing activities + practice steps (admin).** CRUD attività promozione con `stats` editabili; update stato step burocrazia.
- **T15 — Scheduler.** `backend/bin/cron.php` (da crontab ogni 15 min) con job: promemoria appuntamenti (giorno prima), `report_settimanale` ai proprietari con immobile `in_vendita`/`in_trattativa` (lunedì 8:00, riassunto KPI 7gg + link app), pulizia magic link scaduti. Idempotente (tabella `cron_runs`).
- **T16 — Statistiche admin.** `GET /api/admin/stats/overview` (KPI 30gg con delta % vs periodo precedente: nuovi lead, contatti gestiti, incarichi, appuntamenti), `GET /api/admin/stats/leads-by-source`, `GET /api/admin/stats/performance` (serie temporale lead/visite/proposte per settimana, ultimi 6 mesi), `GET /api/admin/stats/property/{id}` (funnel del singolo immobile).

### FASE C — API Area Cliente (T17–T19)

- **T17 — Dashboard proprietario.** `GET /api/customer/property` (l'immobile del proprietario loggato: dati, cover, stato) + `GET /api/customer/property/kpi`: appuntamenti 30gg, visite 30gg, proposte in trattativa, interest score % + trend (InterestScoreService), serie andamento visite per settimana (ultime 8 settimane).
- **T18 — Feed cliente.** `GET /api/customer/visits` (solo `visible_to_owner`, con feedback), `GET /api/customer/proposals`, `GET /api/customer/marketing` (dove/come promosso, con stats), `GET /api/customer/practice-steps` (avanzamento burocrazia), `GET /api/customer/timeline` (feed cronologico unificato di tutti gli eventi visibili).
- **T19 — Chatbot AI cliente.** `POST /api/customer/chat` → `AnthropicService` (env `ANTHROPIC_API_KEY`, modello da env `ANTHROPIC_MODEL` default `claude-sonnet-4-6`, max_tokens 1000). System prompt: assistente di RT CASA LIVE, tono professionale e rassicurante in italiano, riceve nel contesto i dati REALI dell'immobile del cliente (stato, KPI, prossimi step burocrazia) e risponde solo su temi della vendita; per richieste fuori ambito o di consulenza legale/fiscale invita a contattare Roberto (+39 345 7771822). Persistenza in `chat_sessions`/`chat_messages`. Se manca la chiave → risposta gentile "assistente momentaneamente non disponibile".

### FASE D — Frontend Area Cliente `customer/` (T20–T26) — MOBILE-FIRST

- **T20 — Base app.** Router (`/app/access`, `/app/login`, `/app` dashboard, `/app/visite`, `/app/proposte`, `/app/promozione`, `/app/pratiche`, `/app/assistente`, `/app/profilo`), TanStack Query + client API con JWT in localStorage e interceptor 401→login, layout mobile con **bottom navigation** 5 voci (Home, Visite, Proposte, Promozione, Assistente) + header con monogramma RT e menu profilo. Splash/login page elegante: fondo navy, monogramma grande, input email, messaggio "Ti abbiamo inviato un link di accesso".
- **T21 — Dashboard Home.** Hero card immobile (cover, indirizzo, badge stato oro), griglia 4 KPI (numeri grandi Playfair: Appuntamenti, Visite, Proposte, Interesse % con freccia trend), grafico andamento visite (recharts, linea navy con dot oro, area sfumata), sezione "Ultimi feedback" (3 card citazione + link Vedi tutti), sezione "Prossimi appuntamenti". Pull-to-refresh o bottone refresh. Skeleton loader brandizzati.
- **T22 — Pagina Visite & Feedback.** Lista cronologica card: data, etichetta visitatore, badge "qualificata", rating stelle oro, testo feedback. Empty state: "Le prime visite appariranno qui".
- **T23 — Pagina Proposte.** Card proposta con importo (Playfair grande), stato con colore semantico, cronologia. Nota fissa: "Ogni proposta viene discussa personalmente con Roberto".
- **T24 — Pagina Promozione ("Dove viene promossa la tua casa").** Card per canale con logo/nome canale, tipo attività, data, stats leggibili (es. "1.234 visualizzazioni · 8 contatti"), link se presente. È la funzionalità "trasparenza totale": curala.
- **T25 — Pagina Pratiche (burocrazia).** Stepper verticale degli step con stato (fatto ✓ oro / in corso / da fare), percentuale completamento in alto. Testo rassicurante: "Ci occupiamo noi di ogni passaggio".
- **T26 — Assistente AI + Profilo.** Chat UI (bolle navy/avorio, input fisso in basso, indicatore "sta scrivendo"), disclaimer iniziale ("Assistente digitale di RT — per parlare con Roberto: tel/email"). Profilo: dati utente, contatti diretti Roberto ben visibili (tel: e mailto:), logout.

### FASE E — Frontend Gestionale `admin/` (T27–T34)

- **T27 — Base admin.** Login page brandizzata, layout sidebar navy (voci: Dashboard, Lead, Immobili, Appuntamenti, Visite, Proposte, Marketing, Statistiche, Impostazioni) + topbar con ricerca globale e utente, Zustand per auth/UI state, routing protetto.
- **T28 — Dashboard.** 4 KPI card con delta % (recharts sparkline), grafico performance 6 mesi, donut "Lead per fonte", tabella "Ultimi lead" con azioni rapide, lista appuntamenti di oggi/domani.
- **T29 — Modulo Lead.** Tabella con filtri/ricerca/paginazione server-side, drawer dettaglio, cambio stato a pipeline (kanban opzionale: colonne per status con drag&drop), note, bottone "Converti in incarico" (wizard: dati proprietario → crea immobile → invia benvenuto).
- **T30 — Modulo Immobili.** Lista card con cover e stato; scheda immobile a tab: Dati, Foto (upload drag&drop con riordino), Visite, Proposte, Marketing, Pratiche (toggle stato step), Statistiche immobile (funnel). Toggle `visible_to_owner` sui singoli elementi con icona occhio.
- **T31 — Modulo Appuntamenti.** Vista calendario settimanale/mensile + lista, creazione rapida da lead o immobile, stati.
- **T32 — Moduli Visite & Proposte.** Form inserimento visita con feedback (rating stelle + testo + label visitatore) — spiega in UI che il feedback sarà visibile al cliente se il toggle è attivo; gestione proposte con pipeline stato.
- **T33 — Modulo Marketing + AI.** Registro attività promozione per immobile (con campo stats). Sezione "Genera con AI": scegli immobile → `POST /api/admin/ai/generate` (Anthropic; tipi: annuncio portale, post social, descrizione breve) → anteprima → modifica → salva in `ai_generations` e copia negli appunti / crea `scheduled_posts` come bozza. Tabella post programmati (predisposizione: la pubblicazione reale sui social arriverà in fase successiva; mostra badge "pubblicazione manuale" con bottone copia contenuto).
- **T34 — Statistiche + Impostazioni.** Pagina statistiche complete (grafici recharts su endpoint T16, filtro periodo, export CSV client-side). Impostazioni: dati agenzia, gestione utenti staff, test invio email, stato integrazioni (Brevo/Anthropic: configurata ✓ / mancante ✗ letto da `GET /api/admin/system/health`).

### FASE F — Qualità, deploy, consegna (T35–T40)

- **T35 — Asset grafici.** Genera in `docs/brand/` e nei public dei frontend: `rt-monogram.svg` (chiaro/scuro), `rt-casa-live-logo.svg` (monogramma + wordmark), `favicon.svg`, template email HTML base, immagine placeholder immobile elegante (SVG line-art casa su fondo avorio con accento oro).
- **T36 — Seed dati demo.** `backend/bin/seed-demo.php`: 1 proprietario demo con immobile "in_vendita" a Silea, 12 appuntamenti, 18 visite con feedback realistici in italiano, 2 proposte, 6 attività marketing con stats, step burocrazia a metà, 15 lead in vari stati distribuiti su 8 settimane — così entrambe le UI si presentano piene per la demo a Roberto.
- **T37 — Test.** PHPUnit sul backend: auth (login, magic link, middleware), endpoint pubblico lead (validazione, honeypot), InterestScoreService, conversione lead→incarico, visibilità dati cliente (un owner NON può vedere immobili altrui — test di sicurezza esplicito). Vitest minimi sui frontend (render pagine principali con mock API).
- **T38 — Hardening.** Verifica: prepared statements ovunque, escaping output, JWT secret obbligatorio (fail fast se `JWT_SECRET` mancante), upload limitati a jpg/png/webp max 8MB, security headers, nessun dato personale dei visitatori esposto lato cliente, CORS ristretto.
- **T39 — Deploy.** `docs/deploy.md`: setup VPS Contabo (nginx config completa fornita: `api.` o path `/api` → PHP-FPM; `/app/` → customer/dist; `/admin/` → admin/dist; HTTPS via certbot), crontab per `cron.php`, workflow GitHub Actions `.github/workflows/deploy.yml` (build frontends, rsync su VPS, `composer install --no-dev`, `php bin/migrate.php`). Includi anche `docker-compose.yml` per sviluppo locale (php-fpm, nginx, postgres).
- **T40 — Consegna.** `README.md` finale con: avvio locale in 5 comandi, architettura, e la **CHECKLIST CHIAVI** da compilare a fine sviluppo:

```
# Da fornire dal committente a fine sviluppo (nel file .env di produzione):
BREVO_API_KEY=            # email transazionali
ANTHROPIC_API_KEY=        # chatbot + generazione testi AI
JWT_SECRET=               # generare: openssl rand -hex 32
ADMIN_DEFAULT_PASSWORD=   # password primo accesso admin
DB_PASSWORD=              # PostgreSQL produzione
SMTP_HOST/USER/PASS=      # opzionale, fallback email
# Predisposti per fasi future (lasciare vuoti):
META_APP_ID= META_APP_SECRET= LINKEDIN_CLIENT_ID= LINKEDIN_CLIENT_SECRET=
```

---

## 6. REGOLE FUNZIONALI TRASVERSALI

- Tutta la UI e tutte le email sono **in italiano**. Date formato `dd/mm/yyyy`, valuta `€ 1.234,56`.
- Ogni evento rilevante per il proprietario (visita, feedback, proposta, step completato) genera email SOLO se l'elemento è `visible_to_owner = true`.
- L'email non contiene mai dati sensibili di terzi né importi: invita ad aprire l'app (deep link alla pagina giusta).
- Il proprietario vede esclusivamente il proprio immobile e i dati flaggati visibili. Enforcement a livello di query, non solo di UI.
- Percentuale interesse: mostra sempre anche la spiegazione "come lo calcoliamo" in un tooltip/info (trasparenza = posizionamento del prodotto).
- Copy UI: tono professionale, rassicurante, concreto. Riprendi il lessico del brand: "Trasparenza. Controllo. Risultati.", "La tua casa. Il tuo futuro. Il mio impegno.", "Sempre informato, sempre sereno."

## 7. ENV COMPLETO (`.env.example`)

```
APP_ENV=local
APP_URL=https://app.rtimmobiliare.it
CORS_ALLOWED_ORIGINS=https://www.rtimmobiliare.it,https://rtimmobiliare.it,http://localhost:5173,http://localhost:5174
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=rtcasalive
DB_USER=rtcasalive
DB_PASSWORD=
JWT_SECRET=
JWT_TTL_ADMIN=28800
JWT_TTL_OWNER=2592000
ADMIN_DEFAULT_PASSWORD=
BREVO_API_KEY=
MAIL_FROM=info@rtimmobiliare.it
MAIL_FROM_NAME="Roberto Tacchetto — RT CASA LIVE"
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6
UPLOAD_MAX_MB=8
META_APP_ID=
META_APP_SECRET=
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
```

**Inizia ora dal task T01 e procedi in autonomia fino al T40.**
