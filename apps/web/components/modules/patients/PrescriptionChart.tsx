'use client';

import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { formatDateIt } from '@/lib/patient-utils';
import type { ApiPrescription } from '@/lib/api';

export interface PrescriptionChartProps {
  prescriptions: ApiPrescription[];
}

type ChartRow = {
  visitKey: string;
  date: string;
  od_sphere_far: number | null;
  os_sphere_far: number | null;
  od_cylinder_far: number | null;
  os_cylinder_far: number | null;
  full: ApiPrescription;
};

function toNum(v: unknown): number | null {
  if (v == null || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function buildRows(prescriptions: ApiPrescription[]): ChartRow[] {
  const sorted = [...prescriptions].sort((a, b) => {
    const da = String(a.visit_date ?? '');
    const db = String(b.visit_date ?? '');
    return da.localeCompare(db);
  });
  return sorted.map((p) => ({
    visitKey: `${String(p.id ?? '')}-${String(p.visit_date ?? '')}`,
    date:     formatDateIt(p.visit_date as string),
    od_sphere_far: toNum(p.od_sphere_far),
    os_sphere_far: toNum(p.os_sphere_far),
    od_cylinder_far: toNum(p.od_cylinder_far),
    os_cylinder_far: toNum(p.os_cylinder_far),
    full: p,
  }));
}

function formatPrescriptionForTooltip(p: ApiPrescription): string {
  const lines: string[] = [];
  const keys = Object.keys(p).sort();
  for (const k of keys) {
    const v = p[k];
    if (v === null || v === undefined || v === '') continue;
    if (typeof v === 'object') continue;
    lines.push(`${k}: ${String(v)}`);
  }
  return lines.join('\n');
}

export default function PrescriptionChart({ prescriptions }: PrescriptionChartProps) {
  if (prescriptions.length < 2) {
    return (
      <div className="rounded-xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center text-sm text-muted-foreground">
        Nessuna prescrizione registrata
      </div>
    );
  }

  const data = buildRows(prescriptions);

  return (
    <div className="w-full rounded-xl border border-border bg-card p-4">
      <h3 className="mb-3 text-sm font-semibold text-foreground">Progressione diottrie (lontano)</h3>
      <div className="h-[320px] w-full min-w-0">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 8 }}>
            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
            <XAxis dataKey="date" tick={{ fontSize: 11 }} interval="preserveStartEnd" />
            <YAxis domain={[-15, 15]} tick={{ fontSize: 11 }} />
            <Tooltip
              content={({ active, payload }) => {
                if (!active || !payload?.length) return null;
                const row = payload[0].payload as ChartRow;
                const text = formatPrescriptionForTooltip(row.full);
                return (
                  <div className="max-h-64 max-w-md overflow-auto rounded-lg border border-border bg-background p-3 text-xs shadow-lg">
                    <p className="mb-2 font-semibold text-foreground">{row.date}</p>
                    <pre className="whitespace-pre-wrap font-mono text-[10px] leading-relaxed text-muted-foreground">
                      {text || '—'}
                    </pre>
                  </div>
                );
              }}
            />
            <Legend wrapperStyle={{ fontSize: 12 }} />
            <Line type="monotone" dataKey="od_sphere_far" name="Sfera OD" stroke="#2563eb" dot={false} strokeWidth={2} connectNulls />
            <Line type="monotone" dataKey="os_sphere_far" name="Sfera OS" stroke="#16a34a" dot={false} strokeWidth={2} connectNulls />
            <Line type="monotone" dataKey="od_cylinder_far" name="Cilindro OD" stroke="#ea580c" dot={false} strokeWidth={2} connectNulls />
            <Line type="monotone" dataKey="os_cylinder_far" name="Cilindro OS" stroke="#9333ea" dot={false} strokeWidth={2} connectNulls />
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
