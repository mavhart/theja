'use client';

import { useEffect, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { disconnectEcho, getEcho } from '@/lib/echo';
import { clearAuthStorage, STORAGE_TOKEN, STORAGE_SESSION_ID } from '@/lib/api';

interface SessionInvalidatedPayload {
  session_id: string;
  reason:     'logged_out_remotely' | 'user_logged_out' | string;
}

/**
 * Hook che protegge la sessione corrente ascoltando il channel WebSocket privato.
 *
 * Quando il server emette un evento `SessionInvalidated` sul channel
 * `private-session.{sessionId}`, il hook:
 * 1. Disconnette Echo
 * 2. Cancella i dati di sessione dal localStorage
 * 3. Mostra un avviso all'utente
 * 4. Reindirizza al login
 *
 * Da usare all'interno del layout autenticato (dashboard).
 */
export function useSessionGuard(): void {
  const router          = useRouter();
  const channelRef      = useRef<ReturnType<ReturnType<typeof getEcho>['private']> | null>(null);
  const isConnectedRef  = useRef(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const token     = localStorage.getItem(STORAGE_TOKEN);
    const sessionId = localStorage.getItem(STORAGE_SESSION_ID);

    if (!token || !sessionId || isConnectedRef.current) return;

    try {
      const echo    = getEcho(token);
      const channel = echo.private(`session.${sessionId}`);
      channelRef.current  = channel;
      isConnectedRef.current = true;

      channel.listen('.SessionInvalidated', (payload: SessionInvalidatedPayload) => {
        console.warn('[Theja] Sessione invalidata:', payload.reason);

        // Pulizia locale
        disconnectEcho();
        clearAuthStorage();

        // Messaggio contestuale in base alla causa
        const message =
          payload.reason === 'logged_out_remotely'
            ? 'La tua sessione è stata chiusa da un altro dispositivo.'
            : 'Sessione terminata. Effettua di nuovo il login.';

        // Usa sessionStorage per passare il messaggio alla pagina di login
        sessionStorage.setItem('theja_logout_reason', message);

        router.replace('/login');
      });
    } catch (err) {
      console.error('[Theja] Errore connessione WebSocket:', err);
    }

    return () => {
      if (channelRef.current) {
        const token     = localStorage.getItem(STORAGE_TOKEN);
        const sessionId = localStorage.getItem(STORAGE_SESSION_ID);
        if (token && sessionId) {
          try {
            getEcho(token).leave(`session.${sessionId}`);
          } catch {
            // ignore
          }
        }
        channelRef.current     = null;
        isConnectedRef.current = false;
      }
    };
  }, [router]);
}
