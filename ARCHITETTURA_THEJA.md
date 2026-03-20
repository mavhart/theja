# ARCHITETTURA_THEJA.md
> Architettura di sistema e decisioni tecniche per Theja.
> Aggiornato: 2026-03 | Versione: 2.0
> Sostituisce: ARCHITETTURA_GESTIONALE_OTTICI.md

---

## 1. Panoramica sistema

Theja è un SaaS multi-tenant con gerarchia organizzativa a due livelli (Organization → Points of Sale). Architettura cloud-native su AWS, stack Laravel 11 + Next.js 14, dati sanitari con isolamento schema-per-tenant e cifratura a riposo.

---

## 2. Stack tecnologico

| Layer | Tecnologia | Versione |
|---|---|---|
| Frontend | Next.js + TypeScript + Tailwind + Shadcn/ui | 14 / 5 |
| PWA | next-pwa + Service Worker | — |
| Backend | Laravel + PHP | 11 / 8.3 |
| Auth | Laravel Sanctum | — |
| RBAC | Spatie Permission | — |
| Audit | Spatie ActivityLog | — |
| Database | PostgreSQL | 16 |
| Cache / Queue | Redis | 7 |
| WebSocket | Laravel Broadcasting + Soketi (self-hosted) | — |
| Storage file | AWS S3 | — |
| Cloud | AWS (EC2, RDS, ElastiCache, S3, SES) | — |
| CI/CD | GitHub Actions | — |
| IaC | Terraform | — |
| Monorepo | pnpm workspaces | — |
| Dev tool | Cursor Pro + Claude Code | — |

---

## 3. Struttura repository

```
theja/
├── apps/
│   ├── api/          # Laravel 11
│   └── web/          # Next.js 14 (include PWA)
├── packages/
│   └── shared/       # Tipi TypeScript condivisi, costanti
├── infra/            # Docker, Terraform AWS
├── docs/             # Documentazione
├── scripts/          # Automazione, tool import dati
└── .github/          # CI/CD workflows
```

---

## 4. Gerarchia tenant

```
Organization
├── Dati condivisi
│   ├── Patients (tutti i POS vedono tutti i pazienti dell'org)
│   ├── Prescriptions (storico clinico sempre accessibile)
│   └── Org-level reports
└── Points of Sale (1..N)
    ├── Users (con ruoli specifici per quel POS)
    ├── Inventory (locale al POS)
    ├── Cash register (locale al POS)
    ├── Orders / Sales
    └── Agenda
```

**Regola fondamentale:** il paziente appartiene all'Organization, non al POS. Ogni POS può leggere e scrivere sulla scheda paziente. Lo storico è sempre completo indipendentemente da quale POS lo consulta.

**Isolamento dati:** schema PostgreSQL separato per ogni Organization (`tenant_{org_id}`). Row-level security aggiuntiva come secondo livello di protezione. Nessuna query può attraversare schemi per design.

---

## 5. Schema database — tabelle core

### Layer organizzativo

```sql
organizations
  id uuid PK
  name varchar
  vat_number varchar
  billing_email varchar
  stripe_customer_id varchar
  created_at, updated_at

points_of_sale
  id uuid PK
  organization_id uuid FK
  name varchar
  address text
  city varchar
  fiscal_code varchar
  vat_number varchar          -- può differire dall'org
  has_local_manager boolean   -- se false, org_owner gestisce direttamente
  has_virtual_cash_register boolean DEFAULT false
  cash_register_hardware_configured boolean DEFAULT false
  ai_analysis_enabled boolean DEFAULT false
  max_concurrent_web_sessions int DEFAULT 1
  max_mobile_devices int DEFAULT 0
  is_active boolean DEFAULT true
  created_at, updated_at

users
  id uuid PK
  organization_id uuid FK
  name varchar
  email varchar UNIQUE
  password_hash varchar
  is_active boolean DEFAULT true
  created_at, updated_at

user_pos_roles
  id uuid PK
  user_id uuid FK
  pos_id uuid FK
  role_id uuid FK
  can_see_purchase_prices boolean DEFAULT false
  created_at
  UNIQUE(user_id, pos_id)
  -- Un utente può avere ruoli diversi in POS diversi della stessa org
```

### RBAC

```sql
roles
  id uuid PK
  organization_id uuid FK NULLABLE  -- null = ruolo di sistema predefinito
  name varchar                       -- es. 'pos_manager', 'optician'
  display_name varchar
  is_system boolean DEFAULT false    -- i ruoli di sistema non sono modificabili
  created_at, updated_at

permissions
  id uuid PK
  key varchar UNIQUE                 -- es. 'inventory.view_purchase_price'
  description varchar
  category varchar                   -- es. 'inventory', 'patients', 'cash'

role_permissions
  role_id uuid FK
  permission_id uuid FK
  value boolean DEFAULT true
  PRIMARY KEY (role_id, permission_id)
```

**Permessi di sistema predefiniti:**
- `inventory.view_purchase_price`
- `inventory.view_other_pos_stock`
- `inventory.transfer_request`
- `patients.view_all_org`
- `patients.edit`
- `prescriptions.edit`
- `sales.create`
- `sales.apply_discount`
- `sales.view_payment_details`
- `cash_register.access`
- `orders.manage`
- `reports.view_pos`
- `reports.view_org_aggregate`
- `agenda.manage`
- `users.manage_pos`

### Device sessions

```sql
device_sessions
  id uuid PK
  user_id uuid FK
  pos_id uuid FK
  device_fingerprint varchar
  device_name varchar            -- es. "PC Cassa", "iPhone di Marco"
  platform enum('web','pwa')
  ip_address varchar
  last_active_at timestamp
  is_active boolean DEFAULT true
  created_at
```

**Logica sessioni:**
1. Al login: conta sessioni attive per (user_id, pos_id, platform) rispetto al limite del POS
2. Se limite superato: restituisce elenco sessioni attive con device_name
3. Frontend mostra: *"Sessione attiva su [device_name]. Vuoi spostarti qui e chiudere quella sessione?"*
4. Conferma → invalidazione via WebSocket Broadcasting channel `session.{session_id}` → logout forzato sul device remoto → creazione nuova sessione
5. Sessioni inattive da >8h vengono invalidate automaticamente da job schedulato

### Pazienti e clinica

```sql
patients
  id uuid PK
  organization_id uuid FK      -- appartiene all'org, non al POS
  first_name, last_name varchar
  date_of_birth date
  fiscal_code varchar
  gender enum
  phone varchar
  email varchar
  address text
  notes text
  gdpr_consent_at timestamp
  marketing_consent boolean DEFAULT false
  created_at, updated_at

-- Campi anamnesi, prescrizioni e scheda clinica:
-- Definiti dopo revisione degli screen forniti dal product owner
-- Struttura placeholder fino alla validazione UI
prescriptions
  id uuid PK
  patient_id uuid FK
  pos_id uuid FK
  optician_user_id uuid FK
  visit_date date
  -- Campi refrattivi OD/OS (sfera, cilindro, asse, addizione, prisma...)
  -- Campi visus, tonometria, cover test, dominanza...
  -- Definiti dopo screen UI
  notes text
  is_for_glasses boolean
  is_for_contacts boolean
  created_at, updated_at
```

### Magazzino

```sql
products
  id uuid PK
  organization_id uuid FK       -- catalogo prodotti condiviso a livello org
  sku varchar
  name varchar
  category enum                 -- 'frame','lens','contact_lens','accessory','other'
  subcategory varchar
  brand varchar
  supplier_id uuid FK NULLABLE
  purchase_price decimal(10,2)  -- visibile solo con permesso specifico
  sale_price decimal(10,2)
  vat_rate decimal(5,2)
  attributes jsonb              -- flessibile: colore, materiale, genere, forma, ecc.
  created_at, updated_at

inventory_items
  id uuid PK
  pos_id uuid FK                -- stock locale al POS
  product_id uuid FK
  quantity int DEFAULT 0
  min_stock_alert int DEFAULT 0
  location varchar NULLABLE     -- posizione fisica in negozio
  updated_at

stock_transfer_requests
  id uuid PK
  from_pos_id uuid FK
  to_pos_id uuid FK
  requested_by_user_id uuid FK
  product_id uuid FK
  quantity int
  status enum('requested','accepted','rejected','in_transit','completed')
  rejection_reason text NULLABLE
  ddt_number varchar NULLABLE
  ddt_pdf_path varchar NULLABLE
  requested_at timestamp
  resolved_at timestamp NULLABLE
  completed_at timestamp NULLABLE
```

### Vendite e pagamenti

```sql
sales
  id uuid PK
  pos_id uuid FK
  patient_id uuid FK NULLABLE   -- può essere vendita a cliente occasionale
  user_id uuid FK               -- chi ha gestito la vendita
  sale_date date
  status enum('quote','confirmed','delivered','cancelled')
  total_amount decimal(10,2)
  discount_amount decimal(10,2) DEFAULT 0
  notes text
  prescription_id uuid FK NULLABLE
  created_at, updated_at

sale_items
  id uuid PK
  sale_id uuid FK
  product_id uuid FK
  quantity int
  unit_price decimal(10,2)
  purchase_price decimal(10,2)  -- snapshot al momento della vendita
  discount decimal(10,2) DEFAULT 0

payments
  id uuid PK
  sale_id uuid FK
  amount decimal(10,2)
  method enum('cash','card','bank_transfer','other')
  payment_date date
  is_scheduled boolean DEFAULT false   -- rate pianificate vs pagamenti reali
  scheduled_date date NULLABLE
  paid_at timestamp NULLABLE
  notes text
  created_at
  -- Supporta: acconto → più acconti → saldo → rate pianificate
  -- Il sistema calcola sempre: totale, versato, residuo
```

### Post-vendita (assistenza)

```sql
after_sale_events
  id uuid PK
  sale_id uuid FK               -- collegato alla fornitura originale
  sale_item_id uuid FK NULLABLE -- opzionale: collegato al prodotto specifico
  type enum('repair','warranty','return','adjustment','other')
  description text
  status enum('open','sent_to_lab','returned','closed')
  opened_at timestamp
  closed_at timestamp NULLABLE
  cost decimal(10,2) NULLABLE   -- eventuale costo riparazione
  notes text
  created_at, updated_at
```

### LAC scadenzario

```sql
lac_supply_schedules
  id uuid PK
  patient_id uuid FK
  pos_id uuid FK
  product_id uuid FK            -- prodotto LAC specifico del paziente
  supply_date date              -- data ultima fornitura
  quantity int                  -- numero confezioni/lenti fornite
  estimated_duration_days int   -- durata stimata in giorni
  estimated_end_date date       -- calcolato: supply_date + duration
  reminder_sent_at timestamp NULLABLE
  patient_reorder_response enum('yes','no','later') NULLABLE
  notes text
  created_at
```

### Comunicazioni

```sql
notifications
  id uuid PK
  organization_id uuid FK
  pos_id uuid FK NULLABLE
  user_id uuid FK NULLABLE      -- null = notifica di sistema
  type varchar                  -- 'lac_reminder','appointment_reminder','transfer_request', ecc.
  title varchar
  body text
  read_at timestamp NULLABLE
  created_at

patient_communications
  id uuid PK
  patient_id uuid FK
  pos_id uuid FK
  type enum('sms','email','push')
  subject varchar NULLABLE
  body text
  sent_at timestamp NULLABLE
  status enum('pending','sent','failed')
  template_key varchar           -- riferimento al template usato
```

### Tessera sanitaria

```sql
sistema_ts_transmissions
  id uuid PK
  organization_id uuid FK
  pos_id uuid FK
  year int
  month int
  status enum('pending','sent','accepted','rejected','error')
  xml_payload text               -- XML firmato inviato
  response_payload text NULLABLE
  sent_at timestamp NULLABLE
  records_count int
  created_at
```

---

## 6. Architettura sessioni e WebSocket

```
Client (browser/PWA)
    │
    ├── HTTP/HTTPS → Laravel API (Sanctum token)
    │                   │
    │                   ├── Middleware: ResolveTenant
    │                   │   → Identifica org da token utente
    │                   │   → Switcha schema PostgreSQL
    │                   │   → Verifica feature attive sul POS
    │                   │   → Risolve permessi utente per quel POS
    │                   │
    │                   └── Middleware: EnforceSessionLimit
    │                       → Conta sessioni attive per (user, pos, platform)
    │                       → Se limite superato → 423 con lista sessioni attive
    │
    └── WebSocket → Soketi (self-hosted)
                    → Channel: session.{session_id}
                      (invalidazione remota sessioni)
                    → Channel: pos.{pos_id}
                      (notifiche transfer request, appuntamenti, ecc.)
                    → Channel: user.{user_id}
                      (notifiche personali)
```

---

## 7. Flusso trasferimento magazzino inter-POS

```
POS A: utente vede prodotto X in stock POS B
→ POST /api/transfers { from_pos: B, to_pos: A, product_id: X, qty: 1 }
→ Crea record status='requested'
→ Notifica WebSocket a pos.{B}: nuova richiesta trasferimento

POS B: manager/optician riceve notifica
→ Visualizza richiesta con dettaglio prodotto e POS richiedente
→ Azione: Accetta
  → status = 'accepted'
  → inventory_items POS B: qty -= 1, flag 'reserved_for_transfer'
  → Genera DDT PDF (numero progressivo, mittente, destinatario, prodotto)
  → Notifica WebSocket a pos.{A}: accettato, DDT disponibile
→ Azione: Rifiuta (con motivo opzionale)
  → status = 'rejected'
  → Notifica WebSocket a pos.{A}: rifiutato + motivo

POS A: conferma ricezione fisica del prodotto
→ status = 'completed'
→ inventory_items POS B: rimuove flag reserved, qty già sottratto
→ inventory_items POS A: qty += 1
→ Audit log completo del trasferimento
```

---

## 8. Modulo Tessera Sanitaria

**Standard tecnico:** Web service SOAP, schema XSD MEF, certificato digitale per firma.

```
app/Services/SistemaTS/
├── Client.php          # Wrapper SOAP con certificato
├── XmlBuilder.php      # Costruttore tracciato XML per tipo spesa
├── Validator.php       # Validazione pre-invio contro XSD
└── TransmissionLog.php # Audit obbligatorio per legge
```

**Ambienti:** collaudo MEF disponibile per sviluppo e test prima della produzione.
**Timing:** invio mensile entro fine mese successivo all'emissione.
**Avvio parallelo:** registrazione portale MEF Sistema TS in Settimana 1, indipendente dal piano di sviluppo.

---

## 9. Modulo Cassa

### RT fisico (incluso nel base)
Integrazione con registratore telematico fisico via protocollo RT standard (XML su porta locale o rete). Il software invia il documento, l'RT lo stampa e trasmette all'AdE.

### RT Software / Cassa virtuale (add-on €15/mese)
Conforme Provvedimento AdE 18/01/2023. Due percorsi:
1. **Fase 7:** integrazione con provider RT Software già certificato (Ditron, Cassa in Cloud TeamSystem o equivalente) via loro API — percorso rapido per il lancio
2. **Post v1:** sviluppo RT Software proprietario Theja con certificazione AdE — percorso a lungo termine se i volumi lo giustificano

---

## 10. Import dati

```
scripts/import/
├── parsers/
│   ├── BluDataParser.php    # Export Focus/Iride → formato Theja
│   ├── OptovisionParser.php
│   └── CsvParser.php        # Formato CSV standard Theja (universale)
├── mappers/
│   ├── PatientMapper.php
│   ├── ProductMapper.php
│   └── SalesHistoryMapper.php
└── ImportRunner.php         # Orchestratore con dry-run, log errori, rollback
```

Il tool import è uno script CLI Laravel (`php artisan theja:import`). Supporta dry-run (nessuna scrittura, solo report errori), log dettagliato, rollback completo in caso di errore. L'import avviene in un'unica transazione per garantire consistenza.

---

## 11. AI Analysis (add-on €2/mese)

Il modulo AI è attivato a livello POS tramite feature flag. Quando attivo:

- API Claude (claude-sonnet) con function calling verso query DB read-only del tenant
- Contesto: dati aggregati del POS (no dati clinici individuali in chiaro)
- Output: testo narrativo + struttura dati per grafici
- Rate limiting per tenant per evitare costi anomali
- Ogni chiamata AI loggata per audit e controllo costi

---

## 12. PWA — configurazione

```
apps/web/public/
├── manifest.json      # Nome app, icone, theme_color, display: standalone
└── sw.js              # Service worker: cache offline per viste principali

next.config.js:
  withPWA({
    dest: 'public',
    register: true,
    skipWaiting: true,
    runtimeCaching: [...] // Cache strategia per API calls
  })
```

Responsive mobile-first su ogni componente dalla Fase 1. La PWA non è un afterthought: ogni schermata viene progettata e testata su viewport mobile durante lo sviluppo.

---

## 13. Ambienti

| Ambiente | Scopo | Infrastruttura |
|---|---|---|
| Local | Sviluppo quotidiano | Docker Compose |
| Staging | Test con dati reali anonimi | AWS separato, mirror di prod |
| Production | Clienti reali | AWS prod |

**Staging attivo dalla Fase 1**, non dalla Fase 9. I due negozi del product owner sono il primo cliente di staging per validazione continua.

---

## 14. Sicurezza e GDPR

- Dati sanitari cifrati a riposo (AES-256 su colonne sensibili via Laravel Encryption)
- Schema-per-tenant: isolamento fisico dei dati, impossibile cross-query per bug applicativo
- Audit log completo su ogni accesso/modifica a dati clinici (Spatie ActivityLog)
- HTTPS ovunque, HSTS
- Backup giornalieri cifrati su S3 con retention 90 giorni
- Data Processing Agreement (DPA) fornito a ogni cliente (obbligo GDPR art. 28)
- Consenso GDPR paziente tracciato con timestamp nel record paziente
- Diritto all'oblio: procedura documentata per cancellazione dati paziente su richiesta

---

*Fine documento — ARCHITETTURA_THEJA.md*
*Ogni ADR (Architecture Decision Record) viene aggiunto in append a questo documento nella sezione 15.*
