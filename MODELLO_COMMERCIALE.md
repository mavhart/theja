# MODELLO_COMMERCIALE.md — Theja
> Documento ufficiale del modello di business, pricing e regole commerciali.
> Aggiornato: 2026-03 | Versione: 1.0

---

## 1. Identità del prodotto

**Nome prodotto:** Theja
**Categoria:** SaaS gestionale per ottici — enterprise-grade, cloud-native
**Mercato:** Italia (espansione EU futura)
**Target:**
- Ottici singoli (1 POS) — volume principale
- Ottici con 2-3 negozi — segmento strategico
- Piccole catene (4-10 POS) — obiettivo medio termine
- Grandi catene (10+ POS) — contratto enterprise dedicato

---

## 2. Struttura abbonamento base

### Primo POS: €59/mese

Tutto incluso senza eccezioni:

- 1 sessione web contemporanea
- Gestione pazienti completa con anamnesi, storico, scheda clinica
- Prescrizioni con storico evolutivo e grafici progressione
- Magazzino con alert scorte e scadenzario LAC
- Trasferimenti inter-POS con DDT automatico (se multi-POS)
- Ordini e workflow laboratori ottici
- Vendita completa: preventivi, acconti multipli, rate, saldo
- Pagamenti multi-metodo (contanti, carta, bonifico, misto)
- Agenda (visite optometriche, prove LAC, appuntamenti generici)
- Comunicazioni automatiche al paziente (reminder, avvisi, promo)
- Fatturazione base
- Fattura elettronica SDI
- Tessera sanitaria (quota annuale separata — vedi sotto)
- Collegamento cassa fisica (RT fisico tramite protocollo standard)
- Ricerca avanzata combinata su magazzino, vendite, pazienti
- Reportistica statistica con grafici (torta, barre, trend per periodo)
- Assistenza post-vendita collegata a ogni fornitura
- Documenti clinici PDF (referto visita, scheda LAC, certificato idoneità visiva)
- RBAC completo con permessi configurabili per ruolo
- Import dati da gestionale precedente (Bludata prioritario, CSV universale)
- Notifiche sistema real-time

### Secondo POS (stessa organizzazione): €35/mese

Stesso contenuto del primo POS. Magazzino e pazienti visibili e consultabili tra i POS dell'organizzazione incluso.

### Dal terzo POS in poi: €25/mese cadauno

### Catene 10+ POS: pricing enterprise su contratto dedicato

Onboarding assistito incluso (setup POS, utenti, import dati in bulk gestito dal team Theja).

---

## 3. Add-on

Pochi, chiari, utili. Niente di superfluo.

### 3.1 Sessioni web aggiuntive

| Opzione | Costo |
|---|---|
| +1 sessione contemporanea | +€10/mese per POS |
| Sessioni illimitate per quel POS | +€17/mese per POS |

**Logica device management:** al superamento del limite, il sistema mostra:
*"Hai già una sessione attiva su [Nome dispositivo]. Se continui, quella sessione verrà chiusa automaticamente."*
L'utente sceglie se procedere o annullare.

### 3.2 App mobile (PWA)

| Opzione | Costo |
|---|---|
| 1 dispositivo mobile | +€8/mese per POS |
| 2 dispositivi mobile | +€13/mese per POS |
| Dispositivi mobili illimitati | +€18/mese per POS |

La PWA è la stessa interfaccia web ottimizzata mobile, installabile su iOS e Android. Non richiede App Store. Conta come sessione separata dalle sessioni web.

### 3.3 Cassa virtuale (RT Software)

+€15/mese per POS

Sostituisce completamente la cassa fisica. Registratore Telematico Software conforme D.Lgs. 127/2015 e Provvedimento AdE 18/01/2023. Emissione scontrini fiscali, corrispettivi elettronici, invio automatico AdE.

Il collegamento RT fisico è incluso nel base. La cassa virtuale è l'alternativa software alla cassa hardware.

### 3.4 AI Analysis

+€2/mese per POS

Analisi intelligente oltre la reportistica statistica standard:
- Trend di vendita con confronto mercato ottico italiano
- Previsione riordino basata su stagionalità e storico
- Identificazione prodotti a bassa rotazione con raccomandazioni operative
- Analisi crescita fatturato con narrative in linguaggio naturale
- Identificazione opportunità (es. pazienti senza rinnovo LAC da X mesi)

### 3.5 Tessera sanitaria

**€40/anno per organizzazione** (una sola volta all'anno, non mensile, non per POS)

Trasmissione automatica spese sanitarie al Sistema TS MEF. Formato XML conforme, firma digitale, log completo per obbligo normativo.

*Nota: il costo annuale effettivo da addebitare verrà calibrato sul costo di accreditamento MEF sostenuto.*

---

## 4. Riepilogo esempio cliente completo

Ottico singolo, 1 POS, 2 PC in negozio, 1 smartphone, vuole cassa virtuale e AI:

| Voce | Costo |
|---|---|
| Base 1 POS | €59/mese |
| +1 sessione web (secondo PC) | +€10/mese |
| +1 dispositivo mobile | +€8/mese |
| Cassa virtuale | +€15/mese |
| AI Analysis | +€2/mese |
| **Totale mensile** | **€94/mese** |
| Tessera sanitaria | +€40/anno |

---

## 5. Regole multi-POS

- Pazienti e storico clinico: sempre visibili a tutti i POS della stessa organizzazione (appartengono all'org, non al POS)
- Magazzino: visibile e consultabile tra POS della stessa org — incluso nel base
- Trasferimento prodotti inter-POS: incluso nel base, con DDT automatico
- Fatturazione: separata per POS (ogni POS può avere propria P.IVA)
- Cassa: separata per POS, ogni POS gestisce i propri corrispettivi fiscali
- Reportistica aggregata org (confronto performance tra POS, totali gruppo): inclusa nel base per org_owner

---

## 6. Struttura ruoli e permessi

### Ruoli predefiniti

| Ruolo | Scope | Note |
|---|---|---|
| `org_owner` | Tutta l'organizzazione | Non modificabile. Vede tutto, sempre |
| `pos_manager` | Singolo POS assegnato | Configurabile dall'owner |
| `optician` | Singolo POS | Accesso clinico completo |
| `sales` | Singolo POS | Vendite, cassa, agenda |
| `cashier` | Singolo POS | Solo cassa e corrispettivi |

### Permessi configurabili dall'owner/manager

- `inventory.view_purchase_price` — visibilità prezzi di acquisto (OFF di default per tutti tranne manager)
- `inventory.view_other_pos_stock` — visibilità magazzino altri POS (ON di default se multi-POS)
- `patients.view_all_org` — visibilità pazienti tutta l'organizzazione (ON di default)
- `reports.view_org_aggregate` — reportistica aggregata org (solo owner e pos_manager)
- `cash_register.access` — accesso cassa (assegnabile a qualsiasi ruolo)
- `sales.apply_discount` — possibilità di applicare sconti
- `sales.view_payment_details` — dettaglio metodi di pagamento e acconti

### POS senza manager locale

Configurabile in fase di setup e modificabile in qualsiasi momento. Se `has_local_manager: false`, l'org_owner gestisce quel POS direttamente. Utile per negozi satellite o aperture nuove.

### Utente su più POS

Un utente può avere ruoli diversi in POS diversi della stessa organizzazione. Seleziona il POS attivo al login o lo switcha durante la sessione.

---

## 7. Import dati — politica

L'import dati dal gestionale precedente è considerato **fondamentale** per l'acquisizione clienti.

### Connettori prioritari

| Gestionale | Priorità | Metodo |
|---|---|---|
| Bludata (Focus / Iride) | Massima — v1 | Parser export proprietario |
| CSV universale | Massima — v1 | Formato Theja standard documentato |
| Optovision | Alta — v1.x | Parser export |
| Winfocus / Winpat | Media — v1.x | Parser export |
| Filemaker custom | Bassa — case by case | CSV + mappatura guidata |

### Formato CSV standard Theja

Documentato pubblicamente. Colonne definite per: pazienti, prescrizioni, prodotti magazzino, fornitori, storico vendite. Chiunque può esportare dal vecchio sistema in questo formato e importare in Theja con mappatura visuale guidata.

---

## 8. Roadmap funzionale futura (post v1)

Non incluso nel lancio, pianificato:

- **App paziente PWA** — notifiche scadenza LAC, prenotazione appuntamenti, storico documenti clinici, riordino LAC con un tap
- **Connettori laboratori ottici** — invio ordini lavorazione diretto al lab con tracking
- **Integrazione fornitori** — per LAC e prodotti con catalogo digitale (dipende da disponibilità API fornitori)
- **Firma digitale documenti** — referti e consensi firmabili digitalmente dal paziente
- **Catene enterprise** — pannello amministrazione centralizzata, SLA dedicati

---

*Fine documento — MODELLO_COMMERCIALE.md*
*Ogni modifica a questo documento deve essere approvata e versionata con data e motivazione.*
