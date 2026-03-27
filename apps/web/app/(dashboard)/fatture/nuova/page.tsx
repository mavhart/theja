'use client';

import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import {
  createInvoice,
  getPatient,
  getSale,
  getStoredPosId,
  type ApiPatient,
} from '@/lib/api';

type Mode = 'sale' | 'manual';
type InvoiceType = 'fattura' | 'ricevuta' | 'fattura_pa';

type Row = {
  id: string;
  description: string;
  quantity: number;
  unit_price: number;
  discount_percent: number;
  vat_rate: number;
  sts_code?: string | null;
};

type RowComputed = Row & {
  subtotal: number;
  vat_amount: number;
  total: number;
};

function calcRow(r: Row): RowComputed {
  const lineSub = r.quantity * r.unit_price;
  const lineSubAfterDiscount = Math.max(0, lineSub - (lineSub * r.discount_percent) / 100);
  const vatAmount = lineSubAfterDiscount * r.vat_rate / 100;
  return {
    ...r,
    subtotal: round2(lineSubAfterDiscount),
    vat_amount: round2(vatAmount),
    total: round2(lineSubAfterDiscount + vatAmount),
  };
}

function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

export default function NuovaFatturaPage() {
  const router = useRouter();

  const [mode, setMode] = useState<Mode>('sale');
  const [invoiceType, setInvoiceType] = useState<InvoiceType>('fattura');
  const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().slice(0, 10));

  const [posId, setPosId] = useState<string>('');
  const [patientId, setPatientId] = useState<string>('');
  const [patient, setPatient] = useState<ApiPatient | null>(null);

  // From sale
  const [saleId, setSaleId] = useState<string>('');

  const [paymentMethod, setPaymentMethod] = useState<string>('carta');
  const [paymentTerms, setPaymentTerms] = useState<string>('');
  const [notes, setNotes] = useState<string>('');

  const [rows, setRows] = useState<RowComputed[]>([
    {
      id: crypto.randomUUID(),
      description: '',
      quantity: 1,
      unit_price: 0,
      discount_percent: 0,
      vat_rate: 22,
      sts_code: null,
      subtotal: 0,
      vat_amount: 0,
      total: 0,
    },
  ]);

  const totals = useMemo(() => {
    const subtotal = rows.reduce((a, x) => a + x.subtotal, 0);
    const vat = rows.reduce((a, x) => a + x.vat_amount, 0);
    const total = rows.reduce((a, x) => a + x.total, 0);
    return { subtotal, vat, total };
  }, [rows]);

  useEffect(() => {
    const stored = getStoredPosId();
    if (stored) setPosId(stored);
  }, []);

  const loadPatient = useCallback(async () => {
    if (!patientId) return;
    const res = await getPatient(patientId);
    if (res.status === 200) setPatient(res.data.data);
  }, [patientId]);

  const prefillFromSale = useCallback(async () => {
    if (!saleId) return;
    const res = await getSale(saleId);
    if (res.status !== 200) return;
    const sale = res.data.data;

    setPosId(sale.pos_id);
    if (sale.patient_id) setPatientId(sale.patient_id);
    if (sale.patient) setPatient(sale.patient);
    if (sale.sale_date) setInvoiceDate(new Date(sale.sale_date).toISOString().slice(0, 10));

    const nextRows: RowComputed[] = (sale.items ?? []).map((it) => {
      const base: Row = {
        id: crypto.randomUUID(),
        description: it.description,
        quantity: Number(it.quantity ?? 0),
        unit_price: Number(it.unit_price ?? 0),
        discount_percent: Number(it.discount_percent ?? 0),
        vat_rate: Number(it.vat_rate ?? 0),
        sts_code: it.sts_code ?? null,
      };
      // Per coerenza con backend: subtotal = total (imponibile) dal sale_item.
      const computed = calcRow(base);
      return {
        ...computed,
        subtotal: Number(it.total ?? computed.subtotal),
        total: round2(Number(it.total ?? 0) + Number(it.total ?? 0) * Number(it.vat_rate ?? 0) / 100),
        vat_amount: round2(Number(it.total ?? 0) * Number(it.vat_rate ?? 0) / 100),
      };
    });

    setRows(nextRows.length ? nextRows : rows);
  }, [saleId, rows]);

  function setRow(id: string, patch: Partial<Row>) {
    setRows((prev) => prev.map((r) => (r.id === id ? calcRow({ ...r, ...patch }) : r)));
  }

  function addRow() {
    setRows((prev) => [
      ...prev,
      calcRow({
        id: crypto.randomUUID(),
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_percent: 0,
        vat_rate: 22,
        sts_code: null,
      }),
    ]);
  }

  function removeRow(id: string) {
    setRows((prev) => (prev.length <= 1 ? prev : prev.filter((r) => r.id !== id)));
  }

  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      if (mode === 'sale') {
        if (!saleId) throw new Error('Seleziona una vendita.');
        const payload: Record<string, unknown> = {
          sale_id: saleId,
          invoice_date: invoiceDate,
          type: invoiceType,
          payment_method: paymentMethod || null,
          payment_terms: paymentTerms || null,
          notes: notes || null,
        };

        const res = await createInvoice(payload);
        if (res.status === 200 || res.status === 201) router.replace(`/fatture/${res.data.data.id}`);
        else throw new Error('Creazione fattura fallita.');
        return;
      }

      if (!posId) throw new Error('POS non disponibile.');
      if (!patientId) throw new Error('Seleziona un paziente.');

      const payload: Record<string, unknown> = {
        pos_id: posId,
        patient_id: patientId,
        invoice_date: invoiceDate,
        type: invoiceType,
        payment_method: paymentMethod || null,
        payment_terms: paymentTerms || null,
        notes: notes || null,
        items: rows.map((r) => ({
          description: r.description,
          quantity: r.quantity,
          unit_price: r.unit_price,
          discount_percent: r.discount_percent,
          vat_rate: r.vat_rate,
          sts_code: r.sts_code ?? null,
        })),
      };

      const res = await createInvoice(payload);
      if (res.status === 200 || res.status === 201) router.replace(`/fatture/${res.data.data.id}`);
      else throw new Error('Creazione fattura fallita.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Errore generico');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <h1 className="text-2xl font-semibold">Nuova fattura</h1>

      <form onSubmit={onSubmit} className="space-y-4">
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant={mode === 'sale' ? 'default' : 'outline'} onClick={() => setMode('sale')}>
            Da vendita
          </Button>
          <Button type="button" variant={mode === 'manual' ? 'default' : 'outline'} onClick={() => setMode('manual')}>
            Manuale
          </Button>
        </div>

        <div className="grid gap-2 sm:grid-cols-3">
          <div className="space-y-1">
            <label className="text-sm text-muted-foreground">Tipo</label>
            <select value={invoiceType} onChange={(e) => setInvoiceType(e.target.value as InvoiceType)} className="w-full rounded border border-border bg-background px-3 py-2 text-sm">
              <option value="fattura">Fattura</option>
              <option value="ricevuta">Ricevuta</option>
              <option value="fattura_pa">Fattura PA</option>
            </select>
          </div>
          <div className="space-y-1">
            <label className="text-sm text-muted-foreground">Data</label>
            <input type="date" value={invoiceDate} onChange={(e) => setInvoiceDate(e.target.value)} className="w-full rounded border border-border bg-background px-3 py-2 text-sm" />
          </div>
          <div className="space-y-1">
            <label className="text-sm text-muted-foreground">Metodo pagamento</label>
            <select value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)} className="w-full rounded border border-border bg-background px-3 py-2 text-sm">
              <option value="contanti">contanti</option>
              <option value="carta">carta</option>
              <option value="bonifico">bonifico</option>
              <option value="assegno">assegno</option>
              <option value="altro">altro</option>
            </select>
          </div>
        </div>

        {mode === 'sale' ? (
          <div className="space-y-2 rounded-xl border border-border bg-card p-4">
            <h2 className="text-sm font-semibold">Selezione vendita</h2>
            <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
              <input value={saleId} onChange={(e) => setSaleId(e.target.value)} placeholder="sale_id" className="rounded border border-border bg-background px-3 py-2 text-sm" />
              <Button type="button" variant="outline" onClick={() => void prefillFromSale()} disabled={!saleId}>
                Precompila
              </Button>
              <div className="text-sm text-muted-foreground self-center">Cliente: {patient?.first_name ? `${patient.first_name} ${patient.last_name}` : '—'}</div>
            </div>
          </div>
        ) : (
          <div className="space-y-2 rounded-xl border border-border bg-card p-4">
            <h2 className="text-sm font-semibold">Dati cliente</h2>
            <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
              <input value={patientId} onChange={(e) => setPatientId(e.target.value)} placeholder="patient_id" className="rounded border border-border bg-background px-3 py-2 text-sm" />
              <Button type="button" variant="outline" onClick={() => void loadPatient()} disabled={!patientId}>
                Carica paziente
              </Button>
            </div>

            <div className="text-sm text-muted-foreground">
              {patient ? (
                <>
                  Cliente: {patient.first_name} {patient.last_name} — CF: {patient.fiscal_code ?? '—'}
                </>
              ) : (
                <>Nessun paziente caricato.</>
              )}
            </div>
          </div>
        )}

        <div className="rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Righe</h2>
          <div className="overflow-x-auto mt-3">
            <table className="w-full min-w-[920px] text-left text-sm">
              <thead className="border-b border-border bg-muted/40">
                <tr>
                  <th className="px-3 py-2">Descrizione</th>
                  <th className="px-3 py-2">Qtà</th>
                  <th className="px-3 py-2">Unit</th>
                  <th className="px-3 py-2">Sconto %</th>
                  <th className="px-3 py-2">IVA %</th>
                  <th className="px-3 py-2">Imponibile</th>
                  <th className="px-3 py-2">IVA</th>
                  <th className="px-3 py-2">Totale</th>
                  {mode === 'manual' && <th className="px-3 py-2">—</th>}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id} className="border-b border-border/60">
                    <td className="px-3 py-2">
                      <input
                        value={r.description}
                        onChange={(e) => setRow(r.id, { description: e.target.value })}
                        disabled={mode !== 'manual'}
                        className="w-full rounded border border-border bg-background px-2 py-1 disabled:opacity-60"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.001"
                        value={r.quantity}
                        onChange={(e) => setRow(r.id, { quantity: Number(e.target.value || 0) })}
                        disabled={mode !== 'manual'}
                        className="w-24 rounded border border-border bg-background px-2 py-1 disabled:opacity-60"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        value={r.unit_price}
                        onChange={(e) => setRow(r.id, { unit_price: Number(e.target.value || 0) })}
                        disabled={mode !== 'manual'}
                        className="w-28 rounded border border-border bg-background px-2 py-1 disabled:opacity-60"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        value={r.discount_percent}
                        onChange={(e) => setRow(r.id, { discount_percent: Number(e.target.value || 0) })}
                        disabled={mode !== 'manual'}
                        className="w-24 rounded border border-border bg-background px-2 py-1 disabled:opacity-60"
                      />
                    </td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        step="0.01"
                        value={r.vat_rate}
                        onChange={(e) => setRow(r.id, { vat_rate: Number(e.target.value || 0) })}
                        disabled={mode !== 'manual'}
                        className="w-24 rounded border border-border bg-background px-2 py-1 disabled:opacity-60"
                      />
                    </td>
                    <td className="px-3 py-2">€ {r.subtotal.toFixed(2)}</td>
                    <td className="px-3 py-2">€ {r.vat_amount.toFixed(2)}</td>
                    <td className="px-3 py-2">€ {r.total.toFixed(2)}</td>
                    {mode === 'manual' && (
                      <td className="px-3 py-2">
                        <Button type="button" size="sm" variant="outline" onClick={() => removeRow(r.id)}>
                          Rimuovi
                        </Button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {mode === 'manual' && (
            <div className="mt-3">
              <Button type="button" variant="outline" onClick={() => addRow()}>
                Aggiungi riga
              </Button>
            </div>
          )}

          <div className="mt-4 flex justify-end gap-6 text-sm">
            <div className="text-right">
              <p>Subtotale: € {totals.subtotal.toFixed(2)}</p>
              <p>IVA: € {totals.vat.toFixed(2)}</p>
              <p className="font-semibold">Totale: € {totals.total.toFixed(2)}</p>
            </div>
          </div>
        </div>

        <div className="grid gap-2 sm:grid-cols-2">
          <div className="space-y-1">
            <label className="text-sm text-muted-foreground">Termini pagamento</label>
            <input value={paymentTerms} onChange={(e) => setPaymentTerms(e.target.value)} className="w-full rounded border border-border bg-background px-3 py-2 text-sm" placeholder="es. 30 giorni" />
          </div>
          <div className="space-y-1">
            <label className="text-sm text-muted-foreground">Note</label>
            <input value={notes} onChange={(e) => setNotes(e.target.value)} className="w-full rounded border border-border bg-background px-3 py-2 text-sm" placeholder="note fattura" />
          </div>
        </div>

        {error && <p className="text-sm text-destructive">{error}</p>}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => router.back()} disabled={busy}>
            Annulla
          </Button>
          <Button type="submit" disabled={busy}>
            {busy ? 'Creazione…' : 'Crea fattura'}
          </Button>
        </div>
      </form>
    </div>
  );
}

