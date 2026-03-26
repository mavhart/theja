'use client';

import { FormEvent, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import type { ApiPosUser } from '@/lib/api';

const DISTANCES = ['far', 'medium', 'near'] as const;
const DIST_LABEL: Record<(typeof DISTANCES)[number], string> = {
  far:    'Lontano',
  medium: 'Medio',
  near:   'Vicino',
};

type Eye = 'od' | 'os';

export interface PrescriptionFormProps {
  initial?: Record<string, unknown> | null;
  posUsers: ApiPosUser[];
  onSubmit: (payload: Record<string, unknown>) => Promise<void> | void;
  onCancel?: () => void;
  submitting?: boolean;
}

function todayISODate(): string {
  const d = new Date();
  const z = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
}

function numOrNull(v: string): number | null {
  const t = v.trim();
  if (t === '') return null;
  const n = Number.parseFloat(t.replace(',', '.'));
  return Number.isFinite(n) ? n : null;
}

function validateSphereCylinder(n: number | null): string | null {
  if (n === null) return null;
  if (n < -30 || n > 30) return 'Tra -30 e +30';
  const stepped = Math.round(n * 4) / 4;
  if (Math.abs(stepped - n) > 1e-4) return 'Step 0,25';
  return null;
}

function buildEmptyRow(): Record<string, string> {
  const o: Record<string, string> = {};
  for (const eye of ['od', 'os'] as const) {
    for (const dist of DISTANCES) {
      o[`${eye}_sphere_${dist}`] = '';
      o[`${eye}_cylinder_${dist}`] = '';
      o[`${eye}_axis_${dist}`] = '';
      o[`${eye}_addition_${dist}`] = '';
      o[`${eye}_prism_${dist}`] = '';
      o[`${eye}_base_${dist}`] = '';
    }
  }
  return o;
}

export default function PrescriptionForm({
  initial,
  posUsers,
  onSubmit,
  onCancel,
  submitting = false,
}: PrescriptionFormProps) {
  const [rowStrings, setRowStrings] = useState<Record<string, string>>(() => {
    const base = buildEmptyRow();
    if (!initial) return base;
    for (const k of Object.keys(base)) {
      const v = initial[k];
      if (v !== undefined && v !== null && v !== '') base[k] = String(v);
    }
    return base;
  });

  const [visitDate, setVisitDate] = useState(
    () => (initial?.visit_date as string) || todayISODate(),
  );
  const [isInternational, setIsInternational] = useState(
    initial?.is_international !== false,
  );
  const [opticianUserId, setOpticianUserId] = useState(
    initial?.optician_user_id != null ? String(initial.optician_user_id) : '',
  );
  const [visusOdNat, setVisusOdNat] = useState(String(initial?.visus_od_natural ?? ''));
  const [visusOdCor, setVisusOdCor] = useState(String(initial?.visus_od_corrected ?? ''));
  const [visusOsNat, setVisusOsNat] = useState(String(initial?.visus_os_natural ?? ''));
  const [visusOsCor, setVisusOsCor] = useState(String(initial?.visus_os_corrected ?? ''));
  const [visusBinoNat, setVisusBinoNat] = useState(String(initial?.visus_bino_natural ?? ''));
  const [visusBinoCor, setVisusBinoCor] = useState(String(initial?.visus_bino_corrected ?? ''));
  const [ipdTotal, setIpdTotal] = useState(String(initial?.ipd_total ?? ''));
  const [ipdRight, setIpdRight] = useState(String(initial?.ipd_right ?? ''));
  const [ipdLeft, setIpdLeft] = useState(String(initial?.ipd_left ?? ''));
  const [nextRecallAt, setNextRecallAt] = useState(
    initial?.next_recall_at ? String(initial.next_recall_at).slice(0, 10) : '',
  );
  const [nextRecallReason, setNextRecallReason] = useState(
    String(initial?.next_recall_reason ?? ''),
  );
  const [notes, setNotes] = useState(String(initial?.notes ?? ''));
  const [fieldError, setFieldError] = useState<string | null>(null);

  const setCell = (key: string, value: string) => {
    setRowStrings((prev) => ({ ...prev, [key]: value }));
  };

  const payload = useMemo(() => {
    const out: Record<string, unknown> = {
      visit_date:       visitDate,
      is_international: isInternational,
      notes:            notes.trim() || null,
    };
    if (opticianUserId) out.optician_user_id = Number.parseInt(opticianUserId, 10);
    else out.optician_user_id = null;

    out.visus_od_natural = visusOdNat.trim() || null;
    out.visus_od_corrected = visusOdCor.trim() || null;
    out.visus_os_natural = visusOsNat.trim() || null;
    out.visus_os_corrected = visusOsCor.trim() || null;
    out.visus_bino_natural = visusBinoNat.trim() || null;
    out.visus_bino_corrected = visusBinoCor.trim() || null;

    out.ipd_total = numOrNull(ipdTotal);
    out.ipd_right = numOrNull(ipdRight);
    out.ipd_left = numOrNull(ipdLeft);

    out.next_recall_at = nextRecallAt || null;
    out.next_recall_reason = nextRecallReason.trim() || null;

    for (const eye of ['od', 'os'] as const) {
      for (const dist of DISTANCES) {
        const sKey = `${eye}_sphere_${dist}`;
        const cKey = `${eye}_cylinder_${dist}`;
        const aKey = `${eye}_axis_${dist}`;
        const addKey = `${eye}_addition_${dist}`;
        const pKey = `${eye}_prism_${dist}`;
        const bKey = `${eye}_base_${dist}`;

        const s = numOrNull(rowStrings[sKey] ?? '');
        const c = numOrNull(rowStrings[cKey] ?? '');
        const axRaw = (rowStrings[aKey] ?? '').trim();
        const ax = axRaw === '' ? null : Number.parseInt(axRaw, 10);
        const add = numOrNull(rowStrings[addKey] ?? '');
        const pr = numOrNull(rowStrings[pKey] ?? '');
        const bs = (rowStrings[bKey] ?? '').trim();

        out[sKey] = s;
        out[cKey] = c;
        out[aKey] = Number.isFinite(ax as number) ? ax : null;
        out[addKey] = add;
        out[pKey] = pr;
        out[bKey] = bs || null;
      }
    }

    return out;
  }, [
    visitDate,
    isInternational,
    opticianUserId,
    visusOdNat,
    visusOdCor,
    visusOsNat,
    visusOsCor,
    visusBinoNat,
    visusBinoCor,
    ipdTotal,
    ipdRight,
    ipdLeft,
    nextRecallAt,
    nextRecallReason,
    notes,
    rowStrings,
  ]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setFieldError(null);

    for (const eye of ['od', 'os'] as const) {
      for (const dist of DISTANCES) {
        const s = numOrNull(rowStrings[`${eye}_sphere_${dist}`] ?? '');
        const c = numOrNull(rowStrings[`${eye}_cylinder_${dist}`] ?? '');
        const errS = validateSphereCylinder(s);
        const errC = validateSphereCylinder(c);
        if (errS) {
          setFieldError(`${eye.toUpperCase()} ${DIST_LABEL[dist]} — Sfera: ${errS}`);
          return;
        }
        if (errC) {
          setFieldError(`${eye.toUpperCase()} ${DIST_LABEL[dist]} — Cilindro: ${errC}`);
          return;
        }
      }
    }

    await onSubmit(payload);
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6 text-sm">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground">Data visita *</span>
          <input
            type="date"
            required
            value={visitDate}
            onChange={(e) => setVisitDate(e.target.value)}
            className="rounded-lg border border-border bg-background px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground">Operatore</span>
          <select
            value={opticianUserId}
            onChange={(e) => setOpticianUserId(e.target.value)}
            className="rounded-lg border border-border bg-background px-3 py-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <option value="">—</option>
            {posUsers.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex items-center gap-2 pt-6">
          <input
            type="checkbox"
            checked={isInternational}
            onChange={(e) => setIsInternational(e.target.checked)}
            className="size-4 rounded border-border"
          />
          <span>Notazione internazionale</span>
        </label>
      </div>

      {DISTANCES.map((dist) => (
        <div key={dist} className="rounded-xl border border-border bg-muted/20 p-4">
          <h4 className="mb-3 font-medium text-foreground">{DIST_LABEL[dist]}</h4>
          <div className="grid gap-4 lg:grid-cols-2">
            {(['od', 'os'] as const).map((eye) => (
              <div key={`${dist}-${eye}`} className="space-y-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {eye === 'od' ? 'Occhio destro (OD)' : 'Occhio sinistro (OS)'}
                </p>
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                  {(
                    [
                      ['sfera', `${eye}_sphere_${dist}`, 'number'],
                      ['cil.', `${eye}_cylinder_${dist}`, 'number'],
                      ['asse', `${eye}_axis_${dist}`, 'text'],
                      ['add.', `${eye}_addition_${dist}`, 'number'],
                      ['prisma', `${eye}_prism_${dist}`, 'number'],
                      ['base', `${eye}_base_${dist}`, 'text'],
                    ] as const
                  ).map(([label, key, kind]) => (
                    <label key={key} className="flex flex-col gap-0.5">
                      <span className="text-[10px] text-muted-foreground">{label}</span>
                      <input
                        type={kind === 'number' ? 'number' : 'text'}
                        {...(kind === 'number'
                          ? { step: '0.25', min: '-30', max: '30' }
                          : {})}
                        value={rowStrings[key] ?? ''}
                        onChange={(e) => setCell(key, e.target.value)}
                        className="w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      />
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      ))}

      <div className="rounded-xl border border-border p-4">
        <h4 className="mb-3 font-medium">Visus</h4>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">OD naturale</span>
            <input
              value={visusOdNat}
              onChange={(e) => setVisusOdNat(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">OD corretto</span>
            <input
              value={visusOdCor}
              onChange={(e) => setVisusOdCor(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">OS naturale</span>
            <input
              value={visusOsNat}
              onChange={(e) => setVisusOsNat(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">OS corretto</span>
            <input
              value={visusOsCor}
              onChange={(e) => setVisusOsCor(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">Binoculare naturale</span>
            <input
              value={visusBinoNat}
              onChange={(e) => setVisusBinoNat(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">Binoculare corretto</span>
            <input
              value={visusBinoCor}
              onChange={(e) => setVisusBinoCor(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
        </div>
      </div>

      <div className="rounded-xl border border-border p-4">
        <h4 className="mb-3 font-medium">Distanza interpupillare (mm)</h4>
        <div className="grid gap-3 sm:grid-cols-3">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">Totale</span>
            <input
              type="number"
              step="0.1"
              value={ipdTotal}
              onChange={(e) => setIpdTotal(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">DX</span>
            <input
              type="number"
              step="0.1"
              value={ipdRight}
              onChange={(e) => setIpdRight(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">SX</span>
            <input
              type="number"
              step="0.1"
              value={ipdLeft}
              onChange={(e) => setIpdLeft(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
        </div>
      </div>

      <div className="rounded-xl border border-border p-4">
        <h4 className="mb-3 font-medium">Prossimo richiamo</h4>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">Data</span>
            <input
              type="date"
              value={nextRecallAt}
              onChange={(e) => setNextRecallAt(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
          <label className="flex flex-col gap-1">
            <span className="text-muted-foreground">Motivo</span>
            <input
              value={nextRecallReason}
              onChange={(e) => setNextRecallReason(e.target.value)}
              className="rounded-lg border border-border bg-background px-3 py-2"
            />
          </label>
        </div>
      </div>

      <label className="flex flex-col gap-1">
        <span className="text-muted-foreground">Note</span>
        <textarea
          rows={4}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          className="rounded-lg border border-border bg-background px-3 py-2"
        />
      </label>

      {fieldError && (
        <p className="text-sm text-destructive" role="alert">
          {fieldError}
        </p>
      )}

      <div className="flex flex-wrap justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>
            Annulla
          </Button>
        )}
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Salvataggio…' : 'Salva prescrizione'}
        </Button>
      </div>
    </form>
  );
}
