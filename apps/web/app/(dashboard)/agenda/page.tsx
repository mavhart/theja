'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  cancelAppointment,
  createAppointment,
  getAppointmentsCalendar,
  getOrders,
  getPatients,
  getPosUsers,
  getStoredPosId,
  updateAppointment,
  type ApiAppointment,
  type ApiAppointmentType,
  type ApiOrder,
  type ApiPatient,
  type ApiPosUser,
} from '@/lib/api';

const HOURS = Array.from({ length: 13 }, (_, i) => 8 + i);

function startOfWeek(d: Date): Date {
  const out = new Date(d);
  const day = out.getDay();
  const diff = (day === 0 ? -6 : 1) - day;
  out.setDate(out.getDate() + diff);
  out.setHours(0, 0, 0, 0);
  return out;
}

function addDays(d: Date, days: number): Date {
  const out = new Date(d);
  out.setDate(out.getDate() + days);
  return out;
}

function toInputDateTime(d: Date): string {
  const yyyy = d.getFullYear();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
}

function typeColor(t: ApiAppointmentType): string {
  switch (t) {
    case 'visita_optometrica':
      return 'bg-blue-100 text-blue-700';
    case 'prova_lac':
      return 'bg-emerald-100 text-emerald-700';
    case 'consegna_ordine':
      return 'bg-orange-100 text-orange-700';
    case 'ritiro_riparazione':
      return 'bg-amber-100 text-amber-700';
    default:
      return 'bg-zinc-100 text-zinc-700';
  }
}

export default function AgendaPage() {
  const [posId, setPosId] = useState<string>('');
  const [weekStart, setWeekStart] = useState<Date>(() => startOfWeek(new Date()));
  const [appointments, setAppointments] = useState<ApiAppointment[]>([]);
  const [users, setUsers] = useState<ApiPosUser[]>([]);
  const [orders, setOrders] = useState<ApiOrder[]>([]);
  const [patientQuery, setPatientQuery] = useState('');
  const [patientOptions, setPatientOptions] = useState<ApiPatient[]>([]);
  const [busy, setBusy] = useState(false);

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<ApiAppointment | null>(null);

  const [type, setType] = useState<ApiAppointmentType>('generico');
  const [status, setStatus] = useState<'scheduled' | 'confirmed' | 'completed' | 'cancelled' | 'no_show'>('scheduled');
  const [title, setTitle] = useState('');
  const [patientId, setPatientId] = useState('');
  const [userId, setUserId] = useState('');
  const [startAt, setStartAt] = useState('');
  const [duration, setDuration] = useState(30);
  const [notes, setNotes] = useState('');
  const [orderId, setOrderId] = useState('');

  const weekDays = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)), [weekStart]);

  const loadWeek = useCallback(async () => {
    if (!posId) return;
    const from = new Date(weekStart);
    const to = addDays(weekStart, 7);
    const { status: http, data } = await getAppointmentsCalendar({
      pos_id: posId,
      from: from.toISOString(),
      to: to.toISOString(),
    });
    if (http === 200) setAppointments(data.data ?? []);
  }, [posId, weekStart]);

  useEffect(() => {
    const id = getStoredPosId();
    if (id) {
      setPosId(id);
      void getPosUsers(id).then((res) => {
        if (res.status === 200) setUsers(res.data.data ?? []);
      });
      void getOrders({ status: 'ready', page: 1 }).then((res) => {
        if (res.status === 200) setOrders(res.data.data ?? []);
      });
    }
  }, []);

  useEffect(() => {
    void loadWeek();
  }, [loadWeek]);

  useEffect(() => {
    if (patientQuery.trim().length < 2) {
      setPatientOptions([]);
      return;
    }
    const t = setTimeout(() => {
      void getPatients(patientQuery, 1).then((res) => {
        if (res.status === 200) setPatientOptions(res.data.data ?? []);
      });
    }, 250);
    return () => clearTimeout(t);
  }, [patientQuery]);

  function openNew(day: Date, hour: number) {
    const dt = new Date(day);
    dt.setHours(hour, 0, 0, 0);
    setEditing(null);
    setType('generico');
    setStatus('scheduled');
    setTitle('');
    setPatientId('');
    setUserId(users[0]?.id ? String(users[0].id) : '');
    setStartAt(toInputDateTime(dt));
    setDuration(30);
    setNotes('');
    setOrderId('');
    setModalOpen(true);
  }

  function openEdit(apt: ApiAppointment) {
    setEditing(apt);
    setType(apt.type);
    setStatus(apt.status);
    setTitle(apt.title ?? '');
    setPatientId(apt.patient_id ?? '');
    setUserId(String(apt.user_id ?? ''));
    setStartAt(toInputDateTime(new Date(apt.start_at)));
    setDuration(apt.duration_minutes ?? 30);
    setNotes(apt.notes ?? '');
    setOrderId(apt.order_id ?? '');
    setModalOpen(true);
  }

  async function save() {
    if (!posId || !startAt || !userId) return;
    setBusy(true);
    const payload: Record<string, unknown> = {
      pos_id: posId,
      patient_id: patientId || null,
      user_id: userId,
      type,
      title: title || null,
      status,
      start_at: new Date(startAt).toISOString(),
      duration_minutes: duration,
      notes: notes || null,
      order_id: type === 'consegna_ordine' ? (orderId || null) : null,
    };

    const res = editing ? await updateAppointment(editing.id, payload) : await createAppointment(payload);
    setBusy(false);
    if (res.status === 200 || res.status === 201) {
      setModalOpen(false);
      await loadWeek();
    }
  }

  async function onCancel() {
    if (!editing) return;
    await cancelAppointment(editing.id);
    setModalOpen(false);
    await loadWeek();
  }

  const todayStr = new Date().toDateString();
  const todays = appointments.filter((a) => new Date(a.start_at).toDateString() === todayStr);

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-2xl font-semibold">Agenda</h1>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setWeekStart((w) => addDays(w, -7))}>←</Button>
          <Button variant="outline" onClick={() => setWeekStart(startOfWeek(new Date()))}>Oggi</Button>
          <Button variant="outline" onClick={() => setWeekStart((w) => addDays(w, 7))}>→</Button>
        </div>
      </div>

      <div className="hidden md:block overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full min-w-[1050px] table-fixed text-xs">
          <thead className="bg-muted/40">
            <tr>
              <th className="w-20 px-2 py-2 text-left">Ora</th>
              {weekDays.map((d) => (
                <th key={d.toISOString()} className="px-2 py-2 text-left">
                  {d.toLocaleDateString('it-IT', { weekday: 'short', day: '2-digit', month: '2-digit' })}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {HOURS.map((h) => (
              <tr key={h} className="border-t border-border/60 align-top">
                <td className="px-2 py-2 text-muted-foreground">{String(h).padStart(2, '0')}:00</td>
                {weekDays.map((d) => {
                  const cellEvents = appointments.filter((a) => {
                    const s = new Date(a.start_at);
                    return s.getHours() === h && s.toDateString() === d.toDateString();
                  });
                  return (
                    <td key={`${d.toISOString()}-${h}`} className="h-20 px-2 py-2">
                      <button
                        type="button"
                        onClick={() => openNew(d, h)}
                        className="mb-1 block h-4 w-full rounded border border-dashed border-zinc-300 text-[10px] text-zinc-500 hover:bg-zinc-50"
                      >
                        +
                      </button>
                      <div className="space-y-1">
                        {cellEvents.map((a) => (
                          <button
                            key={a.id}
                            type="button"
                            onClick={() => openEdit(a)}
                            className={`block w-full rounded px-2 py-1 text-left text-[11px] ${typeColor(a.type)}`}
                          >
                            <div className="font-medium">{new Date(a.start_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}</div>
                            <div className="truncate">{a.patient ? `${a.patient.last_name} ${a.patient.first_name}` : (a.title ?? 'Appuntamento')}</div>
                          </button>
                        ))}
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="md:hidden rounded-xl border border-border bg-card p-4">
        <h2 className="mb-2 text-sm font-semibold">Appuntamenti di oggi</h2>
        {todays.length === 0 ? (
          <p className="text-sm text-muted-foreground">Nessun appuntamento oggi.</p>
        ) : (
          <div className="space-y-2">
            {todays.map((a) => (
              <button key={a.id} onClick={() => openEdit(a)} className={`w-full rounded p-2 text-left text-sm ${typeColor(a.type)}`}>
                <div className="font-medium">{new Date(a.start_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}</div>
                <div>{a.patient ? `${a.patient.last_name} ${a.patient.first_name}` : (a.title ?? 'Appuntamento')}</div>
              </button>
            ))}
          </div>
        )}
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-2xl space-y-3 rounded-xl bg-background p-5">
            <h3 className="text-lg font-semibold">{editing ? 'Modifica appuntamento' : 'Nuovo appuntamento'}</h3>
            <div className="grid gap-2 sm:grid-cols-2">
              <select value={type} onChange={(e) => setType(e.target.value as ApiAppointmentType)} className="rounded border border-border px-3 py-2">
                <option value="visita_optometrica">Visita optometrica</option>
                <option value="prova_lac">Prova LAC</option>
                <option value="consegna_ordine">Consegna ordine</option>
                <option value="ritiro_riparazione">Ritiro riparazione</option>
                <option value="generico">Generico</option>
              </select>
              <select value={status} onChange={(e) => setStatus(e.target.value as typeof status)} className="rounded border border-border px-3 py-2">
                <option value="scheduled">Programmato</option>
                <option value="confirmed">Confermato</option>
                <option value="completed">Completato</option>
                <option value="cancelled">Annullato</option>
                <option value="no_show">No show</option>
              </select>
              <input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Titolo (solo generico)" className="rounded border border-border px-3 py-2" />
              <input type="datetime-local" value={startAt} onChange={(e) => setStartAt(e.target.value)} className="rounded border border-border px-3 py-2" />
              <select value={String(duration)} onChange={(e) => setDuration(Number(e.target.value))} className="rounded border border-border px-3 py-2">
                {[15, 30, 45, 60, 90].map((m) => <option key={m} value={m}>{m} min</option>)}
              </select>
              <select value={userId} onChange={(e) => setUserId(e.target.value)} className="rounded border border-border px-3 py-2">
                <option value="">Operatore</option>
                {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
              </select>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
              <div className="space-y-1">
                <input value={patientQuery} onChange={(e) => setPatientQuery(e.target.value)} placeholder="Ricerca paziente…" className="w-full rounded border border-border px-3 py-2" />
                {patientOptions.length > 0 && (
                  <div className="max-h-32 overflow-auto rounded border border-border">
                    {patientOptions.map((p) => (
                      <button key={p.id} type="button" onClick={() => { setPatientId(p.id); setPatientQuery(`${p.last_name} ${p.first_name}`); setPatientOptions([]); }} className="block w-full px-2 py-1 text-left text-sm hover:bg-zinc-50">
                        {p.last_name} {p.first_name}
                      </button>
                    ))}
                  </div>
                )}
              </div>
              {type === 'consegna_ordine' ? (
                <select value={orderId} onChange={(e) => setOrderId(e.target.value)} className="rounded border border-border px-3 py-2">
                  <option value="">Collega ordine</option>
                  {orders.map((o) => <option key={o.id} value={o.id}>{o.job_code ?? o.id.slice(0, 8)}</option>)}
                </select>
              ) : <div />}
            </div>

            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Note" className="w-full rounded border border-border px-3 py-2" />

            <div className="flex justify-between">
              <div>
                {editing && <Button variant="destructive" onClick={() => void onCancel()}>Annulla appuntamento</Button>}
              </div>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setModalOpen(false)}>Chiudi</Button>
                <Button disabled={busy || !startAt || !userId} onClick={() => void save()}>{busy ? 'Salvataggio…' : 'Salva'}</Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

