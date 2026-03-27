'use client';

import Link from 'next/link';

export default function TesseraSanitariaPlaceholderPage() {
  return (
    <div className="mx-auto max-w-3xl space-y-4 p-6">
      <h1 className="text-2xl font-semibold">Tessera Sanitaria</h1>
      <p className="text-sm text-muted-foreground">
        Placeholder Fase 5: invio tracciato MEF e integrazione SistemaTS in arrivo nelle prossime fasi.
      </p>
      <Link href="/fatture" className="text-sm text-blue-600 hover:underline">
        Torna alle fatture
      </Link>
    </div>
  );
}

