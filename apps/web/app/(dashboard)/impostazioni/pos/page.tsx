'use client';

export default function PosSettingsPlaceholderPage() {
  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Impostazioni POS</h1>
        <p className="text-sm text-zinc-500">Placeholder configurazione Cassa virtuale e pagamenti.</p>
      </div>

      <section className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
        <h2 className="text-lg font-semibold">Cassa</h2>
        <p className="text-sm text-zinc-600 dark:text-zinc-300">
          Toggle abilita cassa virtuale, provider RT (Log/Cassa in Cloud/altro), credenziali provider cifrate.
        </p>
      </section>

      <section className="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 space-y-4">
        <h2 className="text-lg font-semibold">Pagamenti</h2>
        <p className="text-sm text-zinc-600 dark:text-zinc-300">
          Campo API key SumUp (cifrata) per POS.
        </p>
      </section>
    </div>
  );
}

