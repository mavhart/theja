# ADR-003 — Autenticazione e device session management

**Data:** 2026-03
**Stato:** accettato

---

## Contesto

Theja ha un modello commerciale basato su sessioni concorrenti come add-on.
Un POS base include 1 sessione web. Sessioni aggiuntive si pagano.
Serve un sistema che: limiti le sessioni attive, notifichi in real-time, permetta
all'utente di scegliere se spostare la sessione o annullare il login.

## Decisione

- **Auth:** Laravel Sanctum (token-based, stateless)
- **Sessioni:** tabella `device_sessions` custom con device_fingerprint e device_name
- **Limite sessioni:** colonne `max_concurrent_web_sessions` e `max_mobile_devices` in `points_of_sale`
- **Invalidazione remota:** WebSocket channel `session.{session_id}` via Soketi
- **UX:** modale "Sessione attiva su [Nome dispositivo]. Vuoi spostarti qui?"

## Conseguenze

**Positive:**
- Sessioni come leva commerciale nativa nell'architettura
- UX chiara per l'utente (non un blocco secco)
- Invalidazione real-time via WebSocket senza polling
- Device name configurabile (es. "PC Cassa", "iPad Banco")

**Negative / trade-off:**
- Device fingerprinting lato browser non è infallibile (VPN, browser diversi)
- Aggiunge complessità al flow di login

**Neutrali:**
- Sessioni inattive >8h invalidate da job schedulato
