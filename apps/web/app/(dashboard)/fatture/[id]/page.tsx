'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { Button } from '@/components/ui/button';
import {
  downloadPdfFromBase64,
  downloadXml,
  fetchInvoicePdf,
  fetchInvoiceXml,
  getInvoice,
  issueInvoice,
  sendSdiInvoice,
  type ApiInvoice,
  type ApiInvoiceItem,
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

export default function FatturaDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [invoice, setInvoice] = useState<ApiInvoice | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!id) return;
    setBusy(true);
    try {
      const res = await getInvoice(id);
      if (res.status === 200) setInvoice(res.data.data);
    } finally {
      setBusy(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const items: ApiInvoiceItem[] = useMemo(() => invoice?.items ?? [], [invoice]);

  async function onIssue() {
    if (!invoice) return;
    const res = await issueInvoice(invoice.id);
    if (res.status === 200 || res.status === 201) await load();
  }

  async function onSendSdi() {
    if (!invoice) return;
    const res = await sendSdiInvoice(invoice.id);
    if (res.status === 200 || res.status === 201) await load();
  }

  async function onDownloadPdf() {
    if (!invoice) return;
    const res = await fetchInvoicePdf(invoice.id);
    if (res.status === 200) downloadPdfFromBase64(res.data.filename, res.data.pdf_base64);
  }

  async function onDownloadXml() {
    if (!invoice) return;
    const res = await fetchInvoiceXml(invoice.id);
    if (res.status === 200) downloadXml(res.data.filename, res.data.xml);
  }

  if (!invoice) {
    return <div className="p-6 text-sm text-muted-foreground">{busy ? 'Caricamento…' : 'Fattura non trovata'}</div>;
  }

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold">
            Fattura {invoice.formatted_number || invoice.invoice_number}
          </h1>
          <p className="text-sm text-muted-foreground">
            Data: {invoice.invoice_date ? new Date(invoice.invoice_date).toLocaleDateString('it-IT') : '—'}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <span className={`rounded px-2 py-0.5 text-xs ${statusBadgeClass(invoice.status)}`}>{invoice.status}</span>
          <Button
            size="sm"
            onClick={() => void onIssue()}
            disabled={invoice.status !== 'draft'}
            variant={invoice.status === 'draft' ? 'default' : 'secondary'}
          >
            Emetti
          </Button>
          <Button
            size="sm"
            onClick={() => void onSendSdi()}
            disabled={invoice.status !== 'issued'}
            variant={invoice.status === 'issued' ? 'secondary' : 'secondary'}
          >
            Invia SDI
          </Button>
          <Button size="sm" variant="outline" onClick={() => void onDownloadPdf()}>
            Scarica PDF
          </Button>
          <Button size="sm" variant="outline" onClick={() => void onDownloadXml()}>
            Scarica XML
          </Button>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <section className="rounded-xl border border-border bg-card p-4">
          <h2 className="mb-2 text-sm font-semibold">Righe</h2>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[860px] text-left text-sm">
              <thead className="border-b border-border bg-muted/40">
                <tr>
                  <th className="px-3 py-2">Descrizione</th>
                  <th className="px-3 py-2">Qtà</th>
                  <th className="px-3 py-2">Prezzo</th>
                  <th className="px-3 py-2">Sconto %</th>
                  <th className="px-3 py-2">Imponibile</th>
                  <th className="px-3 py-2">IVA</th>
                  <th className="px-3 py-2">Totale riga</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-3 py-6 text-center text-muted-foreground">
                      Nessuna riga
                    </td>
                  </tr>
                ) : (
                  items.map((it) => {
                    const subtotal = Number(it.subtotal ?? 0);
                    const vat = Number(it.vat_amount ?? 0);
                    const total = Number(it.total ?? 0);
                    return (
                      <tr key={it.id} className="border-b border-border/60">
                        <td className="px-3 py-2">{it.description}</td>
                        <td className="px-3 py-2">{Number(it.quantity ?? 0).toFixed(3)}</td>
                        <td className="px-3 py-2">€ {Number(it.unit_price ?? 0).toFixed(2)}</td>
                        <td className="px-3 py-2">{Number(it.discount_percent ?? 0).toFixed(2)}</td>
                        <td className="px-3 py-2">€ {subtotal.toFixed(2)}</td>
                        <td className="px-3 py-2">
                          {Number(it.vat_rate ?? 0).toFixed(2)}% (€ {vat.toFixed(2)})
                        </td>
                        <td className="px-3 py-2">€ {total.toFixed(2)}</td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex justify-end gap-6 text-sm">
            <div className="text-right">
              <p>Subtotale: € {Number(invoice.subtotal ?? 0).toFixed(2)}</p>
              <p>IVA: € {Number(invoice.vat_amount ?? 0).toFixed(2)}</p>
              <p className="font-semibold">Totale: € {Number(invoice.total ?? 0).toFixed(2)}</p>
            </div>
          </div>

          {invoice.notes && <p className="mt-4 text-sm text-muted-foreground">Note: {invoice.notes}</p>}
        </section>

        <aside className="space-y-4 rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Cliente</h2>
          <p className="text-sm">
            <strong>{invoice.customer_name}</strong>
            <br />
            CF: {invoice.customer_fiscal_code ?? '—'}
          </p>
          <p className="text-sm text-muted-foreground">
            {invoice.customer_city || invoice.customer_cap ? (
              <>
                {invoice.customer_city ?? ''} {invoice.customer_cap ?? ''}
                <br />
              </>
            ) : null}
            {invoice.customer_province ? `${invoice.customer_province}` : null}
            {invoice.customer_country ? <><br />{invoice.customer_country}</> : null}
          </p>

          <div>
            <h2 className="mb-2 text-sm font-semibold">Pagamento</h2>
            <p className="text-sm">
              Metodo: {invoice.payment_method ?? '—'}
              <br />
              Termini: {invoice.payment_terms ?? '—'}
            </p>
          </div>

          <div>
            <h2 className="mb-2 text-sm font-semibold">SDI</h2>
            <p className="text-sm">
              Identificativo: {invoice.sdi_identifier ?? '—'}
              <br />
              Inviato: {invoice.sdi_sent_at ? new Date(invoice.sdi_sent_at).toLocaleString('it-IT') : '—'}
            </p>
          </div>
        </aside>
      </div>
    </div>
  );
}

