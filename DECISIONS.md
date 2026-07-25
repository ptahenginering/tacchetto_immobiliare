# DECISIONS — RT CASA LIVE

Registro delle decisioni prese in autonomia durante lo sviluppo, come previsto dalla modalità operativa del prompt.

## D01 — Monorepo dentro il repo esistente `tacchetto_immobiliare`
Il prompt indica un monorepo `rt-casa-live/`. Il repo corrente (`tacchetto_immobiliare`, remote GitHub `ptahenginering/tacchetto_immobiliare`) è già attivo e contiene il sito vetrina statico in `public_html/`. La piattaforma viene sviluppata **nella root di questo repo** (`backend/`, `customer/`, `admin/`, `docs/`) mantenendo `public_html/` come sito vetrina. Un solo repo = un solo workflow di deploy.

## D02 — Deploy su SiteGround via FTP (non VPS Contabo)
Il prompt (T39) descrive un VPS Contabo con nginx + rsync. Il committente ha però fornito credenziali reali **SiteGround** (FTP `ftp.tacchettoimmobiliare.it` + PostgreSQL gestito) e ha chiesto esplicitamente il deploy "su SiteGround tramite action di GitHub" sul modello del progetto fantic-store-replica. Si adotta quindi:
- GitHub Actions con `SamKirkland/FTP-Deploy-Action` (job separati backend/customer/admin);
- migrazioni post-deploy via endpoint HTTP protetto `run-migrations.php?key=MIGRATION_KEY` (il runner CLI `bin/migrate.php` resta per locale/CI);
- `docs/deploy.md` documenta SiteGround; la variante VPS/nginx+docker-compose resta documentata come alternativa per sviluppo locale.
- Dominio di produzione: **tacchettoimmobiliare.it** (le URL `app.rtimmobiliare.it` del prompt diventano `https://tacchettoimmobiliare.it/app/` e `/admin/`; il sito vetrina resta sul dominio principale). Configurabile via env `APP_URL`.

## D03 — shadcn-ui: componenti implementati manualmente
La CLI di shadcn è interattiva e trascina dipendenze non necessarie. I componenti UI (Button, Card, Input, Dialog, Badge, ecc.) sono implementati manualmente nello stesso stile shadcn (Tailwind + CVA + Radix dove serve), con il tema navy/oro/avorio di §3 nel `tailwind.config`.

## D04 — Nessun PostgreSQL locale sulla macchina di sviluppo
`psql` non è disponibile in locale: le migrazioni sono verificate a livello di sintassi/lint e con i test PHPUnit su repository mockati; l'esecuzione reale avviene al primo deploy tramite l'endpoint migrazioni. Il `docker-compose.yml` (T39) permette comunque sviluppo locale completo a chi ha Docker.

## D05 — Node 18 in locale
La macchina ha Node 18.12 (Vite 5 richiede ≥18). Le GitHub Actions usano Node 20. Le dipendenze sono scelte compatibili con entrambi.
