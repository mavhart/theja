'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
  api,
  createProduct,
  createStockMovement,
  getInventoryStock,
  getProductByBarcode,
  getProduct,
  getStockMovements,
  getSuppliers,
  updateProduct,
  type ApiInventoryStockItem,
  type ApiProduct,
  type ApiStockMovement,
  type ApiSupplier,
} from '@/lib/api';

type TabId = 'principale' | 'stock' | 'movimenti' | 'note';

export default function ProductDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = typeof params.id === 'string' ? params.id : '';

  const [tab, setTab] = useState<TabId>('principale');
  const [product, setProduct] = useState<ApiProduct | null>(null);
  const [suppliers, setSuppliers] = useState<ApiSupplier[]>([]);
  const [stockRows, setStockRows] = useState<ApiInventoryStockItem[]>([]);
  const [movements, setMovements] = useState<ApiStockMovement[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [canViewPurchasePrice, setCanViewPurchasePrice] = useState(false);

  const [showCarico, setShowCarico] = useState(false);
  const [images, setImages] = useState<File[]>([]);

  const [f, setF] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const [pRes, supRes, stockRes, movRes, meRes] = await Promise.all([
        getProduct(id),
        getSuppliers({ page: 1 }),
        getInventoryStock(id),
        getStockMovements(id),
        api.me(),
      ]);
      if (pRes.status === 401) {
        router.replace('/login');
        return;
      }
      if (pRes.status !== 200 || !pRes.data.data) {
        setError('Prodotto non trovato.');
        setProduct(null);
        return;
      }
      const p = pRes.data.data;
      setProduct(p);
      setF({
        supplier_id: String(p.supplier_id ?? ''),
        category: String(p.category ?? ''),
        barcode: String(p.barcode ?? ''),
        sku: String(p.sku ?? ''),
        internal_code: String(p.internal_code ?? ''),
        personal_code: String(p.personal_code ?? ''),
        brand: String(p.brand ?? ''),
        line: String(p.line ?? ''),
        model: String(p.model ?? ''),
        color: String(p.color ?? ''),
        material: String(p.material ?? ''),
        lens_type: String(p.lens_type ?? ''),
        user_type: String(p.user_type ?? ''),
        caliber: String(p.caliber ?? ''),
        bridge: String(p.bridge ?? ''),
        temple: String(p.temple ?? ''),
        purchase_price: String(p.purchase_price ?? ''),
        markup_percent: String(p.markup_percent ?? ''),
        net_price: String(p.net_price ?? ''),
        list_price: String(p.list_price ?? ''),
        sale_price: String(p.sale_price ?? ''),
        vat_rate: String(p.vat_rate ?? '22'),
        notes: String(p.notes ?? ''),
      });
      setSuppliers(supRes.status === 200 ? supRes.data.data ?? [] : []);
      setStockRows(stockRes.status === 200 ? stockRes.data.data ?? [] : []);
      setMovements(movRes.status === 200 ? movRes.data.data ?? [] : []);
      const perms = meRes.status === 200 ? meRes.data.permissions ?? [] : [];
      setCanViewPurchasePrice(perms.includes('inventory.view_purchase_price'));
    } catch {
      setError('Errore di rete.');
    } finally {
      setLoading(false);
    }
  }, [id, router]);

  useEffect(() => {
    void load();
  }, [load]);

  function setField(key: string, value: string) {
    setF((prev) => ({ ...prev, [key]: value }));
  }

  async function saveMain(e: FormEvent) {
    e.preventDefault();
    if (!id) return;
    setSaving(true);
    setError(null);
    try {
      const payload: Record<string, unknown> = {
        supplier_id: f.supplier_id || null,
        category: f.category,
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
        user_type: f.user_type || null,
        caliber: f.caliber ? Number(f.caliber) : null,
        bridge: f.bridge ? Number(f.bridge) : null,
        temple: f.temple ? Number(f.temple) : null,
        markup_percent: f.markup_percent ? Number(f.markup_percent) : null,
        net_price: f.net_price ? Number(f.net_price) : null,
        list_price: f.list_price ? Number(f.list_price) : null,
        sale_price: f.sale_price ? Number(f.sale_price) : null,
        vat_rate: f.vat_rate ? Number(f.vat_rate) : 22,
        notes: f.notes || null,
      };
      if (canViewPurchasePrice) payload.purchase_price = f.purchase_price ? Number(f.purchase_price) : null;
      const { status, data } = await updateProduct(id, payload);
      if (status !== 200) {
        setError('Salvataggio non riuscito.');
        return;
      }
      setProduct(data.data);
    } catch {
      setError('Errore di rete.');
    } finally {
      setSaving(false);
    }
  }

  const tabs: { id: TabId; label: string }[] = [
    { id: 'principale', label: 'Principale' },
    { id: 'stock', label: 'Stock' },
    { id: 'movimenti', label: 'Movimenti' },
    { id: 'note', label: 'Note' },
  ];

  const stockByPos = useMemo(() => stockRows, [stockRows]);

  if (loading && !product) {
    return (
      <div className="mx-auto max-w-6xl space-y-4 p-6">
        <div className="h-8 w-64 animate-pulse rounded-md bg-muted" />
        <div className="h-72 animate-pulse rounded-xl bg-muted" />
      </div>
    );
  }

  if (!product) {
    return (
      <div className="mx-auto max-w-6xl p-6">
        <p className="text-sm text-destructive">{error ?? 'Prodotto non disponibile.'}</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link href="/magazzino" className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), '-ml-2 mb-2')}>
            ← Catalogo
          </Link>
          <h1 className="text-2xl font-semibold tracking-tight">
            {[product.brand, product.line, product.model].filter(Boolean).join(' / ') || 'Scheda prodotto'}
          </h1>
          <p className="text-sm text-muted-foreground">{product.category}</p>
        </div>
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <div className="flex flex-wrap gap-1 border-b border-border">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={cn(
              'rounded-t-lg px-4 py-2 text-sm font-medium',
              tab === t.id ? 'border border-b-0 border-border bg-card text-foreground' : 'text-muted-foreground hover:text-foreground',
            )}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'principale' && (
        <form onSubmit={saveMain} className="space-y-6">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label className="flex flex-col gap-1">
              <span className="text-xs text-muted-foreground">Categoria</span>
              <select value={f.category ?? ''} onChange={(e) => setField('category', e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2">
                <option value="montatura">Montatura</option>
                <option value="lente_oftalmica">Lente oftalmica</option>
                <option value="lente_contatto">Lente contatto</option>
                <option value="liquido_accessorio">Liquido/accessorio</option>
                <option value="servizio">Servizio</option>
              </select>
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-xs text-muted-foreground">Fornitore</span>
              <select value={f.supplier_id ?? ''} onChange={(e) => setField('supplier_id', e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2">
                <option value="">—</option>
                {suppliers.map((s) => (
                  <option key={s.id} value={s.id}>
                    {(s.company_name as string) || `${String(s.last_name ?? '')} ${String(s.first_name ?? '')}`.trim()}
                  </option>
                ))}
              </select>
            </label>
            {[
              ['barcode', 'Codice a barre'],
              ['sku', 'SKU'],
              ['internal_code', 'Codice interno'],
              ['personal_code', 'Codice personale'],
              ['brand', 'Marchio'],
              ['line', 'Linea'],
              ['model', 'Modello'],
              ['color', 'Colore'],
              ['material', 'Materiale'],
              ['lens_type', 'Tipo (sole/vista)'],
              ['user_type', 'Utente'],
              ['caliber', 'Calibro'],
              ['bridge', 'Ponte'],
              ['temple', 'Asta'],
              ['markup_percent', 'Ricarico %'],
              ['net_price', 'Prezzo netto'],
              ['list_price', 'Prezzo listino'],
              ['sale_price', 'Prezzo vendita'],
              ['vat_rate', 'IVA %'],
            ].map(([k, l]) => (
              <label key={k} className="flex flex-col gap-1">
                <span className="text-xs text-muted-foreground">{l}</span>
                <input value={f[k] ?? ''} onChange={(e) => setField(k, e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
              </label>
            ))}
            {canViewPurchasePrice && (
              <label className="flex flex-col gap-1">
                <span className="text-xs text-muted-foreground">Prezzo acquisto</span>
                <input value={f.purchase_price ?? ''} onChange={(e) => setField('purchase_price', e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
              </label>
            )}
          </div>

          <div className="rounded-xl border border-border bg-card p-4">
            <p className="mb-2 text-sm font-medium">Immagini prodotto (max 5)</p>
            <input
              type="file"
              multiple
              accept="image/*"
              onChange={(e) => {
                const files = Array.from(e.target.files ?? []).slice(0, 5);
                setImages(files);
              }}
            />
            {images.length > 0 && (
              <ul className="mt-2 list-disc pl-6 text-xs text-muted-foreground">
                {images.map((f) => (
                  <li key={f.name}>{f.name}</li>
                ))}
              </ul>
            )}
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={saving}>
              {saving ? 'Salvataggio…' : 'Salva'}
            </Button>
          </div>
        </form>
      )}

      {tab === 'stock' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button type="button" onClick={() => setShowCarico(true)}>
              Carico manuale
            </Button>
          </div>
          <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <table className="w-full min-w-[820px] text-left text-sm">
              <thead className="border-b border-border bg-muted/40">
                <tr>
                  <th className="px-4 py-3">POS</th>
                  <th className="px-4 py-3">Qtà magazzino</th>
                  <th className="px-4 py-3">In arrivo</th>
                  <th className="px-4 py-3">Prenotata</th>
                  <th className="px-4 py-3">Venduta</th>
                  <th className="px-4 py-3">Scorta min/max</th>
                </tr>
              </thead>
              <tbody>
                {stockByPos.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                      Nessun dato stock disponibile
                    </td>
                  </tr>
                ) : (
                  stockByPos.map((s) => (
                    <tr key={s.id} className="border-b border-border/60">
                      <td className="px-4 py-3">{s.pos_name ?? s.pos_id}</td>
                      <td className="px-4 py-3">{s.quantity}</td>
                      <td className="px-4 py-3">{s.quantity_arriving}</td>
                      <td className="px-4 py-3">{s.quantity_reserved}</td>
                      <td className="px-4 py-3">{s.quantity_sold}</td>
                      <td className="px-4 py-3">
                        {s.min_stock} / {s.max_stock}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {tab === 'movimenti' && (
        <div className="overflow-x-auto rounded-xl border border-border bg-card">
          <table className="w-full min-w-[880px] text-left text-sm">
            <thead className="border-b border-border bg-muted/40">
              <tr>
                <th className="px-4 py-3">Tipo</th>
                <th className="px-4 py-3">Data</th>
                <th className="px-4 py-3">Quantità</th>
                <th className="px-4 py-3">DDT</th>
                <th className="px-4 py-3">Operatore</th>
              </tr>
            </thead>
            <tbody>
              {movements.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                    Nessun movimento disponibile
                  </td>
                </tr>
              ) : (
                movements.map((m) => (
                  <tr key={m.id} className="border-b border-border/60">
                    <td className="px-4 py-3">{m.type}</td>
                    <td className="px-4 py-3">{new Date(m.created_at).toLocaleString('it-IT')}</td>
                    <td className="px-4 py-3">{m.quantity}</td>
                    <td className="px-4 py-3">
                      {m.ddt_number ?? '—'}
                      {m.reference ? ` (${m.reference})` : ''}
                    </td>
                    <td className="px-4 py-3">{m.user_id ?? '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'note' && (
        <div className="space-y-4">
          <textarea
            rows={8}
            value={f.notes ?? ''}
            onChange={(e) => setField('notes', e.target.value)}
            className="w-full rounded-xl border border-border bg-background p-4 text-sm"
            placeholder="Note prodotto…"
          />
          <div className="rounded-xl border border-border bg-card p-4">
            <p className="mb-2 text-sm font-medium">Immagini prodotto</p>
            <input
              type="file"
              multiple
              accept="image/*"
              onChange={(e) => {
                const files = Array.from(e.target.files ?? []).slice(0, 5);
                setImages(files);
              }}
            />
            {images.length > 0 && (
              <ul className="mt-2 list-disc pl-6 text-xs text-muted-foreground">
                {images.map((f) => (
                  <li key={`note-${f.name}`}>{f.name}</li>
                ))}
              </ul>
            )}
          </div>
          <div className="flex justify-end">
            <Button onClick={(e) => void saveMain(e as unknown as FormEvent)} disabled={saving}>
              Salva note
            </Button>
          </div>
        </div>
      )}

      {showCarico && (
        <CaricoModal
          suppliers={suppliers}
          product={product}
          onClose={() => setShowCarico(false)}
          onSaved={async () => {
            setShowCarico(false);
            await load();
          }}
        />
      )}
    </div>
  );
}

function CaricoModal({
  suppliers,
  product,
  onClose,
  onSaved,
}: {
  suppliers: ApiSupplier[];
  product: ApiProduct;
  onClose: () => void;
  onSaved: () => Promise<void>;
}) {
  const [supplierId, setSupplierId] = useState(String(product.supplier_id ?? ''));
  const [barcode, setBarcode] = useState(String(product.barcode ?? ''));
  const [ddtNumber, setDdtNumber] = useState('');
  const [ddtDate, setDdtDate] = useState('');
  const [qty, setQty] = useState('1');
  const [purchasePrice, setPurchasePrice] = useState('');
  const [lot, setLot] = useState('');
  const [barcodeFound, setBarcodeFound] = useState<ApiProduct | null>(null);
  const [quickCreate, setQuickCreate] = useState(false);
  const [quickBrand, setQuickBrand] = useState('');
  const [quickModel, setQuickModel] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const { status, data } = await createStockMovement({
        pos_id: localStorage.getItem('theja_active_pos')
          ? (JSON.parse(localStorage.getItem('theja_active_pos') as string) as { id: string }).id
          : undefined,
        product_id: product.id,
        type: 'carico',
        quantity: Number(qty),
        supplier_id: supplierId || null,
        ddt_number: ddtNumber || null,
        ddt_date: ddtDate || null,
        purchase_price: purchasePrice ? Number(purchasePrice) : null,
        lot: lot || null,
      });
      if (status !== 200) {
        setError((data as { message?: string }).message ?? 'Carico non riuscito.');
        return;
      }
      await onSaved();
    } catch {
      setError('Errore di rete.');
    } finally {
      setBusy(false);
    }
  }

  async function lookupBarcode() {
    const code = barcode.trim();
    if (!code) return;
    const { status, data } = await getProductByBarcode(code);
    if (status === 200 && data.data?.product) {
      const found = data.data.product;
      setBarcodeFound(found);
      setQuickCreate(false);
      if (found.supplier_id) setSupplierId(String(found.supplier_id));
      if (found.id === product.id) {
        if (found.barcode) setBarcode(String(found.barcode));
      }
      return;
    }
    setBarcodeFound(null);
    setQuickCreate(true);
  }

  async function createQuickProduct() {
    const code = barcode.trim();
    if (!code) return;
    const { status, data } = await createProduct({
      category: product.category,
      supplier_id: supplierId || null,
      barcode: code,
      brand: quickBrand || null,
      model: quickModel || null,
      sale_price: purchasePrice ? Number(purchasePrice) : null,
    });
    if (status !== 200 && status !== 201) {
      setError('Creazione rapida non riuscita.');
      return;
    }
    setBarcodeFound(data.data);
    setQuickCreate(false);
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <form onSubmit={submit} className="w-full max-w-lg space-y-4 rounded-xl border border-border bg-background p-6">
        <div className="flex items-center justify-between">
          <h3 className="text-lg font-semibold">Carico manuale</h3>
          <Button type="button" variant="ghost" size="sm" onClick={onClose}>
            Chiudi
          </Button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="flex flex-col gap-1 sm:col-span-2">
            <span className="text-xs text-muted-foreground">Barcode (scanner)</span>
            <input
              autoFocus
              value={barcode}
              onChange={(e) => setBarcode(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  void lookupBarcode();
                }
              }}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          {barcodeFound && (
            <div className="sm:col-span-2 rounded-md border border-emerald-500/40 bg-emerald-50 p-2 text-xs text-emerald-700">
              Trovato: {[barcodeFound.brand, barcodeFound.model].filter(Boolean).join(' ') || barcodeFound.id}
            </div>
          )}
          {quickCreate && (
            <div className="sm:col-span-2 space-y-2 rounded-md border border-amber-500/40 bg-amber-50 p-3">
              <p className="text-xs text-amber-800">Barcode non trovato. Crea prodotto rapido.</p>
              <div className="grid gap-2 sm:grid-cols-2">
                <input value={quickBrand} onChange={(e) => setQuickBrand(e.target.value)} placeholder="Marchio" className="rounded border border-border bg-background px-2 py-1.5 text-sm" />
                <input value={quickModel} onChange={(e) => setQuickModel(e.target.value)} placeholder="Modello" className="rounded border border-border bg-background px-2 py-1.5 text-sm" />
              </div>
              <Button type="button" variant="outline" size="sm" onClick={() => void createQuickProduct()}>Crea prodotto rapido</Button>
            </div>
          )}
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Fornitore</span>
            <select value={supplierId} onChange={(e) => setSupplierId(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2">
              <option value="">—</option>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id}>
                  {(s.company_name as string) || `${String(s.last_name ?? '')} ${String(s.first_name ?? '')}`.trim()}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Numero DDT</span>
            <input value={ddtNumber} onChange={(e) => setDdtNumber(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Data DDT</span>
            <input type="date" value={ddtDate} onChange={(e) => setDdtDate(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Quantità</span>
            <input type="number" min={1} value={qty} onChange={(e) => setQty(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Prezzo acquisto</span>
            <input value={purchasePrice} onChange={(e) => setPurchasePrice(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-xs text-muted-foreground">Lotto</span>
            <input value={lot} onChange={(e) => setLot(e.target.value)} className="rounded-lg border border-border bg-background px-3 py-2" />
          </label>
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose} disabled={busy}>
            Annulla
          </Button>
          <Button type="submit" disabled={busy}>
            {busy ? 'Salvataggio…' : 'Salva carico'}
          </Button>
        </div>
      </form>
    </div>
  );
}
