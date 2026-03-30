'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import AppShell from '@/components/layout/AppShell';
import { useSessionGuard } from '@/hooks/useSessionGuard';
import { api, clearAuthStorage, getStoredToken, STORAGE_POS, STORAGE_SESSION_ID, STORAGE_USER } from '@/lib/api';

interface StoredUser { name: string }
interface StoredPos  { name: string }

/**
 * Layout per tutte le pagine autenticate (route group (dashboard), URL base /).
 * - Verifica che il token sia presente; se non c'è, rimanda al login.
 * - Usa useSessionGuard per ascoltare gli eventi WebSocket di sessione.
 * - Monta AppShell con i dati utente/POS recuperati dal localStorage.
 */
export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [userName, setUserName] = useState<string | undefined>();
  const [posName,  setPosName]  = useState<string | undefined>();
  const [ready,    setReady]    = useState(false);

  // Guarda WebSocket per notifiche di sessione invalidata
  useSessionGuard();

  useEffect(() => {
    const token     = getStoredToken();
    const sessionId = localStorage.getItem(STORAGE_SESSION_ID);

    if (!token || !sessionId) {
      router.replace('/login');
      return;
    }

    // Carica dati utente e POS dal localStorage (evita round-trip all'avvio)
    try {
      const userJson = localStorage.getItem(STORAGE_USER);
      const posJson  = localStorage.getItem(STORAGE_POS);
      if (userJson) setUserName((JSON.parse(userJson) as StoredUser).name);
      if (posJson)  setPosName((JSON.parse(posJson) as StoredPos).name);
    } catch {
      // ignore
    }

    // Verifica che il token sia ancora valido con una chiamata /me
    api.me().then(({ status, data }) => {
      if (status === 401) {
        clearAuthStorage();
        router.replace('/login');
      } else if (status === 200) {
        setUserName(data.user?.name);
        setPosName(data.active_pos?.name);
        setReady(true);
      }
    }).catch(() => {
      // In caso di errore di rete, mostriamo il layout con i dati locali
      setReady(true);
    });
  }, [router]);

  if (!ready) {
    return (
      <div className="flex h-dvh items-center justify-center bg-zinc-50 dark:bg-zinc-950">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center animate-pulse">
            <span className="text-white text-sm font-bold">T</span>
          </div>
          <p className="text-sm text-zinc-400">Caricamento…</p>
        </div>
      </div>
    );
  }

  return (
    <AppShell posName={posName} userName={userName}>
      {children}
    </AppShell>
  );
}
