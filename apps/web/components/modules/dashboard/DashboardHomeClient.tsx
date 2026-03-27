'use client';

import { useEffect, useState } from 'react';
import { getAppointmentsToday, getBirthdayPatients, getLacSchedules, getPendingOrdersDashboard, type ApiAppointment, type ApiLacSchedule, type ApiPatient } from '@/lib/api';

interface StatCard {
  label:       string;
  value:       string;
  description: string;
  color:       string;
}

export default function DashboardHomeClient({ statCards }: { statCards: StatCard[] }) {
  const [lacRows, setLacRows] = useState<ApiLacSchedule[]>([]);
  const [lacLoading, setLacLoading] = useState(true);
  const [orders, setOrders] = useState({ sent: 0, in_progress: 0, ready: 0 });
  const [appointmentsToday, setAppointmentsToday] = useState<ApiAppointment[]>([]);
  const [birthdaysToday, setBirthdaysToday] = useState<ApiPatient[]>([]);

  useEffect(() => {
    getLacSchedules({ expiring_days: 7 })
      .then(({ status, data }) => {
        if (status === 200) setLacRows(data.data ?? []);
      })
      .finally(() => setLacLoading(false));

    getPendingOrdersDashboard().then(({ status, data }) => {
      if (status === 200) {
        setOrders({
          sent: data.data.sent ?? 0,
          in_progress: data.data.in_progress ?? 0,
          ready: data.data.ready ?? 0,
        });
      }
    });

    getAppointmentsToday().then(({ status, data }) => {
      if (status === 200) setAppointmentsToday(data.data ?? []);
    });

    getBirthdayPatients().then(({ status, data }) => {
      if (status === 200) setBirthdaysToday(data.data ?? []);
    });
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
          <div className="w-full space-y-3">
            <h2 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Ordini lab</h2>
            <div className="grid grid-cols-1 gap-2 text-sm">
              <div className="rounded-lg border border-zinc-200 dark:border-zinc-800 p-2 flex items-center justify-between">
                <span>In attesa lavorazione</span>
                <span className="rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-semibold">{orders.sent}</span>
              </div>
              <div className="rounded-lg border border-zinc-200 dark:border-zinc-800 p-2 flex items-center justify-between">
                <span>In lavorazione</span>
                <span className="rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-semibold">{orders.in_progress}</span>
              </div>
              <div className="rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 p-2 flex items-center justify-between">
                <span>Pronti da consegnare</span>
                <span className="rounded-full bg-emerald-100 dark:bg-emerald-900 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">{orders.ready}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm">
          <h2 className="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Appuntamenti oggi</h2>
          {appointmentsToday.length === 0 ? (
            <p className="text-sm text-zinc-400 dark:text-zinc-500">Nessun appuntamento per oggi</p>
          ) : (
            <div className="space-y-2">
              {appointmentsToday.slice(0, 8).map((a) => (
                <div key={a.id} className="rounded-lg border border-zinc-200 dark:border-zinc-800 px-3 py-2 text-sm">
                  <div className="font-medium">
                    {new Date(a.start_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })} — {a.type}
                  </div>
                  <div className="text-zinc-500 dark:text-zinc-400">
                    {a.patient ? `${a.patient.last_name} ${a.patient.first_name}` : (a.title ?? 'Appuntamento')}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 shadow-sm">
          <h2 className="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Compleanni oggi</h2>
          {birthdaysToday.length === 0 ? (
            <p className="text-sm text-zinc-400 dark:text-zinc-500">Nessun compleanno oggi</p>
          ) : (
            <div className="space-y-2">
              {birthdaysToday.slice(0, 8).map((p) => (
                <div key={p.id} className="rounded-lg border border-zinc-200 dark:border-zinc-800 px-3 py-2 text-sm">
                  <div className="font-medium">{p.last_name} {p.first_name}</div>
                  <div className="text-zinc-500 dark:text-zinc-400">
                    {p.date_of_birth ? new Date(p.date_of_birth).toLocaleDateString('it-IT') : '—'}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
