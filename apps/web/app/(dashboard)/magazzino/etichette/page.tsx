'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import Link from 'next/link';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
  downloadPdfFromBase64,
  getLabelTemplates,
  getProductByBarcode,
  printLabelsPdf,
  type ApiLabelTemplate,
  type ApiProduct,
} from '@/lib/api';

type SelectedItem = { product: ApiProduct; qty: number };

export default function EtichettePage() {
  const [templates, setTemplates] = useState<ApiLabelTemplate[]>([]);
  const [selectedTemplateId, setSelectedTemplateId] = useState('');
  const [scanner, setScanner] = useState('');
  const [items, setItems] = useState<SelectedItem[]>([]);
  const [startPosition, setStartPosition] = useState(1);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    void (async () => {
      const res = await getLabelTemplates();
      if (res.status === 200) {
        const rows = res.data.data ?? [];
        setTemplates(rows);
        if (rows.length > 0) setSelectedTemplateId(rows[0].id);
      }
    })();
  }, []);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const selectedTemplate = useMemo(
    () => templates.find((t) => t.id === selectedTemplateId) ?? null,
    [templates, selectedTemplateId],
  );

  const totalLabels = useMemo(() => items.reduce((acc, x) => acc + Math.max(1, x.qty), 0), [items]);

  const gridCells = useMemo(() => {
    if (!selectedTemplate) return 0;
    return Number(selectedTemplate.cols) * Number(selectedTemplate.rows);
  }, [selectedTemplate]);

  async function addByBarcode() {
    const code = scanner.trim();
    if (!code) return;
    setError(null);
    const { status, data } = await getProductByBarcode(code);
    if (status !== 200 || !data.data?.product?.id) {
      setError('Barcode non trovato.');
      return;
    }
    const p = data.data.product;
    setItems((prev) => {
      const idx = prev.findIndex((x) => x.product.id === p.id);
      if (idx === -1) return [...prev, { product: p, qty: 1 }];
      const copy = [...prev];
      copy[idx] = { ...copy[idx], qty: copy[idx].qty + 1 };
      return copy;
    });
    setScanner('');
    inputRef.current?.focus();
  }

  async function generatePdf() {
    if (!selectedTemplateId || totalLabels <= 0) return;
    setBusy(true);
    setError(null);
    try {
      const ids: string[] = [];
      items.forEach((item) => {
        for (let i = 0; i < Math.max(1, item.qty); i++) ids.push(item.product.id);
      });
      const { status, data } = await printLabelsPdf({
        product_ids: ids,
        template_id: selectedTemplateId,
        start_position: startPosition,
        copies: 1,
      });
      if (status !== 200) {
        setError('Generazione PDF non riuscita.');
        return;
      }
      downloadPdfFromBase64(data.filename, data.pdf_base64);
    } catch {
      setError('Errore durante la generazione PDF.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Stampa etichette</h1>
          <p className="text-sm text-muted-foreground">Scanner barcode, selezione template e stampa A4.</p>
        </div>
        <Link href="/magazzino/etichette/template" className={cn(buttonVariants({ variant: 'outline' }))}>
          Gestisci template
        </Link>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <section className="space-y-4 rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Prodotti da stampare</h2>
          <input
            ref={inputRef}
            value={scanner}
            onChange={(e) => setScanner(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                void addByBarcode();
              }
            }}
            placeholder="Scanner barcode e premi Invio"
            className="w-full rounded-lg border border-border bg-background px-3 py-2"
          />
          <div className="overflow-hidden rounded-lg border border-border">
            <table className="w-full text-sm">
              <thead className="bg-muted/40">
                <tr>
                  <th className="px-3 py-2 text-left">Prodotto</th>
                  <th className="px-3 py-2 text-left">Barcode</th>
                  <th className="px-3 py-2 text-left">Qtà</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr><td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">Nessun prodotto selezionato</td></tr>
                ) : items.map((x) => (
                  <tr key={x.product.id} className="border-t border-border">
                    <td className="px-3 py-2">{[x.product.brand, x.product.model].filter(Boolean).join(' ') || x.product.id}</td>
                    <td className="px-3 py-2">{x.product.barcode ?? '—'}</td>
                    <td className="px-3 py-2">
                      <input
                        type="number"
                        min={1}
                        value={x.qty}
                        onChange={(e) => setItems((prev) => prev.map((p) => p.product.id === x.product.id ? { ...p, qty: Math.max(1, Number(e.target.value || 1)) } : p))}
                        className="w-20 rounded border border-border bg-background px-2 py-1"
                      />
                    </td>
                    <td className="px-3 py-2 text-right">
                      <Button variant="ghost" size="sm" onClick={() => setItems((prev) => prev.filter((p) => p.product.id !== x.product.id))}>Rimuovi</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="text-sm font-medium">Totale etichette: {totalLabels}</p>
        </section>

        <section className="space-y-4 rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Configurazione stampa</h2>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Template</span>
            <select value={selectedTemplateId} onChange={(e) => setSelectedTemplateId(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2">
              {templates.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name} ({t.cols}x{t.rows})
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Posizione di partenza</span>
            <input
              type="number"
              min={1}
              max={Math.max(1, gridCells)}
              value={startPosition}
              onChange={(e) => setStartPosition(Math.max(1, Number(e.target.value || 1)))}
              className="w-40 rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>

          {selectedTemplate && (
            <div className="space-y-2">
              <p className="text-xs text-muted-foreground">Anteprima griglia</p>
              <div className="grid gap-1 rounded-lg border border-border p-2" style={{ gridTemplateColumns: `repeat(${selectedTemplate.cols}, minmax(0,1fr))` }}>
                {Array.from({ length: gridCells }, (_, i) => {
                  const pos = i + 1;
                  const selected = pos === startPosition;
                  const toPrint = pos >= startPosition && pos < startPosition + totalLabels;
                  return (
                    <button
                      type="button"
                      key={pos}
                      onClick={() => setStartPosition(pos)}
                      className={cn(
                        'h-8 rounded text-[10px] border',
                        selected ? 'border-blue-600 bg-blue-100 text-blue-700' : toPrint ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-border bg-background',
                      )}
                    >
                      {pos}
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          {error && <p className="text-sm text-destructive">{error}</p>}
          <Button disabled={busy || totalLabels === 0 || !selectedTemplateId} onClick={() => void generatePdf()}>
            {busy ? 'Generazione…' : 'Genera PDF'}
          </Button>
        </section>
      </div>
    </div>
  );
}
