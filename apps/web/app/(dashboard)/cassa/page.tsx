'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ApiCashRegisterSession,
  ApiFiscalReceipt,
  closeCashRegisterSession,
  emitFiscalDocument,
  getCashRegisterSession,
  getCashRegisterSummary,
  getStoredPosId,
  openCashRegisterSession,
} from '@/lib/api';

export default function CassaPage() {
  const [openingAmount, setOpeningAmount] = useState('0.00');
  const [closingAmount, setClosingAmount] = useState('0.00');
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(false);
  const [session, setSession] = useState<ApiCashRegisterSession | null>(null);
  const [summary, setSummary] = useState<{ receipts: ApiFiscalReceipt[] }>({ receipts: [] });

  const posId = useMemo(() => getStoredPosId() ?? undefined, []);

  const refresh = useCallback(async (): Promise<void> => {
    const [s, x] = await Promise.all([
      getCashRegisterSession(posId),
      getCashRegisterSummary(posId),
    ]);
    setSession((s.data as { data: ApiCashRegisterSession | null }).data);
    setSummary((x.data as { data: { session: ApiCashRegisterSession | null; receipts: ApiFiscalReceipt[] } }).data ?? { receipts: [] });
  }, [posId]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  async function handleOpen(): Promise<void> {
    setLoading(true);
    try {
      await openCashRegisterSession({ pos_id: posId, opening_amount: Number(openingAmount) || 0 });
      await refresh();
    } finally {
      setLoading(false);
    }
  }

  async function handleClose(): Promise<void> {
    if (!session?.id) return;
    setLoading(true);
    try {
      await closeCashRegisterSession({
        pos_id: posId,
        session_id: String(session.id),
        closing_amount: Number(closingAmount) || 0,
        notes,
      });
      await refresh();
    } finally {
      setLoading(false);
    }
  }

  async function handleEmit(saleId: string): Promise<void> {
    setLoading(true);
    try {
      await emitFiscalDocument({ sale_id: saleId, type: 'scontrino' });
      await refresh();
    } finally {
      setLoading(false);
    }
  }

  const isOpen = session?.status === 'open';
  const totalSales = Number(session?.total_sales ?? 0);
  const totalCash = Number(session?.total_cash ?? 0);
  const totalCard = Number(session?.total_card ?? 0);
  const totalOther = Number(session?.total_other ?? 0);
  const diff = (Number(closingAmount) || 0) - (Number(session?.opening_amount ?? 0) + totalCash);

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Cassa virtuale</h1>
        <p className="text-sm text-zinc-500">Gestione sessione cassa e invio documento fiscale.</p>
      </div>

      {!isOpen && (
        <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 max-w-lg space-y-3">
          <h2 className="text-lg font-semibold">Apri sessione cassa</h2>
          <input
            type="number"
            step="0.01"
            value={openingAmount}
            onChange={(e) => setOpeningAmount(e.target.value)}
            className="w-full rounded-lg border border-zinc-300 dark:border-zinc-700 px-3 py-2 bg-transparent"
            placeholder="Importo apertura"
          />
          <button onClick={() => void handleOpen()} disabled={loading} className="rounded-lg bg-blue-600 text-white px-4 py-2 disabled:opacity-50">
            Apri sessione
          </button>
        </div>
      )}

      {isOpen && (
        <>
          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-2">
            <h2 className="text-lg font-semibold">Sessione aperta</h2>
            <p className="text-sm">Apertura: {String(session?.opened_at ?? '-')}</p>
            <p className="text-sm">Operatore: #{String(session?.user_id ?? '-')}</p>
            <p className="text-sm">Importo apertura: EUR {Number(session?.opening_amount ?? 0).toFixed(2)}</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <Stat label="Totale vendite" value={totalSales} />
            <Stat label="Contanti" value={totalCash} />
            <Stat label="Carta" value={totalCard} />
            <Stat label="Altro" value={totalOther} />
          </div>

          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-3">
            <h3 className="font-semibold">Ultimi documenti fiscali</h3>
            <div className="space-y-2">
              {summary.receipts.length === 0 && <p className="text-sm text-zinc-500">Nessun documento emesso nella sessione.</p>}
              {summary.receipts.map((r) => (
                <div key={String(r.id)} className="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-sm">
                  <div>
                    <p className="font-medium">{String(r.receipt_number)} — EUR {Number(r.total_amount ?? 0).toFixed(2)}</p>
                    <p className="text-zinc-500">Stato: {String(r.status ?? 'pending')}</p>
                  </div>
                  {!r.sale_id ? null : (
                    <button className="rounded bg-zinc-100 dark:bg-zinc-800 px-2 py-1" onClick={() => void handleEmit(String(r.sale_id))}>
                      Emetti documento fiscale
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-3 max-w-lg">
            <h3 className="font-semibold">Chiudi sessione</h3>
            <input
              type="number"
              step="0.01"
              value={closingAmount}
              onChange={(e) => setClosingAmount(e.target.value)}
              className="w-full rounded-lg border border-zinc-300 dark:border-zinc-700 px-3 py-2 bg-transparent"
              placeholder="Importo contato"
            />
            <p className="text-sm">Differenza calcolata: EUR {diff.toFixed(2)}</p>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="w-full rounded-lg border border-zinc-300 dark:border-zinc-700 px-3 py-2 bg-transparent"
              placeholder="Note"
            />
            <button onClick={() => void handleClose()} disabled={loading} className="rounded-lg bg-emerald-600 text-white px-4 py-2 disabled:opacity-50">
              Chiudi sessione
            </button>
          </div>
        </>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
      <p className="text-xs text-zinc-500">{label}</p>
      <p className="text-lg font-semibold">EUR {value.toFixed(2)}</p>
    </div>
  );
}

