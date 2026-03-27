'use client';

import { useCallback, useEffect, useState } from 'react';
import { getSupplier, getSuppliers, type ApiSupplier } from '@/lib/api';
import { useDebounce } from '@/hooks/useDebounce';
import { Button } from '@/components/ui/button';

function supplierName(s: ApiSupplier): string {
  if (s.company_name) return s.company_name;
  return [s.last_name, s.first_name].filter(Boolean).join(' ') || '—';
}

export default function FornitoriPage() {
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search, 300);
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<ApiSupplier[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<ApiSupplier | null>(null);
  const [open, setOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { status, data } = await getSuppliers({ q: debounced, page });
      if (status === 200) {
        setRows(data.data ?? []);
        setMeta({
          current_page: data.meta?.current_page ?? 1,
          last_page: data.meta?.last_page ?? 1,
          total: data.meta?.total ?? 0,
        });
      } else {
        setRows([]);
      }
    } finally {
      setLoading(false);
    }
  }, [debounced, page]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [debounced]);

  async function openDetail(id: string) {
    const { status, data } = await getSupplier(id);
    if (status === 200) {
      setSelected(data.data);
      setOpen(true);
    }
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Fornitori</h1>
        <p className="text-sm text-muted-foreground">Rubrica fornitori e categorie merceologiche.</p>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Cerca per nome o codice…"
          className="w-full max-w-md rounded-lg border border-border bg-background px-3 py-2 text-sm"
        />
        <span className="text-sm text-muted-foreground">{meta.total} risultati</span>
      </div>

      <div className="overflow-x-auto rounded-xl border border-border bg-card">
        {loading ? (
          <div className="space-y-2 p-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="h-10 animate-pulse rounded-md bg-muted" />
            ))}
          </div>
        ) : (
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="border-b border-border bg-muted/40">
              <tr>
                <th className="px-4 py-3">Ragione sociale / Nome</th>
                <th className="px-4 py-3">Codice</th>
                <th className="px-4 py-3">Città</th>
                <th className="px-4 py-3">Telefono</th>
                <th className="px-4 py-3">Categorie</th>
                <th className="px-4 py-3">Attivo</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                    Nessun fornitore trovato
                  </td>
                </tr>
              ) : (
                rows.map((s) => (
                  <tr key={s.id} className="cursor-pointer border-b border-border/60 hover:bg-muted/50" onClick={() => void openDetail(s.id)}>
                    <td className="px-4 py-3">{supplierName(s)}</td>
                    <td className="px-4 py-3">{String(s.code ?? '—')}</td>
                    <td className="px-4 py-3">{String(s.city ?? '—')}</td>
                    <td className="px-4 py-3">{String(s.phone ?? '—')}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        {(s.categories ?? []).map((c) => (
                          <span key={c} className="rounded-full bg-muted px-2 py-0.5 text-xs">
                            {c}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${s.is_active ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200' : 'bg-zinc-500/15 text-zinc-700 dark:text-zinc-300'}`}>
                        {s.is_active ? 'Sì' : 'No'}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}
      </div>

      {!loading && meta.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
            Precedente
          </Button>
          <span className="text-sm text-muted-foreground">
            Pagina {meta.current_page} di {meta.last_page}
          </span>
          <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>
            Successiva
          </Button>
        </div>
      )}

      {open && selected && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog">
          <div className="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-border bg-background p-6">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold">{supplierName(selected)}</h3>
              <Button variant="ghost" size="sm" onClick={() => setOpen(false)}>
                Chiudi
              </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              {Object.entries(selected).map(([k, v]) => (
                <div key={k} className="rounded-lg border border-border p-3">
                  <p className="text-xs text-muted-foreground">{k}</p>
                  <p className="text-sm break-words">
                    {Array.isArray(v) ? v.join(', ') : v == null || v === '' ? '—' : String(v)}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
