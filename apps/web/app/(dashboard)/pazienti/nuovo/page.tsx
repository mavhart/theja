'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';
import PatientAnagraphicForm from '@/components/modules/patients/PatientAnagraphicForm';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { createPatient, getStoredPosId, type ApiPatientPayload } from '@/lib/api';

export default function NuovoPazientePage() {
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleCreate(payload: ApiPatientPayload) {
    setError(null);
    const posId = getStoredPosId();
    if (!posId) {
      setError('Nessun POS attivo: seleziona un punto vendita dal login.');
      return;
    }
    setSubmitting(true);
    try {
      const { data: body, status } = await createPatient({ ...payload, pos_id: posId });
      if (status === 401) {
        router.replace('/login');
        return;
      }
      if (status === 422) {
        const msg = (body as unknown as { message?: string }).message;
        if (msg) setError(msg);
        else setError('Dati non validi.');
        return;
      }
      if (status !== 201 && status !== 200) {
        setError('Creazione non riuscita.');
        return;
      }
      const id = (body as { data?: { id: string } }).data?.id;
      if (id) router.replace(`/pazienti/${id}`);
      else router.replace('/pazienti');
    } catch {
      setError('Errore di rete.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex flex-wrap items-center gap-3">
        <Link href="/pazienti" className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}>
          ← Elenco pazienti
        </Link>
      </div>
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Nuovo paziente</h1>
        <p className="text-sm text-muted-foreground">Compila l&apos;anagrafica e salva.</p>
      </div>
      {error && (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}
      <PatientAnagraphicForm initial={null} onSubmit={handleCreate} submitting={submitting} submitLabel="Crea paziente" />
    </div>
  );
}
