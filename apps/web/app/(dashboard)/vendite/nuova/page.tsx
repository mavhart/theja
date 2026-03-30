'use client';

import { FormEvent, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { addSalePayment, createSale, getProductByBarcode, type ApiProduct } from '@/lib/api';

type CartRow = {
  id: string;
  product_id?: string | null;
  barcode?: string | null;
  description: string;
  quantity: number;
  unit_price: number;
  discount_percent: number;
  discount_amount: number;
  total: number;
  item_type: 'montatura' | 'lente_dx' | 'lente_sx' | 'lente_contatto' | 'accessorio' | 'servizio' | 'altro';
};

const PAYMENT_ACTIONS = [
  'Acconto',
  'Pagamento',
  'Consegna',
  'Acconto Fattura',
  'Pagamento Fattura',
  'Pagamento Consegna Fattura',
  'Acconto Ricevuta',
  'Pagamento Ricevuta',
  'Pagamento Consegna Ricevuta',
];

export default function NuovaVenditaPage() {
  const router = useRouter();
  const [patientId, setPatientId] = useState('');
  const [saleType, setSaleType] = useState<'occhiale_vista' | 'occhiale_sole' | 'sostituzione_lenti' | 'sostituzione_montatura' | 'lac' | 'accessorio' | 'servizio' | 'generico'>('occhiale_vista');
  const [barcode, setBarcode] = useState('');
  const [rows, setRows] = useState<CartRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [createdSaleId, setCreatedSaleId] = useState<string | null>(null);

  const [showPay, setShowPay] = useState(false);
  const [payAction, setPayAction] = useState('Pagamento');
  const [payAmount, setPayAmount] = useState('0');
  const [payMethod, setPayMethod] = useState<'contanti' | 'carta' | 'bonifico' | 'assegno' | 'altro'>('carta');
  const [payDate, setPayDate] = useState(new Date().toISOString().slice(0, 10));
  const [payNotes, setPayNotes] = useState('');

  const total = useMemo(() => rows.reduce((a, x) => a + x.total, 0), [rows]);

  function upsertFromProduct(p: ApiProduct) {
    const desc = [p.brand, p.line, p.model].filter(Boolean).join(' ') || p.id;
    const unit = Number(p.sale_price ?? 0);
    setRows((prev) => {
      const idx = prev.findIndex((r) => r.product_id === p.id);
      if (idx >= 0) {
        const copy = [...prev];
        const row = { ...copy[idx], quantity: copy[idx].quantity + 1 };
        row.total = calcTotal(row);
        copy[idx] = row;
        return copy;
      }
      const row: CartRow = {
        id: crypto.randomUUID(),
        product_id: p.id,
        barcode: p.barcode,
        description: desc,
        quantity: 1,
        unit_price: unit,
        discount_percent: 0,
        discount_amount: 0,
        total: unit,
        item_type: 'altro',
      };
      return [...prev, row];
    });
  }

  function calcTotal(r: CartRow): number {
    const sub = r.quantity * r.unit_price;
    return Math.max(0, sub - (sub * r.discount_percent / 100) - r.discount_amount);
  }

  async function onScanEnter() {
    const code = barcode.trim();
    if (!code) return;
    setError(null);
    const { status, data } = await getProductByBarcode(code);
    if (status !== 200 || !data.data?.product) {
      setError('Barcode non trovato.');
      return;
    }
    upsertFromProduct(data.data.product);
    setBarcode('');
  }

  async function create() {
    if (rows.length === 0) return;
    setBusy(true);
    setError(null);
    try {
      const activePos = typeof window !== 'undefined' ? localStorage.getItem('theja_active_pos') : null;
      const posId = activePos ? (JSON.parse(activePos) as { id: string }).id : null;
      if (!posId) {
        setError('POS attivo non trovato.');
        return;
      }

      const payload = {
        pos_id: posId,
        patient_id: patientId || null,
        type: saleType,
        sale_date: new Date().toISOString().slice(0, 10),
        items: rows.map((r) => ({
          product_id: r.product_id ?? null,
          description: r.description,
          quantity: r.quantity,
          unit_price: r.unit_price,
          discount_percent: r.discount_percent,
          discount_amount: r.discount_amount,
          item_type: r.item_type,
        })),
      };

      const { status, data } = await createSale(payload);
      if (status !== 200 && status !== 201) {
        setError('Creazione vendita non riuscita.');
        return;
      }
      const id = data.data?.id;
      if (id) {
        setCreatedSaleId(id);
        router.replace(`/vendite/${id}`);
      }
    } finally {
      setBusy(false);
    }
  }

  async function submitPayment(e: FormEvent) {
    e.preventDefault();
    if (!createdSaleId) return;
    const { status } = await addSalePayment(createdSaleId, {
      amount: Number(payAmount),
      method: payMethod,
      payment_date: payDate,
      notes: `${payAction}${payNotes ? ' — ' + payNotes : ''}`,
    });
    if (status === 200) setShowPay(false);
  }

  return (
    <div className="mx-auto max-w-7xl grid gap-6 p-6 lg:grid-cols-[1.6fr_1fr]">
      <section className="space-y-4 rounded-xl border border-border bg-card p-4">
        <h1 className="text-xl font-semibold">Vendita rapida</h1>
        <div className="grid gap-3 sm:grid-cols-2">
          <input placeholder="ID paziente (scanner tessera)" value={patientId} onChange={(e) => setPatientId(e.target.value)} className="rounded border border-border bg-background px-3 py-2" />
          <select value={saleType} onChange={(e) => setSaleType(e.target.value as typeof saleType)} className="rounded border border-border bg-background px-3 py-2">
            <option value="occhiale_vista">Occhiale vista</option>
            <option value="occhiale_sole">Occhiale sole</option>
            <option value="sostituzione_lenti">Sostituzione lenti</option>
            <option value="sostituzione_montatura">Sostituzione montatura</option>
            <option value="lac">LAC</option>
            <option value="accessorio">Accessorio</option>
            <option value="servizio">Servizio</option>
            <option value="generico">Generico</option>
          </select>
        </div>

        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-muted/40">
              <tr>
                <th className="px-2 py-2 text-left">Barcode</th>
                <th className="px-2 py-2 text-left">Descrizione</th>
                <th className="px-2 py-2 text-left">Qtà</th>
                <th className="px-2 py-2 text-left">Prezzo</th>
                <th className="px-2 py-2 text-left">Sconto %</th>
                <th className="px-2 py-2 text-left">Sconto €</th>
                <th className="px-2 py-2 text-left">Totale</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr><td colSpan={7} className="px-2 py-6 text-center text-muted-foreground">Carrello vuoto</td></tr>
              ) : rows.map((r) => (
                <tr key={r.id} className="border-t border-border">
                  <td className="px-2 py-2">{r.barcode ?? '—'}</td>
                  <td className="px-2 py-2">{r.description}</td>
                  <td className="px-2 py-2"><input type="number" min={1} value={r.quantity} onChange={(e) => setRows((prev) => prev.map((x) => x.id === r.id ? { ...x, quantity: Number(e.target.value || 1), total: calcTotal({ ...x, quantity: Number(e.target.value || 1) }) } : x))} className="w-16 rounded border border-border px-2 py-1" /></td>
                  <td className="px-2 py-2"><input type="number" step="0.01" value={r.unit_price} onChange={(e) => setRows((prev) => prev.map((x) => x.id === r.id ? { ...x, unit_price: Number(e.target.value || 0), total: calcTotal({ ...x, unit_price: Number(e.target.value || 0) }) } : x))} className="w-24 rounded border border-border px-2 py-1" /></td>
                  <td className="px-2 py-2"><input type="number" step="0.01" value={r.discount_percent} onChange={(e) => setRows((prev) => prev.map((x) => x.id === r.id ? { ...x, discount_percent: Number(e.target.value || 0), total: calcTotal({ ...x, discount_percent: Number(e.target.value || 0) }) } : x))} className="w-20 rounded border border-border px-2 py-1" /></td>
                  <td className="px-2 py-2"><input type="number" step="0.01" value={r.discount_amount} onChange={(e) => setRows((prev) => prev.map((x) => x.id === r.id ? { ...x, discount_amount: Number(e.target.value || 0), total: calcTotal({ ...x, discount_amount: Number(e.target.value || 0) }) } : x))} className="w-24 rounded border border-border px-2 py-1" /></td>
                  <td className="px-2 py-2">€ {r.total.toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <input
          autoFocus
          placeholder="Scanner barcode articolo e premi Invio"
          value={barcode}
          onChange={(e) => setBarcode(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              void onScanEnter();
            }
          }}
          className="w-full rounded border border-border bg-background px-3 py-2"
        />
      </section>

      <aside className="space-y-4 rounded-xl border border-border bg-card p-4">
        <h2 className="text-sm font-semibold">Riepilogo</h2>
        <div className="rounded border border-border p-3 text-sm">
          <p>Totale: <strong>€ {total.toFixed(2)}</strong></p>
          <p>Residuo: <strong>€ {total.toFixed(2)}</strong></p>
        </div>
        <Button onClick={() => void create()} disabled={busy || rows.length === 0}>{busy ? 'Creazione...' : 'Salva vendita'}</Button>
        <div className="grid gap-2">
          {PAYMENT_ACTIONS.map((action) => (
            <Button key={action} variant="outline" onClick={() => {
              setPayAction(action);
              setPayAmount(total.toFixed(2));
              setShowPay(true);
            }}>
              {action}
            </Button>
          ))}
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
      </aside>

      {showPay && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <form onSubmit={submitPayment} className="w-full max-w-md space-y-3 rounded-xl bg-background p-5">
            <h3 className="text-lg font-semibold">{payAction}</h3>
            <input type="number" step="0.01" value={payAmount} onChange={(e) => setPayAmount(e.target.value)} className="w-full rounded border border-border px-3 py-2" />
            <select value={payMethod} onChange={(e) => setPayMethod(e.target.value as typeof payMethod)} className="w-full rounded border border-border px-3 py-2">
              <option value="contanti">Contanti</option>
              <option value="carta">Carta</option>
              <option value="bonifico">Bonifico</option>
              <option value="assegno">Assegno</option>
              <option value="altro">Altro</option>
            </select>
            <input type="date" value={payDate} onChange={(e) => setPayDate(e.target.value)} className="w-full rounded border border-border px-3 py-2" />
            <textarea value={payNotes} onChange={(e) => setPayNotes(e.target.value)} className="w-full rounded border border-border px-3 py-2" placeholder="Note" />
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setShowPay(false)}>Annulla</Button>
              <Button type="submit">Conferma</Button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}