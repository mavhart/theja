'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  fetchInvoicePdf,
  fetchInvoiceXml,
  getInvoices,
  issueInvoice,
  sendSdiInvoice,
  downloadPdfFromBase64,
  downloadXml,
  type ApiInvoice,
  type ApiInvoiceStatus,
} from '@/lib/api';

function statusBadgeClass(status: ApiInvoiceStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-zinc-100 text-zinc-700';
    case 'issued':
      return 'bg-blue-100 text-blue-700';
    case 'sent_sdi':
      return 'bg-orange-100 text-orange-700';
    case 'accepted':
      return 'bg-emerald-100 text-emerald-700';
    case 'rejected':
      return 'bg-red-100 text-red-700';
    case 'cancelled':
      return 'bg-zinc-200 text-zinc-700';
    default:
      return 'bg-zinc-100 text-zinc-700';
  }
}

export default function FatturePage() {
  const [rows, setRows] = useState<ApiInvoice[]>([]);
  const [status, setStatus] = useState<ApiInvoiceStatus | ''>('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setBusy(true);
    try {
      const { status: http, data } = await getInvoices({
        status: status || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page: 1,
      });

      if (http === 200) setRows(data.data ?? []);
    } finally {
      setBusy(false);
    }
  }, [status, dateFrom, dateTo]);

  useEffect(() => {
    void load();
  }, [load]);

  const statusOptions: Array<{ value: ApiInvoiceStatus | ''; label: string }> = useMemo(
    () => [
      { value: '', label: 'Tutti stati' },
      { value: 'draft', label: 'Draft' },
      { value: 'issued', label: 'Emessa' },
      { value: 'sent_sdi', label: 'Inviata SDI' },
      { value: 'accepted', label: 'Accettata' },
      { value: 'rejected', label: 'Rifiutata' },
      { value: 'cancelled', label: 'Annullata' },
    ],
    [],
  );

  async function onIssue(id: string) {
    const res = await issueInvoice(id);
    if (res.status === 200 || res.status === 201) await load();
  }

  async function onSendSdi(id: string) {
    const res = await sendSdiInvoice(id);
    if (res.status === 200 || res.status === 201) await load();
  }

  async function onDownloadPdf(id: string) {
    const res = await fetchInvoicePdf(id);
    if (res.status === 200) downloadPdfFromBase64(res.data.filename, res.data.pdf_base64);
  }

  async function onDownloadXml(id: string) {
    const res = await fetchInvoiceXml(id);
    if (res.status === 200) downloadXml(res.data.filename, res.data.xml);
  }

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Fatture</h1>
        <Link href="/fatture/nuova" className="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground">
          Nuova fattura
        </Link>
      </div>

      <div className="grid gap-2 sm:grid-cols-4">
        <select
          value={status}
          onChange={(e) => setStatus((e.target.value as ApiInvoiceStatus | '') || '')}
          className="rounded border border-border bg-background px-3 py-2 text-sm"
        >
          {statusOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <input
          type="date"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          className="rounded border border-border bg-background px-3 py-2 text-sm"
        />
        <input
          type="date"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          className="rounded border border-border bg-background px-3 py-2 text-sm"
        />
        <div className="flex items-center justify-end">
          <Button variant="outline" disabled={busy} onClick={() => void load()}>
            {busy ? 'Caricamento…' : 'Aggiorna'}
          </Button>
        </div>
      </div>

      <div className="overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full min-w-[980px] text-left text-sm">
          <thead className="border-b border-border bg-muted/40">
            <tr>
              <th className="px-3 py-2">Numero</th>
              <th className="px-3 py-2">Data</th>
              <th className="px-3 py-2">Cliente</th>
              <th className="px-3 py-2">Totale</th>
              <th className="px-3 py-2">IVA</th>
              <th className="px-3 py-2">Stato SDI</th>
              <th className="px-3 py-2">Azioni</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                  Nessuna fattura trovata
                </td>
              </tr>
            ) : (
              rows.map((inv) => {
                const total = Number(inv.total ?? 0);
                const vat = Number(inv.vat_amount ?? 0);
                return (
                  <tr key={inv.id} className="border-b border-border/60 hover:bg-muted/40">
                    <td className="px-3 py-2">
                      <Link href={`/fatture/${inv.id}`}>{inv.formatted_number || inv.invoice_number}</Link>
                    </td>
                    <td className="px-3 py-2">{inv.invoice_date ? new Date(inv.invoice_date).toLocaleDateString('it-IT') : '—'}</td>
                    <td className="px-3 py-2">{inv.customer_name}</td>
                    <td className="px-3 py-2">€ {total.toFixed(2)}</td>
                    <td className="px-3 py-2">€ {vat.toFixed(2)}</td>
                    <td className="px-3 py-2">
                      <span className={`rounded px-2 py-0.5 text-xs ${statusBadgeClass(inv.status)}`}>{inv.status}</span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap gap-2">
                        {inv.status === 'draft' && (
                          <Button size="sm" onClick={() => void onIssue(inv.id)}>
                            Emetti
                          </Button>
                        )}
                        {inv.status === 'issued' && (
                          <Button size="sm" variant="secondary" onClick={() => void onSendSdi(inv.id)}>
                            Invia SDI
                          </Button>
                        )}
                        <Button size="sm" variant="outline" onClick={() => void onDownloadPdf(inv.id)}>
                          PDF
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => void onDownloadXml(inv.id)}>
                          XML
                        </Button>
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

