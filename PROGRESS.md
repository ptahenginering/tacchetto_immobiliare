# PROGRESS — RT CASA LIVE

| Task | Stato | Note |
|------|-------|------|
| T01 — Scaffolding monorepo | ✅ | backend composer (Slim4+DI+JWT+PHPMailer+Dompdf), customer/ e admin/ Vite+React-TS+Tailwind con tema RT, build OK; .env.example completo |
| T02 — Connection + migration runner | ✅ | PDO singleton pgsql, bin/migrate.php transazionale con schema_migrations |
| T03 — Migrazioni complete + seed | ✅ | 13 migrazioni idempotenti (001–013), seed agenzia RT + template step burocrazia in SQL; admin seed in PHP via Migrator::ensureAdminUser (bcrypt da ADMIN_DEFAULT_PASSWORD) |
| T04 — Kernel Slim | ✅ | index.php + PHP-DI, error handler JSON `{"error":{code,message}}`, CorsMiddleware da env, Logger su logs/app.log; testato /api/health e 404 in locale |
| T05 — Autenticazione | ✅ | POST /admin/login (JWT 8h) + /auth/refresh, AdminMiddleware/CustomerMiddleware su base JwtAuthMiddleware, LoginRateLimiter (10/15min per IP, tabella login_attempts, migrazione 014), JWT fail-fast senza secret; roundtrip testato |
| T06 — Magic link proprietari | ✅ | request-access (token 30min sha256, risposta anti-enumeration, rate limit) + verify (single-use → JWT owner 30gg); MailerInterface con LogOnlyMailer temporaneo (sostituito in T07) |
| T07 — MailService + BrevoService | ✅ | Cascata Brevo API → SMTP PHPMailer → log "disattivata"; 8 template HTML brand RT (testati con escaping); ogni invio in email_log; mai eccezioni verso il flusso applicativo |
| T08 — Endpoint pubblico lead | ✅ | POST /public/leads: honeypot `website`, throttle 5/h per IP (tabella request_throttle, migrazione 015), validazione 422 con campi, email nuovo_lead_admin + autoresponder opzionale (LEAD_AUTORESPONDER); docs/integrazione-sito.md con snippet |
| T09 — CRUD Leads (admin) | ✅ | List (filtri status/source/search/date + paginazione), dettaglio con appuntamenti, update parziale validato, convert→incarico transazionale (owner + property valutazione + seed step + benvenuto con magic link 7gg); gruppo /admin protetto da AdminMiddleware (401 verificato) |
| T10 — CRUD Properties (admin) | ✅ | CRUD completo, upload multipart con GD (resize 1600px, jpg/png/webp, max UPLOAD_MAX_MB, mime da byte reali), riordino/cover/delete immagini, auto-cover, serve file via /api/files con anti path-traversal, auto-seed practice_steps |
| T11 — CRUD Appointments (admin) | ✅ | CRUD con validazione FK lead/property, GET ?from&to per calendario; promemoria email gestito dal cron T15 (colonna reminder_sent_at) |
| T12 — CRUD Visits + feedback (admin) | ✅ | CRUD con visitor_label obbligatoria (mai dati personali), rating 1–5, toggle visible_to_owner; email nuovo_feedback/nuova_visita al proprietario solo se visibile (§6) |
| T13 — CRUD Proposals (admin) | ✅ | CRUD con pipeline stato, email nuova_proposta SENZA importo (solo invito app), toggle visible_to_owner |
| T14 — Marketing + practice steps (admin) | ✅ | CRUD attività promozione con stats JSONB editabili; update step burocrazia con completed_at automatico ed email step_completato (template aggiunto) se visibile |
| T15 — Scheduler | ✅ | bin/cron.php ogni 15min: promemoria appuntamenti giorno-prima (flag reminder_sent_at), report settimanale lunedì ≥8 (idempotente via cron_runs, migrazione 016), pulizia magic link/login_attempts/throttle |
| T16 — Statistiche admin | ✅ | overview KPI 30gg con delta % vs periodo precedente, leads-by-source con conversioni, performance serie settimanale 6 mesi zero-filled, funnel per immobile |
| T17 — Dashboard proprietario (API) | ✅ | GET /customer/property (owner-scoped a query) + /customer/property/kpi (KPI 30gg, InterestScoreService con formula documentata e trend up/down/flat, serie visite 8 settimane zero-filled); gruppo protetto da CustomerMiddleware |
| T18 — Feed cliente (API) | ✅ | /customer/visits, /proposals, /marketing, /practice-steps (con progress_pct), /timeline (UNION cronologico 5 tipi evento); tutto owner-scoped e filtrato visible_to_owner a query |
| T19 — Chatbot AI cliente | ✅ | AnthropicService (Messages API, modello da env, max_tokens 1000), system prompt italiano con dati reali immobile+KPI+step, persistenza chat_sessions/messages con tokens, throttle 30/15min, degradazione gentile senza chiave |
| T20 — Customer base app | ✅ | Router base /app (login, access, 7 route protette), client API con JWT localStorage e 401→login, layout mobile con bottom nav 5 voci + header monogramma, splash login navy con magic link, EmptyState e Skeleton brandizzati; build OK |
| T21 — Customer Dashboard Home | ✅ | Hero card immobile con badge stato oro, 4 KPI Playfair (incl. interesse % con trend e tooltip spiegazione), grafico area visite (recharts, dot oro), ultimi 3 riscontri, prossimi appuntamenti, bottone refresh, skeleton |
| T22 — Customer Visite & Feedback | ✅ | Lista cronologica con data, etichetta visitatore, badge qualificata, stelle oro, citazione feedback; empty state curato |
| T23 — Customer Proposte | ✅ | Card con importo Playfair € it-IT, stato con colori semantici, nota fissa "discussa personalmente con Roberto" |
| T24 — Customer Promozione | ✅ | Card per canale con stats leggibili (1.234 visualizzazioni · 8 contatti), link esterno, label canali |
| T25 — Customer Pratiche | ✅ | Stepper verticale (✓ oro/in corso/da fare), barra % completamento, copy rassicurante |
| T26 — Customer Assistente + Profilo | ✅ | Chat bolle navy/avorio con typing indicator e disclaimer, sessione persistente; profilo con contatti Roberto (tel/mailto) e logout |
| T27 — Admin base | ✅ | Login brandizzato, sidebar navy 9 voci + topbar con ricerca globale, Zustand (auth persist + UI), routing protetto, client API con 401→logout, tipi e label condivise; build OK |
| T28 — Admin Dashboard | ✅ | 4 KPI con delta %, line chart performance 6 mesi, donut lead per fonte, tabella ultimi lead, appuntamenti oggi/domani |
| T29 — Admin Lead | ✅ | Tabella con filtri/ricerca/paginazione server-side, drawer dettaglio con pipeline stato e note, wizard "Converti in incarico" (proprietario → immobile → benvenuto) |
| T30 — Admin Immobili | ✅ | Lista card con cover/stato/contatori, scheda a 7 tab (Dati, Foto drag&drop con riordino/cover/delete, Visite, Proposte, Marketing, Pratiche con toggle ciclico, Statistiche funnel); occhio visible_to_owner ovunque |
| T31 — Admin Appuntamenti | ✅ | Vista calendario settimanale + lista, creazione/modifica/eliminazione con modal, navigazione settimane |
| T32 — Admin Visite & Proposte | ✅ | Form visita con stelle+label visitatore e avviso privacy/visibilità; proposte con cambio stato pipeline inline e toggle occhio |
| T33 — Admin Marketing + AI | ✅ | Registro attività con stats; sezione "Genera con AI" (backend POST /admin/ai/generate con Anthropic, 4 tipi, salvataggio ai_generations) → anteprima → modifica → copia/bozza scheduled_posts; tabella post con badge "pubblicazione manuale" |
| T34 — Admin Statistiche + Impostazioni | ✅ | Statistiche con filtro periodo + export CSV client-side; impostazioni: dati agenzia, stato integrazioni da /admin/system/health, test email, gestione utenti staff (solo admin) |
| T35 — Asset grafici | ✅ | docs/brand/: monogramma chiaro/scuro, logo CASA LIVE con wordmark e payoff, placeholder immobile line-art, template email base HTML; favicon.svg + componente <RTLogo/> già nei frontend; copiati nei public |
| T36 — Seed dati demo | ✅ | bin/seed-demo.php idempotente: owner demo + immobile Silea in_vendita, 12 appuntamenti, 18 visite con feedback italiani realistici, 2 proposte, 6 attività marketing con stats, step a metà, 15 lead su 8 settimane |
| T37 — Test | ⏳ | |
| T38 — Hardening | ⏳ | |
| T39 — Deploy | ⏳ | |
| T40 — Consegna | ⏳ | |
