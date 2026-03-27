'use client';

import { useEffect, useMemo, useState } from 'react';
import { createSumupPayment, getStoredPosId, getSumupPaymentStatus } from '@/lib/api';

interface SumUpPaymentModalProps {
  open: boolean;
  onClose: () => void;
  amount: number;
  description?: string;
}

export default function SumUpPaymentModal({ open, onClose, amount, description }: SumUpPaymentModalProps) {
  const [loading, setLoading] = useState(false);
  const [paymentId, setPaymentId] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open || !paymentId) return;
    const id = setInterval(async () => {
      const res = await getSumupPaymentStatus(paymentId, getStoredPosId() ?? undefined);
      if (res.status >= 400) return;
      const next = String((res.data.data as Record<string, unknown>)?.status ?? '');
      setStatus(next);
      if (['SUCCESSFUL', 'FAILED', 'CANCELLED'].includes(next)) {
        clearInterval(id);
      }
    }, 3000);
    return () => clearInterval(id);
  }, [open, paymentId]);

  const finalState = useMemo(() => ['SUCCESSFUL', 'FAILED', 'CANCELLED'].includes(status ?? ''), [status]);

  async function handleStart(): Promise<void> {
    setLoading(true);
    setError(null);
    setStatus(null);
    try {
      const res = await createSumupPayment({
        pos_id: getStoredPosId() ?? undefined,
        amount,
        description: description ?? 'Pagamento vendita',
      });
      if (res.status >= 400) throw new Error('Errore creazione pagamento SumUp');
      const id = String((res.data.data as Record<string, unknown>)?.id ?? '');
      if (!id) throw new Error('ID pagamento non disponibile');
      setPaymentId(id);
      setStatus(String((res.data.data as Record<string, unknown>)?.status ?? 'PENDING'));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Errore imprevisto');
    } finally {
      setLoading(false);
    }
  }

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div className="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-5 space-y-4">
        <div>
          <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Pagamento SumUp</h3>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">Importo: EUR {amount.toFixed(2)}</p>
        </div>

        <button
          onClick={() => void handleStart()}
          disabled={loading || !!paymentId}
          className="w-full rounded-lg bg-blue-600 text-white py-2.5 disabled:opacity-50"
        >
          {loading ? 'Avvio...' : 'Avvia pagamento SumUp'}
        </button>

        {paymentId && (
          <div className="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-sm">
            <p><span className="font-medium">Payment ID:</span> {paymentId}</p>
            <p className={finalState ? 'font-semibold' : 'animate-pulse'}>
              Stato: {status ?? 'PENDING'}
            </p>
          </div>
        )}

        {error && <p className="text-sm text-red-600">{error}</p>}

        <div className="flex justify-end">
          <button onClick={onClose} className="rounded-lg px-3 py-2 text-sm bg-zinc-100 dark:bg-zinc-800">
            Chiudi
          </button>
        </div>
      </div>
    </div>
  );
}

