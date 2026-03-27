import type { Metadata } from 'next';
import DashboardHomeClient from '@/components/modules/dashboard/DashboardHomeClient';

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
  return <DashboardHomeClient statCards={STAT_CARDS} />;
}
