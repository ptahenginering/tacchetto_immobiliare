# Deploy RT CASA LIVE

> Target di produzione: **SiteGround** (hosting condiviso con PostgreSQL gestito),
> dominio **tacchettoimmobiliare.it**, deploy automatico via **GitHub Actions + FTP**
> (vedi [DECISIONS.md](../DECISIONS.md) D02 — il VPS è documentato in fondo come alternativa).

## Architettura in produzione

> ⚠️ La root dell'account FTP SiteGround è la **home utente**, non il docroot:
> il workflow deploya quindi su `tacchettoimmobiliare.it/public_html/…`.

```
~/tacchettoimmobiliare.it/public_html/          ← docroot
├── index.html, img/, …      ← sito vetrina statico (repo: public_html/)
├── .htaccess                 ← HTTPS redirect (generato dal workflow)
├── app/                      ← Area Cliente (customer/dist, base /app/)
│   └── .htaccess             ← SPA fallback → /app/index.html
├── admin/                    ← Gestionale (admin/dist, base /admin/)
│   └── .htaccess             ← SPA fallback → /admin/index.html
└── api/                      ← Backend Slim (backend/, basePath /api)
    ├── .htaccess             ← rewrite → public/index.php, blocco config/log/bin
    ├── public/index.php
    ├── public/run-migrations.php  (protetto da MIGRATION_KEY)
    ├── config/.env           ← generato dal workflow dai GitHub Secrets
    └── storage/uploads/      ← immagini (escluse dal sync, .htaccess anti-PHP)
```

## GitHub Secrets da configurare

Repository → Settings → Secrets and variables → Actions:

| Secret | Valore |
|--------|--------|
| `FTP_HOST` | `ftp.tacchettoimmobiliare.it` |
| `FTP_USERNAME` | utente FTP SiteGround |
| `FTP_PASSWORD` | password FTP |
| `DB_HOST` | `localhost` (Postgres SiteGround è locale al server) |
| `DB_PORT` | `5432` |
| `DB_NAME` | nome database Postgres SiteGround |
| `DB_USER` | utente database |
| `DB_PASSWORD` | password database |
| `JWT_SECRET` | generare: `openssl rand -hex 32` |
| `MIGRATION_KEY` | generare: `openssl rand -hex 24` |
| `ADMIN_EMAIL` | `admin@rtimmobiliare.it` |
| `ADMIN_DEFAULT_PASSWORD` | password primo accesso gestionale |
| `APP_URL` | `https://tacchettoimmobiliare.it` |
| `VITE_API_BASE_URL` | `https://tacchettoimmobiliare.it/api` |
| `CORS_ALLOWED_ORIGINS` | `https://www.rtimmobiliare.it,https://rtimmobiliare.it,https://tacchettoimmobiliare.it` |
| `MAIL_FROM` / `MAIL_FROM_NAME` | `info@rtimmobiliare.it` / `Roberto Tacchetto — RT CASA LIVE` |
| `BREVO_API_KEY` | (quando disponibile — senza, le email finiscono in email_log come "disattivata") |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASS` | fallback SMTP opzionale |
| `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` | chatbot + AI marketing (`claude-sonnet-4-6`) |
| `LEAD_AUTORESPONDER` | `true`/`false` |

## Primo deploy

1. **SiteGround → Site Tools → Devs → PostgreSQL**: creare database e utente, annotare le credenziali.
2. Configurare tutti i **secrets** qui sopra.
3. `git push origin main` → il workflow esegue 4 job paralleli (sito, app, admin, api). Il job backend al termine:
   - chiama `https://…/api/public/run-migrations.php?key=MIGRATION_KEY` (crea schema + admin + protezioni uploads);
   - verifica `GET /api/health`.
4. **Cron** (Site Tools → Devs → Cron Jobs), ogni 15 minuti:
   ```
   php /home/<utente>/www/tacchettoimmobiliare.it/public_html/api/bin/cron.php
   ```
5. (Facoltativo, demo) da SSH SiteGround: `php public_html/api/bin/seed-demo.php`
6. Verifiche:
   - `https://tacchettoimmobiliare.it/api/health` → `{"status":"ok"}`
   - `https://tacchettoimmobiliare.it/admin/` → login con `ADMIN_EMAIL` / `ADMIN_DEFAULT_PASSWORD`
   - `https://tacchettoimmobiliare.it/app/` → richiesta magic link
7. Aggiornare il form del sito vetrina come da [integrazione-sito.md](integrazione-sito.md).

### Reset password admin
`…/api/public/run-migrations.php?key=MIGRATION_KEY&reset-admin=1` reimposta la password admin al valore di `ADMIN_DEFAULT_PASSWORD`.

## Sviluppo locale

```bash
docker compose up -d postgres          # Postgres 16 su localhost:5432 (rtcasalive/rtcasalive)
cp .env.example backend/config/.env    # DB_* già coerenti col compose, aggiungere JWT_SECRET
cd backend && composer install && php bin/migrate.php && php bin/seed-demo.php
php -S localhost:8080 -t public public/index.php
# in altri terminali:
cd customer && npm run dev             # http://localhost:5173 (proxy /api → 8080)
cd admin && npm run dev                # http://localhost:5174
```

Stack completo containerizzato (nginx + php-fpm): `docker compose --profile full up -d` con `docs/nginx-local.conf`.

## Alternativa VPS (nginx + rsync)

Se in futuro si migra su VPS, configurazione nginx equivalente:

```nginx
server {
    listen 443 ssl http2;
    server_name tacchettoimmobiliare.it;
    root /var/www/rt-casa-live/public_html;

    # API Slim
    location /api/ {
        alias /var/www/rt-casa-live/backend/public/;
        try_files $uri /api/index.php$is_args$args;
        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/rt-casa-live/backend/public/index.php;
        }
    }

    # SPA
    location /app/   { alias /var/www/rt-casa-live/customer/dist/; try_files $uri $uri/ /app/index.html; }
    location /admin/ { alias /var/www/rt-casa-live/admin/dist/;    try_files $uri $uri/ /admin/index.html; }

    location / { try_files $uri $uri/ =404; }
}
```

Deploy: sostituire i job FTP con `rsync -avz --delete` via SSH e lanciare `php bin/migrate.php` da remoto. HTTPS via `certbot --nginx`. Crontab identico (ogni 15 min su `bin/cron.php`).
