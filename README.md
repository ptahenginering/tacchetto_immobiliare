# RT CASA LIVE — Piattaforma Roberto Tacchetto Real Estate Advisor

> *"Trasparenza. Controllo. Risultati."*

Monorepo della piattaforma RT CASA LIVE + sito vetrina statico.

## Struttura

| Cartella | Contenuto |
|----------|-----------|
| `public_html/` | Sito vetrina statico (già online) |
| `backend/` | API — PHP Slim 4 + PostgreSQL, basePath `/api` |
| `customer/` | Area Cliente — React 18 + Vite, mobile-first, servita da `/app/` |
| `admin/` | Gestionale Agenzia — React 18 + Vite, servito da `/admin/` |
| `docs/` | Documentazione (deploy, integrazione sito, brand) |

## Avvio locale

```bash
# 1. Backend
cd backend && composer install
cp ../.env.example config/.env   # compila DB_* e JWT_SECRET

# 2. Database (con Docker) + migrazioni
docker compose up -d postgres
php bin/migrate.php

# 3. API dev server
php -S localhost:8080 -t public public/index.php

# 4. Frontend (in due terminali)
cd customer && npm install && npm run dev   # http://localhost:5173
cd admin && npm install && npm run dev      # http://localhost:5174
```

## Stato sviluppo

Vedi [PROGRESS.md](PROGRESS.md) per lo stato dei task e [DECISIONS.md](DECISIONS.md) per le decisioni architetturali.

*(README completo di architettura, deploy e checklist chiavi in arrivo con il task T40.)*
