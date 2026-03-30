'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { cn } from '@/lib/utils';
import { api, clearAuthStorage, getStoredToken } from '@/lib/api';
import { disconnectEcho } from '@/lib/echo';

// ─── Tipi ─────────────────────────────────────────────────────────────────────

interface NavItem {
  href:  string;
  label: string;
  icon:  React.ReactNode;
  children?: { href: string; label: string }[];
}

// ─── Icone inline (nessuna dipendenza esterna per ora) ────────────────────────

const icons = {
  dashboard: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </svg>
  ),
  patients: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  ),
  inventory: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
    </svg>
  ),
  sales: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <line x1="12" y1="1" x2="12" y2="23" />
      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
    </svg>
  ),
  agenda: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
      <line x1="16" y1="2" x2="16" y2="6" />
      <line x1="8"  y1="2" x2="8"  y2="6" />
      <line x1="3"  y1="10" x2="21" y2="10" />
    </svg>
  ),
  cashRegister: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <path d="M7 9h10" />
      <path d="M8 14h2" />
      <path d="M12 14h4" />
    </svg>
  ),
  settings: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <circle cx="12" cy="12" r="3" />
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
    </svg>
  ),
  invoices: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M6 2h9l3 3v17H6z" />
      <path d="M9 12h6" />
      <path d="M9 16h6" />
      <path d="M9 8h4" />
    </svg>
  ),
  communications: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    </svg>
  ),
  report: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14" />
      <path d="M8 9h4" />
      <path d="M8 13h6" />
      <path d="M6 21h12" />
    </svg>
  ),
  menu:  (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="w-5 h-5">
      <line x1="3" y1="6"  x2="21" y2="6" />
      <line x1="3" y1="12" x2="21" y2="12" />
      <line x1="3" y1="18" x2="21" y2="18" />
    </svg>
  ),
  logout: (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} className="w-5 h-5">
      <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
      <polyline points="16 17 21 12 16 7" />
      <line x1="21" y1="12" x2="9" y2="12" />
    </svg>
  ),
};

const NAV_ITEMS: NavItem[] = [
  { href: '/',  label: 'Dashboard',  icon: icons.dashboard  },
  { href: '/pazienti',   label: 'Pazienti',   icon: icons.patients   },
  {
    href: '/magazzino',
    label: 'Magazzino',
    icon: icons.inventory,
    children: [
      { href: '/magazzino', label: 'Prodotti' },
      { href: '/magazzino/fornitori', label: 'Fornitori' },
      { href: '/magazzino/etichette', label: 'Etichette' },
    ],
  },
  { href: '/vendite',    label: 'Vendite',    icon: icons.sales      },
  { href: '/cassa',      label: 'Cassa',      icon: icons.cashRegister },
  { href: '/agenda',     label: 'Agenda',     icon: icons.agenda     },
  {
    href: '/fatture',
    label: 'Fatture',
    icon: icons.invoices,
    children: [
      { href: '/fatture', label: 'Fatture' },
      { href: '/fatture/tessera-sanitaria', label: 'Tessera Sanitaria' },
    ],
  },
  { href: '/comunicazioni', label: 'Comunicazioni', icon: icons.communications },
  { href: '/report', label: 'Report', icon: icons.report },
  { href: '/impostazioni', label: 'Impostazioni', icon: icons.settings },
];

// ─── Componente ───────────────────────────────────────────────────────────────

interface AppShellProps {
  children:    React.ReactNode;
  posName?:    string;
  userName?:   string;
}

export default function AppShell({ children, posName, userName }: AppShellProps) {
  const pathname          = usePathname();
  const router            = useRouter();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loggingOut, setLoggingOut]   = useState(false);

  async function handleLogout() {
    if (loggingOut) return;
    setLoggingOut(true);

    try {
      const token = getStoredToken();
      if (token) await api.logout(token);
    } finally {
      disconnectEcho();
      clearAuthStorage();
      router.replace('/login');
    }
  }

  return (
    <div className="flex h-dvh overflow-hidden bg-zinc-50 dark:bg-zinc-950">

      {/* ── Overlay mobile ──────────────────────────────────────────────────── */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-20 bg-black/40 md:hidden"
          onClick={() => setSidebarOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* ── Sidebar (desktop sempre visibile, mobile slide-in) ──────────────── */}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 transition-transform duration-200',
          'md:relative md:translate-x-0',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        {/* Logo */}
        <div className="flex h-16 items-center gap-2 px-5 border-b border-zinc-200 dark:border-zinc-800">
          <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
            <span className="text-white text-sm font-bold">T</span>
          </div>
          <span className="font-semibold text-zinc-900 dark:text-zinc-100">Theja</span>
          {posName && (
            <span className="ml-auto text-xs text-zinc-500 dark:text-zinc-400 truncate max-w-[90px]">
              {posName}
            </span>
          )}
        </div>

        {/* Nav items */}
        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
          {NAV_ITEMS.map((item) => {
            const active = pathname === item.href || pathname.startsWith(item.href + '/');
            return (
              <div key={item.href} className="space-y-0.5">
                <Link
                  href={item.href}
                  onClick={() => setSidebarOpen(false)}
                  className={cn(
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                    active
                      ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400'
                      : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-zinc-100',
                  )}
                >
                  {item.icon}
                  {item.label}
                </Link>
                {item.children && active && (
                  <div className="ml-8 space-y-0.5">
                    {item.children.map((c) => {
                      const cActive = pathname === c.href || pathname.startsWith(c.href + '/');
                      return (
                        <Link
                          key={c.href}
                          href={c.href}
                          onClick={() => setSidebarOpen(false)}
                          className={cn(
                            'block rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                            cActive
                              ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400'
                              : 'text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800',
                          )}
                        >
                          {c.label}
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </nav>

        {/* User + logout */}
        <div className="border-t border-zinc-200 dark:border-zinc-800 p-3">
          <div className="flex items-center gap-3 px-2 py-2 rounded-xl">
            <div className="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center flex-shrink-0">
              <span className="text-xs font-medium text-zinc-600 dark:text-zinc-300">
                {userName?.[0]?.toUpperCase() ?? 'U'}
              </span>
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                {userName ?? 'Utente'}
              </p>
            </div>
            <button
              onClick={handleLogout}
              disabled={loggingOut}
              title="Logout"
              className="text-zinc-400 hover:text-red-500 dark:hover:text-red-400 transition-colors p-1 rounded"
            >
              {icons.logout}
            </button>
          </div>
        </div>
      </aside>

      {/* ── Main area ─────────────────────────────────────────────────────── */}
      <div className="flex flex-1 flex-col overflow-hidden">

        {/* Header mobile */}
        <header className="flex h-14 items-center gap-3 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 md:hidden">
          <button
            onClick={() => setSidebarOpen(true)}
            className="text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
            aria-label="Apri menu"
          >
            {icons.menu}
          </button>
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 rounded-md bg-blue-600 flex items-center justify-center">
              <span className="text-white text-xs font-bold">T</span>
            </div>
            <span className="font-semibold text-sm text-zinc-900 dark:text-zinc-100">
              {posName ?? 'Theja'}
            </span>
          </div>
          <div className="ml-auto">
            <button
              onClick={handleLogout}
              disabled={loggingOut}
              className="text-zinc-400 hover:text-red-500 transition-colors p-1 rounded"
              title="Logout"
            >
              {icons.logout}
            </button>
          </div>
        </header>

        {/* Contenuto pagina */}
        <main className="flex-1 overflow-y-auto">
          {children}
        </main>

        {/* ── Bottom nav mobile (PWA) ────────────────────────────────────── */}
        <nav className="flex md:hidden border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 safe-area-bottom">
          {NAV_ITEMS.slice(0, 5).map((item) => {
            const active = pathname === item.href || pathname.startsWith(item.href + '/');
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex flex-1 flex-col items-center justify-center gap-1 py-2 text-[10px] font-medium transition-colors',
                  active
                    ? 'text-blue-600 dark:text-blue-400'
                    : 'text-zinc-400 dark:text-zinc-500',
                )}
              >
                {item.icon}
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>
      </div>
    </div>
  );
}
