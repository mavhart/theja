'use client';

import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import PatientAnagraphicForm from '@/components/modules/patients/PatientAnagraphicForm';
import PrescriptionForm from '@/components/modules/patients/PrescriptionForm';
import { Button, buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { formatDateIt } from '@/lib/patient-utils';
import {
  createLacExam,
  createPrescription,
  getLacExams,
  getPatient,
  getPosUsers,
  getPrescriptions,
  getStoredPosId,
  updateLacExam,
  updatePatient,
  updatePrescription,
  type ApiLacExam,
  type ApiPatient,
  type ApiPatientPayload,
  type ApiPosUser,
  type ApiPrescription,
} from '@/lib/api';

type TabId = 'anagrafica' | 'optometria' | 'lac' | 'storico' | 'occhiali';

function prescriptionSummary(p: ApiPrescription): { od: string; os: string } {
  const fmt = (eye: 'od' | 'os') => {
    const s = p[`${eye}_sphere_far`];
    const c = p[`${eye}_cylinder_far`];
    const a = p[`${eye}_axis_far`];
    const parts = [
      s != null ? String(s) : '—',
      c != null ? String(c) : '—',
      a != null ? String(a) : '—',
    ];
    return `${parts[0]} / ${parts[1]} × ${parts[2]}`;
  };
  return { od: fmt('od'), os: fmt('os') };
}

function LacExamForm({
  initial,
  posUsers,
  onSubmit,
  onCancel,
  submitting,
}: {
  initial: Record<string, unknown> | null;
  posUsers: ApiPosUser[];
  onSubmit: (payload: Record<string, unknown>) => Promise<void>;
  onCancel: () => void;
  submitting: boolean;
}) {
  const [examDate, setExamDate] = useState(
    () => (initial?.exam_date as string)?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
  );
  const [optician, setOptician] = useState(
    initial?.optician_user_id != null ? String(initial.optician_user_id) : '',
  );
  const fields = ['od', 'os'] as const;
  const keys = ['r1', 'r2', 'media'] as const;
  const [vals, setVals] = useState<Record<string, string>>(() => {
    const o: Record<string, string> = {};
    for (const e of fields) {
      for (const k of keys) {
        const key = `${e}_${k}`;
        const v = initial?.[key];
        o[key] = v != null && v !== '' ? String(v) : '';
      }
    }
    return o;
  });

  const setCell = (k: string, v: string) => setVals((prev) => ({ ...prev, [k]: v }));

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const payload: Record<string, unknown> = { exam_date: examDate };
    if (optician) payload.optician_user_id = Number.parseInt(optician, 10);
    else payload.optician_user_id = null;
    for (const e of fields) {
      for (const k of keys) {
        const key = `${e}_${k}`;
        const t = vals[key]?.trim();
        if (t === '' || t === undefined) {
          payload[key] = null;
        } else {
          const n = Number.parseFloat(t.replace(',', '.'));
          payload[key] = Number.isFinite(n) ? n : null;
        }
      }
    }
    await onSubmit(payload);
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4 text-sm">
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground">Data esame *</span>
          <input
            type="date"
            required
            value={examDate}
            onChange={(e) => setExamDate(e.target.value)}
            className="rounded-lg border border-border bg-background px-3 py-2"
          />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-muted-foreground">Operatore</span>
          <select
            value={optician}
            onChange={(e) => setOptician(e.target.value)}
            className="rounded-lg border border-border bg-background px-3 py-2"
          >
            <option value="">—</option>
            {posUsers.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </select>
        </label>
      </div>
      {fields.map((eye) => (
        <div key={eye} className="rounded-lg border border-border p-3">
          <p className="mb-2 text-xs font-semibold uppercase text-muted-foreground">{eye === 'od' ? 'OD' : 'OS'}</p>
          <div className="grid grid-cols-3 gap-2">
            {keys.map((k) => (
              <label key={k} className="flex flex-col gap-1">
                <span className="text-[10px] text-muted-foreground">{k}</span>
                <input
                  type="number"
                  step="0.01"
                  value={vals[`${eye}_${k}`] ?? ''}
                  onChange={(e) => setCell(`${eye}_${k}`, e.target.value)}
                  className="rounded-md border border-border px-2 py-1 text-xs"
                />
              </label>
            ))}
          </div>
        </div>
      ))}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>
          Annulla
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Salvataggio…' : 'Salva esame'}
        </Button>
      </div>
    </form>
  );
}

export default function PazienteDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = typeof params.id === 'string' ? params.id : '';

  const [tab, setTab] = useState<TabId>('anagrafica');
  const [patient, setPatient] = useState<ApiPatient | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const [prescriptions, setPrescriptions] = useState<ApiPrescription[]>([]);
  const [lacExams, setLacExams] = useState<ApiLacExam[]>([]);
  const [posUsers, setPosUsers] = useState<ApiPosUser[]>([]);

  const [rxModal, setRxModal] = useState<{ mode: 'create' } | { mode: 'edit'; item: ApiPrescription } | null>(null);
  const [rxSaving, setRxSaving] = useState(false);

  const [lacModal, setLacModal] = useState<{ mode: 'create' } | { mode: 'edit'; item: ApiLacExam } | null>(null);
  const [lacSaving, setLacSaving] = useState(false);

  const loadAll = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const posId = getStoredPosId();
      const [pr, presc, lac, users] = await Promise.all([
        getPatient(id),
        getPrescriptions(id),
        getLacExams(id),
        getPosUsers(posId ?? undefined),
      ]);

      if (pr.status === 401) {
        router.replace('/login');
        return;
      }
      if (pr.status !== 200 || !pr.data.data) {
        setError('Paziente non trovato.');
        setPatient(null);
        return;
      }
      setPatient(pr.data.data);
      setPrescriptions(presc.status === 200 ? presc.data.data ?? [] : []);
      setLacExams(lac.status === 200 ? lac.data.data ?? [] : []);
      setPosUsers(users.status === 200 ? users.data.data ?? [] : []);
    } catch {
      setError('Errore di rete.');
    } finally {
      setLoading(false);
    }
  }, [id, router]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  async function handleSaveAnagrafica(payload: ApiPatientPayload) {
    if (!id) return;
    setSaving(true);
    setError(null);
    try {
      const { data: body, status } = await updatePatient(id, payload);
      if (status === 401) {
        router.replace('/login');
        return;
      }
      if (status !== 200) {
        setError('Salvataggio non riuscito.');
        return;
      }
      setPatient(body.data);
    } catch {
      setError('Errore di rete.');
    } finally {
      setSaving(false);
    }
  }

  async function handleSavePrescription(payload: Record<string, unknown>) {
    if (!id || !rxModal) return;
    setRxSaving(true);
    try {
      if (rxModal.mode === 'create') {
        const { status } = await createPrescription(id, payload);
        if (status === 401) {
          router.replace('/login');
          return;
        }
      } else {
        const { status } = await updatePrescription(rxModal.item.id as string, payload);
        if (status === 401) {
          router.replace('/login');
          return;
        }
      }
      setRxModal(null);
      const r = await getPrescriptions(id);
      if (r.status === 200) setPrescriptions(r.data.data ?? []);
    } finally {
      setRxSaving(false);
    }
  }

  async function handleSaveLac(payload: Record<string, unknown>) {
    if (!id || !lacModal) return;
    setLacSaving(true);
    try {
      if (lacModal.mode === 'create') {
        const { status } = await createLacExam(id, payload);
        if (status === 401) {
          router.replace('/login');
          return;
        }
      } else {
        const { status } = await updateLacExam(lacModal.item.id as string, payload);
        if (status === 401) {
          router.replace('/login');
          return;
        }
      }
      setLacModal(null);
      const r = await getLacExams(id);
      if (r.status === 200) setLacExams(r.data.data ?? []);
    } finally {
      setLacSaving(false);
    }
  }

  const tabs: { id: TabId; label: string }[] = [
    { id: 'anagrafica', label: 'Anagrafica' },
    { id: 'optometria', label: 'Optometria' },
    { id: 'lac', label: 'LAC' },
    { id: 'storico', label: 'Storico' },
    { id: 'occhiali', label: 'Occhiali / Buste' },
  ];

  if (loading && !patient) {
    return (
      <div className="mx-auto max-w-5xl space-y-4 p-6">
        <div className="h-8 w-48 animate-pulse rounded-md bg-muted" />
        <div className="h-64 animate-pulse rounded-xl bg-muted" />
      </div>
    );
  }

  if (!patient) {
    return (
      <div className="mx-auto max-w-5xl p-6">
        <p className="text-destructive">{error ?? 'Paziente non disponibile.'}</p>
        <Link href="/pazienti" className={cn(buttonVariants({ variant: 'outline' }), 'mt-4 inline-flex')}>
          Torna all&apos;elenco
        </Link>
      </div>
    );
  }

  const title = [patient.last_name, patient.first_name].filter(Boolean).join(' ');

  return (
    <div className="mx-auto max-w-5xl space-y-6 p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <Link
            href="/pazienti"
            className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }), 'mb-2 -ml-2 inline-flex')}
          >
            ← Elenco pazienti
          </Link>
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          <p className="text-sm text-muted-foreground">
            {patient.city ?? '—'} · CF {patient.fiscal_code ? `****${String(patient.fiscal_code).slice(-4)}` : '—'}
          </p>
        </div>
      </div>

      {error && (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}

      <div className="flex flex-wrap gap-1 border-b border-border">
        {tabs.map((t) => (
          <button
            key={t.id}
            type="button"
            onClick={() => setTab(t.id)}
            className={`rounded-t-lg px-4 py-2 text-sm font-medium transition-colors ${
              tab === t.id
                ? 'border border-b-0 border-border bg-card text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'anagrafica' && (
        <PatientAnagraphicForm initial={patient} onSubmit={handleSaveAnagrafica} submitting={saving} />
      )}

      {tab === 'optometria' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button type="button" onClick={() => setRxModal({ mode: 'create' })}>
              Nuova prescrizione
            </Button>
          </div>
          {prescriptions.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nessuna prescrizione registrata.</p>
          ) : (
            <ul className="divide-y divide-border rounded-xl border border-border bg-card">
              {prescriptions.map((p) => {
                const s = prescriptionSummary(p);
                return (
                  <li key={String(p.id)}>
                    <button
                      type="button"
                      className="flex w-full flex-col gap-1 px-4 py-3 text-left text-sm transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between"
                      onClick={() => setRxModal({ mode: 'edit', item: p })}
                    >
                      <div>
                        <span className="font-medium">{formatDateIt(p.visit_date as string)}</span>
                        <span className="ml-2 text-muted-foreground">
                          Prossimo richiamo: {formatDateIt(p.next_recall_at as string)}
                          {p.next_recall_reason ? ` (${String(p.next_recall_reason)})` : ''}
                        </span>
                      </div>
                      <div className="font-mono text-xs text-muted-foreground">
                        OD {s.od} · OS {s.os}
                      </div>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      )}

      {tab === 'lac' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <Button type="button" onClick={() => setLacModal({ mode: 'create' })}>
              Nuovo esame LAC
            </Button>
          </div>
          {lacExams.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nessun esame LAC registrato.</p>
          ) : (
            <ul className="divide-y divide-border rounded-xl border border-border bg-card">
              {lacExams.map((e) => (
                <li key={String(e.id)}>
                  <button
                    type="button"
                    className="flex w-full flex-col gap-1 px-4 py-3 text-left text-sm transition-colors hover:bg-muted/40 sm:flex-row sm:items-center sm:justify-between"
                    onClick={() => setLacModal({ mode: 'edit', item: e })}
                  >
                    <span className="font-medium">{formatDateIt(e.exam_date as string)}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                      OD R1 {e.od_r1 ?? '—'} / R2 {e.od_r2 ?? '—'} · OS R1 {e.os_r1 ?? '—'} / R2 {e.os_r2 ?? '—'}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {tab === 'storico' && (
        <div className="flex min-h-[160px] items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
          Disponibile dalla Fase 4
        </div>
      )}

      {tab === 'occhiali' && (
        <div className="flex min-h-[160px] items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
          Disponibile dalla Fase 4
        </div>
      )}

      {rxModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog">
          <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl border border-border bg-background p-6 shadow-lg">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold">
                {rxModal.mode === 'create' ? 'Nuova prescrizione' : 'Modifica prescrizione'}
              </h2>
              <Button type="button" variant="ghost" size="sm" onClick={() => setRxModal(null)}>
                Chiudi
              </Button>
            </div>
            <PrescriptionForm
              initial={rxModal.mode === 'edit' ? rxModal.item : null}
              posUsers={posUsers}
              submitting={rxSaving}
              onCancel={() => setRxModal(null)}
              onSubmit={handleSavePrescription}
            />
          </div>
        </div>
      )}

      {lacModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog">
          <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-background p-6 shadow-lg">
            <div className="mb-4 flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold">
                {lacModal.mode === 'create' ? 'Nuovo esame LAC' : 'Modifica esame LAC'}
              </h2>
              <Button type="button" variant="ghost" size="sm" onClick={() => setLacModal(null)}>
                Chiudi
              </Button>
            </div>
            <LacExamForm
              initial={lacModal.mode === 'edit' ? lacModal.item : null}
              posUsers={posUsers}
              submitting={lacSaving}
              onCancel={() => setLacModal(null)}
              onSubmit={handleSaveLac}
            />
          </div>
        </div>
      )}
    </div>
  );
}
