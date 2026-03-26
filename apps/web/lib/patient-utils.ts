/** Maschera CF: asterischi + ultime 4 cifre/lettere per riconoscimento. */
export function maskFiscalCode(cf: string | null | undefined): string {
  if (!cf || cf.length < 4) return '—';
  return `****${cf.slice(-4)}`;
}

export function formatDateIt(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function formatDateTimeIt(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString('it-IT', {
    day:    '2-digit',
    month:  '2-digit',
    year:   'numeric',
    hour:   '2-digit',
    minute: '2-digit',
  });
}
