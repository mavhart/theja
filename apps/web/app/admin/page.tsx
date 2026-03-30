'use client';

import { useEffect, useMemo, useState } from 'react';
import { getStoredToken } from '@/lib/api';

type AdminOrg = {
  id: string;
  name: string;
  pos_count: number;
  subscription_status: string | null;
  features?: {
    ai_analysis_enabled?: boolean;
    virtual_cash_register_enabled?: boolean;
  };
};

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

async function apiFetch<T>(path: string, token: string): Promise<{ status: number; data: T }> {
  const res = await fetch(`${API_URL}/api${path}`, {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  const data = await res.json().catch(() => ({}));
  return { status: res.status, data: data as T };
}

export default function AdminPage() {
  const token = useMemo(() => getStoredToken() ?? '', []);

  const [orgs, setOrgs] = useState<AdminOrg[]>([]);
  const [stats, setStats] = useState<{ org_total: number; pos_active: number; revenue_total: number } | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [createName, setCreateName] = useState('');
  const [createOwnerEmail, setCreateOwnerEmail] = useState('');
  const [createPosCount, setCreatePosCount] = useState(1);
  const [creating, setCreating] = useState(false);

  async function loadAll(): Promise<void> {
    if (!token) {
      setError('Non autenticato.');
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    const [orgRes, statsRes] = await Promise.all([
      apiFetch<{ data: AdminOrg[] }>('/admin/organizations', token),
      apiFetch<{ data: { org_total: number; pos_active: number; revenue_total: number } }>('/admin/stats', token),
    ]);

    if (orgRes.status === 200) setOrgs(orgRes.data.data ?? []);
    else setError(`Errore nel caricamento organizzazioni (status ${orgRes.status}).`);

    if (statsRes.status === 200) setStats(statsRes.data.data ?? null);
    setLoading(false);
  }

  useEffect(() => {
    void loadAll();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function toggleFeature(orgId: string, key: 'ai_analysis_enabled' | 'virtual_cash_register_enabled', value: boolean) {
    if (!token) return;

    // Recuperiamo lo stato corrente dalla UI per inviare entrambe le feature
    const org = orgs.find((o) => o.id === orgId);
    const currentAi = org?.features?.ai_analysis_enabled ?? false;
    const currentVirtual = org?.features?.virtual_cash_register_enabled ?? false;

    const payload = {
      features: {
        ai_analysis_enabled: key === 'ai_analysis_enabled' ? value : currentAi,
        virtual_cash_register_enabled: key === 'virtual_cash_register_enabled' ? value : currentVirtual,
      },
    };

    await fetch(`${API_URL}/api/admin/organizations/${orgId}/features`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(payload),
    });

    await loadAll();
  }

  const canShowCreate = token.length > 0;

  return (
    <div className="p-4 md:p-6 lg:p-8 max-w-6xl mx-auto space-y-6">
      <div className="space-y-1">
        <h1 className="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Admin</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400">Gestione organizzazioni e feature flags (super_admin).</p>
      </div>

      {loading && <p className="text-sm text-zinc-500">Caricamento...</p>}
      {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}

      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p className="text-xs text-zinc-500">Organizzazioni</p>
            <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">{stats.org_total}</p>
          </div>
          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p className="text-xs text-zinc-500">POS attivi</p>
            <p className="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{stats.pos_active}</p>
          </div>
          <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5">
            <p className="text-xs text-zinc-500">Revenue totale</p>
            <p className="text-3xl font-bold text-amber-600 dark:text-amber-400">{stats.revenue_total.toFixed(2)}</p>
          </div>
        </div>
      )}

      <div className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
        <div className="flex items-start justify-between gap-3 flex-wrap">
          <div className="space-y-1">
            <h2 className="font-semibold text-sm">Organizzazioni</h2>
            <p className="text-xs text-zinc-500">Abbonamento, POS count e feature flags.</p>
          </div>
        </div>

        {canShowCreate && (
          <div className="space-y-3 border-t border-zinc-100 dark:border-zinc-800 pt-4">
            <h3 className="font-semibold text-sm">Crea organizzazione</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="text-xs text-zinc-500">Nome org</label>
                <input
                  value={createName}
                  onChange={(e) => setCreateName(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="text-xs text-zinc-500">Owner email</label>
                <input
                  value={createOwnerEmail}
                  onChange={(e) => setCreateOwnerEmail(e.target.value)}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="text-xs text-zinc-500">POS count</label>
                <input
                  type="number"
                  min={1}
                  max={50}
                  value={createPosCount}
                  onChange={(e) => setCreatePosCount(Number(e.target.value))}
                  className="mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div className="flex justify-end">
              <button
                disabled={creating || !createName.trim() || !createOwnerEmail.trim()}
                onClick={async () => {
                  // Nota: inviamo body nella chiamata fetch direttamente sotto.
                  setCreating(true);
                  setError(null);
                  const res = await fetch(`${API_URL}/api/admin/organizations`, {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                      Accept: 'application/json',
                      Authorization: `Bearer ${token}`,
                    },
                    body: JSON.stringify({
                      name: createName.trim(),
                      owner_email: createOwnerEmail.trim(),
                      pos_count: createPosCount,
                    }),
                  });
                  if (!res.ok) setError('Errore creazione organizzazione.');
                  setCreating(false);
                  await loadAll();
                }}
                className="rounded-lg bg-blue-600 text-white px-4 py-2 text-sm disabled:opacity-50"
              >
                {creating ? 'Creazione...' : 'Crea organizzazione'}
              </button>
            </div>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="text-xs text-zinc-500">
                <th className="pb-2">Org</th>
                <th className="pb-2">POS</th>
                <th className="pb-2">Abbonamento</th>
                <th className="pb-2">AI Analysis</th>
                <th className="pb-2">Cassa virtuale</th>
              </tr>
            </thead>
            <tbody>
              {orgs.map((org) => (
                <tr key={org.id} className="border-t border-zinc-100 dark:border-zinc-800">
                  <td className="py-3">
                    <div className="font-medium">{org.name}</div>
                    <div className="text-xs text-zinc-500">{org.id}</div>
                  </td>
                  <td className="py-3">{org.pos_count}</td>
                  <td className="py-3">{org.subscription_status ?? '-'}</td>
                  <td className="py-3">
                    <label className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        checked={Boolean(org.features?.ai_analysis_enabled)}
                        onChange={(e) => void toggleFeature(org.id, 'ai_analysis_enabled', e.target.checked)}
                      />
                      <span className="text-xs text-zinc-600">AI</span>
                    </label>
                  </td>
                  <td className="py-3">
                    <label className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        checked={Boolean(org.features?.virtual_cash_register_enabled)}
                        onChange={(e) => void toggleFeature(org.id, 'virtual_cash_register_enabled', e.target.checked)}
                      />
                      <span className="text-xs text-zinc-600">RT</span>
                    </label>
                  </td>
                </tr>
              ))}
              {orgs.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-6 text-center text-sm text-zinc-500">
                    Nessuna organizzazione.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

