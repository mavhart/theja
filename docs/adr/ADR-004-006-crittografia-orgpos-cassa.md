# ADR-004 — Crittografia dati sanitari

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

Theja tratta dati sanitari (prescrizioni, anamnesi, diagnosi) soggetti a GDPR
e normativa italiana sui dati di categoria speciale (art. 9 GDPR).

## Decisione

- **Cifratura a riposo:** Laravel Encryption (AES-256-CBC) su colonne sensibili
- **Colonne cifrate:** dati refrattivi prescrizioni, note cliniche, anamnesi, credenziali terze parti (Sistema TS, cassa virtuale)
- **Cifratura in transito:** HTTPS ovunque, HSTS
- **Backup:** cifrati su S3 con chiave separata
- **2FA:** opzionale per utenti, fortemente consigliato per org_owner

## Conseguenze

**Positive:**
- Compliance GDPR art. 9 per dati sanitari
- Credenziali terze parti (MEF, cassa) non leggibili da DB dump

**Negative / trade-off:**
- Colonne cifrate non sono ricercabili direttamente (serve decrypt in memoria)
- Piccolo overhead performance su read/write colonne cifrate

---

# ADR-005 — Gerarchia Organization / Point of Sale

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

Theja deve supportare ottici con più negozi (da 2 fino a catene enterprise).
I pazienti devono essere visibili a tutti i negozi della stessa organizzazione.
Il magazzino è locale al negozio ma consultabile tra negozi.
Fatturazione e cassa sono separate per negozio.

## Decisione

Gerarchia a due livelli: Organization → Points of Sale.

- **Paziente:** appartiene all'Organization (non al POS)
- **Prescrizione:** appartiene all'Organization, inserita da un POS
- **Inventory:** appartiene al POS (stock locale)
- **Cash register:** appartiene al POS
- **Fatturazione:** separata per POS (P.IVA può differire)
- **User:** appartiene all'Organization, ha ruoli per ogni POS tramite `user_pos_roles`

## Conseguenze

**Positive:**
- Paziente visto da tutti i negozi — storico clinico sempre completo
- Multi-negozio nativo, non un afterthought
- Ruoli diversi per lo stesso utente in negozi diversi

**Negative / trade-off:**
- Più complessità nel middleware di risoluzione contesto
- Query pazienti devono essere consapevoli dello scope org vs pos

---

# ADR-006 — Cassa virtuale: integrazione provider vs RT proprietario

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

La cassa virtuale (RT Software) richiede certificazione AdE per operare legalmente.
Sviluppare e certificare un RT Software proprietario richiede mesi di iter burocratico.
Il lancio v1 non può aspettare questo percorso.

## Decisione

**v1:** integrazione con provider RT Software già certificato (es. Cassa in Cloud TeamSystem
o equivalente) tramite le loro API. Le credenziali del provider appartengono all'ottico,
Theja le conserva cifrate e chiama le API a nome dell'ottico.

**Lungo termine:** sviluppo RT Software proprietario Theja con certificazione AdE diretta,
quando i volumi lo giustificano.

Stessa architettura della tessera sanitaria: credenziali ottico, non accreditamento Theja.

## Conseguenze

**Positive:**
- Cassa virtuale disponibile al lancio senza iter burocratico
- Tempo zero per certificazione nella fase v1

**Negative / trade-off:**
- Dipendenza da provider terzo per feature core
- Margine ridotto sul canone cassa (share con provider)
- Cambio provider in futuro richiede migrazione credenziali ottico

**Neutrali:**
- L'architettura è la stessa del lungo termine — solo il provider cambia
