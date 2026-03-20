# PIANO_SVILUPPO_THEJA.md
> Piano completo di sviluppo Theja — 27 settimane / ~7 mesi
> Aggiornato: 2026-03 | Versione: 2.0
> Sostituisce: PIANO_SVILUPPO_DETTAGLIATO.md

---

## Regola ferrea

Ogni modifica al codice deve essere riflessa nella documentazione corrispondente.
Nessuna modifica è completa senza aggiornare:
- Stato della fase/capability nel presente documento
- ARCHITETTURA_THEJA.md se la struttura è cambiata (con ADR in append)
- MODELLO_COMMERCIALE.md se cambia qualcosa di commerciale
- Dipendenze in README o SETUP

---

## Attività parallele fuori dal piano di sviluppo

Queste attività vanno avviate **subito**, indipendentemente dalla fase corrente di sviluppo.

### Tessera sanitaria — avvio immediato
- [ ] Registrazione portale MEF Sistema TS come sviluppatore software
- [ ] Richiesta credenziali ambiente di collaudo
- [ ] Download specifiche tecniche tracciato XML e schema XSD
- [ ] Lettura normativa: D.Lgs. 175/2014, Provvedimento MEF 31/07/2015 e aggiornamenti
- [ ] Ottenimento certificato digitale per firma chiamate SOAP
- URL: sistemats1.sanita.finanze.it

### Cassa virtuale — avvio immediato
- [ ] Lettura Provvedimento AdE 18/01/2023 su RT Software
- [ ] Contatto con almeno 2 provider RT Software già certificati (Ditron, TeamSystem) per valutare integrazione API
- [ ] Raccolta documentazione tecnica provider scelto

### Marchio e dominio
- [ ] Verifica disponibilità dominio: theja.it / theja.io / theja.eu
- [ ] Ricerca marchio su uibm.gov.it (classe 42 + classe 44)
- [ ] Eventuale deposito marchio UIBM

### Import Bludata
- [ ] Eseguire export completo da Focus/Iride dei propri negozi
- [ ] Analizzare formato export per costruire il parser

---

## FASE 0 — Infrastruttura ✅ COMPLETATA
**Settimane 1-2**

- Ambiente dev Docker Compose
- Monorepo pnpm
- CI/CD GitHub Actions
- Struttura repository
- Ambienti: local + staging AWS (staging attivo subito, non alla fine)

---

## FASE 1 — Fondamenta
**Settimane 3-4**

### Obiettivo
Tutto il layer infrastrutturale che ogni feature successiva usa. Niente di visibile all'utente finale, ma tutto deve essere perfetto qui.

### Tasks

**Multi-tenant e gerarchia org/POS**
- [ ] Migrations: `organizations`, `points_of_sale`
- [ ] Middleware `ResolveTenant`: identifica org da token, switcha schema PostgreSQL
- [ ] Middleware `EnforceSessionLimit`: conta sessioni attive vs limite POS
- [ ] Seeder: dati di test (2 org, 3 POS, utenti vari)

**Autenticazione**
- [ ] Laravel Sanctum setup
- [ ] Login con selezione POS attivo (se utente ha accesso a più POS)
- [ ] Switch POS durante sessione
- [ ] Logout

**RBAC**
- [ ] Migrations: `roles`, `permissions`, `role_permissions`, `user_pos_roles`
- [ ] Seeder: ruoli e permessi di sistema predefiniti
- [ ] Spatie Permission integrato con logica multi-POS
- [ ] Helper: `userCan($permission, $pos_id)`

**Device session management**
- [ ] Migration: `device_sessions`
- [ ] Logica creazione sessione con device fingerprint
- [ ] API: lista sessioni attive per utente corrente
- [ ] API: invalidazione sessione remota
- [ ] WebSocket Broadcasting (Soketi): channel `session.{id}` per logout forzato
- [ ] Frontend: modale "Sessione attiva su [device] — vuoi spostarti qui?"
- [ ] Job schedulato: cleanup sessioni inattive >8h

**Subscription features (feature flags)**
- [ ] Logica lettura feature attive dal record POS
- [ ] Middleware `CheckFeatureActive`: blocca API se feature non attiva sul POS
- [ ] Stripe Billing base: creazione customer, gestione abbonamento

**PWA base**
- [ ] next-pwa configurato
- [ ] manifest.json + service worker base
- [ ] Responsive mobile-first su tutti i layout base (shell, navigation, auth)

### Definition of Done Fase 1
- Login funzionante con sessioni limitate e logout remoto via WebSocket
- RBAC funzionante: permessi verificati su ogni route API
- Due utenti con ruoli diversi vedono schermate diverse
- Staging aggiornato e testato con dati reali anonimi

---

## FASE 2 — Core Pazienti e Clinica
**Settimane 5-7**

### Tasks

**Anagrafica pazienti**
- [ ] Migration: `patients` (schema da validare con screen UI del product owner)
- [ ] CRUD paziente con validazione GDPR consent
- [ ] Ricerca paziente (nome, cognome, codice fiscale, telefono)
- [ ] Paziente visibile a tutti i POS dell'org — logica cross-POS
- [ ] UI: scheda paziente con tab (anagrafica / clinica / forniture / comunicazioni)

**Prescrizioni e scheda clinica**
- [ ] Migration: `prescriptions` (schema da validare con screen UI)
- [ ] CRUD prescrizione collegata a paziente e optician
- [ ] Storico evolutivo con grafici progressione (sfera, cilindro per OD/OS nel tempo)
- [ ] Alert automatico: prescrizione > 18 mesi → badge visivo sulla scheda paziente
- [ ] Comparazione affiancata di due prescrizioni

**OCR prescrizioni mediche**
- [ ] Upload foto ricetta oculistica cartacea
- [ ] Integrazione Vision API (GPT-4o) per parsing campi refrattivi
- [ ] Precompilazione form prescrizione con dati estratti + revisione manuale obbligatoria

**Documenti clinici PDF**
- [ ] Template: referto visita con prescrizione definitiva
- [ ] Template: scheda LAC da consegnare al paziente
- [ ] Template: certificato idoneità visiva (es. per patente)
- [ ] Flag `visibile_al_paziente` per ogni documento generato
- [ ] Storico documenti sulla scheda paziente

**Data model app paziente (senza app)**
- [ ] Struttura dati per supportare future notifiche push e accesso documenti da app paziente
- [ ] Nessuna UI esterna — solo il DB model e le API pronte

### Definition of Done Fase 2
- Scheda paziente completa navigabile
- Prescrizione inserita e grafico progressione visibile
- PDF referto generato e scaricabile
- Test con dati reali su staging

---

## FASE 3 — Magazzino
**Settimane 8-9**

### Tasks

**Prodotti e catalogo**
- [ ] Migration: `products`, `inventory_items`, `suppliers`
- [ ] Attributi flessibili via jsonb: colore, materiale, genere, forma, ecc.
- [ ] Categorie: montature (sole/vista), lenti, LAC, accessori, altro
- [ ] CRUD prodotto con tutti i campi (da validare con screen UI)
- [ ] Gestione fornitori

**Magazzino locale per POS**
- [ ] Movimenti di magazzino (carico, scarico, rettifica)
- [ ] Alert scorte minime configurabili per prodotto/POS
- [ ] Visibilità stock altri POS della stessa org (con permesso)

**Trasferimenti inter-POS**
- [ ] Migration: `stock_transfer_requests`
- [ ] Flusso completo: richiesta → accettazione/rifiuto → DDT → completamento
- [ ] Generazione DDT PDF automatica con numero progressivo
- [ ] Notifiche WebSocket real-time tra POS
- [ ] Audit trail completo del trasferimento

**Scadenzario LAC**
- [ ] Migration: `lac_supply_schedules`
- [ ] Calcolo automatico data esaurimento stimata per paziente/prodotto
- [ ] Dashboard mattutina: "Questa settimana scadono: [lista pazienti]"
- [ ] Reminder configurabile: avvisa X giorni prima della scadenza
- [ ] Notifica automatica al paziente (email/SMS)

### Definition of Done Fase 3
- Magazzino operativo su staging con dati reali negozi
- Trasferimento inter-POS testato end-to-end con DDT generato
- Scadenzario LAC funzionante con reminder inviati

---

## FASE 4 — Vendite e Ordini
**Settimane 10-12**

### Tasks

**Preventivi**
- [ ] Creazione preventivo collegato a paziente e prescrizione
- [ ] Linee prodotto con prezzi, sconti, note
- [ ] Stato: bozza → inviato → accettato → convertito in ordine
- [ ] PDF preventivo professionale con logo POS

**Ordini e workflow**
- [ ] Conversione preventivo → ordine confermato
- [ ] Stati ordine: confermato → in lavorazione → pronto → consegnato
- [ ] Collegamento a laboratorio ottico con tracking stato lavorazione
- [ ] Notifica paziente: "I tuoi occhiali sono pronti"

**Vendita al banco**
- [ ] Schermata vendita rapida (senza preventivo)
- [ ] Aggiunta prodotti da magazzino con disponibilità real-time
- [ ] Applicazione sconti con permesso `sales.apply_discount`

**Pagamenti multi-acconto**
- [ ] Migration: `payments` con supporto rate pianificate
- [ ] Registrazione acconti multipli con metodo di pagamento per ciascuno
- [ ] Pianificazione rate con date previste
- [ ] Dashboard pagamenti: totale / versato / residuo / prossima scadenza
- [ ] Storico pagamenti sulla scheda vendita

**Assistenza post-vendita**
- [ ] Migration: `after_sale_events`
- [ ] Sezione assistenza su ogni fornitura: riparazione, garanzia, reso, ritiro
- [ ] Stati: aperto → inviato a lab → rientrato → chiuso
- [ ] Notifica paziente su cambio stato
- [ ] Storico assistenza sulla scheda paziente

### Definition of Done Fase 4
- Flusso completo preventivo → ordine → consegna → pagamenti testato
- Pagamento con 3 acconti + saldo funzionante
- Assistenza post-vendita registrata e visibile su scheda paziente

---

## FASE 5 — Fatturazione
**Settimane 13-15**

### Tasks

**Fatturazione base**
- [ ] Generazione fattura da vendita/ordine
- [ ] Numerazione progressiva per POS
- [ ] PDF fattura professionale

**Fattura elettronica SDI**
- [ ] Formato XML FatturaPA
- [ ] Invio a SDI
- [ ] Gestione notifiche ricevuta/rifiuto/scarto
- [ ] Conservazione digitale (obbligo 10 anni)

**Tessera sanitaria**
- [ ] Modulo `SistemaTS`: Client SOAP, XmlBuilder, Validator, TransmissionLog
- [ ] Mapping vendite ottiche → tracciato XML MEF
- [ ] Test su ambiente di collaudo MEF (credenziali già ottenute in parallelo)
- [ ] Invio automatico mensile con job schedulato
- [ ] Dashboard trasmissioni: stato invii, errori, log

**RT fisico — integrazione base**
- [ ] Protocollo RT standard per invio documento fiscale al registratore fisico
- [ ] Configurazione IP/porta RT per ogni POS
- [ ] Test con hardware reale

### Definition of Done Fase 5
- Fattura elettronica inviata e ricevuta da SDI in staging
- Trasmissione tessera sanitaria testata su ambiente collaudo MEF
- Scontrino fiscale stampato da RT fisico su hardware reale

---

## FASE 6 — Agenda e Comunicazioni
**Settimane 16-17**

### Tasks

**Agenda**
- [ ] Calendario settimanale/mensile per POS
- [ ] Tipologie appuntamento: visita optometrica, prima prova LAC, generico
- [ ] Durata configurabile per tipologia
- [ ] Vista per operatore (chi gestisce l'appuntamento)
- [ ] Blocco orari (chiusura, pausa)

**Reminder automatici**
- [ ] Reminder appuntamento: email/SMS configurabile (es. 24h prima)
- [ ] Avviso occhiali pronti: notifica a ordine nello stato "pronto"
- [ ] Reminder revisione prescrizione: alert su scheda paziente dopo N mesi
- [ ] Reminder rinnovo LAC: integrato con scadenzario LAC (Fase 3)
- [ ] Auguri compleanno + comunicazioni promozionali dedicate

**Infrastruttura comunicazioni**
- [ ] Provider email: AWS SES
- [ ] Provider SMS: da valutare (Twilio o provider italiano)
- [ ] Template email/SMS configurabili per POS (logo, tono, lingua)
- [ ] Log invii su `patient_communications`

### Definition of Done Fase 6
- Appuntamento prenotato, reminder inviato 24h prima, confermato
- Notifica "occhiali pronti" inviata a paziente reale in staging

---

## FASE 7 — Cassa virtuale e integrazioni pagamento
**Settimane 18-21**

### Tasks

**Cassa virtuale RT Software**
- [ ] Integrazione API con provider RT Software certificato (scelto in parallelo)
- [ ] Emissione scontrino fiscale da Theja senza hardware
- [ ] Corrispettivi elettronici e invio automatico AdE
- [ ] Gestione chiusura giornaliera
- [ ] Attivazione/disattivazione basata su feature flag POS

**Integrazione pagamenti fisici**
- [ ] SumUp API: pagamento carta al banco
- [ ] Square API (opzionale, da valutare domanda mercato)
- [ ] Riconciliazione automatica: pagamento ricevuto → acconto registrato sulla vendita

**Pagamento online (futuro, placeholder)**
- [ ] Struttura dati pronta per link di pagamento Stripe da inviare al paziente
- [ ] Non implementato in v1, solo data model

### Definition of Done Fase 7
- Scontrino virtuale emesso e trasmesso ad AdE da staging
- Pagamento SumUp ricevuto e riconciliato su vendita

---

## FASE 8 — Reportistica e AI
**Settimane 22-23**

### Tasks

**Query builder visuale**
- [ ] UI: selezione campi/filtri in linguaggio comprensibile (non SQL)
- [ ] Filtri per categoria prodotto, genere, fascia prezzo, marca, periodo, POS
- [ ] Risultati in tabella con export Excel/PDF
- [ ] Grafici: torta, barre, trend per periodo (mesi/trimestri/anni)
- [ ] Salvataggio query frequenti

**Reportistica standard**
- [ ] Dashboard principale POS: vendite giornaliere/mensili, appuntamenti oggi, magazzino alert
- [ ] Report fatturato per periodo, per categoria, per operatore
- [ ] Report magazzino: rotazione prodotti, giacenza, movimenti
- [ ] Report pazienti: nuovi, visite, LAC attivi
- [ ] Reportistica aggregata org (tutti i POS) per org_owner

**AI Analysis (add-on)**
- [ ] Integrazione Claude API con function calling su query read-only tenant
- [ ] Feature flag: attivo solo per POS con add-on attivo
- [ ] Analisi trend, previsioni riordino, narrative fatturato
- [ ] Rate limiting per tenant, log chiamate AI per audit costi

### Definition of Done Fase 8
- Query builder funzionante con export su dati reali staging
- AI Analysis: risposta narrativa su trend vendite testata

---

## FASE 9 — QA, Security, Deploy Production
**Settimane 24-27**

### Tasks

**Test coverage**
- [ ] Unit test: servizi core (sessioni, RBAC, trasferimenti, pagamenti)
- [ ] Feature test: tutti i flussi API principali
- [ ] E2E test (Playwright): flussi critici (login, vendita, fattura, tessera sanitaria)
- [ ] Test di carico: simulazione multi-tenant con dati realistici

**Security audit**
- [ ] Penetration test su autenticazione e RBAC
- [ ] Verifica isolamento tenant (impossibilità cross-schema)
- [ ] Audit GDPR: data mapping, DPA template, procedura cancellazione
- [ ] Dependency audit (npm audit, composer audit)

**Import dati — tool finale**
- [ ] Parser Bludata completato e testato su export reali
- [ ] Tool CLI `php artisan theja:import` con dry-run e rollback
- [ ] Documentazione formato CSV standard per altri gestionali
- [ ] Test importazione completa dati negozi reali in staging

**Pannello admin interno**
- [ ] Creazione org/POS/utenti in bulk (per onboarding catene)
- [ ] Gestione abbonamenti e feature flag da pannello admin
- [ ] Monitoraggio errori e log sistema

**Infrastructure production**
- [ ] AWS production: EC2 autoscaling, RDS Multi-AZ, ElastiCache cluster, S3 + CloudFront
- [ ] Backup automatici giornalieri S3 con retention 90 giorni
- [ ] Monitoring: CloudWatch + Sentry
- [ ] SSL, HSTS, WAF base
- [ ] Runbook go-live e piano di rollback

### Definition of Done Fase 9 / Go-live
- Test coverage >80% su codice critico
- Zero critical/high da security audit
- Import dati Bludata testato su dati reali
- Production live con primo cliente reale

---

## Timeline riepilogativa

| Fase | Contenuto | Settimane |
|---|---|---|
| 0 | Infrastruttura ✅ | 1-2 |
| 1 | Fondamenta: auth, RBAC, sessioni, org/POS | 3-4 |
| 2 | Pazienti, prescrizioni, clinica, OCR | 5-7 |
| 3 | Magazzino, trasferimenti inter-POS, LAC | 8-9 |
| 4 | Vendite, preventivi, pagamenti multi-acconto, assistenza | 10-12 |
| 5 | Fatturazione, SDI, tessera sanitaria, RT fisico | 13-15 |
| 6 | Agenda, comunicazioni automatiche, reminder | 16-17 |
| 7 | Cassa virtuale, SumUp, pagamenti | 18-21 |
| 8 | Reportistica, query builder, AI Analysis | 22-23 |
| 9 | QA, security, import dati, go-live | 24-27 |

**Attività parallele permanenti** (non bloccano le fasi ma vanno avviate subito):
- Registrazione MEF Sistema TS
- Contatto provider RT Software
- Analisi export Bludata
- Verifica marchio/dominio Theja

---

*Fine documento — PIANO_SVILUPPO_THEJA.md*
