'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
  createProduct,
  getProductByBarcode,
  getSuppliers,
  type ApiSupplier,
  type ProductCategory,
} from '@/lib/api';

const CATEGORY_OPTIONS: { id: ProductCategory; label: string }[] = [
  { id: 'montatura', label: 'Montatura' },
  { id: 'lente_oftalmica', label: 'Lente oftalmica' },
  { id: 'lente_contatto', label: 'Lente a contatto' },
  { id: 'liquido_accessorio', label: 'Liquido/accessorio' },
  { id: 'servizio', label: 'Servizio' },
];

export default function NuovoProdottoPage() {
  const router = useRouter();
  const [category, setCategory] = useState<ProductCategory>('montatura');
  const [suppliers, setSuppliers] = useState<ApiSupplier[]>([]);
  const [supplierQuery, setSupplierQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const barcodeRef = useRef<HTMLInputElement | null>(null);

  const [f, setF] = useState<Record<string, string>>({
    supplier_id: '',
    barcode: '',
    sku: '',
    internal_code: '',
    personal_code: '',
    brand: '',
    line: '',
    model: '',
    color: '',
    material: '',
    lens_type: '',
    lens_color: '',
    user_type: '',
    mounting_type: '',
    caliber: '',
    bridge: '',
    temple: '',
    purchase_price: '',
    markup_percent: '',
    net_price: '',
    list_price: '',
    sale_price: '',
    vat_rate: '22',
    notes: '',
  });

  useEffect(() => {
    getSuppliers({ q: supplierQuery, page: 1 }).then(({ status, data }) => {
      if (status === 200) setSuppliers(data.data ?? []);
    });
  }, [supplierQuery]);

  useEffect(() => {
    barcodeRef.current?.focus();
  }, []);

  function setField(key: string, value: string) {
    setF((prev) => ({ ...prev, [key]: value }));
  }

  const fieldsForCategory = useMemo(() => {
    if (category === 'montatura') {
      return ['brand', 'line', 'model', 'color', 'material', 'lens_type', 'user_type', 'caliber', 'bridge', 'temple'];
    }
    if (category === 'lente_oftalmica') {
      return ['brand', 'line', 'model', 'lens_type', 'lens_color', 'notes'];
    }
    if (category === 'lente_contatto') {
      return ['brand', 'model', 'lens_type', 'lens_color', 'notes'];
    }
    if (category === 'liquido_accessorio') {
      return ['brand', 'line', 'model', 'notes'];
    }
    return ['brand', 'model', 'notes'];
  }, [category]);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const payload: Record<string, unknown> = {
        category,
        supplier_id: f.supplier_id || null,
        barcode: f.barcode || null,
        sku: f.sku || null,
        internal_code: f.internal_code || null,
        personal_code: f.personal_code || null,
        brand: f.brand || null,
        line: f.line || null,
        model: f.model || null,
        color: f.color || null,
        material: f.material || null,
        lens_type: f.lens_type || null,
        lens_color: f.lens_color || null,
        user_type: f.user_type || null,
        mounting_type: f.mounting_type || null,
        caliber: f.caliber ? Number(f.caliber) : null,
        bridge: f.bridge ? Number(f.bridge) : null,
        temple: f.temple ? Number(f.temple) : null,
        purchase_price: f.purchase_price ? Number(f.purchase_price) : null,
        markup_percent: f.markup_percent ? Number(f.markup_percent) : null,
        net_price: f.net_price ? Number(f.net_price) : null,
        list_price: f.list_price ? Number(f.list_price) : null,
        sale_price: f.sale_price ? Number(f.sale_price) : null,
        vat_rate: f.vat_rate ? Number(f.vat_rate) : 22,
        notes: f.notes || null,
      };
      const { status, data } = await createProduct(payload);
      if (status !== 200 && status !== 201) {
        setError((data as { message?: string }).message ?? 'Creazione non riuscita.');
        return;
      }
      const id = data.data?.id;
      if (id) router.replace(`/magazzino/${id}`);
      else router.replace('/magazzino');
    } catch {
      setError('Errore di rete.');
    } finally {
      setBusy(false);
    }
  }

  async function lookupBarcodeAndMaybeRedirect() {
    const code = f.barcode?.trim();
    if (!code) return;
    const res = await getProductByBarcode(code);
    const existingId = res.status === 200 ? res.data.data?.product?.id : null;
    if (existingId) {
      router.replace(`/magazzino/${existingId}`);
    }
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6 p-6">
      <div>
        <Link href="/magazzino" className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), '-ml-2 mb-2')}>
          ← Catalogo
        </Link>
        <h1 className="text-2xl font-semibold tracking-tight">Nuovo prodotto</h1>
        <p className="text-sm text-muted-foreground">Seleziona categoria e compila i dati principali.</p>
      </div>

      <div className="flex flex-wrap gap-1 rounded-xl border border-border bg-card p-1">
        {CATEGORY_OPTIONS.map((c) => (
          <button
            key={c.id}
            type="button"
            onClick={() => setCategory(c.id)}
            className={cn(
              'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              category === c.id ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
          >
            {c.label}
          </button>
        ))}
      </div>

      <form onSubmit={submit} className="space-y-5">
        <div className="rounded-xl border border-border bg-card p-4">
          <p className="mb-3 text-sm font-medium">Fornitore</p>
          <div className="grid gap-3 sm:grid-cols-2">
            <input
              placeholder="Cerca fornitore…"
              value={supplierQuery}
              onChange={(e) => setSupplierQuery(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
            <select
              value={f.supplier_id}
              onChange={(e) => setField('supplier_id', e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            >
              <option value="">—</option>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  {(s.company_name as string) || `${String(s.last_name ?? '')} ${String(s.first_name ?? '')}`.trim()}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ['barcode', 'Codice a barre'],
            ['sku', 'SKU'],
            ['internal_code', 'Codice interno'],
            ['personal_code', 'Codice personale'],
            ...fieldsForCategory.map((x) => [x, x.replaceAll('_', ' ')]) as string[][],
            ['purchase_price', 'Prezzo acquisto'],
            ['markup_percent', 'Ricarico %'],
            ['net_price', 'Prezzo netto'],
            ['list_price', 'Prezzo listino'],
            ['sale_price', 'Prezzo vendita'],
            ['vat_rate', 'IVA %'],
          ].map(([key, label]) => (
            <label key={key} className="flex flex-col gap-1">
              <span className="text-xs capitalize text-muted-foreground">{label}</span>
              <input
                ref={key === 'barcode' ? barcodeRef : null}
                value={f[key] ?? ''}
                onChange={(e) => setField(key, e.target.value)}
                onKeyDown={(e) => {
                  if (key === 'barcode' && e.key === 'Enter') {
                    e.preventDefault();
                    void lookupBarcodeAndMaybeRedirect();
                  }
                }}
                className="rounded-lg border border-border bg-background px-3 py-2"
              />
            </label>
          ))}
        </div>

        <label className="flex flex-col gap-1">
          <span className="text-xs text-muted-foreground">Note</span>
          <textarea
            rows={4}
            value={f.notes}
            onChange={(e) => setField('notes', e.target.value)}
            className="rounded-xl border border-border bg-background p-3"
          />
        </label>

        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="flex justify-end gap-2">
          <Link href="/magazzino" className={cn(buttonVariants({ variant: 'outline' }))}>
            Annulla
          </Link>
          <Button type="submit" disabled={busy}>
            {busy ? 'Creazione…' : 'Crea prodotto'}
          </Button>
        </div>
      </form>
    </div>
  );
}
