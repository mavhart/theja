# CONTRIBUTING.md — Theja

## Git Workflow

### Branch

```
main      → produzione
develop   → staging
feature/* → nuove feature
fix/*     → bugfix
hotfix/*  → fix urgenti su produzione
```

### Regole

- Mai push diretto su `main` o `develop`
- Ogni feature/fix su branch separato da `develop`
- PR richiesta per merge su `develop`
- `main` riceve merge solo da `develop` dopo verifica staging

### Ciclo di vita di un task

```bash
git checkout develop && git pull
git checkout -b feature/fase1-device-sessions
# ... lavora ...
git commit -m "feat(auth): device session management con WebSocket invalidation"
# ... apri PR verso develop ...
```

---

## Convenzioni commit (Conventional Commits)

```
feat(module):     nuova funzionalità
fix(module):      bugfix
refactor(module): refactoring senza cambio funzionalità
test(module):     aggiunta/modifica test
docs(module):     solo documentazione
chore:            dipendenze, config, tooling
migration:        nuova migration DB
```

**Moduli validi:**
`auth` `tenant` `patients` `prescriptions` `inventory` `sales` `billing`
`agenda` `reports` `ai` `cash` `sistema-ts` `notifications` `import`

**Esempi:**
```
feat(inventory): trasferimento inter-POS con generazione DDT automatica
fix(auth): correzione conteggio sessioni attive per platform PWA
migration: aggiunge tabella stock_transfer_requests
docs(onboarding): aggiorna istruzioni setup Docker
```

---

## Standard di codice

### PHP / Laravel

- PSR-12 per stile codice
- Nessuna logica di business nei Controller — tutto nei Service
- API Resources su ogni response — mai array grezzi
- Form Request per ogni input — mai validazione inline nel Controller
- Policy per ogni Model — mai autorizzazione inline
- Ogni migration ha `down()` implementato correttamente
- Tipizzazione PHP 8.x strict — sempre dichiarare tipi return e parametri

### TypeScript / Next.js

- TypeScript strict mode sempre attivo
- Tipi condivisi in `packages/shared/`
- Nessun `any` senza commento esplicativo
- Componenti React funzionali con hooks — niente class components
- Server Components dove possibile (App Router Next.js 14)

---

## Testing obbligatorio

### Backend

- **Unit test** per ogni Service (logica di business)
- **Feature test** per ogni endpoint API
- Coverage minimo: 80% su codice critico

```bash
php artisan test
php artisan test --coverage --min=80
```

### Frontend

- **Unit test** per hooks e utilities complesse
- **E2E test** (Playwright) per flussi critici:
  - Login + device session management
  - Vendita completa con acconti
  - Trasferimento magazzino inter-POS
  - Generazione fattura + invio SDI

---

## Documentazione — regola ferrea

**Un task non è completo se non hai aggiornato:**

| Cosa è cambiato | Dove aggiornare |
|---|---|
| Completato un task/fase | `PIANO_SVILUPPO_THEJA.md` — spunta checkbox |
| Nuova tabella o modifica schema | `ARCHITETTURA_THEJA.md` sezione Schema DB |
| Nuova decisione architetturale | `docs/adr/ADR-00X-titolo.md` (nuovo file) + append in ARCHITETTURA |
| Cambio stack o dipendenza | `THEJA_MASTER.md` sezione 2 + `ONBOARDING_DEVELOPER.md` |
| Cambio commerciale/pricing | `MODELLO_COMMERCIALE.md` |
| Cambio setup ambiente | `ONBOARDING_DEVELOPER.md` |

---

## Aggiungere un ADR

Copia `docs/adr/ADR-000-template.md`, rinomina con numero progressivo e titolo.

```
docs/adr/ADR-007-titolo-decisione.md
```

Ogni ADR deve avere: data, stato (proposto/accettato/deprecato), contesto, decisione, conseguenze.

---

## Pull Request checklist

Prima di aprire una PR verifica:

- [ ] I test passano tutti (`php artisan test`)
- [ ] Nessun errore TypeScript (`pnpm type-check`)
- [ ] Documentazione aggiornata (vedi tabella sopra)
- [ ] Commit message segue la convenzione
- [ ] La branch è aggiornata con develop (`git rebase develop`)
- [ ] Nessun `console.log` o `dd()` lasciato nel codice
- [ ] Le variabili `.env` nuove sono documentate in `.env.example`
