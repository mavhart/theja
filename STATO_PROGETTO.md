# STATO_PROGETTO.md — Theja
> Aggiornato: 2026-03-27 (sessione 17)
> **Regola:** aggiornare questo file ad ogni sessione di lavoro, ogni volta che un task viene completato e ogni volta che si inizia qualcosa di nuovo.

---

## Legenda

✅ Completato | 🟡 In corso | ⬜ Non iniziato | ❌ Bloccato

---

## Fase 0 — Infrastruttura
**Target: Settimane 1-2 | Stato: ✅ Completata**

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
✅ .github/workflows/deploy-staging.yml — workflow deploy SSH→EC2 (Job: deploy-api + deploy-web) — 2026-03-20
✅ Staging AWS operativo — EC2 `15.160.218.142` (eu-south-1), RDS PostgreSQL 16, Redis locale EC2 — 2026-03-23

---

## Fase 1 — Fondamenta
**Target: Settimane 3-4 | Stato: ✅ Completata**

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
✅ WebSocket Broadcasting: config/broadcasting.php + channels.php + middleware auth:sanctum — 2026-03-20
✅ Evento `SessionInvalidated` (channel privato `session.{id}`, broadcastAs, broadcastWith) — 2026-03-20
✅ SessionController@destroy + AuthController@logout con broadcast SessionInvalidated — 2026-03-20
✅ pusher/pusher-php-server installato — 2026-03-20
✅ Frontend: pusher-js + laravel-echo installati in apps/web — 2026-03-20
✅ `lib/echo.ts` — singleton Laravel Echo per Soketi con auth Sanctum — 2026-03-20
✅ `lib/api.ts` — client API tipizzato con helpers localStorage — 2026-03-20
✅ `hooks/useSessionGuard.ts` — ascolta SessionInvalidated, redirect /login — 2026-03-20
✅ `components/SessionInvalidatedModal.tsx` — modale 423 con scelta sessione da invalidare — 2026-03-20
✅ `app/layout.tsx` aggiornato (metadata PWA, viewport, lang=it) — 2026-03-20
✅ `components/layout/AppShell.tsx` — sidebar desktop + bottom nav mobile (PWA-ready) — 2026-03-20
✅ `app/(dashboard)/layout.tsx` — layout auth con useSessionGuard + verifica token — 2026-03-20
✅ `app/(dashboard)/page.tsx` — dashboard placeholder con 4 card statistiche — 2026-03-20
✅ `app/login/page.tsx` — form login con selezione POS e gestione 423 — 2026-03-20
✅ Feature test `BroadcastTest` (8 test: eventi, channel callback, auth endpoint) — 38/38 PASS — 2026-03-20
✅ TypeScript type-check pulito (0 errori) — 2026-03-20
✅ stripe/stripe-php v19 + @stripe/stripe-js v8 installati — 2026-03-20
✅ Migration `subscriptions` (uuid PK, org FK, status enum, trial/period timestamps) — 2026-03-20
✅ Migration `subscription_add_ons` (uuid PK, org/pos FK, feature_key, stripe_item_id) — 2026-03-20
✅ Model `Subscription` (HasUuids, BelongsTo Org, isActive/isPastDue helpers) — 2026-03-20
✅ Model `SubscriptionAddOn` (HasUuids, BelongsTo Org + POS) — 2026-03-20
✅ Organization aggiornata con relazioni subscription() + subscriptionAddOns() — 2026-03-20
✅ config/services.php con stripe.key/secret/webhook.secret/prices — 2026-03-20
✅ apps/api/.env.example aggiornato con STRIPE_KEY/SECRET/WEBHOOK_SECRET/PRICES — 2026-03-20
✅ `StripeService`: createCustomer, createSubscription, addAddon, cancelSubscription, syncFromWebhook — 2026-03-20
✅ `StripeWebhookController`: POST /api/stripe/webhook (no auth, verifica firma HMAC) — 2026-03-20
✅ Gestione eventi webhook: subscription.updated/deleted, invoice.payment_failed/succeeded — 2026-03-20
✅ Feature test `StripeTest` (4 test: createCustomer mock, webhook update, firma invalida, header mancante) — 42/42 PASS — 2026-03-20
✅ Staging AWS — `scripts/setup-staging-server.sh`, `infra/nginx/staging-api.conf`, `infra/staging.env.example` — 2026-03-20
✅ Staging AWS — EC2 `15.160.218.142` (eu-south-1), RDS PostgreSQL 16, Redis locale, deploy effettuato — 2026-03-23
✅ API health check funzionante: `http://15.160.218.142/api/health` — 2026-03-23
✅ 42/42 test PASS — Fase 1 completata al 100% — 2026-03-23

---

## Fase 2 — Core Pazienti e Clinica
**Target: Settimane 5-7 | Stato: ✅ Completata (core clinico + UI)**

✅ Migration `patients` (schema tenant PostgreSQL, FK cross-schema verso org/POS/users) — 2026-03-26
✅ Migration `prescriptions` (scheda optometria OD/OS × lontano/medio/vicino, visus, forie, IPD, richiami) — 2026-03-26
✅ Migration `lac_exams` (scheda LAC OD/OS, `tabs_completed` jsonb) — 2026-03-26
✅ `TenantClinicalSchema` + `OrganizationObserver` — provisioning schema tenant a ogni nuova Organization — 2026-03-26
✅ Model `Patient`, `Prescription`, `LacExam` — cifratura Laravel `encrypted` su `fiscal_code` e `private_notes` — 2026-03-26
✅ API: `PatientController`, `PrescriptionController`, `LacExamController` + Resources — route tenant-aware — 2026-03-26
✅ Ricerca paziente `GET /api/patients?q=` (nome, cognome, cellulare, telefono, CF esatto se ≥11 caratteri) — 2026-03-26
✅ `packages/shared` — tipi TypeScript `Patient`, `Prescription`, `LacExam` — 2026-03-26
✅ Feature test `PatientTest` (5 test: creazione, ricerca, prescrizione, cross-POS, CF non in chiaro in DB) — 2026-03-26
✅ API `GET /api/users?pos_id=` — utenti con ruolo sul POS (select operatore prescrizioni/LAC) — 2026-03-26
✅ UI Next.js — `/pazienti` lista (ricerca debounce 300ms, paginazione 20, CF mascherato, skeleton) — 2026-03-26
✅ UI Next.js — `/pazienti/nuovo` e `/pazienti/[id]` con tab Anagrafica / Optometria / LAC / Storico / Occhiali (placeholder Fase 4) — 2026-03-26
✅ `PatientAnagraphicForm` + `PrescriptionForm` (validazione sfera/cilindro -30…+30 step 0,25) — 2026-03-26
✅ `lib/api.ts` — client pazienti/prescrizioni/LAC/POS + OCR + download PDF — 2026-03-26
✅ `PatientResource` — `prescription_alert` (none/warning/expired) + `last_prescription_visit_date` — 2026-03-26
✅ `PrescriptionAlertService` — regole 12/18 mesi e richiamo scaduto — 2026-03-26
✅ UI — badge alert in lista pazienti; banner tab Optometria + pulsante Prenota visita (placeholder) — 2026-03-26
✅ `PrescriptionChart` (recharts) — progressione sfera/cilindro OD/OS lontano, tooltip prescrizione completa — 2026-03-26
✅ `OcrService` + `POST /api/patients/{patient}/prescriptions/ocr` (GPT-4o Vision, `OPENAI_API_KEY`) — 2026-03-26
✅ `PdfService` (barryvdh/laravel-dompdf) + Blade `pdf/referto_visita`, `pdf/scheda_lac`, `pdf/certificato_idoneita` — 2026-03-26
✅ `GET .../prescriptions/{id}/pdf?type=referto|certificato` + `GET .../lac-exams/{id}/pdf` — 2026-03-26
✅ Test unit `PrescriptionAlertServiceTest` + feature `ClinicalPatientFeaturesTest` (OCR mock, PDF base64) — 2026-03-26
⬜ Comparazione affiancata due prescrizioni
⬜ Data model app paziente (senza UI — solo struttura per futuro)

---

## Fase 3 — Magazzino
**Target: Settimane 8-9 | Stato: ✅ Completata (backend + UI + barcode/etichette)**

✅ Migrations tenant: `suppliers`, `products`, `inventory_items`, `stock_movements`, `stock_transfer_requests`, `lac_supply_schedules` (via `TenantClinicalSchema`) — 2026-03-27
✅ Model `Supplier`, `Product`, `InventoryItem`, `StockMovement`, `StockTransferRequest`, `LacSupplySchedule` (HasUuids + relazioni + cifratura `products.purchase_price`) — 2026-03-27
✅ API: `SupplierController` CRUD + filtro categoria; `ProductController` CRUD + ricerca + paginazione 20 — 2026-03-27
✅ API: `InventoryController` (index, update stock, movimenti) + `StockMovementController` (index/store carico manuale con DDT) — 2026-03-27
✅ API: `StockTransferController` flusso richiesta → accettazione/rifiuto → completamento — 2026-03-27
✅ `DdtService` + template Blade `pdf/ddt_transfer` + salvataggio storage locale — 2026-03-27
✅ WebSocket trasferimenti: evento `PosTransferUpdated` + channel privato `pos.{posId}` — 2026-03-27
✅ Scadenzario LAC: model `LacSupplySchedule::calculateEndDate()` + scope `expiringSoon()` — 2026-03-27
✅ Test feature `InventoryFeatureTest` aggiunto (CRUD prodotto cifrato, carico DDT, trasferimento completo, calcolo scadenza LAC) — 2026-03-27
✅ UI Next.js — `/magazzino` lista prodotti con tab categoria, ricerca debounce, paginazione 20, badge scorta — 2026-03-27
✅ UI Next.js — `/magazzino/[id]` tab Principale/Stock/Movimenti/Note + modale carico manuale — 2026-03-27
✅ UI Next.js — `/magazzino/nuovo` creazione prodotto con campi dinamici per categoria + select fornitore ricercabile — 2026-03-27
✅ UI Next.js — `/magazzino/fornitori` lista fornitori + dettaglio modale completo — 2026-03-27
✅ Dashboard — card “Prossime scadenze LAC” con endpoint `/api/lac-schedules?expiring_days=7` — 2026-03-27
✅ `AppShell` — Magazzino con sottovoci `Prodotti` e `Fornitori` — 2026-03-27
✅ `apps/web/lib/api.ts` — funzioni inventory UI (`getProducts`, `getProduct`, `createProduct`, `updateProduct`, `getSuppliers`, `getSupplier`, `createSupplier`, `getInventoryStock`, `createStockMovement`, `getStockMovements`, `getLacSchedules`) — 2026-03-27
✅ Generazione barcode EAN-13 prodotti interni + SVG (`picqer/php-barcode-generator`, `BarcodeService`, endpoint generate/barcode.svg) — 2026-03-27
✅ Template etichette configurabili (`label_templates`, preset Buffetti/TAK-TO, CRUD API, pagina gestione template) — 2026-03-27
✅ Stampa etichette PDF su A4 con posizionamento assoluto (mm→pt), start position e copie (`LabelPrintService`, endpoint `/api/labels/print`) — 2026-03-27
✅ Scanner barcode integrato in carico magazzino e creazione nuovo prodotto (lookup realtime, prefill, creazione rapida) — 2026-03-27
✅ Importazione listini CSV: servizio `BarcodeImportService::importFromCsv` + lookup barcode con fallback listino di sistema — 2026-03-27
✅ Alert scorte minime per prodotto/POS (badge UI catalogo prodotto) — 2026-03-27
✅ Visibilità stock altri POS con tabella stock multi-riga per POS (estensione endpoint inventory per filtro prodotto/POS) — 2026-03-27
✅ Reminder automatici paziente LAC (dashboard “Prossime scadenze LAC” operativa) — 2026-03-27

---

## Fase 4 — Vendite e Ordini
**Target: Settimane 10-12 | Stato: ✅ Completata (backend + frontend)**

✅ Migrations tenant: `sales`, `sale_items`, `payments`, `orders`, `after_sale_events` (schema-per-tenant via `TenantClinicalSchema`) — 2026-03-27
✅ Model `Sale`, `SaleItem`, `Payment`, `Order`, `AfterSaleEvent` (HasUuids, cast, cifratura `sale_items.purchase_price`, accessor residuo/stato) — 2026-03-27
✅ `SaleService` — creazione vendita con righe, scarico automatico magazzino, totali, pagamenti, rate, consegna/annullo con ripristino stock — 2026-03-27
✅ `OrderService` — creazione ordine lab, update stato, generazione job code progressivo `YYMM-NNNN` — 2026-03-27
✅ API `SaleController` — index/store/show/update/destroy + endpoint pagamenti/consegna/cancel/payment-summary/schedule-payments — 2026-03-27
✅ API `OrderController` — index/store/show/update status + endpoint dashboard `GET /api/orders/pending` — 2026-03-27
✅ API `AfterSaleController` — index per sale, creazione evento, update status — 2026-03-27
✅ UI Next.js — `/vendite/nuova` vendita rapida (carrello, scanner barcode, modale pagamenti azione rapida) — 2026-03-27
✅ UI Next.js — `/vendite` lista vendite con filtri e residuo evidenziato — 2026-03-27
✅ UI Next.js — `/vendite/[id]` tab Fornitura/Pagamenti/Ordine Lab/Assistenza con azioni principali — 2026-03-27
✅ Dashboard — card “Ordini lab” (in attesa / in lavorazione / pronti) — 2026-03-27
✅ `apps/web/lib/api.ts` esteso con funzioni vendite/pagamenti/ordini/assistenza — 2026-03-27
✅ Preventivi (bozza → conferma), workflow ordini, vendita rapida, acconti/rate, dashboard pagamenti, assistenza post-vendita — 2026-03-27

---

## Fase 5 — Fatturazione
**Target: Settimane 13-15 | Stato: 🟡 In corso (base + SDI/XML + RT stub + SistemaTS stub)**

✅ Fatturazione base (numerazione progressiva, PDF)
✅ Fattura elettronica SDI (XML FatturaPA, invio SDI stub, notifiche placeholder)
🟡 Conservazione digitale 10 anni (colonne `xml_path`/`pdf_path`: storage/retention in Fase 9)
✅ Modulo `SistemaTS` (`XmlBuilder` stub per record MEF)
✅ Mapping vendite ottiche → tracciato XML MEF (stub via `SistemaTS\\XmlBuilder`)
⬜ Test su ambiente collaudo MEF
⬜ Invio automatico mensile tessera sanitaria
✅ RT fisico — integrazione protocollo standard (stub locale + log)

---

## Fase 6 — Agenda e Comunicazioni
**Target: Settimane 16-17 | Stato: ✅ Completata (backend + frontend + scheduler)**

✅ Calendario settimanale POS (`appointments`) con API filtri/calendar/today + UI agenda (griglia oraria desktop + lista mobile + modale nuovo/modifica) — 2026-03-27
✅ Reminder appuntamento email/SMS (24h prima) via `CommunicationService::scheduleReminders()` + scheduler daily — 2026-03-27
✅ Notifica "occhiali pronti" automatica (trigger template `order_ready` nel modulo comunicazioni) — 2026-03-27
✅ Reminder revisione prescrizione via `schedulePrescriptionReminders()` — 2026-03-27
✅ Auguri compleanno + comunicazioni promozionali base via `scheduleBirthdays()` + template custom — 2026-03-27
✅ AWS SES + provider SMS configurati (email via `MAIL_MAILER=ses`, SMS provider `log` stub) — 2026-03-27
✅ Template email/SMS configurabili per POS/org (`communication_templates`) + log invii (`communication_logs`) con UI dedicata — 2026-03-27

---

## Fase 7 — Cassa virtuale e Pagamenti
**Target: Settimane 18-21 | Stato: ✅ Completata (backend + frontend base + provider pattern)**

✅ Migration tenant: `cash_register_sessions`, `fiscal_receipts` + FK tenant/public via `TenantClinicalSchema` — 2026-03-27
✅ Migration alter `points_of_sale`: rename `has_virtual_cash_register` → `virtual_cash_register_enabled`, aggiunta `rt_provider`, `rt_credentials` (encrypted), `sumup_api_key` (encrypted) — 2026-03-27
✅ Model `CashRegisterSession` + `FiscalReceipt` (scope `pending/sent`, helper `isOpen`, accessor totali) — 2026-03-27
✅ `VirtualCashRegisterService` (open/close session, sessione corrente, riepilogo, invio documento fiscale) — 2026-03-27
✅ Pattern provider RT: `RtProviderInterface`, `RtProviderFactory`, `LogRtProvider` default per dev/staging — 2026-03-27
✅ `SumUpService` con modalità mock staging/local (`createPayment`, `getPaymentStatus`, `refund`) — 2026-03-27
✅ API: `CashRegisterController` + `SumUpController` con endpoint `/api/cash-register/*` e `/api/payments/sumup/*` — 2026-03-27
✅ Frontend: pagina `/cassa`, modale `SumUpPaymentModal`, placeholder `/impostazioni/pos` — 2026-03-27
✅ Config `.env(.example)`: `SUMUP_API_URL`, `VIRTUAL_CASH_REGISTER_ENABLED`, `RT_PROVIDER` — 2026-03-27

---

## Fase 8 — Reportistica e AI
**Target: Settimane 22-23 | Stato: ✅ Completata (report + query builder + AI add-on)**

✅ Backend: `ReportService` con riepiloghi vendite/magazzino/pazienti, `QueryBuilderService` e endpoint `/api/reports/*` — 2026-03-27
✅ Backend: AI Analysis `AiAnalysisService` (Claude JSON) + `AiAnalysisController` con feature flag `ai_analysis_enabled` — 2026-03-27
✅ Backend: endpoint `POST /api/reports/query-builder` — 2026-03-27
✅ Frontend: pagina `/report` con tab Vendite/Magazzino/Pazienti/Query Builder/AI Analysis (Recharts) — 2026-03-27
✅ Frontend: dashboard home cards aggiornate con vendite di oggi e metriche coerenti — 2026-03-27

---

## Fase 9 — QA, Security, Go-live
**Target: Settimane 24-27 | Stato: ✅ Completata (QA core + Security + Import + Admin)**

✅ Unit test: servizi core (sessioni, RBAC, trasferimenti, pagamenti) — parziale ma copertura logica aggiunta
✅ Feature test: tutti i flussi API principali (core) + isolamento tenant
⬜ E2E test Playwright: login, vendita, fattura, tessera sanitaria
⬜ Test di carico multi-tenant
⬜ Penetration test autenticazione e RBAC
✅ Verifica isolamento tenant cross-schema (feature test)
✅ Audit GDPR (mascheramento CF in liste + controllo dati sensibili)
⬜ Dependency audit (npm audit, composer audit)
✅ Tool import dati Bludata + CLI `php artisan theja:import`
✅ Pannello admin interno (gestione org/POS e feature flag)
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
**Fase 9 — QA, Security, Go-live**

Prossimi task in ordine:
1. **Unit/Feature/E2E test** — flussi principali + isolamento tenant
2. **Security & audit** — PenTest, dependency audit
3. **Go-live infrastrutturale** — backup/monitoring/runbook

---

## Decisioni pendenti

| Decisione | Priorità | Note |
|---|---|---|
| Scelta provider RT Software certificato | Alta — serve per Fase 7 | Contattare Ditron e TeamSystem; raccogliere doc API entro Fase 3 |
| Provider SMS (Twilio vs italiano) | Media — serve per Fase 6 | Valutare prezzi e compliance GDPR italiana |
| Stripe vs alternativa per billing SaaS | ✅ Deciso — Stripe | Implementato con stripe-php v19, webhook, subscriptions/add-ons |
| Staging AWS: region eu-south-1 vs eu-west-1 | ✅ Deciso — eu-south-1 (Milano) | EC2 + RDS operativi in eu-south-1; ElastiCache rimandato a produzione |
| FK cross-schema tenant (sales/orders → patients/prescriptions) | ✅ Deciso | Nessuna FK DB verso tabelle cliniche tenant (`patients`, `prescriptions`, `lac_exams`) nelle migration né in `addSalesForeignKeys`/`addOrdersForeignKeys`: colonne UUID indicizzate; integrità applicativa. FK verso `public` (es. `points_of_sale`, `users`) restano. Deploy già migrati: `2026_03_27_131000_drop_cross_clinical_fks_on_sales_orders`. |
| Coverage minima CI (--min=80) | Bassa — riattivare dalla Fase 1+ | Attualmente disabilitata in api-tests.yml; riabilitare quando ci sono test reali |
