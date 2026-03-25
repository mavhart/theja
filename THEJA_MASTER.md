# THEJA_MASTER.md
> File di contesto principale per sessioni Cursor e Claude Code.
> Carica sempre questo file all'inizio di ogni sessione di sviluppo.
> Versione: 1.0 | Aggiornato: 2026-03

---

## 1. Cos'è Theja

Theja è un gestionale SaaS enterprise per ottici, cloud-native, multi-tenant.
Sviluppato da zero con architettura enterprise-grade per il mercato italiano.
Nome: Theja (da Theia, titanide greca della vista; variante sarda).

**Obiettivo:** diventare il miglior gestionale per ottici in Italia, con differenziatori
reali su prescrizioni evolutive, cassa virtuale integrata, AI analysis e gestione
multi-punto vendita nativa.

---

## 2. Stack tecnologico — DEFINITIVO, non cambiare

- **Frontend:** Next.js 14 + TypeScript + Tailwind CSS + Shadcn/ui
- **PWA:** next-pwa (stessa codebase web, nessuna app nativa separata)
- **Backend:** Laravel 11 + PHP 8.3
- **Auth:** Laravel Sanctum + device session management custom
- **RBAC:** Spatie Permission (con logica multi-POS custom)
- **Audit:** Spatie ActivityLog
- **Database:** PostgreSQL 16 — schema-per-tenant (UN schema per organization)
- **Cache/Queue:** Redis 7
- **WebSocket:** Laravel Broadcasting + Soketi (self-hosted)
- **Storage:** AWS S3
- **Cloud:** AWS (EC2, RDS, ElastiCache, S3, SES)
- **Monorepo:** pnpm workspaces
- **CI/CD:** GitHub Actions
- **IaC:** Terraform

---

## 3. Struttura repository

```
theja/
├── apps/
│   ├── api/                    # Laravel 11
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Middleware/  # ResolveTenant, EnforceSessionLimit, CheckFeature
│   │   │   │   └── Resources/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   │   └── SistemaTS/  # Tessera sanitaria: Client, XmlBuilder, Validator
│   │   │   └── Policies/
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   └── seeders/
│   │   └── routes/
│   └── web/                    # Next.js 14
│       ├── app/                # App Router
│       ├── components/
│       │   ├── ui/             # Shadcn components
│       │   └── modules/        # Componenti per modulo (patients, inventory, ecc.)
│       ├── lib/
│       └── public/             # manifest.json, sw.js (PWA)
├── packages/
│   └── shared/                 # Tipi TypeScript condivisi tra api e web
├── infra/
│   ├── docker/                 # Docker Compose per sviluppo locale
│   └── terraform/              # AWS infrastructure as code
├── docs/
│   ├── adr/                    # Architecture Decision Records
│   └── FASE0_STATUS.md
├── scripts/
│   └── import/                 # Tool import dati da Bludata e CSV
└── .github/
    └── workflows/              # CI/CD
```

---

## 4. Architettura multi-tenant — CRITICO

**Schema-per-tenant:** ogni Organization ha il proprio schema PostgreSQL (`tenant_{org_id}`).
NON usare row-level security con tenant_id come unico isolamento — i dati sanitari
richiedono isolamento fisico degli schemi.

**Gerarchia:**
```
Organization (es. "Ottica Rossi Group")
└── Points of Sale (1..N)
    ├── Users (con ruoli specifici per POS)
    ├── Inventory (locale al POS)
    ├── Cash register (locale al POS)
    └── Orders / Sales / Agenda

Dati condivisi a livello org (tutti i POS li vedono):
- Patients
- Prescriptions
- Org-level reports
```

**Middleware chain su ogni request:**
1. `ResolveTenant` → identifica org da token, switcha schema PostgreSQL
2. `EnforceSessionLimit` → verifica sessioni attive vs limite POS
3. `CheckFeatureActive` → verifica feature flag attive sul POS
4. Auth + RBAC normale

---

## 5. Device session management — CRITICO

Le sessioni sono la leva commerciale principale. Schema:

```sql
device_sessions (
  id, user_id, pos_id,
  device_fingerprint, device_name,
  platform ENUM('web','pwa'),
  last_active_at, is_active
)
```

**Logica al login:**
- Conta sessioni attive per (user_id, pos_id, platform) vs limite in `points_of_sale.max_concurrent_web_sessions`
- Se limite superato → 423 con lista sessioni attive + device_name
- Frontend mostra modale: "Sessione attiva su [Nome PC]. Vuoi spostarti qui?"
- Conferma → invalida via WebSocket channel `session.{id}` → crea nuova sessione

---

## 6. RBAC — permessi granulari

Ruoli di sistema (non modificabili): `org_owner`, `pos_manager`, `optician`, `sales`, `cashier`
Ruoli custom: creabili per organization.

Permessi chiave:
- `inventory.view_purchase_price` — prezzi acquisto (OFF default)
- `inventory.view_other_pos_stock` — stock altri POS (ON default se multi-POS)
- `inventory.transfer_request` — richiesta trasferimento
- `patients.view_all_org` — pazienti tutta org (ON default)
- `sales.apply_discount` — sconti
- `cash_register.access` — cassa
- `reports.view_org_aggregate` — report aggregati org

Un utente può avere ruoli diversi in POS diversi della stessa org (`user_pos_roles`).

---

## 7. Modello commerciale — sintesi

**Base:** €59/mese per POS (tutto incluso)
**Secondo POS:** €35/mese | **Dal terzo:** €25/mese

**Add-on:**
- Sessioni web aggiuntive: +€10 (1 sessione) / +€17 (illimitate) per POS/mese
- App mobile PWA: +€8 (1 device) / +€13 (2) / +€18 (illimitati) per POS/mese
- Cassa virtuale RT Software: +€15/mese per POS
- AI Analysis: +€2/mese per POS
- Tessera sanitaria: €40/anno per org (una tantum annuale)

**Feature flags nel DB:** colonne booleane/intere dirette in `points_of_sale`,
non tabelle separate con chiavi stringa.

---

## 8. Moduli principali — lista completa

### Inclusi nel base
- **Pazienti** — anagrafica, anamnesi, storico completo, consenso GDPR
- **Prescrizioni** — scheda clinica, storico evolutivo, grafici progressione OD/OS, OCR ricette
- **Magazzino** — prodotti, fornitori, movimenti, alert scorte, trasferimenti inter-POS con DDT
- **Scadenzario LAC** — calcolo automatico esaurimento, reminder paziente
- **Vendite** — preventivi, acconti multipli, rate pianificate, saldo
- **Pagamenti** — multi-metodo, multi-acconto, residuo sempre visibile
- **Ordini** — workflow con laboratori ottici, stati, tracking
- **Assistenza post-vendita** — collegata a ogni fornitura (riparazione, garanzia, reso)
- **Fatturazione** — base + fattura elettronica SDI
- **Tessera sanitaria** — invio SOAP MEF con credenziali dell'ottico
- **Cassa RT fisico** — integrazione protocollo standard
- **Agenda** — visite optometriche, prove LAC, appuntamenti generici
- **Comunicazioni** — reminder, avvisi, promo, scadenzari automatici
- **Ricerca avanzata** — query builder visuale + grafici statistici
- **Documenti clinici** — PDF referto visita, scheda LAC, certificato idoneità visiva
- **Import dati** — Bludata (Focus/Iride) e CSV standard Theja

### Add-on
- **Cassa virtuale** — RT Software via provider certificato (Fase 7)
- **AI Analysis** — Claude API con function calling su dati tenant (Fase 8)

### Post v1 (roadmap futura)
- App paziente PWA
- Connettori laboratori ottici diretti
- RT Software proprietario certificato AdE

---

## 9. Flusso trasferimento magazzino inter-POS

```
POS A richiede prodotto X da POS B
→ POST /api/transfers
→ Notifica WebSocket a pos.{B}

POS B accetta:
→ status = 'accepted'
→ inventory POS B: qty -= 1 (flag 'reserved')
→ Genera DDT PDF (numero progressivo)
→ Notifica WebSocket a pos.{A}

POS A conferma ricezione:
→ status = 'completed'
→ inventory POS A: qty += 1
→ inventory POS B: rimuove flag reserved

POS B rifiuta:
→ status = 'rejected' + motivo opzionale
→ Notifica WebSocket a pos.{A}
```

---

## 10. Tessera sanitaria — architettura corretta

Le credenziali Sistema TS appartengono all'ottico, non a Theja.
L'ottico inserisce le proprie credenziali MEF nella configurazione POS.
Theja le conserva cifrate e le usa per chiamare le API MEF a nome dell'ottico.

```
apps/api/app/Services/SistemaTS/
├── Client.php       # Wrapper SOAP con credenziali ottico
├── XmlBuilder.php   # Costruttore tracciato XML
├── Validator.php    # Validazione contro XSD MEF
└── TransmissionLog.php  # Audit obbligatorio
```

---

## 11. Cassa virtuale — architettura corretta

**Per il lancio v1:** integrazione con provider RT Software già certificato AdE.
L'ottico ha account sul provider, Theja chiama le API con credenziali ottico.
Stesso pattern della tessera sanitaria.

**Lungo termine:** RT Software proprietario Theja con certificazione AdE diretta.

Invio corrispettivi giornalieri all'AdE: gestito dal provider certificato nella fase v1.

---

## 12. Ambienti

| Ambiente | Scopo | Note |
|---|---|---|
| Local | Sviluppo quotidiano | Docker Compose (PostgreSQL 5434, Redis 6379, Soketi 6001) |
| Staging | Test con dati reali anonimi | AWS eu-south-1 (Milano) — operativo dalla Fase 1 |
| Production | Clienti reali | AWS prod — da configurare alla Fase 9 |

### Staging — dettagli infrastruttura (operativo dal 2026-03-23)

| Risorsa | Valore | Note |
|---|---|---|
| EC2 | `15.160.218.142` | Ubuntu 24.04, eu-south-1, PHP 8.4, Nginx, Composer |
| RDS PostgreSQL | `theja-staging.cpei4y62e8yn.eu-south-1.rds.amazonaws.com` | PostgreSQL 16, porta 5432, VPC privata |
| Redis | Locale su EC2 | Redis 7 installato sul server; ElastiCache rimandato a produzione |
| API health check | `http://15.160.218.142/api/health` | Risponde `{"status":"ok"}` |
| Regione AWS | `eu-south-1` (Milano) | Scelta per latenza Italia e compliance dati |
| Deploy | GitHub Actions `deploy-staging.yml` | Trigger: push su `main` → SSH → git pull → migrate → cache |

### Variabili d'ambiente staging
Template: `infra/staging.env.example`
File reale: `/var/www/theja/apps/api/.env` sul server EC2 (non versionato)

Staging attivo dalla Fase 1, non alla Fase 9.
I negozi del product owner sono il primo cliente staging.

---

## 13. Piano di sviluppo — fasi

| Fase | Contenuto | Settimane |
|---|---|---|
| 0 | Infrastruttura, Docker, CI/CD | 1-2 |
| 1 | Auth, RBAC, org/POS, device sessions, PWA base | 3-4 |
| 2 | Pazienti, prescrizioni, clinica, OCR | 5-7 |
| 3 | Magazzino, trasferimenti inter-POS, LAC | 8-9 |
| 4 | Vendite, preventivi, pagamenti multi-acconto, assistenza | 10-12 |
| 5 | Fatturazione, SDI, tessera sanitaria, RT fisico | 13-15 |
| 6 | Agenda, comunicazioni automatiche | 16-17 |
| 7 | Cassa virtuale, SumUp | 18-21 |
| 8 | Reportistica, query builder, AI Analysis | 22-23 |
| 9 | QA, security, import dati, go-live | 24-27 |

---

## 14. Regole per Cursor — SEMPRE rispettare

1. **Schema-per-tenant sempre** — mai query cross-schema, mai assumere schema default
2. **Middleware chain completa** — ResolveTenant → EnforceSessionLimit → CheckFeature → Auth
3. **Feature flag da colonne POS** — non da tabelle separate con chiavi stringa
4. **Permessi verificati su ogni route API** — usare `userCan($permission, $pos_id)`
5. **Dati sanitari cifrati** — Laravel Encryption su colonne sensibili
6. **Ogni migration ha il suo rollback** — down() sempre implementato
7. **Nessuna logica di business nei Controller** — tutto nei Service
8. **API Resources su ogni response** — mai array grezzi dal DB
9. **Test per ogni Service** — unit test obbligatori su logica di business
10. **Documentazione aggiornata** — nessun task è completo senza aggiornare lo stato fase

---

## 15. Decisioni architetturali prese — non riaprire

- Stack: Laravel 11 + Next.js 14 (non Node.js, non Vue, non altro)
- DB isolamento: schema-per-tenant (non row-level con tenant_id)
- Mobile: PWA (non app nativa iOS/Android)
- Cassa v1: integrazione provider certificato (non RT proprietario)
- Tessera sanitaria: credenziali ottico, non accreditamento software house
- Sessioni: device management come feature commerciale, non solo tecnica
- Monorepo: pnpm workspaces (non repos separati)

---

*Questo file è il punto di verità unico per Cursor e Claude Code.*
*Aggiornarlo ad ogni decisione architetturale significativa.*
