'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { buttonVariants } from '@/components/ui/button';
import { useDebounce } from '@/hooks/useDebounce';
import { cn } from '@/lib/utils';
import { getProducts, type ApiProduct, type ProductCategory } from '@/lib/api';

type ProductTab = ProductCategory | 'all';

const TABS: { id: ProductTab; label: string }[] = [
  { id: 'montatura', label: 'Montature' },
  { id: 'lente_oftalmica', label: 'Lenti Oftalmiche' },
  { id: 'lente_contatto', label: 'Lenti a Contatto' },
  { id: 'liquido_accessorio', label: 'Liquidi e Accessori' },
  { id: 'all', label: 'Tutti' },
];

function eur(v: unknown): string {
  if (v == null || v === '') return '—';
  const n = Number(v);
  if (!Number.isFinite(n)) return '—';
  return n.toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });
}

function stockBadge(qty: number): { text: string; cls: string } {
  if (qty <= 0) return { text: 'Esaurita', cls: 'bg-red-500/15 text-red-800 dark:text-red-200' };
  if (qty <= 2) return { text: 'Bassa', cls: 'bg-amber-500/15 text-amber-900 dark:text-amber-100' };
  return { text: 'OK', cls: 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200' };
}

function qtyAvailable(p: ApiProduct): number {
  const inv = (p as ApiProduct & { inventory_total?: number }).inventory_total;
  return Number.isFinite(Number(inv)) ? Number(inv) : 0;
}

export default function MagazzinoPage() {
  const router = useRouter();
  const [tab, setTab] = useState<ProductTab>('montatura');
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search, 300);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [rows, setRows] = useState<ApiProduct[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data, status } = await getProducts({
        q: debounced,
        category: tab,
        page,
      });
      if (status === 401) {
        router.replace('/login');
        return;
      }
      if (status !== 200) {
        setError('Impossibile caricare i prodotti.');
        setRows([]);
        return;
      }
      setRows(data.data ?? []);
      setMeta({
        current_page: data.meta?.current_page ?? 1,
        last_page: data.meta?.last_page ?? 1,
        total: data.meta?.total ?? 0,
      });
    } catch {
      setError('Errore di rete.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [debounced, tab, page, router]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [debounced, tab]);

  const table = useMemo(() => {
    if (loading) {
      return (
        <div className="space-y-2 p-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="h-10 animate-pulse rounded-md bg-muted" />
          ))}
        </div>
      );
    }
    if (rows.length === 0) {
      return (
        <div className="flex min-h-[180px] items-center justify-center p-8 text-sm text-muted-foreground">
          Nessun prodotto trovato
        </div>
      );
    }
    return (
      <table className="w-full min-w-[1100px] text-left text-sm">
        <thead className="border-b border-border bg-muted/40">
          <tr>
            <th className="px-4 py-3 font-medium">Codice a barre</th>
            <th className="px-4 py-3 font-medium">Fornitore</th>
            <th className="px-4 py-3 font-medium">Marchio / Linea / Modello</th>
            <th className="px-4 py-3 font-medium">Colore</th>
            <th className="px-4 py-3 font-medium">Calibro / Ponte / Asta</th>
            <th className="px-4 py-3 font-medium">Tipo</th>
            <th className="px-4 py-3 font-medium">Utente</th>
            <th className="px-4 py-3 font-medium">Prezzo vendita</th>
            <th className="px-4 py-3 font-medium">Qtà disponibile</th>
            <th className="px-4 py-3 font-medium">Scorta</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((p) => {
            const qty = qtyAvailable(p);
            const badge = stockBadge(qty);
            return (
              <tr
                key={p.id}
                className="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/50"
                onClick={() => router.push(`/magazzino/${p.id}`)}
              >
                <td className="px-4 py-3 font-mono text-xs">{p.barcode ?? '—'}</td>
                <td className="px-4 py-3">{(p.supplier?.company_name as string) ?? (p.supplier?.last_name as string) ?? '—'}</td>
                <td className="px-4 py-3">{[p.brand, p.line, p.model].filter(Boolean).join(' / ') || '—'}</td>
                <td className="px-4 py-3">{p.color ?? '—'}</td>
                <td className="px-4 py-3">{[p.caliber, p.bridge, p.temple].filter((v) => v != null).join(' / ') || '—'}</td>
                <td className="px-4 py-3">{p.lens_type ?? '—'}</td>
                <td className="px-4 py-3">{p.user_type ?? '—'}</td>
                <td className="px-4 py-3">{eur(p.sale_price)}</td>
                <td className="px-4 py-3">{qty}</td>
                <td className="px-4 py-3">
                  <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', badge.cls)}>
                    {badge.text}
                  </span>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    );
  }, [loading, rows, router]);

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Magazzino</h1>
          <p className="text-sm text-muted-foreground">Catalogo prodotti e disponibilità.</p>
        </div>
        <Link href="/magazzino/nuovo" className={cn(buttonVariants())}>
          Nuovo prodotto
        </Link>
      </div>

      <div className="flex flex-wrap gap-1 rounded-xl border border-border bg-card p-1">
        {TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={cn(
              'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              tab === t.id ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <input
          type="search"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Cerca barcode, modello, fornitore…"
          className="w-full max-w-md rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
        <span className="text-sm text-muted-foreground">{meta.total} risultati</span>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <div className="overflow-x-auto rounded-xl border border-border bg-card">{table}</div>

      {!loading && meta.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button
            type="button"
            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Precedente
          </button>
          <span className="text-sm text-muted-foreground">
            Pagina {meta.current_page} di {meta.last_page}
          </span>
          <button
            type="button"
            className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Successiva
          </button>
        </div>
      )}
    </div>
  );
}
