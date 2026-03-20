# ONBOARDING_DEVELOPER.md — Theja
> Prima di toccare il codice, leggi tutto questo documento.

## Ordine di lettura obbligatorio

1. `README.md`
2. `THEJA_MASTER.md` ← il più importante
3. `ARCHITETTURA_THEJA.md`
4. `MODELLO_COMMERCIALE.md`
5. `PIANO_SVILUPPO_THEJA.md`
6. `docs/adr/` — tutti gli ADR in ordine numerico
7. Questo file
8. `CONTRIBUTING.md`

---

## Prerequisiti

- PHP 8.3+
- Composer 2+
- Node.js 20+
- pnpm 8+
- Docker + Docker Compose
- Git

---

## Setup ambiente locale

### 1. Clone e dipendenze

```bash
git clone https://github.com/mavhart/theja.git
cd theja
pnpm install
cd apps/api && composer install
```

### 2. Variabili d'ambiente

```bash
cd apps/api
cp .env.example .env
php artisan key:generate
```

Variabili richieste in `.env`:
```
APP_NAME=Theja
APP_ENV=local

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=theja
DB_USERNAME=theja
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=theja
PUSHER_APP_KEY=theja-key
PUSHER_APP_SECRET=theja-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-south-1
AWS_BUCKET=theja-storage
```

### 3. Docker Compose (sviluppo locale)

```bash
# Dalla root del progetto
docker compose -f infra/docker/docker-compose.yml up -d

# Avvia: PostgreSQL 16, Redis 7, Soketi (WebSocket)
```

### 4. Database

```bash
cd apps/api
php artisan migrate
php artisan db:seed  # dati di test: 2 org, 3 POS, utenti con ruoli diversi
```

### 5. Avvio sviluppo

```bash
# Dalla root (avvia api + web in parallelo)
pnpm dev

# Oppure separatamente:
cd apps/api && php artisan serve      # http://localhost:8000
cd apps/web && pnpm dev               # http://localhost:3000
```

---

## Ambienti

| Ambiente | URL API | URL Web | DB |
|---|---|---|---|
| Local | localhost:8000 | localhost:3000 | Docker locale |
| Staging | api.staging.theja.it | staging.theja.it | AWS RDS staging |
| Production | api.theja.it | theja.it | AWS RDS prod |

Staging è sempre allineato a main. Deploy automatico via GitHub Actions su push a `main`.

---

## Workflow Git

Vedi `CONTRIBUTING.md` per dettagli completi.

```
main          → produzione (deploy automatico)
develop       → staging (deploy automatico)
feature/*     → sviluppo feature
fix/*         → bugfix
```

**Mai pushare direttamente su main o develop.**

---

## Struttura branch per fase

```bash
# Esempio: lavorare su Fase 1 - Auth
git checkout develop
git pull
git checkout -b feature/fase1-auth-multitenant

# Lavora, committa frequentemente
git commit -m "feat(auth): implementa ResolveTenant middleware"

# Apri PR verso develop quando completato
```

---

## Convenzioni commit

Seguire Conventional Commits:

```
feat(module): descrizione
fix(module): descrizione
refactor(module): descrizione
test(module): descrizione
docs(module): descrizione
chore: descrizione
```

Moduli: `auth`, `tenant`, `patients`, `inventory`, `sales`, `billing`, `agenda`, `reports`, `ai`

---

## Regola documentazione

**Nessun task è completo senza:**
- Aggiornare lo stato nel `PIANO_SVILUPPO_THEJA.md` (spuntare il checkbox)
- Aggiornare `ARCHITETTURA_THEJA.md` se la struttura DB o i servizi sono cambiati
- Aggiungere ADR in `docs/adr/` se è stata presa una decisione architetturale
- Aggiornare `THEJA_MASTER.md` se cambia qualcosa di fondamentale

---

## Tool di sviluppo

- **Cursor Pro** — sviluppo quotidiano feature per feature
  - Carica sempre `THEJA_MASTER.md` come contesto
  - Usa Agent Mode con claude-sonnet-4 per task complessi
- **Claude Code** — refactoring multi-file, architettura, migrazioni complesse
- **Claude.ai** — strategia, documentazione, decisioni commerciali

---

## Testing

```bash
cd apps/api

# Tutti i test
php artisan test

# Solo unit
php artisan test --testsuite=Unit

# Solo feature
php artisan test --testsuite=Feature

# Con coverage
php artisan test --coverage --min=80
```

```bash
cd apps/web

# Unit/integration
pnpm test

# E2E (Playwright)
pnpm test:e2e
```

---

## FAQ sviluppo

**Q: Come faccio a testare con due organization diverse in locale?**
A: Il seeder crea già 2 org con 3 POS e utenti diversi. Usa i token di test in `database/seeders/TestDataSeeder.php`.

**Q: Come switcho schema PostgreSQL in locale?**
A: Il middleware ResolveTenant lo fa automaticamente. In locale funziona allo stesso modo del prod.

**Q: Posso aggiungere dipendenze npm/composer?**
A: Sì, ma documenta il motivo in CONTRIBUTING.md e aggiorna SETUP.md se cambia il setup.

**Q: Come faccio un reset completo del DB in locale?**
A: `php artisan migrate:fresh --seed`
