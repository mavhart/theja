'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useDebounce } from '@/hooks/useDebounce';
import { formatDateIt, maskFiscalCode } from '@/lib/patient-utils';
import { getPatients, type ApiPatient } from '@/lib/api';

function fullName(p: ApiPatient): string {
  return [p.last_name, p.first_name, p.last_name2].filter(Boolean).join(' ');
}

function TableSkeleton() {
  return (
    <div className="animate-pulse space-y-2">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-10 rounded-md bg-muted" />
      ))}
    </div>
  );
}

export default function PazientiListPage() {
  const router = useRouter();
  const [search, setSearch] = useState('');
  const debounced = useDebounce(search, 300);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [rows, setRows] = useState<ApiPatient[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data: body, status } = await getPatients(debounced, page);
      if (status === 401) {
        router.replace('/login');
        return;
      }
      if (status !== 200) {
        setError('Impossibile caricare l\'elenco pazienti.');
        setRows([]);
        return;
      }
      setRows(body.data ?? []);
      setMeta({
        current_page: body.meta?.current_page ?? 1,
        last_page:    body.meta?.last_page ?? 1,
        total:        body.meta?.total ?? 0,
      });
    } catch {
      setError('Errore di rete.');
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [debounced, page, router]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [debounced]);

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Pazienti</h1>
          <p className="text-sm text-muted-foreground">Cerca e gestisci le schede anagrafiche.</p>
        </div>
        <Link href="/pazienti/nuovo" className={cn(buttonVariants())}>
          Nuovo paziente
        </Link>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <input
          type="search"
          placeholder="Cerca per nome, cognome, CF, cellulare…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full max-w-md rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
        {meta.total > 0 && (
          <span className="text-sm text-muted-foreground">
            {meta.total} risultat{meta.total === 1 ? 'o' : 'i'}
          </span>
        )}
      </div>

      {error && (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}

      <div className="overflow-x-auto rounded-xl border border-border bg-card">
        {loading ? (
          <div className="p-4">
            <TableSkeleton />
          </div>
        ) : rows.length === 0 ? (
          <div className="flex min-h-[200px] items-center justify-center p-8 text-muted-foreground">
            Nessun paziente trovato
          </div>
        ) : (
          <table className="w-full min-w-[720px] text-left text-sm">
            <thead className="border-b border-border bg-muted/40">
              <tr>
                <th className="px-4 py-3 font-medium">Nome completo</th>
                <th className="px-4 py-3 font-medium">Data nascita</th>
                <th className="px-4 py-3 font-medium">Codice fiscale</th>
                <th className="px-4 py-3 font-medium">Cellulare</th>
                <th className="px-4 py-3 font-medium">Città</th>
                <th className="px-4 py-3 font-medium">Data inserimento</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((p) => (
                <tr
                  key={p.id}
                  role="link"
                  tabIndex={0}
                  onClick={() => router.push(`/pazienti/${p.id}`)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.preventDefault();
                      router.push(`/pazienti/${p.id}`);
                    }
                  }}
                  className="cursor-pointer border-b border-border/60 transition-colors hover:bg-muted/50"
                >
                  <td className="px-4 py-3 font-medium text-foreground">{fullName(p)}</td>
                  <td className="px-4 py-3 text-muted-foreground">{formatDateIt(p.date_of_birth)}</td>
                  <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{maskFiscalCode(p.fiscal_code)}</td>
                  <td className="px-4 py-3">{p.mobile ?? '—'}</td>
                  <td className="px-4 py-3">{p.city ?? '—'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{formatDateIt(p.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {!loading && meta.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Precedente
          </Button>
          <span className="text-sm text-muted-foreground">
            Pagina {meta.current_page} di {meta.last_page}
          </span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={page >= meta.last_page}
            onClick={() => setPage((p) => p + 1)}
          >
            Successiva
          </Button>
        </div>
      )}
    </div>
  );
}
