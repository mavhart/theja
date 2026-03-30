'use client';

import { useEffect, useMemo, useState } from 'react';
import {
  Bar,
  BarChart,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import {
  getAiForecastReorders,
  getAiOpportunities,
  getAiRevenueAnalysis,
  getAiTrends,
  getInventoryReport,
  getPatientsReport,
  getSalesReport,
  getTopProductsReport,
  queryBuilderReport,
  type ApiAiAnalysisResult,
  type ApiChartPoint,
  type ApiInventoryReport,
  type ApiPatientReport,
  type ApiSalesSummary,
  type ReportEntity,
} from '@/lib/api';

type TabKey = 'sales' | 'inventory' | 'patients' | 'query-builder' | 'ai';

type ChartType = 'pie' | 'bar' | 'line' | 'table';

const FILTER_KEYS: Record<ReportEntity, Array<{ key: string; label: string }>> = {
  sales: [
    { key: 'status', label: 'Stato' },
    { key: 'supply_type', label: 'Tipo fornitura' },
    { key: 'operator', label: 'Operatore (user_id)' },
    { key: 'payment_method', label: 'Metodo pagamento' },
    { key: 'date_from', label: 'Data da (YYYY-MM-DD)' },
    { key: 'date_to', label: 'Data a (YYYY-MM-DD)' },
  ],
  products: [
    { key: 'category', label: 'Categoria' },
    { key: 'supplier', label: 'Fornitore (supplier_id)' },
    { key: 'brand', label: 'Marchio' },
    { key: 'price_range', label: 'Fascia prezzo (min,max)' },
  ],
  patients: [
    { key: 'gender', label: 'Genere' },
    { key: 'city', label: 'Città' },
    { key: 'inserted_from', label: 'Inserimento da (YYYY-MM-DD)' },
    { key: 'inserted_to', label: 'Inserimento a (YYYY-MM-DD)' },
  ],
};

function toCsv(rows: Array<Record<string, unknown>>): string {
  const headers = Object.keys(rows[0] ?? {});
  const escape = (v: unknown) => {
    const s = String(v ?? '');
    if (s.includes('"') || s.includes(',') || s.includes('\n')) return `"${s.replaceAll('"', '""')}"`;
    return s;
  };
  const lines = [
    headers.join(','),
    ...rows.map((r) => headers.map((h) => escape(r[h])).join(',')),
  ];
  return lines.join('\n');
}

function exportCsv(filename: string, csv: string): void {
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

function ChartView({ chartType, data }: { chartType: ChartType; data: ApiChartPoint[] }) {
  if (chartType === 'table') {
    return (
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="text-xs text-zinc-500">
              <th className="pb-2">Label</th>
              <th className="pb-2">Valore</th>
            </tr>
          </thead>
          <tbody>
            {data.map((p) => (
              <tr key={p.label} className="border-t border-zinc-100 dark:border-zinc-800">
                <td className="py-2">{p.label}</td>
                <td className="py-2 font-medium">{p.value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  }

  if (chartType === 'pie') {
    return (
      <ResponsiveContainer width="100%" height={280}>
        <PieChart>
          <Tooltip />
          <Pie data={data} dataKey="value" nameKey="label" outerRadius={90} />
        </PieChart>
      </ResponsiveContainer>
    );
  }

  if (chartType === 'bar') {
    return (
      <ResponsiveContainer width="100%" height={280}>
        <BarChart data={data}>
          <Tooltip />
          <XAxis dataKey="label" />
          <YAxis />
          <Bar dataKey="value" fill="#2563eb" />
        </BarChart>
      </ResponsiveContainer>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={280}>
      <LineChart data={data}>
        <Tooltip />
        <XAxis dataKey="label" />
        <YAxis />
        <Line type="monotone" dataKey="value" stroke="#2563eb" strokeWidth={2} dot={false} />
      </LineChart>
    </ResponsiveContainer>
  );
}

export default function ReportPage() {
  const [tab, setTab] = useState<TabKey>('sales');

  // Periodo
  const [periodPreset, setPeriodPreset] = useState<'today' | 'week' | 'month' | 'year' | 'custom'>('month');
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');

  // POS (attivo)
  const aiEnabled = useMemo(() => {
    if (typeof window === 'undefined') return false;
    try {
      const raw = localStorage.getItem('theja_active_pos');
      if (!raw) return false;
      const parsed = JSON.parse(raw) as unknown as { ai_analysis_enabled?: boolean };
      return parsed.ai_analysis_enabled === true;
    } catch {
      return false;
    }
  }, []);

  const [salesLoading, setSalesLoading] = useState(false);
  const [salesSummary, setSalesSummary] = useState<ApiSalesSummary | null>(null);
  const [revenueByPeriod, setRevenueByPeriod] = useState<Array<ApiChartPoint>>([]);
  const [topProducts, setTopProducts] = useState<Array<{ label: string; qty: number; revenue: number }>>([]);

  const [inventoryLoading, setInventoryLoading] = useState(false);
  const [inventoryReport, setInventoryReport] = useState<ApiInventoryReport | null>(null);

  const [patientsLoading, setPatientsLoading] = useState(false);
  const [patientsReport, setPatientsReport] = useState<ApiPatientReport | null>(null);

  // Query builder state
  const [qbEntity, setQbEntity] = useState<ReportEntity>('sales');
  const [qbChartType, setQbChartType] = useState<ChartType>('bar');
  const [qbGroupBy, setQbGroupBy] = useState<string>('month');
  const [qbFilters, setQbFilters] = useState<Record<string, string>>({});
  const [filterKeyToAdd, setFilterKeyToAdd] = useState<string>(FILTER_KEYS['sales'][0]?.key ?? 'status');
  const [filterValueToAdd, setFilterValueToAdd] = useState<string>('');
  const [qbLoading, setQbLoading] = useState(false);
  const [qbResult, setQbResult] = useState<{ chart_data: ApiChartPoint[]; table_data: ApiChartPoint[] } | null>(null);

  // AI state
  const [aiLoading, setAiLoading] = useState(false);
  const [aiResult, setAiResult] = useState<{
    trends?: ApiAiAnalysisResult;
    forecast?: ApiAiAnalysisResult;
    revenue?: ApiAiAnalysisResult;
    opp?: ApiAiAnalysisResult;
  }>({});

  const salesFromTo = useMemo(() => {
    const now = new Date();
    const fmt = (d: Date) => {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    };

    if (periodPreset === 'today') {
      const d = new Date(now);
      return { from: fmt(d), to: fmt(d), group_by: 'day' };
    }
    if (periodPreset === 'week') {
      const to = new Date(now);
      const from = new Date(now);
      from.setDate(from.getDate() - 6);
      return { from: fmt(from), to: fmt(to), group_by: 'day' };
    }
    if (periodPreset === 'year') {
      const to = new Date(now);
      const from = new Date(now);
      from.setMonth(from.getMonth() - 11);
      return { from: fmt(from), to: fmt(to), group_by: 'month' };
    }
    if (periodPreset === 'custom') {
      return { from: customFrom, to: customTo, group_by: 'month' };
    }
    // month
    const to = new Date(now);
    const from = new Date(now);
    from.setMonth(from.getMonth() - 1);
    return { from: fmt(from), to: fmt(to), group_by: 'month' };
  }, [periodPreset, customFrom, customTo]);

  async function loadSales(): Promise<void> {
    if (!salesFromTo.from || !salesFromTo.to) return;
    setSalesLoading(true);
    try {
      const [salesRes, topRes] = await Promise.all([
        getSalesReport({ from: salesFromTo.from, to: salesFromTo.to, group_by: salesFromTo.group_by }),
        getTopProductsReport({ from: salesFromTo.from, to: salesFromTo.to, limit: 10 }),
      ]);

      if (salesRes.status === 200) {
        setSalesSummary(salesRes.data.sales_summary);
        setRevenueByPeriod(salesRes.data.revenue_by_period.chart_data ?? []);
      }
      if (topRes.status === 200) {
        setTopProducts((topRes.data.items ?? []).map((i) => ({ label: i.label, qty: i.qty, revenue: i.revenue })));
      }
    } finally {
      setSalesLoading(false);
    }
  }

  async function loadInventory(): Promise<void> {
    setInventoryLoading(true);
    try {
      const res = await getInventoryReport();
      if (res.status === 200) setInventoryReport(res.data);
    } finally {
      setInventoryLoading(false);
    }
  }

  async function loadPatients(): Promise<void> {
    setPatientsLoading(true);
    try {
      const res = await getPatientsReport();
      if (res.status === 200) setPatientsReport(res.data);
    } finally {
      setPatientsLoading(false);
    }
  }

  async function runQueryBuilder(): Promise<void> {
    setQbLoading(true);
    try {
      const res = await queryBuilderReport({
        entity: qbEntity,
        filters: qbFilters,
        group_by: qbGroupBy,
        chart_type: qbChartType,
      });
      if (res.status === 200) {
        const r = res.data.result;
        setQbResult({ chart_data: r.chart_data ?? [], table_data: r.table_data ?? [] });
      }
    } finally {
      setQbLoading(false);
    }
  }

  useEffect(() => {
    if (tab === 'sales') void loadSales();
    if (tab === 'inventory') void loadInventory();
    if (tab === 'patients') void loadPatients();
  }, [tab, salesFromTo.from, salesFromTo.to, periodPreset]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    setFilterKeyToAdd(FILTER_KEYS[qbEntity][0]?.key ?? 'status');
    setQbFilters({});
  }, [qbEntity]);

  async function analyze(key: 'trends' | 'forecast' | 'revenue' | 'opp'): Promise<void> {
    setAiLoading(true);
    try {
      let res: { status: number; data: ApiAiAnalysisResult };
      if (key === 'trends') res = await getAiTrends();
      else if (key === 'forecast') res = await getAiForecastReorders();
      else if (key === 'revenue') res = await getAiRevenueAnalysis();
      else res = await getAiOpportunities();

      if (res.status === 200) {
        setAiResult((prev) => ({ ...prev, [key]: res.data }));
      }
    } finally {
      setAiLoading(false);
    }
  }

  const tabs: Array<{ key: TabKey; label: string }> = [
    { key: 'sales', label: 'Vendite' },
    { key: 'inventory', label: 'Magazzino' },
    { key: 'patients', label: 'Pazienti' },
    { key: 'query-builder', label: 'Query Builder' },
    { key: 'ai', label: 'AI Analysis' },
  ];

  return (
    <div className="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
      <div className="space-y-1">
        <h1 className="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Report</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400">Dashboard statistica e analisi guidata per il tuo punto vendita.</p>
      </div>

      <div className="flex flex-wrap gap-2">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`px-3 py-2 rounded-xl text-sm border transition-colors ${
              tab === t.key
                ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-900 text-blue-700 dark:text-blue-300'
                : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'sales' && (
        <div className="space-y-4">
          <div className="flex flex-col md:flex-row md:items-end gap-3">
            <div className="flex-1">
              <label className="text-xs text-zinc-500 dark:text-zinc-400">Periodo</label>
              <select
                value={periodPreset}
                onChange={(e) => setPeriodPreset(e.target.value as typeof periodPreset)}
                className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
              >
                <option value="today">Oggi</option>
                <option value="week">Settimana</option>
                <option value="month">Mese</option>
                <option value="year">Anno</option>
                <option value="custom">Custom</option>
              </select>
            </div>
            {periodPreset === 'custom' && (
              <div className="flex gap-3 w-full md:w-auto">
                <div>
                  <label className="text-xs text-zinc-500 dark:text-zinc-400">Da</label>
                  <input type="date" value={customFrom} onChange={(e) => setCustomFrom(e.target.value)} className="mt-1 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm" />
                </div>
                <div>
                  <label className="text-xs text-zinc-500 dark:text-zinc-400">A</label>
                  <input type="date" value={customTo} onChange={(e) => setCustomTo(e.target.value)} className="mt-1 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm" />
                </div>
              </div>
            )}
          </div>

          {salesLoading && <p className="text-sm text-zinc-500">Caricamento report vendite...</p>}

          {!salesLoading && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Fatturato per periodo</h2>
                <ChartView chartType="line" data={revenueByPeriod} />
              </div>

              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Vendite per tipo</h2>
                <ChartView chartType="pie" data={(salesSummary?.by_type ?? []).map((r) => ({ label: r.type, value: r.total }))} />
              </div>

              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Top prodotti</h2>
                <ResponsiveContainer width="100%" height={280}>
                  <BarChart data={topProducts.map((p) => ({ label: p.label, value: p.revenue }))}>
                    <Tooltip />
                    <XAxis dataKey="label" />
                    <YAxis />
                    <Bar dataKey="value" fill="#16a34a" />
                  </BarChart>
                </ResponsiveContainer>
              </div>

              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Vendite per operatore</h2>
                <ChartView
                  chartType="bar"
                  data={(salesSummary?.by_operator ?? []).map((r) => ({ label: r.user_name ?? r.user_id, value: r.total }))}
                />
              </div>
            </div>
          )}
        </div>
      )}

      {tab === 'inventory' && (
        <div className="space-y-4">
          {inventoryLoading && <p className="text-sm text-zinc-500">Caricamento report magazzino...</p>}
          {!inventoryLoading && inventoryReport && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Sotto scorta</h2>
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead>
                      <tr className="text-xs text-zinc-500">
                        <th className="pb-2">Prodotto</th>
                        <th className="pb-2">Qtà</th>
                        <th className="pb-2">Min</th>
                        <th className="pb-2">Max</th>
                      </tr>
                    </thead>
                    <tbody>
                      {inventoryReport.below_stock.length === 0 ? (
                        <tr><td colSpan={4} className="py-3 text-zinc-400">Nessun prodotto sotto scorta</td></tr>
                      ) : (
                        inventoryReport.below_stock.map((r) => (
                          <tr key={r.product_id} className="border-t border-zinc-100 dark:border-zinc-800">
                            <td className="py-2">
                              <div className="font-medium">{r.product_brand ?? ''} {r.product_model ?? ''}</div>
                              <div className="text-xs text-zinc-500">{r.product_category ?? ''}</div>
                            </td>
                            <td className="py-2">{r.quantity}</td>
                            <td className="py-2">{r.min_stock}</td>
                            <td className="py-2">{r.max_stock}</td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
                <h2 className="font-semibold text-sm">Valore magazzino</h2>
                <p className="text-3xl font-bold text-emerald-700 dark:text-emerald-300">{inventoryReport.inventory_value_total.toFixed(2)} EUR</p>

                <div>
                  <h3 className="font-semibold text-sm mb-2">Rotazione per categoria</h3>
                  <ChartView chartType="bar" data={inventoryReport.rotation_by_category.map((r) => ({ label: r.category, value: r.sold_qty }))} />
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {tab === 'patients' && (
        <div className="space-y-4">
          {patientsLoading && <p className="text-sm text-zinc-500">Caricamento report pazienti...</p>}
          {!patientsLoading && patientsReport && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
                <h2 className="font-semibold text-sm mb-2">Nuovi pazienti (ultimi mesi)</h2>
                <ChartView
                  chartType="line"
                  data={patientsReport.new_patients_by_month.map((p) => ({ label: p.period, value: p.value }))}
                />
              </div>
              <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
                <div>
                  <p className="text-xs text-zinc-500">Prescrizioni in scadenza</p>
                  <p className="text-3xl font-bold text-amber-700 dark:text-amber-300">{patientsReport.prescriptions_expired_count}</p>
                </div>
                <div>
                  <p className="text-xs text-zinc-500">LAC attivi</p>
                  <p className="text-3xl font-bold text-emerald-700 dark:text-emerald-300">{patientsReport.lac_active_count}</p>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {tab === 'query-builder' && (
        <div className="space-y-4">
          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="text-xs text-zinc-500">Entity</label>
                <select
                  value={qbEntity}
                  onChange={(e) => setQbEntity(e.target.value as ReportEntity)}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                >
                  <option value="sales">Vendite</option>
                  <option value="products">Prodotti</option>
                  <option value="patients">Pazienti</option>
                </select>
              </div>

              <div>
                <label className="text-xs text-zinc-500">Chart type</label>
                <select
                  value={qbChartType}
                  onChange={(e) => setQbChartType(e.target.value as ChartType)}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                >
                  <option value="pie">Torta</option>
                  <option value="bar">Barre</option>
                  <option value="line">Linea</option>
                  <option value="table">Tabella</option>
                </select>
              </div>

              <div>
                <label className="text-xs text-zinc-500">Group by</label>
                <select
                  value={qbGroupBy}
                  onChange={(e) => setQbGroupBy(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                >
                  <option value="month">Mese</option>
                  <option value="day">Giorno</option>
                  <option value="week">Settimana</option>
                  <option value="year">Anno</option>
                  <option value="type">Tipo</option>
                  <option value="payment_method">Metodo pagamento</option>
                  <option value="operator">Operatore</option>
                  <option value="category">Categoria</option>
                </select>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-end gap-2">
                <div className="flex-1">
                  <label className="text-xs text-zinc-500">Filtro (chiave)</label>
                  <select
                    value={filterKeyToAdd}
                    onChange={(e) => setFilterKeyToAdd(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                  >
                    {FILTER_KEYS[qbEntity].map((f) => (
                      <option key={f.key} value={f.key}>{f.label}</option>
                    ))}
                  </select>
                </div>
                <div className="flex-1">
                  <label className="text-xs text-zinc-500">Valore</label>
                  <input
                    value={filterValueToAdd}
                    onChange={(e) => setFilterValueToAdd(e.target.value)}
                    className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                    placeholder="es. confirmed / 2026-03-01"
                  />
                </div>
                <button
                  onClick={() => {
                    if (!filterKeyToAdd || filterValueToAdd.trim() === '') return;
                    setQbFilters((prev) => ({ ...prev, [filterKeyToAdd]: filterValueToAdd.trim() }));
                    setFilterValueToAdd('');
                  }}
                  className="rounded-lg bg-blue-600 text-white px-4 py-2 mb-0.5"
                >
                  Aggiungi
                </button>
              </div>

              <div className="flex flex-wrap gap-2">
                {Object.entries(qbFilters).length === 0 && (
                  <p className="text-sm text-zinc-500">Aggiungi filtri per affinare la ricerca.</p>
                )}
                {Object.entries(qbFilters).map(([k, v]) => (
                  <div key={k} className="flex items-center gap-2 rounded-full border border-zinc-200 dark:border-zinc-800 px-3 py-1 text-xs">
                    <span className="text-zinc-700 dark:text-zinc-200">{k}: <span className="text-zinc-900 dark:text-zinc-100">{v}</span></span>
                    <button onClick={() => setQbFilters((prev) => {
                      const next = { ...prev };
                      delete next[k];
                      return next;
                    })} className="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">×</button>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex justify-end">
              <button
                onClick={() => void runQueryBuilder()}
                disabled={qbLoading}
                className="rounded-lg bg-emerald-600 text-white px-4 py-2 disabled:opacity-50"
              >
                Esegui ricerca
              </button>
            </div>

            {qbLoading && <p className="text-sm text-zinc-500">Caricamento risultati...</p>}
          </div>

          {qbResult && (
            <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
              <div className="flex justify-between items-center gap-3 flex-wrap">
                <h3 className="font-semibold text-sm">Risultati</h3>
                <button
                  className="rounded-lg border border-zinc-200 dark:border-zinc-800 px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                  onClick={() => {
                    const rows = qbResult.table_data.map((p) => ({ label: p.label, value: p.value }));
                    const csv = toCsv(rows);
                    exportCsv('query-builder.csv', csv);
                  }}
                >
                  Export CSV
                </button>
              </div>

              <ChartView chartType={qbChartType} data={qbResult.chart_data} />
            </div>
          )}
        </div>
      )}

      {tab === 'ai' && (
        <div className="space-y-4">
          {!aiEnabled && (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-950/20 border-amber-300 p-5">
              <h2 className="font-semibold">Attiva AI Analysis per €2/mese</h2>
              <p className="text-sm text-zinc-600 dark:text-zinc-300 mt-1">
                Disponibile come add-on per POS con flag `ai_analysis_enabled`.
              </p>
              <p className="text-xs text-zinc-500 mt-2">
                Contatta l’amministratore per attivare il servizio.
              </p>
            </div>
          )}

          {aiEnabled && (
            <>
              {aiLoading && (
                <div className="rounded-2xl border border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-950/40 p-5">
                  <p className="font-semibold">L&apos;AI sta analizzando i tuoi dati...</p>
                </div>
              )}

              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <AiCard
                  title="Trend vendite"
                  result={aiResult.trends}
                  onAnalyze={() => void analyze('trends')}
                />
                <AiCard
                  title="Previsione riordini"
                  result={aiResult.forecast}
                  onAnalyze={() => void analyze('forecast')}
                />
                <AiCard
                  title="Analisi fatturato"
                  result={aiResult.revenue}
                  onAnalyze={() => void analyze('revenue')}
                />
                <AiCard
                  title="Opportunità"
                  result={aiResult.opp}
                  onAnalyze={() => void analyze('opp')}
                />
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}

function AiCard({ title, result, onAnalyze }: { title: string; result?: ApiAiAnalysisResult; onAnalyze: () => void }) {
  return (
    <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-semibold text-sm">{title}</h2>
          <p className="text-xs text-zinc-500">Narrativa + grafico derivati dai dati aggregati.</p>
        </div>
        <button onClick={onAnalyze} className="rounded-lg bg-blue-600 text-white px-3 py-2 text-sm">Analizza</button>
      </div>

      {!result ? (
        <p className="text-sm text-zinc-500">Nessun risultato ancora.</p>
      ) : (
        <>
          <div className="text-sm text-zinc-700 dark:text-zinc-200 whitespace-pre-wrap">
            {result.narrative}
          </div>
          {result.chart_data && result.chart_data.length > 0 && (
            <ChartView chartType="bar" data={result.chart_data} />
          )}
        </>
      )}
    </div>
  );
}

