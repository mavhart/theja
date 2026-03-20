'use client';

import { useState } from 'react';
import { api, ActiveSession, ApiPointOfSale, STORAGE_TOKEN, STORAGE_SESSION_ID, STORAGE_POS } from '@/lib/api';

interface Props {
  activeSessions: ActiveSession[];
  posId:          string;
  token:          string;
  onSuccess:      (pos: ApiPointOfSale, sessionId: string, permissions: string[]) => void;
  onClose:        () => void;
}

/**
 * Modale mostrata quando il login tenta di selezionare un POS
 * ma il limite di sessioni simultanee è stato raggiunto (HTTP 423).
 *
 * Permette all'utente di scegliere quale sessione remota invalidare
 * per poi trasferire la propria sessione sul dispositivo corrente.
 */
export default function SessionInvalidatedModal({
  activeSessions,
  posId,
  token,
  onSuccess,
  onClose,
}: Props) {
  const [loading, setLoading] = useState<string | null>(null);
  const [error,   setError]   = useState<string | null>(null);

  async function handleTransfer(sessionId: string) {
    setLoading(sessionId);
    setError(null);

    try {
      // 1. Invalida la sessione remota
      const { status: deleteStatus } = await api.deleteSession(sessionId);

      if (deleteStatus !== 200) {
        setError('Impossibile invalidare la sessione remota. Riprova.');
        setLoading(null);
        return;
      }

      // 2. Riprova la selezione del POS
      const { data, status } = await api.selectPos(posId, token);

      if (status === 200) {
        // Salva i dati di sessione nel localStorage
        localStorage.setItem(STORAGE_TOKEN, token);
        localStorage.setItem(STORAGE_SESSION_ID, data.session_id);
        localStorage.setItem(STORAGE_POS, JSON.stringify(data.active_pos));

        onSuccess(data.active_pos, data.session_id, data.permissions);
      } else {
        setError('Errore durante la selezione del POS. Riprova.');
      }
    } catch {
      setError('Errore di rete. Controlla la connessione.');
    } finally {
      setLoading(null);
    }
  }

  function formatDate(isoString: string): string {
    return new Date(isoString).toLocaleString('it-IT', {
      day:    '2-digit',
      month:  '2-digit',
      year:   'numeric',
      hour:   '2-digit',
      minute: '2-digit',
    });
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md">
        {/* Header */}
        <div className="p-6 border-b border-zinc-200 dark:border-zinc-700">
          <div className="flex items-start gap-3">
            <div className="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-full flex items-center justify-center">
              <svg className="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div>
              <h2 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                Limite sessioni raggiunto
              </h2>
              <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Hai già raggiunto il numero massimo di sessioni attive per questo punto vendita.
                Scegli quale dispositivo disconnettere per continuare qui.
              </p>
            </div>
          </div>
        </div>

        {/* Sessioni attive */}
        <div className="p-6 space-y-3 max-h-72 overflow-y-auto">
          {activeSessions.map((session) => (
            <div
              key={session.id}
              className="flex items-center justify-between gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50"
            >
              <div className="min-w-0">
                <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                  {session.device_name}
                </p>
                <p className="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                  Attivo: {formatDate(session.last_active_at)}
                  {' · '}
                  <span className="uppercase">{session.platform}</span>
                </p>
              </div>
              <button
                onClick={() => handleTransfer(session.id)}
                disabled={loading !== null}
                className="flex-shrink-0 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white transition-colors"
              >
                {loading === session.id ? (
                  <span className="flex items-center gap-1.5">
                    <svg className="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                    Spostando…
                  </span>
                ) : (
                  'Sposta qui'
                )}
              </button>
            </div>
          ))}
        </div>

        {/* Error */}
        {error && (
          <div className="px-6 pb-2">
            <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
          </div>
        )}

        {/* Footer */}
        <div className="p-6 pt-3 border-t border-zinc-200 dark:border-zinc-700">
          <button
            onClick={onClose}
            className="w-full px-4 py-2.5 text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded-xl transition-colors"
          >
            Annulla
          </button>
        </div>
      </div>
    </div>
  );
}
