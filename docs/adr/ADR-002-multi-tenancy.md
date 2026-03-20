# ADR-002 — Multi-tenancy: schema-per-tenant

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

Theja gestisce dati sanitari dei pazienti (prescrizioni, anamnesi, storico clinico).
Il GDPR richiede separazione e accountability dei dati per ogni titolare del trattamento.
Ogni ottico è un titolare del trattamento separato.

## Decisione

Schema PostgreSQL separato per ogni Organization: `tenant_{org_id}`.
Row-level security aggiuntiva come secondo livello, ma l'isolamento primario è lo schema.

## Conseguenze

**Positive:**
- Isolamento fisico dei dati — impossibile cross-query per bug applicativo
- Più difendibile da audit GDPR
- Backup e restore per singolo tenant più semplice
- Possibilità futura di spostare un tenant su DB separato senza refactoring

**Negative / trade-off:**
- Migration più complessa (deve girare su ogni schema)
- Query aggregate cross-tenant (per analytics interni) richiedono approccio separato
- Overhead gestione connessioni PostgreSQL per molti tenant

**Neutrali:**
- Middleware ResolveTenant switcha schema su ogni request
