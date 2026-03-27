'use client';

import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  createCommunicationTemplate,
  deleteCommunicationTemplate,
  getCommunicationLogs,
  getCommunicationTemplates,
  updateCommunicationTemplate,
  type ApiCommunicationLog,
  type ApiCommunicationTemplate,
  type CommunicationTrigger,
} from '@/lib/api';

type Tab = 'templates' | 'logs';

const VARIABLES = ['{paziente_nome}', '{data_appuntamento}', '{data_scadenza}', '{data_richiamo}'];

export default function ComunicazioniPage() {
  const [tab, setTab] = useState<Tab>('templates');
  const [templates, setTemplates] = useState<ApiCommunicationTemplate[]>([]);
  const [logs, setLogs] = useState<ApiCommunicationLog[]>([]);

  const [editing, setEditing] = useState<ApiCommunicationTemplate | null>(null);
  const [type, setType] = useState<'email' | 'sms'>('sms');
  const [trigger, setTrigger] = useState<CommunicationTrigger>('appointment_reminder');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [language, setLanguage] = useState('it');
  const [isActive, setIsActive] = useState(true);

  const loadTemplates = useCallback(async () => {
    const res = await getCommunicationTemplates(1);
    if (res.status === 200) setTemplates(res.data.data ?? []);
  }, []);

  const loadLogs = useCallback(async () => {
    const res = await getCommunicationLogs({ page: 1 });
    if (res.status === 200) setLogs(res.data.data ?? []);
  }, []);

  useEffect(() => {
    void loadTemplates();
    void loadLogs();
  }, [loadTemplates, loadLogs]);

  function startNew() {
    setEditing(null);
    setType('sms');
    setTrigger('appointment_reminder');
    setSubject('');
    setBody('');
    setLanguage('it');
    setIsActive(true);
  }

  function startEdit(tpl: ApiCommunicationTemplate) {
    setEditing(tpl);
    setType(tpl.type);
    setTrigger(tpl.trigger);
    setSubject(tpl.subject ?? '');
    setBody(tpl.body);
    setLanguage(tpl.language ?? 'it');
    setIsActive(!!tpl.is_active);
  }

  async function saveTemplate() {
    const payload: Partial<ApiCommunicationTemplate> = {
      type,
      trigger,
      subject: type === 'email' ? (subject || null) : null,
      body,
      variables: VARIABLES,
      language,
      is_active: isActive,
    };
    const res = editing
      ? await updateCommunicationTemplate(editing.id, payload)
      : await createCommunicationTemplate(payload);

    if (res.status === 200 || res.status === 201) {
      await loadTemplates();
      startNew();
    }
  }

  async function removeTemplate(id: string) {
    await deleteCommunicationTemplate(id);
    await loadTemplates();
    if (editing?.id === id) startNew();
  }

  function appendVariable(v: string) {
    setBody((prev) => `${prev}${prev.endsWith(' ') || prev === '' ? '' : ' '}${v}`);
  }

  return (
    <div className="mx-auto max-w-7xl space-y-4 p-6">
      <h1 className="text-2xl font-semibold">Comunicazioni</h1>

      <div className="flex gap-2">
        <Button variant={tab === 'templates' ? 'default' : 'outline'} onClick={() => setTab('templates')}>Template</Button>
        <Button variant={tab === 'logs' ? 'default' : 'outline'} onClick={() => setTab('logs')}>Log invii</Button>
      </div>

      {tab === 'templates' && (
        <div className="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
          <section className="rounded-xl border border-border bg-card p-4">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold">Template</h2>
              <Button size="sm" variant="outline" onClick={startNew}>Nuovo</Button>
            </div>
            <div className="space-y-2">
              {templates.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nessun template disponibile.</p>
              ) : templates.map((t) => (
                <div key={t.id} className="flex items-start justify-between rounded border border-border p-3">
                  <button type="button" onClick={() => startEdit(t)} className="text-left">
                    <p className="font-medium text-sm">{t.trigger} · {t.type}</p>
                    <p className="text-xs text-muted-foreground">{t.subject || '(senza subject)'}</p>
                  </button>
                  <Button size="sm" variant="destructive" onClick={() => void removeTemplate(t.id)}>Elimina</Button>
                </div>
              ))}
            </div>
          </section>

          <section className="rounded-xl border border-border bg-card p-4 space-y-2">
            <h2 className="text-sm font-semibold">{editing ? 'Modifica template' : 'Nuovo template'}</h2>
            <select value={type} onChange={(e) => setType(e.target.value as 'email' | 'sms')} className="w-full rounded border border-border px-3 py-2">
              <option value="sms">SMS</option>
              <option value="email">Email</option>
            </select>
            <select value={trigger} onChange={(e) => setTrigger(e.target.value as CommunicationTrigger)} className="w-full rounded border border-border px-3 py-2">
              <option value="appointment_reminder">appointment_reminder</option>
              <option value="order_ready">order_ready</option>
              <option value="lac_reminder">lac_reminder</option>
              <option value="prescription_reminder">prescription_reminder</option>
              <option value="birthday">birthday</option>
              <option value="custom">custom</option>
            </select>
            {type === 'email' && (
              <input value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Subject email" className="w-full rounded border border-border px-3 py-2" />
            )}
            <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={8} placeholder="Corpo template" className="w-full rounded border border-border px-3 py-2" />
            <div className="flex flex-wrap gap-2">
              {VARIABLES.map((v) => (
                <button key={v} type="button" onClick={() => appendVariable(v)} className="rounded-full border border-border px-2 py-1 text-xs">
                  {v}
                </button>
              ))}
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              <input value={language} onChange={(e) => setLanguage(e.target.value)} className="rounded border border-border px-3 py-2" />
              <label className="flex items-center gap-2 rounded border border-border px-3 py-2 text-sm">
                <input type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
                Attivo
              </label>
            </div>
            <Button onClick={() => void saveTemplate()}>Salva template</Button>
          </section>
        </div>
      )}

      {tab === 'logs' && (
        <section className="overflow-x-auto rounded-xl border border-border bg-card">
          <table className="w-full min-w-[980px] text-left text-sm">
            <thead className="border-b border-border bg-muted/40">
              <tr>
                <th className="px-3 py-2">Data</th>
                <th className="px-3 py-2">Paziente</th>
                <th className="px-3 py-2">Tipo</th>
                <th className="px-3 py-2">Trigger</th>
                <th className="px-3 py-2">Stato</th>
                <th className="px-3 py-2">Errore</th>
              </tr>
            </thead>
            <tbody>
              {logs.length === 0 ? (
                <tr><td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">Nessun log invio</td></tr>
              ) : logs.map((l) => (
                <tr key={l.id} className="border-b border-border/60">
                  <td className="px-3 py-2">{l.created_at ? new Date(l.created_at).toLocaleString('it-IT') : '—'}</td>
                  <td className="px-3 py-2">{l.patient ? `${l.patient.last_name} ${l.patient.first_name}` : (l.patient_id ?? '—')}</td>
                  <td className="px-3 py-2">{l.type}</td>
                  <td className="px-3 py-2">{l.trigger}</td>
                  <td className="px-3 py-2">{l.status}</td>
                  <td className="px-3 py-2 text-xs text-red-600">{l.error_message ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}
    </div>
  );
}

