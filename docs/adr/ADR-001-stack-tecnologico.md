# ADR-001 — Stack tecnologico

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

Scelta dello stack tecnologico per Theja, gestionale SaaS enterprise per ottici.
Requisiti: robustezza enterprise, ecosistema maturo, facilità di gestione dati sanitari,
sviluppatore singolo con Cursor Pro e Claude Code come tool principali.

## Decisione

- **Backend:** Laravel 11 + PHP 8.3
- **Frontend:** Next.js 14 + TypeScript + Tailwind CSS + Shadcn/ui
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis 7
- **WebSocket:** Soketi (self-hosted, compatibile Pusher)
- **Storage:** AWS S3
- **Cloud:** AWS
- **Monorepo:** pnpm workspaces

## Conseguenze

**Positive:**
- Laravel ha un ecosistema maturo per autenticazione, RBAC, audit log (Spatie)
- Next.js 14 App Router + TypeScript per frontend type-safe
- PostgreSQL supporta schema-per-tenant nativamente
- Stack ben supportato da Cursor e Claude Code
- Shadcn/ui accelera lo sviluppo UI senza sacrificare personalizzazione

**Negative / trade-off:**
- Due linguaggi (PHP + TypeScript) richiedono switch di contesto
- Laravel non è la scelta più "moderna" ma è la più pragmatica per uno sviluppatore solo
- AWS introduce costi di infrastruttura non banali a basso volume

**Neutrali:**
- Monorepo pnpm workspaces per condividere tipi TypeScript tra api e web
