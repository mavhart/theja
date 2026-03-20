import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Dashboard' };

// ─── Card statistiche ─────────────────────────────────────────────────────────

interface StatCard {
  label:       string;
  value:       string;
  description: string;
  color:       string;
}

const STAT_CARDS: StatCard[] = [
  {
    label:       'Pazienti oggi',
    value:       '—',
    description: 'Nuovi pazienti registrati oggi',
    color:       'text-blue-600 dark:text-blue-400',
  },
  {
    label:       'Vendite oggi',
    value:       '—',
    description: 'Totale vendite di giornata',
    color:       'text-emerald-600 dark:text-emerald-400',
  },
  {
    label:       'Ordini in attesa',
    value:       '—',
    description: 'Da completare o consegnare',
    color:       'text-amber-600 dark:text-amber-400',
  },
  {
    label:       'Appuntamenti oggi',
    value:       '—',
    description: 'Visite programmate',
    color:       'text-violet-600 dark:text-violet-400',
  },
];

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function DashboardPage() {
  return (
    <div className="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

      {/* Header */}
      <div>
        <h1 className="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
          Dashboard
        </h1>
        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
          Riepilogo della giornata per il tuo punto vendita.
        </p>
      </div>

      {/* Statistiche */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        {STAT_CARDS.map((card) => (
          <div
            key={card.label}
            className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 md:p-5 shadow-sm"
          >
            <p className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
              {card.label}
            </p>
            <p className={`mt-2 text-3xl font-bold ${card.color}`}>
              {card.value}
            </p>
            <p className="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
              {card.description}
            </p>
          </div>
        ))}
      </div>

      {/* Placeholder sezioni future */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm min-h-[200px] flex items-center justify-center">
          <p className="text-sm text-zinc-400 dark:text-zinc-500">
            Ultimi appuntamenti — disponibile dalla Fase 6
          </p>
        </div>
        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm min-h-[200px] flex items-center justify-center">
          <p className="text-sm text-zinc-400 dark:text-zinc-500">
            Ultime vendite — disponibile dalla Fase 4
          </p>
        </div>
      </div>

    </div>
  );
}
