'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { addSalePayment, createAfterSaleEvent, createOrder, getAfterSaleEvents, getSale, scheduleSalePayments, updateOrderStatus, type ApiAfterSaleEvent, type ApiOrder, type ApiSale } from '@/lib/api';

type TabId = 'fornitura' | 'pagamenti' | 'ordine' | 'assistenza';

export default function SaleDetailPage() {
  const { id } = useParams<{ id: string }>();
  const [tab, setTab] = useState<TabId>('fornitura');
  const [sale, setSale] = useState<ApiSale | null>(null);
  const [events, setEvents] = useState<ApiAfterSaleEvent[]>([]);
  const [order, setOrder] = useState<ApiOrder | null>(null);
  const [amount, setAmount] = useState('0');
  const [method, setMethod] = useState<'contanti' | 'carta' | 'bonifico' | 'assegno' | 'altro'>('carta');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [rateCount, setRateCount] = useState(3);
  const [evtType, setEvtType] = useState<'riparazione' | 'garanzia' | 'reso' | 'adattamento' | 'altro'>('riparazione');
  const [evtDesc, setEvtDesc] = useState('');

  const load = useCallback(async () => {
    if (!id) return;
    const [s, e] = await Promise.all([getSale(id), getAfterSaleEvents(id)]);
    if (s.status === 200) {
      setSale(s.data.data);
      setAmount(String(s.data.data.remaining_amount ?? 0));
      const firstOrder = Array.isArray((s.data.data as unknown as { order?: ApiOrder[] }).order) ? ((s.data.data as unknown as { order?: ApiOrder[] }).order?.[0] ?? null) : null;
      setOrder(firstOrder);
    }
    if (e.status === 200) setEvents(e.data.data ?? []);
  }, [id]);

  useEffect(() => { void load(); }, [load]);

  if (!sale) return <div className="p-6 text-sm text-muted-foreground">Caricamento vendita...</div>;

  async function savePayment(e: FormEvent) {
    e.preventDefault();
    await addSalePayment(sale!.id, { amount: Number(amount), method, payment_date: date });
    await load();
  }

  async function planRates() {
    const rem = Number(sale!.remaining_amount ?? 0);
    const base = Math.round((rem / rateCount) * 100) / 100;
    const schedule = Array.from({ length: rateCount }).map((_, i) => ({
      amount: i === rateCount - 1 ? Math.max(0, rem - base * (rateCount - 1)) : base,
      scheduled_date: new Date(Date.now() + (i + 1) * 30 * 24 * 3600 * 1000).toISOString().slice(0, 10),
      method,
    }));
    await scheduleSalePayments(sale!.id, schedule);
    await load();
  }

  async function createLabOrder() {
    const raw = localStorage.getItem('theja_active_pos');
    const posId = raw ? (JSON.parse(raw) as { id: string }).id : null;
    if (!posId) return;
    const res = await createOrder({ pos_id: posId, sale_id: sale!.id, patient_id: sale!.patient_id, order_date: new Date().toISOString().slice(0, 10), total_amount: sale!.total_amount });
    if (res.status === 200 || res.status === 201) setOrder(res.data.data);
  }

  async function addEvent(e: FormEvent) {
    e.preventDefault();
    await createAfterSaleEvent({ sale_id: sale!.id, type: evtType, description: evtDesc });
    setEvtDesc('');
    await load();
  }

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <h1 className="text-2xl font-semibold">Vendita {sale.id.slice(0, 8)}</h1>
      <div className="flex gap-1 border-b border-border">
        {['fornitura','pagamenti','ordine','assistenza'].map((k) => (
          <button key={k} onClick={() => setTab(k as TabId)} className={tab === k ? 'rounded-t border border-b-0 border-border bg-card px-4 py-2 text-sm' : 'px-4 py-2 text-sm text-muted-foreground'}>{k === 'ordine' ? 'ordine lab' : k}</button>
        ))}
      </div>

      {tab === 'fornitura' && (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 text-sm">
          {(sale.items ?? []).map((it) => <div key={it.id} className="flex items-center justify-between border-b border-border/60 py-2"><span>{it.description} ({it.item_type})</span><span>EUR {Number(it.total).toFixed(2)}</span></div>)}
          {sale.type.includes('occhiale') && <p className="text-muted-foreground">Sezione occhiali/buste con montatura + lente DX/SX e prescrizione collegata.</p>}
        </div>
      )}

      {tab === 'pagamenti' && (
        <div className="space-y-4 rounded-xl border border-border bg-card p-4 text-sm">
          <p>Totale EUR {Number(sale.total_amount).toFixed(2)} - Pagato EUR {Number(sale.paid_amount).toFixed(2)} - Residuo EUR {Number(sale.remaining_amount ?? 0).toFixed(2)}</p>
          <div className="h-3 overflow-hidden rounded bg-muted"><div className="h-full bg-emerald-500" style={{ width: `${Math.min(100, Math.round((Number(sale.paid_amount) / Math.max(1, Number(sale.total_amount))) * 100))}%` }} /></div>
          <form onSubmit={savePayment} className="grid gap-2 sm:grid-cols-4">
            <input type="number" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} className="rounded border border-border px-2 py-1" />
            <select value={method} onChange={(e) => setMethod(e.target.value as typeof method)} className="rounded border border-border px-2 py-1"><option value="contanti">Contanti</option><option value="carta">Carta</option><option value="bonifico">Bonifico</option><option value="assegno">Assegno</option><option value="altro">Altro</option></select>
            <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="rounded border border-border px-2 py-1" />
            <Button type="submit">Aggiungi pagamento</Button>
          </form>
          <div className="flex gap-2"><input type="number" min={2} max={12} value={rateCount} onChange={(e) => setRateCount(Number(e.target.value || 3))} className="w-24 rounded border border-border px-2 py-1" /><Button variant="outline" onClick={() => void planRates()}>Pianifica rate</Button></div>
          {(sale.payments ?? []).map((p) => <div key={p.id} className="flex justify-between border-b border-border/60 py-1"><span>{new Date(p.payment_date).toLocaleDateString('it-IT')} - {p.method} {p.is_scheduled ? '(rata)' : ''}</span><span>EUR {Number(p.amount).toFixed(2)}</span></div>)}
        </div>
      )}

      {tab === 'ordine' && (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 text-sm">
          {!order ? <Button onClick={() => void createLabOrder()}>Crea ordine lab</Button> : (
            <>
              <p>Codice busta: <strong>{order.job_code ?? '-'}</strong></p>
              <p>Stato: <strong>{order.status}</strong></p>
              <div className="flex flex-wrap gap-2">{(['draft','sent','in_progress','ready','delivered','cancelled'] as const).map((s) => <Button key={s} size="sm" variant={order.status === s ? 'default' : 'outline'} onClick={() => void updateOrderStatus(order.id, s).then((r) => { if (r.status === 200) setOrder(r.data.data); })}>{s}</Button>)}</div>
            </>
          )}
        </div>
      )}

      {tab === 'assistenza' && (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 text-sm">
          <form onSubmit={addEvent} className="grid gap-2 sm:grid-cols-[180px_1fr_auto]">
            <select value={evtType} onChange={(e) => setEvtType(e.target.value as typeof evtType)} className="rounded border border-border px-2 py-1"><option value="riparazione">Riparazione</option><option value="garanzia">Garanzia</option><option value="reso">Reso</option><option value="adattamento">Adattamento</option><option value="altro">Altro</option></select>
            <input required value={evtDesc} onChange={(e) => setEvtDesc(e.target.value)} className="rounded border border-border px-2 py-1" placeholder="Descrizione evento" />
            <Button type="submit">Nuovo evento</Button>
          </form>
          {events.length === 0 ? <p className="text-muted-foreground">Nessun evento post-vendita</p> : events.map((e) => <div key={e.id} className="flex justify-between border-b border-border/60 py-1"><span>{e.type} - {e.description}</span><span>{e.status}</span></div>)}
        </div>
      )}
    </div>
  );
}