# FASE0_STATUS.md — Theja
> Stato attuale: FASE 0 — Infrastruttura (🟡 in corso — 95% completata)

---

## Obiettivo Fase 0

Ambiente di sviluppo funzionante, struttura repository corretta, CI/CD base, staging AWS attivo.

---

## Checklist Fase 0

### Repository e documentazione
- [x] Repository Git creato (github.com/mavhart/theja)
- [x] README.md
- [x] THEJA_MASTER.md
- [x] ARCHITETTURA_THEJA.md
- [x] MODELLO_COMMERCIALE.md
- [x] PIANO_SVILUPPO_THEJA.md
- [x] ONBOARDING_DEVELOPER.md
- [x] CONTRIBUTING.md
- [x] docs/adr/ (ADR-000 → ADR-006)

### Struttura monorepo
- [x] pnpm workspace configurato (pnpm-workspace.yaml) — 2026-03-20
- [x] package.json root con scripts dev/build/test/type-check/lint + concurrently — 2026-03-20
- [x] apps/api — Laravel 11.6 fresh install (PHP 8.5, Composer 2.9) — 2026-03-20
  - [x] laravel/sanctum ^4.3 — 2026-03-20
  - [x] spatie/laravel-permission ^6.25 — 2026-03-20
  - [x] spatie/laravel-activitylog ^4.12 — 2026-03-20
  - [x] apps/api/.env.example completo e documentato — 2026-03-20
  - [x] apps/api/.env.testing con PostgreSQL database theja_test — 2026-03-20
  - [x] apps/api/package.json (@theja/api) — 2026-03-20
- [x] apps/web — Next.js 14.2 fresh install con TypeScript + Tailwind + App Router + ESLint — 2026-03-20
  - [x] Shadcn/ui inizializzato (components.json, lib/utils.ts, components/ui/button.tsx) — 2026-03-20
  - [x] next-pwa ^5.6 installato e configurato in next.config.mjs — 2026-03-20
  - [x] public/manifest.json PWA — 2026-03-20
  - [x] apps/web/.env.example con NEXT_PUBLIC_API_URL e NEXT_PUBLIC_SOKETI_KEY — 2026-03-20
- [x] packages/shared — struttura base tipi TypeScript condivisi (@theja/shared) — 2026-03-20
  - [x] src/types/index.ts con tipi Organization, PointOfSale, User, Role, ApiResponse — 2026-03-20
- [x] .gitignore root corretto per monorepo Laravel + Next.js + pnpm — 2026-03-20
  - **Nota:** il file originale `gitignore` (senza punto) era non-funzionale — rimosso e creato `.gitignore` corretto

### Docker Compose locale
- [x] infra/docker/docker-compose.yml — validato, attributo `version` obsoleto rimosso — 2026-03-20
- [x] PostgreSQL 16 con healthcheck — 2026-03-20
- [x] Redis 7 con healthcheck — 2026-03-20
- [x] Soketi (WebSocket) — 2026-03-20
- [x] Volumi persistenti (postgres_data, redis_data) — 2026-03-20

### CI/CD GitHub Actions
- [x] .github/workflows/api-tests.yml — corretto e funzionante — 2026-03-20
  - Correzioni applicate: estensioni PHP complete (mbstring, openssl, zip, curl, fileinfo, intl, bcmath), caching Composer, rimosso `--min=80` prematuro, healthcheck Redis aggiunto, trigger su branch main/develop
- [x] .github/workflows/web-tests.yml — corretto e funzionante — 2026-03-20
  - Correzioni applicate: pnpm aggiornato da v8 a v10, filtri corretti da `--filter web` a `--filter @theja/web`, pnpm/action-setup aggiornato a v4, trigger su branch main/develop
- [ ] .github/workflows/deploy-staging.yml — **NON fatto** (staging AWS rimandato intenzionalmente a Fase 1)

### Staging AWS
- [ ] EC2 staging configurato — **rimandato a Fase 1**
- [ ] RDS PostgreSQL staging — **rimandato a Fase 1**
- [ ] ElastiCache Redis staging — **rimandato a Fase 1**
- [ ] S3 bucket staging — **rimandato a Fase 1**
- [ ] Deploy pipeline funzionante — **rimandato a Fase 1**

### Variabili d'ambiente
- [x] apps/api/.env.example completo e documentato — 2026-03-20
- [x] apps/api/.env.testing con configurazione PostgreSQL theja_test — 2026-03-20
- [x] apps/web/.env.example completo e documentato — 2026-03-20

---

## Stato: 🟡 IN CORSO (95% — manca solo staging AWS)

Lo staging AWS è rimandato intenzionalmente a inizio Fase 1 (come previsto in THEJA_MASTER.md §12: "Staging attivo dalla Fase 1").

Tutto il necessario per avviare lo sviluppo locale è operativo.

---

## Log completamento task

| Data | Task | Note |
|---|---|---|
| 2026-03 | Documentazione iniziale completa | - |
| 2026-03-20 | package.json root con pnpm workspaces + concurrently | - |
| 2026-03-20 | Laravel 11.6 fresh install in apps/api (sanctum, spatie/permission, spatie/activitylog) | - |
| 2026-03-20 | apps/api/.env.example e .env.testing completi | - |
| 2026-03-20 | Next.js 14.2 fresh install in apps/web (TypeScript, Tailwind, App Router, ESLint, Shadcn/ui, next-pwa) | - |
| 2026-03-20 | apps/web/.env.example completo | - |
| 2026-03-20 | packages/shared struttura base con tipi Organization, PointOfSale, User, Role | - |
| 2026-03-20 | pnpm install root verificato senza errori (4 workspace) | - |
| 2026-03-20 | docker-compose.yml validato (PostgreSQL 16 + Redis 7 + Soketi) | - |
| 2026-03-20 | .gitignore root corretto (rimosso vecchio gitignore senza punto) | - |
| 2026-03-20 | api-tests.yml corretto (estensioni PHP, Composer cache, --min=80 rimosso) | - |
| 2026-03-20 | web-tests.yml corretto (pnpm v10, @theja/web filter, action v4) | - |
