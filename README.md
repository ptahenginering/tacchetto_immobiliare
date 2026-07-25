# RT CASA LIVE

> *"Trasparenza. Controllo. Risultati."* — La piattaforma di Roberto Tacchetto, Real Estate Advisor (network Tecnocasa, Treviso).

Due lati sullo stesso backend:

- **Area Cliente** (`/app/`) — webapp mobile-first per il proprietario che vende casa: KPI in tempo reale, indice di interesse, visite e riscontri, proposte, promozione, avanzamento pratiche, assistente AI. Accesso senza password via **magic link**.
- **Gestionale Agenzia** (`/admin/`) — CRM completo: lead con pipeline e conversione in incarico, immobili con foto, appuntamenti, visite/proposte, marketing con generazione testi AI, statistiche, impostazioni.

## Architettura

| Componente | Stack | Percorso |
|-----------|-------|----------|
| API | PHP 8.2 · Slim 4 · PHP-DI · JWT · PostgreSQL (PDO) | [backend/](backend/) → `/api` |
| Area Cliente | React 18 · Vite · TS · Tailwind · TanStack Query · recharts | [customer/](customer/) → `/app/` |
| Gestionale | React 18 · Vite · TS · Zustand · recharts | [admin/](admin/) → `/admin/` |
| Sito vetrina | HTML statico (già online) | [public_html/](public_html/) → `/` |
| Email | Brevo API → fallback SMTP → log (`email_log`) | 10 template HTML brand |
| AI | Anthropic Messages API (chatbot cliente + copywriting marketing) | `AnthropicService` |
| Deploy | GitHub Actions → FTP SiteGround + migrazioni remote | [.github/workflows/deploy.yml](.github/workflows/deploy.yml) |

Dettagli: [docs/deploy.md](docs/deploy.md) · [docs/integrazione-sito.md](docs/integrazione-sito.md) · [DECISIONS.md](DECISIONS.md) · [PROGRESS.md](PROGRESS.md)

## Avvio locale in 5 comandi

```bash
docker compose up -d postgres                              # 1. Postgres 16 (rtcasalive/rtcasalive)
cp .env.example backend/config/.env                        # 2. env (aggiungi JWT_SECRET: openssl rand -hex 32)
cd backend && composer install && php bin/migrate.php && php bin/seed-demo.php   # 3. deps + schema + dati demo
php -S localhost:8080 -t public public/index.php &         # 4. API su :8080
cd ../customer && npm i && npm run dev                     # 5. Area cliente su :5173 (admin: cd admin && npm run dev → :5174)
```

Login gestionale locale: `ADMIN_EMAIL` / `ADMIN_DEFAULT_PASSWORD` del tuo `.env`.
Accesso cliente demo: richiedi il magic link per `demo.proprietario@rtimmobiliare.it` (senza provider email il link resta in `email_log`… oppure guarda `magic_links` a DB).

## Test

```bash
cd backend && vendor/bin/phpunit        # 35 test (auth, magic link, lead, interest score, sicurezza owner-scoping)
cd customer && npm test                 # 6 test
cd admin && npm test                    # 3 test
```

## Scheduler

`backend/bin/cron.php` ogni 15 minuti (SiteGround Cron / crontab): promemoria appuntamenti del giorno dopo, report settimanale del lunedì ai proprietari, pulizia token/rate-limit.

## CHECKLIST CHIAVI (produzione)

Da fornire dal committente a fine sviluppo — come **GitHub Secrets** (il workflow genera il `.env` di produzione):

```
# Obbligatorie al primo deploy
FTP_HOST= FTP_USERNAME= FTP_PASSWORD=      # FTP SiteGround
DB_HOST=localhost DB_PORT=5432
DB_NAME= DB_USER= DB_PASSWORD=             # PostgreSQL produzione
JWT_SECRET=                                # generare: openssl rand -hex 32
MIGRATION_KEY=                             # generare: openssl rand -hex 24
ADMIN_EMAIL=admin@rtimmobiliare.it
ADMIN_DEFAULT_PASSWORD=                    # password primo accesso admin
APP_URL=https://tacchettoimmobiliare.it
VITE_API_BASE_URL=https://tacchettoimmobiliare.it/api
CORS_ALLOWED_ORIGINS=https://www.rtimmobiliare.it,https://rtimmobiliare.it,https://tacchettoimmobiliare.it
MAIL_FROM=info@rtimmobiliare.it
MAIL_FROM_NAME=Roberto Tacchetto — RT CASA LIVE
LEAD_AUTORESPONDER=false

# Attivano le rispettive feature (senza: degradazione controllata, mai crash)
BREVO_API_KEY=                             # email transazionali
ANTHROPIC_API_KEY=                         # chatbot + generazione testi AI
ANTHROPIC_MODEL=claude-sonnet-4-6
SMTP_HOST= SMTP_PORT=587 SMTP_USER= SMTP_PASS=   # opzionale, fallback email

# Predisposti per fasi future (lasciare vuoti)
META_APP_ID= META_APP_SECRET= LINKEDIN_CLIENT_ID= LINKEDIN_CLIENT_SECRET=
```
