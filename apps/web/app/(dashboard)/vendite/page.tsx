'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { getSales, type ApiSale } from '@/lib/api';

export default function VenditePage() {
  const [rows, setRows] = useState<ApiSale[]>([]);
  const [status, setStatus] = useState('');
  const [type, setType] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [patientId, setPatientId] = useState('');

  useEffect(() => {
    void (async () => {
      const { status: http, data } = await getSales({
        status: status || undefined,
        type: type || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        patient_id: patientId || undefined,
      });
      if (http === 200) setRows(data.data ?? []);
    })();
  }, [status, type, dateFrom, dateTo, patientId]);

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Vendite</h1>
        <Link href="/vendite/nuova" className="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground">Nuova vendita</Link>
      </div>

      <div className="grid gap-2 sm:grid-cols-5">
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border border-border bg-background px-3 py-2 text-sm">
          <option value="">Tutti stati</option>
          <option value="quote">Preventivo</option>
          <option value="confirmed">Confermata</option>
          <option value="delivered">Consegnata</option>
          <option value="cancelled">Annullata</option>
        </select>
        <input value={type} onChange={(e) => setType(e.target.value)} placeholder="Tipo" className="rounded border border-border bg-background px-3 py-2 text-sm" />
        <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="rounded border border-border bg-background px-3 py-2 text-sm" />
        <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="rounded border border-border bg-background px-3 py-2 text-sm" />
        <input value={patientId} onChange={(e) => setPatientId(e.target.value)} placeholder="Paziente (id)" className="rounded border border-border bg-background px-3 py-2 text-sm" />
      </div>

      <div className="overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full min-w-[980px] text-left text-sm">
          <thead className="border-b border-border bg-muted/40">
            <tr>
              <th className="px-3 py-2">Data</th>
              <th className="px-3 py-2">Paziente</th>
              <th className="px-3 py-2">Tipo</th>
              <th className="px-3 py-2">Totale</th>
              <th className="px-3 py-2">Pagato</th>
              <th className="px-3 py-2">Residuo</th>
              <th className="px-3 py-2">Stato</th>
              <th className="px-3 py-2">Operatore</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr><td colSpan={8} className="px-3 py-8 text-center text-muted-foreground">Nessuna vendita trovata</td></tr>
            ) : rows.map((s) => {
              const residual = Number(s.remaining_amount ?? 0);
              return (
                <tr key={s.id} className="border-b border-border/60 hover:bg-muted/40">
                  <td className="px-3 py-2"><Link href={`/vendite/${s.id}`}>{new Date(s.sale_date).toLocaleDateString('it-IT')}</Link></td>
                  <td className="px-3 py-2">{s.patient ? `${s.patient.last_name} ${s.patient.first_name}` : 'Occasionale'}</td>
                  <td className="px-3 py-2">{s.type}</td>
                  <td className="px-3 py-2">€ {Number(s.total_amount).toFixed(2)}</td>
                  <td className="px-3 py-2">€ {Number(s.paid_amount).toFixed(2)}</td>
                  <td className="px-3 py-2">
                    <span className={residual > 0 ? 'rounded bg-red-100 px-2 py-0.5 text-red-700' : 'rounded bg-emerald-100 px-2 py-0.5 text-emerald-700'}>
                      € {residual.toFixed(2)}
                    </span>
                  </td>
                  <td className="px-3 py-2">{s.status_label ?? s.status}</td>
                  <td className="px-3 py-2">{s.user?.name ?? s.user_id}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}