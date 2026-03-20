# STATO_PROGETTO.md — Theja
> Aggiornato: 2026-03-20 (sessione 3)
> **Regola:** aggiornare questo file ad ogni sessione di lavoro, ogni volta che un task viene completato e ogni volta che si inizia qualcosa di nuovo.

---

## Legenda

✅ Completato | 🟡 In corso | ⬜ Non iniziato | ❌ Bloccato

---

## Fase 0 — Infrastruttura
**Target: Settimane 1-2 | Stato: 🟡 In corso (95% completata)**

✅ Repository Git creato (github.com/mavhart/theja)
✅ Documentazione completa (README, THEJA_MASTER, ARCHITETTURA, MODELLO_COMMERCIALE, PIANO_SVILUPPO, ONBOARDING, CONTRIBUTING, ADR 000-006)
✅ pnpm-workspace.yaml configurato
✅ package.json root con scripts dev/build/test/type-check/lint + concurrently
✅ apps/api — Laravel 11.6 fresh install con PHP 8.5 / Composer 2.9
✅ apps/api — laravel/sanctum, spatie/laravel-permission, spatie/laravel-activitylog installati
✅ apps/api/.env.example completo e documentato
✅ apps/api/.env.testing con DB theja_test
✅ apps/web — Next.js 14.2 fresh install (TypeScript, Tailwind, App Router, ESLint)
✅ apps/web — Shadcn/ui inizializzato
✅ apps/web — next-pwa installato e configurato
✅ apps/web/.env.example con NEXT_PUBLIC_API_URL e NEXT_PUBLIC_SOKETI_KEY
✅ packages/shared — @theja/shared con tipi base (Organization, PointOfSale, User, Role)
✅ .gitignore root corretto (monorepo Laravel + Next.js + pnpm)
✅ infra/docker/docker-compose.yml — PostgreSQL 16 + Redis 7 + Soketi validato
✅ .github/workflows/api-tests.yml — corretto per Laravel 11 + PHP 8.3 + PostgreSQL
✅ .github/workflows/web-tests.yml — corretto per pnpm 10 + @theja/web
✅ pnpm install dalla root verificato senza errori
⬜ .github/workflows/deploy-staging.yml — NON fatto (staging AWS rimandato)
⬜ Staging AWS (EC2, RDS, ElastiCache, S3) — NON fatto (rimandato intenzionalmente)

---

## Fase 1 — Fondamenta
**Target: Settimane 3-4 | Stato: 🟡 In corso**

✅ Multi-tenant: migrations `organizations`, `points_of_sale` — 2026-03-20
✅ Migration users: aggiunto `organization_id` (uuid FK) e `is_active` — 2026-03-20
✅ Model `Organization` (HasUuids, relazioni HasMany POS e User, getTenantSchemaName) — 2026-03-20
✅ Model `PointOfSale` (HasUuids, feature flags come colonne dirette, BelongsTo Org) — 2026-03-20
✅ Model `User` aggiornato (HasApiTokens, BelongsTo Organization) — 2026-03-20
✅ Middleware `ResolveTenant` — identifica org da token, switcha schema PostgreSQL — 2026-03-20
✅ routes/api.php configurato con route tenant-aware e health endpoint — 2026-03-20
✅ bootstrap/app.php aggiornato (api routes + alias middleware `tenant`) — 2026-03-20
✅ Factory `OrganizationFactory`, `PointOfSaleFactory`, `UserFactory` aggiornata — 2026-03-20
✅ Sanctum migrations pubblicate (personal_access_tokens) — 2026-03-20
✅ DatabaseSeeder: 2 org (Ottica Rossi + Ottica Bianchi), 3 POS, 4 utenti — 2026-03-20
✅ Feature test `OrganizationTest` (7 test) — PASS — 2026-03-20
✅ Feature test `ResolveTenantTest` (6 test incl. 401 no-token, 401 invalid, schema switch) — PASS — 2026-03-20
✅ 15/15 test passati — 2026-03-20
✅ Middleware `EnforceSessionLimit` — conta sessioni attive vs limite POS, HTTP 423 — 2026-03-20
✅ Auth endpoints: `POST /api/auth/login`, `POST /api/auth/select-pos`, `POST /api/auth/logout`, `GET /api/auth/me` — 2026-03-20
✅ RBAC: Spatie Permission migrations (`roles`, `permissions`, `role_has_permissions`, `model_has_roles`) — 2026-03-20
✅ Migration `user_pos_roles` (user_id, pos_id, role_id, can_see_purchase_prices) — 2026-03-20
✅ Spatie Permission: 5 ruoli sistema (org_owner, pos_manager, optician, sales, cashier) + 15 permessi — 2026-03-20
✅ Helper `PermissionHelper::userCan($user, $permission, $posId)` + `permissionsForPos()` — 2026-03-20
✅ Migration `device_sessions` + Model `DeviceSession` + Model `UserPosRole` — 2026-03-20
✅ `SessionController`: GET /api/sessions + DELETE /api/sessions/{id} — 2026-03-20
✅ Command `theja:cleanup-sessions` + scheduler hourly — 2026-03-20
✅ Middleware `CheckFeatureActive` — blocca API se feature non attiva sul POS, HTTP 403 — 2026-03-20
✅ DatabaseSeeder aggiornato con RolePermissionSeeder + user_pos_roles — 2026-03-20
✅ Feature test `AuthTest` (8 test) + `SessionTest` (7 test) — 30/30 PASS — 2026-03-20
✅ predis/predis installato (phpredis non disponibile su questa macchina dev) — 2026-03-20
⬜ WebSocket Broadcasting (Soketi) — channel `session.{id}`
⬜ Frontend: modale "Sessione attiva su [device] — vuoi spostarti qui?"
⬜ Stripe Billing base
⬜ PWA: manifest.json + service worker + layout responsive mobile-first
⬜ Staging AWS attivo e testato (obbligatorio entro fine Fase 1)

---

## Fase 2 — Core Pazienti e Clinica
**Target: Settimane 5-7 | Stato: ⬜ Non iniziato**

⬜ Migration `patients` + CRUD con GDPR consent
⬜ Ricerca paziente (nome, cognome, CF, telefono)
⬜ UI scheda paziente (tab: anagrafica / clinica / forniture / comunicazioni)
⬜ Migration `prescriptions` + CRUD + storico evolutivo
⬜ Grafici progressione OD/OS nel tempo
⬜ Alert prescrizione > 18 mesi
⬜ Comparazione affiancata due prescrizioni
⬜ OCR prescrizioni (GPT-4o Vision API) + revisione manuale
⬜ PDF: referto visita, scheda LAC, certificato idoneità visiva
⬜ Data model app paziente (senza UI — solo struttura per futuro)

---

## Fase 3 — Magazzino
**Target: Settimane 8-9 | Stato: ⬜ Non iniziato**

⬜ Migrations `products`, `inventory_items`, `suppliers`
⬜ Attributi flessibili via jsonb (colore, materiale, genere, forma)
⬜ Categorie prodotti (montature, lenti, LAC, accessori)
⬜ CRUD prodotto + fornitori
⬜ Movimenti di magazzino (carico, scarico, rettifica)
⬜ Alert scorte minime per prodotto/POS
⬜ Visibilità stock altri POS (con permesso `inventory.view_other_pos_stock`)
⬜ Migration `stock_transfer_requests` + flusso completo richiesta→DDT→completamento
⬜ Generazione DDT PDF automatica con numero progressivo
⬜ Notifiche WebSocket real-time tra POS
⬜ Migration `lac_supply_schedules` + calcolo data esaurimento
⬜ Dashboard "scadenze LAC questa settimana"
⬜ Reminder automatici paziente LAC

---

## Fase 4 — Vendite e Ordini
**Target: Settimane 10-12 | Stato: ⬜ Non iniziato**

⬜ Preventivi (bozza → inviato → accettato → ordine) + PDF
⬜ Workflow ordini (stati + tracking laboratorio)
⬜ Vendita rapida al banco
⬜ Sconti con permesso `sales.apply_discount`
⬜ Migration `payments` + acconti multipli + rate pianificate
⬜ Dashboard pagamenti (totale / versato / residuo / prossima scadenza)
⬜ Migration `after_sale_events` + assistenza post-vendita (riparazione, garanzia, reso)

---

## Fase 5 — Fatturazione
**Target: Settimane 13-15 | Stato: ⬜ Non iniziato**

⬜ Fatturazione base (numerazione progressiva, PDF)
⬜ Fattura elettronica SDI (XML FatturaPA, invio, notifiche)
⬜ Conservazione digitale 10 anni
⬜ Modulo `SistemaTS` (Client SOAP, XmlBuilder, Validator, TransmissionLog)
⬜ Mapping vendite ottiche → tracciato XML MEF
⬜ Test su ambiente collaudo MEF
⬜ Invio automatico mensile tessera sanitaria
⬜ RT fisico — integrazione protocollo standard

---

## Fase 6 — Agenda e Comunicazioni
**Target: Settimane 16-17 | Stato: ⬜ Non iniziato**

⬜ Calendario settimanale/mensile per POS (tipologie, durata, blocchi)
⬜ Reminder appuntamento email/SMS (24h prima)
⬜ Notifica "occhiali pronti" automatica
⬜ Reminder revisione prescrizione
⬜ Auguri compleanno + comunicazioni promozionali
⬜ AWS SES + provider SMS configurati
⬜ Template email/SMS configurabili per POS

---

## Fase 7 — Cassa virtuale e Pagamenti
**Target: Settimane 18-21 | Stato: ⬜ Non iniziato**

⬜ Integrazione API provider RT Software certificato (da scegliere — vedi Decisioni pendenti)
⬜ Emissione scontrino fiscale virtuale + corrispettivi elettronici AdE
⬜ Gestione chiusura giornaliera
⬜ SumUp API — pagamento carta al banco + riconciliazione automatica
⬜ Placeholder data model pagamento online Stripe (no UI in v1)

---

## Fase 8 — Reportistica e AI
**Target: Settimane 22-23 | Stato: ⬜ Non iniziato**

⬜ Query builder visuale (filtri, tabella, export Excel/PDF, grafici)
⬜ Dashboard principale POS (vendite giornaliere/mensili, alert, appuntamenti)
⬜ Report fatturato, magazzino, pazienti
⬜ Reportistica aggregata org (tutti i POS)
⬜ AI Analysis add-on (Claude API con function calling su query read-only tenant)

---

## Fase 9 — QA, Security, Go-live
**Target: Settimane 24-27 | Stato: ⬜ Non iniziato**

⬜ Unit test: servizi core (sessioni, RBAC, trasferimenti, pagamenti)
⬜ Feature test: tutti i flussi API principali
⬜ E2E test Playwright: login, vendita, fattura, tessera sanitaria
⬜ Test di carico multi-tenant
⬜ Penetration test autenticazione e RBAC
⬜ Verifica isolamento tenant cross-schema
⬜ Audit GDPR
⬜ Dependency audit (npm audit, composer audit)
⬜ Tool import dati Bludata + CLI `php artisan theja:import`
⬜ Pannello admin interno (gestione org/POS, abbonamenti, feature flag)
⬜ AWS production (EC2 autoscaling, RDS Multi-AZ, ElastiCache, S3+CloudFront)
⬜ Backup automatici, monitoring (CloudWatch + Sentry), SSL, WAF
⬜ Runbook go-live + piano rollback

---

## Attività parallele permanenti
*(non bloccano le fasi ma vanno avviate subito)*

⬜ Tessera sanitaria: registrazione portale MEF Sistema TS + credenziali collaudo + XSD
⬜ Cassa virtuale: contatto provider RT Software certificato (Ditron, TeamSystem)
⬜ Marchio/dominio: verifica theja.it + ricerca UIBM classe 42+44
⬜ Import Bludata: export dai propri negozi + analisi formato

---

## Prossimo task da eseguire

**Continuare Fase 1 — WebSocket + Stripe Billing + PWA base**

Prossimi task in ordine:
1. **WebSocket Soketi** — configurare Broadcasting, channel `session.{id}`, evento `SessionInvalidated`
2. **Stripe Billing base** — `stripe/stripe-php`, migration `subscriptions`, webhook handler
3. **PWA** — aggiornare `apps/web` con manifest, service worker, layout responsive mobile-first
4. **Staging AWS** — CI/CD pipeline + EC2/RDS staging (obbligatorio entro fine Fase 1)

---

## Decisioni pendenti

| Decisione | Priorità | Note |
|---|---|---|
| Scelta provider RT Software certificato | Alta — serve per Fase 7 | Contattare Ditron e TeamSystem; raccogliere doc API entro Fase 3 |
| Provider SMS (Twilio vs italiano) | Media — serve per Fase 6 | Valutare prezzi e compliance GDPR italiana |
| Stripe vs alternativa per billing SaaS | Media — serve per Fase 1 | Stripe è assunto nel piano; confermare prima di implementare |
| Staging AWS: region eu-south-1 vs eu-west-1 | Alta — serve per Fase 1 | eu-south-1 (Milano) preferita per latenza Italia; verificare disponibilità servizi AWS |
| Coverage minima CI (--min=80) | Bassa — riattivare dalla Fase 1+ | Attualmente disabilitata in api-tests.yml; riabilitare quando ci sono test reali |
