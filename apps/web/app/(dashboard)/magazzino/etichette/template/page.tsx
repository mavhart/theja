'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { createLabelTemplate, getLabelTemplates, type ApiLabelField, type ApiLabelTemplate } from '@/lib/api';

const FIELD_PRESETS: ApiLabelField[] = [
  { key: 'barcode', label: 'Barcode', visible: true, font_size: 9 },
  { key: 'barcode_number', label: 'Codice', visible: true, font_size: 8 },
  { key: 'brand', label: 'Marchio', visible: true, font_size: 8 },
  { key: 'model', label: 'Modello', visible: true, font_size: 8 },
  { key: 'caliber_bridge', label: 'Calibro/Ponte', visible: true, font_size: 8 },
  { key: 'color', label: 'Colore', visible: true, font_size: 8 },
  { key: 'sale_price', label: 'Prezzo', visible: true, font_size: 10 },
  { key: 'supplier', label: 'Fornitore', visible: false, font_size: 7 },
];

export default function LabelTemplatePage() {
  const [templates, setTemplates] = useState<ApiLabelTemplate[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<ApiLabelField[]>(FIELD_PRESETS);
  const [form, setForm] = useState({
    name: '',
    paper_format: 'A4' as 'A4' | 'A5',
    label_width_mm: '48.5',
    label_height_mm: '25.4',
    cols: '4',
    rows: '10',
    margin_top_mm: '10',
    margin_left_mm: '5',
    spacing_h_mm: '2.5',
    spacing_v_mm: '0',
  });

  async function load() {
    const { status, data } = await getLabelTemplates();
    if (status === 200) setTemplates(data.data ?? []);
  }

  useEffect(() => {
    void load();
  }, []);

  const labelsPerSheet = useMemo(() => Number(form.cols || 0) * Number(form.rows || 0), [form.cols, form.rows]);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const { status } = await createLabelTemplate({
        ...form,
        label_width_mm: Number(form.label_width_mm),
        label_height_mm: Number(form.label_height_mm),
        cols: Number(form.cols),
        rows: Number(form.rows),
        margin_top_mm: Number(form.margin_top_mm),
        margin_left_mm: Number(form.margin_left_mm),
        spacing_h_mm: Number(form.spacing_h_mm),
        spacing_v_mm: Number(form.spacing_v_mm),
        fields,
      });
      if (status !== 200 && status !== 201) {
        setError('Salvataggio template non riuscito.');
        return;
      }
      setForm((prev) => ({ ...prev, name: '' }));
      await load();
    } catch {
      setError('Errore di rete.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto max-w-7xl space-y-6 p-6">
      <h1 className="text-2xl font-semibold tracking-tight">Template etichette</h1>
      <div className="grid gap-6 lg:grid-cols-2">
        <section className="space-y-3 rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Template disponibili</h2>
          <div className="grid gap-3 md:grid-cols-2">
            {templates.map((t) => (
              <div key={t.id} className="rounded-lg border border-border p-3">
                <p className="text-sm font-medium">{t.name}</p>
                <p className="text-xs text-muted-foreground">{t.label_width_mm}x{t.label_height_mm}mm • {t.cols}x{t.rows}</p>
                <div className="mt-2 grid gap-1 rounded border border-border p-2" style={{ gridTemplateColumns: `repeat(${t.cols}, minmax(0,1fr))` }}>
                  {Array.from({ length: Number(t.cols) * Number(t.rows) }, (_, i) => (
                    <div key={i} className="h-3 rounded bg-muted" />
                  ))}
                </div>
              </div>
            ))}
          </div>
        </section>

        <form onSubmit={submit} className="space-y-4 rounded-xl border border-border bg-card p-4">
          <h2 className="text-sm font-semibold">Nuovo template</h2>
          <div className="grid gap-3 sm:grid-cols-2">
            {Object.entries(form).map(([k, v]) => (
              <label key={k} className="flex flex-col gap-1">
                <span className="text-xs capitalize text-muted-foreground">{k.replaceAll('_', ' ')}</span>
                <input
                  value={v}
                  onChange={(e) => setForm((prev) => ({ ...prev, [k]: e.target.value }))}
                  className="rounded-lg border border-border bg-background px-3 py-2"
                />
              </label>
            ))}
          </div>
          <p className="text-xs text-muted-foreground">Calcolatore: {labelsPerSheet} etichette/foglio</p>

          <div className="space-y-2">
            <p className="text-xs font-medium">Campi stampati (ordinabili)</p>
            {fields.map((f, idx) => (
              <div key={f.key} className="flex items-center gap-2 rounded border border-border p-2 text-xs">
                <input
                  type="checkbox"
                  checked={f.visible}
                  onChange={(e) => setFields((prev) => prev.map((x) => x.key === f.key ? { ...x, visible: e.target.checked } : x))}
                />
                <span className="w-28">{f.label}</span>
                <input
                  type="number"
                  min={6}
                  max={18}
                  value={f.font_size}
                  onChange={(e) => setFields((prev) => prev.map((x) => x.key === f.key ? { ...x, font_size: Number(e.target.value || 8) } : x))}
                  className="w-16 rounded border border-border bg-background px-2 py-1"
                />
                <div className="ml-auto flex gap-1">
                  <Button type="button" variant="ghost" size="sm" disabled={idx === 0} onClick={() => setFields((prev) => {
                    const copy = [...prev]; [copy[idx - 1], copy[idx]] = [copy[idx], copy[idx - 1]]; return copy;
                  })}>↑</Button>
                  <Button type="button" variant="ghost" size="sm" disabled={idx === fields.length - 1} onClick={() => setFields((prev) => {
                    const copy = [...prev]; [copy[idx + 1], copy[idx]] = [copy[idx], copy[idx + 1]]; return copy;
                  })}>↓</Button>
                </div>
              </div>
            ))}
          </div>

          <div className="rounded-lg border border-border p-3">
            <p className="mb-2 text-xs font-medium">Anteprima etichetta</p>
            <div className="space-y-1 rounded border border-dashed border-border p-2 text-xs">
              {fields.filter((x) => x.visible).map((x) => (
                <div key={x.key} style={{ fontSize: `${x.font_size}px` }}>{x.label}</div>
              ))}
            </div>
          </div>

          {error && <p className="text-sm text-destructive">{error}</p>}
          <Button type="submit" disabled={busy}>{busy ? 'Salvataggio…' : 'Salva template'}</Button>
        </form>
      </div>
    </div>
  );
}
