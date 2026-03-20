# Theja

Gestionale SaaS enterprise per ottici — cloud-native, multi-tenant, made in Sardinia.

## Stack

| Layer | Tecnologia |
|---|---|
| Frontend + PWA | Next.js 14, TypeScript, Tailwind CSS, Shadcn/ui |
| Backend API | Laravel 11, PHP 8.3 |
| Auth | Laravel Sanctum |
| RBAC | Spatie Permission |
| Audit | Spatie ActivityLog |
| Database | PostgreSQL 16 |
| Cache / Queue | Redis 7 |
| WebSocket | Laravel Broadcasting + Soketi |
| Storage | AWS S3 |
| Cloud | AWS (EC2, RDS, ElastiCache, S3, SES) |
| CI/CD | GitHub Actions |
| IaC | Terraform |
| Monorepo | pnpm workspaces |

## Struttura repository

```
theja/
├── apps/
│   ├── api/          # Laravel 11
│   └── web/          # Next.js 14 (include PWA)
├── packages/
│   └── shared/       # Tipi TypeScript condivisi
├── infra/            # Docker Compose, Terraform AWS
├── docs/             # Documentazione e ADR
├── scripts/          # Automazione, tool import dati
└── .github/          # CI/CD workflows
```

## Documentazione — ordine di lettura

1. `README.md` — questo file
2. `THEJA_MASTER.md` — contesto completo del progetto (usato da Cursor)
3. `ARCHITETTURA_THEJA.md` — architettura, schema DB, decisioni tecniche
4. `MODELLO_COMMERCIALE.md` — pricing, add-on, regole multi-POS
5. `PIANO_SVILUPPO_THEJA.md` — roadmap 27 settimane
6. `docs/adr/` — Architecture Decision Records
7. `ONBOARDING_DEVELOPER.md` — setup ambiente, workflow
8. `CONTRIBUTING.md` — regole contribuzione, Git workflow

## Setup rapido

```bash
git clone https://github.com/mavhart/theja.git
cd theja
pnpm install
cd apps/api && composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
cd ../.. && pnpm dev
```

Vedi `ONBOARDING_DEVELOPER.md` per setup completo con Docker.

## Fase attuale

**FASE 0 — Infrastruttura** (in corso)

Vedi `docs/FASE0_STATUS.md` per stato dettagliato.

## Regola ferrea

Ogni modifica al codice deve essere riflessa nella documentazione.
Nessun task è completo senza aggiornare stato fase, architettura (se cambiata) e piano di sviluppo.
