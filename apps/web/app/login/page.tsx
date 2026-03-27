'use client';

import { FormEvent, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { cn } from '@/lib/utils';
import {
  api,
  ActiveSession,
  ApiPointOfSale,
  clearAuthStorage,
  STORAGE_POS,
  STORAGE_SESSION_ID,
  STORAGE_TOKEN,
  STORAGE_USER,
} from '@/lib/api';
import SessionInvalidatedModal from '@/components/SessionInvalidatedModal';

// Next.js non può esportare metadata da un Client Component.
// Per il titolo usiamo il tag direttamente nel return.

type Step = 'credentials' | 'select-pos';

export default function LoginPage() {
  const router = useRouter();

  // Form state
  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState<string | null>(null);
  const [loading,  setLoading]  = useState(false);

  // Flusso multi-step
  const [step,    setStep]    = useState<Step>('credentials');
  const [token,   setToken]   = useState('');
  const [posList, setPosList] = useState<ApiPointOfSale[]>([]);

  // Modal 423
  const [showSessionModal, setShowSessionModal] = useState(false);
  const [activeSessions,   setActiveSessions]   = useState<ActiveSession[]>([]);
  const [selectedPosId,    setSelectedPosId]    = useState('');

  // Messaggio dal logout remoto
  const [logoutReason, setLogoutReason] = useState<string | null>(null);

  useEffect(() => {
    const reason = sessionStorage.getItem('theja_logout_reason');
    if (reason) {
      setLogoutReason(reason);
      sessionStorage.removeItem('theja_logout_reason');
    }
    clearAuthStorage();
  }, []);

  // ── Step 1: Credenziali ─────────────────────────────────────────────────────

  async function handleLogin(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const { data, status } = await api.login(email, password);

      if (status === 401) {
        setError(data.message ?? 'Credenziali non valide.');
        return;
      }

      if (status !== 200) {
        setError('Errore del server. Riprova tra qualche istante.');
        return;
      }

      // Login riuscito
      setToken(data.token ?? '');

      if (!data.requires_pos_selection && data.active_pos && data.session_id && data.token) {
        // Un solo POS → auto-selezionato
        await completeLogin(data.token, data.active_pos, data.session_id, data.permissions ?? [], data.user);
      } else if (data.points_of_sale) {
        // Più POS → mostra selezione
        setPosList(data.points_of_sale);
        setStep('select-pos');
      }
    } catch {
      setError('Errore di rete. Controlla la connessione.');
    } finally {
      setLoading(false);
    }
  }

  // ── Step 2: Selezione POS ──────────────────────────────────────────────────

  async function handleSelectPos(posId: string) {
    setError(null);
    setLoading(true);

    try {
      const { data, status } = await api.selectPos(posId, token);

      if (status === 200) {
        await completeLogin(token, data.active_pos, data.session_id, data.permissions, undefined);
        return;
      }

      if (status === 423) {
        // Limite sessioni → mostra modal
        const sessionData = data as unknown as { active_sessions: ActiveSession[] };
        setActiveSessions(sessionData.active_sessions ?? []);
        setSelectedPosId(posId);
        setShowSessionModal(true);
        return;
      }

      setError('Impossibile selezionare il POS. Riprova.');
    } catch {
      setError('Errore di rete. Controlla la connessione.');
    } finally {
      setLoading(false);
    }
  }

  // ── Completamento login ────────────────────────────────────────────────────

  async function completeLogin(
    t:           string,
    pos:         ApiPointOfSale,
    sessionId:   string,
    permissions: string[],
    user:        unknown,
  ) {
    localStorage.setItem(STORAGE_TOKEN,      t);
    localStorage.setItem(STORAGE_SESSION_ID, sessionId);
    localStorage.setItem(STORAGE_POS,        JSON.stringify(pos));
    if (user) {
      localStorage.setItem(STORAGE_USER, JSON.stringify(user));
    }
    router.replace('/dashboard');
  }

  return (
    <div className="flex min-h-dvh items-center justify-center bg-zinc-50 dark:bg-zinc-950 p-4">
      <title>Accedi — Theja</title>

      <div className="w-full max-w-sm">

        {/* Logo */}
        <div className="mb-8 text-center">
          <div className="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-600 mb-4 shadow-lg">
            <span className="text-white text-xl font-bold">T</span>
          </div>
          <h1 className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Theja</h1>
          <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Gestionale per ottici
          </p>
        </div>

        {/* Avviso logout remoto */}
        {logoutReason && (
          <div className="mb-4 flex items-start gap-2 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-3">
            <svg className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
              <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            </svg>
            <p className="text-xs text-amber-700 dark:text-amber-300">{logoutReason}</p>
          </div>
        )}

        {/* Card */}
        <div className="rounded-2xl bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800 p-6">

          {step === 'credentials' && (
            <>
              <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-5">
                Accedi al tuo account
              </h2>

              <form onSubmit={handleLogin} className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Email
                  </label>
                  <input
                    type="email"
                    autoComplete="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="nome@ottica.it"
                    className="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-transparent px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 transition"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                    Password
                  </label>
                  <input
                    type="password"
                    autoComplete="current-password"
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••"
                    className="w-full rounded-xl border border-zinc-300 dark:border-zinc-700 bg-transparent px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 transition"
                  />
                </div>

                {error && (
                  <p className="text-xs text-red-600 dark:text-red-400">{error}</p>
                )}

                <button
                  type="submit"
                  disabled={loading}
                  className={cn(
                    'w-full rounded-xl bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white text-sm font-medium py-2.5 transition-colors flex items-center justify-center gap-2',
                  )}
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                      </svg>
                      Accesso in corso…
                    </>
                  ) : (
                    'Accedi'
                  )}
                </button>
              </form>
            </>
          )}

          {step === 'select-pos' && (
            <>
              <h2 className="text-base font-semibold text-zinc-900 dark:text-zinc-100 mb-1">
                Seleziona punto vendita
              </h2>
              <p className="text-sm text-zinc-500 dark:text-zinc-400 mb-5">
                Il tuo account ha accesso a più punti vendita. Scegli da quale vuoi accedere.
              </p>

              <div className="space-y-2">
                {posList.map((pos) => (
                  <button
                    key={pos.id}
                    onClick={() => handleSelectPos(pos.id)}
                    disabled={loading}
                    className="w-full flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-blue-500 dark:hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/20 p-3.5 text-left transition-colors disabled:opacity-50"
                  >
                    <div className="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950/40 flex items-center justify-center flex-shrink-0">
                      <svg className="w-4 h-4 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2}>
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                      </svg>
                    </div>
                    <div>
                      <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{pos.name}</p>
                      {pos.city && (
                        <p className="text-xs text-zinc-500 dark:text-zinc-400">{pos.city}</p>
                      )}
                    </div>
                  </button>
                ))}
              </div>

              {error && (
                <p className="mt-3 text-xs text-red-600 dark:text-red-400">{error}</p>
              )}

              <button
                onClick={() => { setStep('credentials'); setToken(''); }}
                className="mt-4 w-full text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 text-center transition-colors"
              >
                ← Torna al login
              </button>
            </>
          )}
        </div>

        <p className="mt-6 text-center text-xs text-zinc-400 dark:text-zinc-500">
          © {new Date().getFullYear()} Theja — Tutti i diritti riservati
        </p>
      </div>

      {/* Modal 423: sessioni attive */}
      {showSessionModal && (
        <SessionInvalidatedModal
          activeSessions={activeSessions}
          posId={selectedPosId}
          token={token}
          onSuccess={(pos, sessionId, permissions) => {
            setShowSessionModal(false);
            completeLogin(token, pos, sessionId, permissions, undefined);
          }}
          onClose={() => setShowSessionModal(false)}
        />
      )}
    </div>
  );
}
