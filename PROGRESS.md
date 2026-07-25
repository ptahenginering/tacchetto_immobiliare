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
| T09 — CRUD Leads (admin) | ⏳ | |
| T10 — CRUD Properties (admin) | ⏳ | |
| T11 — CRUD Appointments (admin) | ⏳ | |
| T12 — CRUD Visits + feedback (admin) | ⏳ | |
| T13 — CRUD Proposals (admin) | ⏳ | |
| T14 — Marketing + practice steps (admin) | ⏳ | |
| T15 — Scheduler | ⏳ | |
| T16 — Statistiche admin | ⏳ | |
| T17 — Dashboard proprietario (API) | ⏳ | |
| T18 — Feed cliente (API) | ⏳ | |
| T19 — Chatbot AI cliente | ⏳ | |
| T20 — Customer base app | ⏳ | |
| T21 — Customer Dashboard Home | ⏳ | |
| T22 — Customer Visite & Feedback | ⏳ | |
| T23 — Customer Proposte | ⏳ | |
| T24 — Customer Promozione | ⏳ | |
| T25 — Customer Pratiche | ⏳ | |
| T26 — Customer Assistente + Profilo | ⏳ | |
| T27 — Admin base | ⏳ | |
| T28 — Admin Dashboard | ⏳ | |
| T29 — Admin Lead | ⏳ | |
| T30 — Admin Immobili | ⏳ | |
| T31 — Admin Appuntamenti | ⏳ | |
| T32 — Admin Visite & Proposte | ⏳ | |
| T33 — Admin Marketing + AI | ⏳ | |
| T34 — Admin Statistiche + Impostazioni | ⏳ | |
| T35 — Asset grafici | ⏳ | |
| T36 — Seed dati demo | ⏳ | |
| T37 — Test | ⏳ | |
| T38 — Hardening | ⏳ | |
| T39 — Deploy | ⏳ | |
| T40 — Consegna | ⏳ | |
