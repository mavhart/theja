'use client';

import { useEffect, useState } from 'react';
import { getLacSchedules, type ApiLacSchedule } from '@/lib/api';

interface StatCard {
  label:       string;
  value:       string;
  description: string;
  color:       string;
}

export default function DashboardHomeClient({ statCards }: { statCards: StatCard[] }) {
  const [lacRows, setLacRows] = useState<ApiLacSchedule[]>([]);
  const [lacLoading, setLacLoading] = useState(true);

  useEffect(() => {
    getLacSchedules({ expiring_days: 7 })
      .then(({ status, data }) => {
        if (status === 200) setLacRows(data.data ?? []);
      })
      .finally(() => setLacLoading(false));
  }, []);

  return (
    <div className="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Dashboard</h1>
        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Riepilogo della giornata per il tuo punto vendita.</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        {statCards.map((card) => (
          <div key={card.label} className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-4 md:p-5 shadow-sm">
            <p className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">{card.label}</p>
            <p className={`mt-2 text-3xl font-bold ${card.color}`}>{card.value}</p>
            <p className="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{card.description}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm">
          <h2 className="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Prossime scadenze LAC</h2>
          {lacLoading ? (
            <div className="space-y-2">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="h-8 animate-pulse rounded bg-zinc-100 dark:bg-zinc-800" />
              ))}
            </div>
          ) : lacRows.length === 0 ? (
            <p className="text-sm text-zinc-400 dark:text-zinc-500">Nessuna scadenza imminente</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[520px] text-left text-sm">
                <thead>
                  <tr className="text-xs text-zinc-500">
                    <th className="pb-2">Paziente</th>
                    <th className="pb-2">Prodotto LAC</th>
                    <th className="pb-2">Data scadenza</th>
                    <th className="pb-2">Giorni rimanenti</th>
                  </tr>
                </thead>
                <tbody>
                  {lacRows.map((r) => (
                    <tr key={r.id} className="border-t border-zinc-100 dark:border-zinc-800">
                      <td className="py-2">{String(r.patient_name ?? r.patient_id)}</td>
                      <td className="py-2">{String(r.product_name ?? r.product_id)}</td>
                      <td className="py-2">{new Date(r.estimated_end_date).toLocaleDateString('it-IT')}</td>
                      <td className="py-2">{r.days_remaining ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm min-h-[200px] flex items-center justify-center">
          <p className="text-sm text-zinc-400 dark:text-zinc-500">Ultime vendite — disponibile dalla Fase 4</p>
        </div>
      </div>
    </div>
  );
}
